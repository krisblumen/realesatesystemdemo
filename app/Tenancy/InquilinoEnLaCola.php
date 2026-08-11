<?php

namespace App\Tenancy;

use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;

/**
 * Un trabajo encolado vuelve a la base del inquilino que lo encoló.
 *
 * EL DEFECTO QUE ESTO CIERRA, encontrado en producción. Un agente envía el
 * contrato a su cliente; la notificación se encola; el worker la levanta y muere
 * con «relation "contratos_intermediacion" does not exist». El correo nunca
 * sale y en el panel no aparece ningún error: el agente cree que lo mandó.
 *
 * POR QUÉ NO ALCANZA UN MIDDLEWARE DE TRABAJO, que es lo que había. Las
 * notificaciones de Laravel usan `SerializesModels`, así que el modelo no viaja
 * entero: viaja como identificador y se RE-CONSULTA al deserializar. Eso ocurre
 * en `CallQueuedHandler::getCommand()`, antes de que exista una instancia del
 * trabajo sobre la cual correr middleware. Cuando el middleware llegaba, la
 * consulta ya había ido a la base equivocada.
 *
 * De ahí las dos mitades:
 *
 * 1. AL ENCOLAR se sella la base del inquilino en el PAYLOAD —texto plano, al
 *    lado del trabajo serializado— porque leer el payload no exige deserializar
 *    nada.
 * 2. AL PROCESAR se apunta la conexión con el evento `JobProcessing`, que el
 *    worker dispara antes de `getCommand()`.
 *
 * SE RESTAURA SIEMPRE al valor que había ANTES de este trabajo, en éxito y en
 * fallo. Un worker es un proceso largo que atiende inquilinos distintos uno
 * detrás del otro, y el modo de falla de no restaurar no lanza excepción: el
 * trabajo siguiente escribe —bien— en la base del anterior.
 *
 * Se restaura al valor previo y no a uno capturado al arrancar el proceso
 * porque con `QUEUE_CONNECTION=sync` el trabajo corre DENTRO de la petición: un
 * valor de arranque devolvería la conexión al estado anterior a resolver el
 * inquilino, y se llevaría puesto el resto de la petición.
 */
class InquilinoEnLaCola
{
    /**
     * La clave del sello dentro del payload.
     *
     * Con prefijo propio: el payload es de Laravel y sus claves pueden crecer
     * en cualquier versión.
     */
    public const SELLO = 'inquilinoDelDemo';

    /**
     * Lo que había antes del trabajo en curso, para devolverlo tal cual.
     *
     * @var list<array{base: ?string, cache: ?string}|null>
     */
    private static array $pila = [];

    /**
     * Se registra en CADA arranque de la aplicación, sin guarda estática.
     *
     * Parecía que hacía falta una: `createPayloadUsing` guarda los callbacks en
     * una estática de la clase `Queue`, que sobrevive a reconstruir la
     * aplicación. Es al revés — el ciclo de vida de los tests de Laravel llama a
     * `Queue::createPayloadUsing(null)` en cada `tearDown`
     * (`InteractsWithTestCaseLifecycle`), así que una guarda deja al sello sin
     * registrar desde el segundo test y todo pasa en verde sin protección.
     */
    public static function registrar(): void
    {
        // Sin capturar nada del contenedor: el callback puede sobrevivir a la
        // aplicación que lo registró, así que todo se lee en el momento.
        Queue::createPayloadUsing(static fn (): array => self::sello());

        Event::listen(JobProcessing::class, static fn (JobProcessing $e) => self::entrar($e->job));
        Event::listen(JobProcessed::class, static fn () => self::salir());
        Event::listen(JobExceptionOccurred::class, static fn () => self::salir());
    }

    /**
     * @return array<string, array{base: ?string, cache: ?string}>
     */
    private static function sello(): array
    {
        // El host central y los comandos de consola no tienen inquilino, y no
        // hay que sellarlos: el alta de un inquilino, por ejemplo, corre cuando
        // su base todavía no existe.
        if (! app(InquilinoActual::class)->hayInquilino()) {
            return [];
        }

        return [self::SELLO => self::dondeEstamos()];
    }

    private static function entrar(Job $trabajo): void
    {
        /** @var array{base?: ?string, cache?: ?string, raiz?: ?string}|null $sello */
        $sello = $trabajo->payload()[self::SELLO] ?? null;

        // SIN SELLO NO SE TOCA NADA, ni al entrar ni al salir. Restaurar «por si
        // acaso» un trabajo que no movimos parece inofensivo y no lo es:
        // `apuntarA` descarta la conexión viva, y con `QUEUE_CONNECTION=sync` el
        // trabajo corre dentro de la petición. En la suite eso se llevó puesta la
        // transacción de `RefreshDatabase` en 20 tests de contratos — los datos
        // sembrados desaparecían a mitad del test.
        //
        // Y no hace falta: el único que puede dejar la conexión movida es un
        // trabajo CON sello, y ese la devuelve él.
        self::$pila[] = $sello === null ? null : self::dondeEstamos();

        if ($sello === null) {
            return;
        }

        self::apuntarA($sello['base'] ?? null, $sello['cache'] ?? null, $sello['raiz'] ?? null);
    }

    private static function salir(): void
    {
        $previo = array_pop(self::$pila);

        if ($previo === null) {
            return;
        }

        self::apuntarA($previo['base'], $previo['cache'], $previo['raiz']);
    }

    /**
     * @return array{base: ?string, cache: ?string, raiz: string}
     */
    private static function dondeEstamos(): array
    {
        return [
            'base' => Config::get('database.connections.pgsql.database'),

            // Se guarda el prefijo YA ARMADO en vez del slug: la fórmula vive en
            // `ResolveTenant` y dos lugares que la arman se separan en el primer
            // cambio.
            'cache' => Config::get('cache.prefix'),

            // Y CON QUÉ HOST SE ARMAN LAS DIRECCIONES.
            //
            // En un trabajo encolado no hay petición, así que `route()` y `url()`
            // no tienen de dónde sacar el host y caen en `APP_URL` — el host
            // central. El enlace de firma de un contrato salía apuntando a
            // `demo.landracore.com/contrato/…` en vez de al subdominio del
            // inquilino, y ese host redirige al sitio promocional: el cliente
            // hacía clic y terminaba en otra página.
            //
            // Se guarda la raíz TAL COMO LA VE quien encola, que en una petición
            // del panel es exactamente el subdominio correcto. Once notificaciones
            // encoladas arman direcciones; sellarlo acá las cubre a todas en vez
            // de parchar una por una.
            'raiz' => rtrim(URL::to('/'), '/'),
        ];
    }

    private static function apuntarA(?string $base, ?string $prefijo, ?string $raiz): void
    {
        Config::set('database.connections.pgsql.database', $base);

        // Descartar la conexión viva no es opcional, y es la diferencia con una
        // petición web: ahí nada se conectó todavía cuando se resuelve el
        // inquilino. En un worker la conexión YA está abierta contra la base del
        // trabajo anterior, así que mover la configuración sola no mueve nada.
        DB::purge('pgsql');

        Config::set('cache.prefix', $prefijo);

        // El almacén ya resuelto conserva el prefijo viejo.
        Cache::forgetDriver(Config::get('cache.default'));

        // Los trabajos encolados antes de que esto existiera no traen raíz. Se
        // los deja como estaban en vez de mandarlos a `APP_URL`: sin sello no se
        // toca nada, y media corrección es peor que ninguna.
        if ($raiz !== null) {
            URL::forceRootUrl($raiz);
        }
    }
}
