<?php

namespace App\Tenancy;

use App\Enums\TenantEstado;
use App\Models\Tenant;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Da de alta un inquilino copiando la plantilla vigente.
 *
 * La copia cuesta 0.2 s contra los segundos que tarda migrar desde cero, y llega
 * completa: PostGIS, índices GIST, catálogo geográfico, las seis páginas del CMS
 * y el contenido de muestra que hace que el demo se vea vivo al entrar.
 */
class AprovisionaInquilinos
{
    /**
     * Nombre propio para la conexión hacia el inquilino recién creado.
     *
     * NO se reapunta la conexión por defecto. En el lote B eso hizo que el
     * registro de las migraciones fuera a una base y el DDL de algunas a otra:
     * la plantilla nacía rota diciendo estar completa, y el test no lo detectó
     * porque dentro del proceso de pruebas la conexión se resolvía limpia.
     */
    private const CONEXION = 'inquilino_en_alta';

    public function __construct(private readonly CreadorDeOwner $creadorDeOwner) {}

    /**
     * @param  string|null  $origenHash  Hash con sal del origen, para el límite
     *                                   por origen (RFC-10). Nulo desde la
     *                                   invitación por consola: no hay origen que
     *                                   contar, y uno inventado ensuciaría el
     *                                   conteo del registro público.
     */
    public function crear(string $email, ?string $origenHash = null): ResultadoDeAlta
    {
        $slug = $this->slugLibre();

        $tenant = Tenant::create([
            'slug' => $slug,
            'database' => GeneradorDeSlug::baseDe($slug),
            'email' => $email,
            'template_version' => Config::get('tenancy.plantilla_vigente'),
            'expira_en' => now()->addDays((int) Config::get('tenancy.dias_de_vida', 30)),
            'estado' => TenantEstado::Aprovisionando,
            'origen_hash' => $origenHash,
        ]);

        try {
            $this->copiarPlantilla($tenant->database);
        } catch (Throwable $e) {
            $this->marcarFallido($tenant, $e);

            throw $e;
        }

        // A PARTIR DE ACÁ YA HAY UNA BASE VIVA. Cualquier fallo deja basura si
        // nadie la borra: una base ocupando conexiones y disco que el padrón
        // muestra como si no existiera.
        try {
            $conexion = $this->conexionA($tenant->database);

            $this->verificarQueLaCopiaSirva($conexion, $tenant);

            $this->copiarArchivosDeLaPlantilla($tenant);

            $password = $this->creadorDeOwner->crear($conexion, $email);

            $tenant->pasarA(TenantEstado::Activo);

            return new ResultadoDeAlta($tenant, $password);
        } catch (Throwable $e) {
            $this->borrarBase($tenant->database);
            $this->marcarFallido($tenant, $e);

            throw $e;
        } finally {
            DB::purge(self::CONEXION);
        }
    }

    /**
     * Comprueba que lo copiado sirva, mirando al INQUILINO y no a la plantilla.
     *
     * La plantilla no se puede inspeccionar: abrirle una conexión es
     * exactamente lo que rompe la siguiente copia. Pero el inquilino recién
     * creado es una copia idéntica y ya estamos conectados a él, así que ahí se
     * verifica gratis.
     *
     * Existe por un caso real: la plantilla vigente por defecto estaba sólo
     * migrada, sin sembrar. El alta terminaba «bien», el inquilino entraba a un
     * panel con cero inmuebles y cero clientes, y NADIE se quejaba. Un demo que
     * nace vacío no se nota hasta que la persona invitada ya se fue.
     */
    private function verificarQueLaCopiaSirva(Connection $conexion, Tenant $tenant): void
    {
        $faltantes = [];

        foreach (['frontend_pages' => 6, 'roles' => 1, 'zones' => 1, 'properties' => 1, 'postal_codes' => 1] as $tabla => $minimo) {
            if ($conexion->table($tabla)->count() < $minimo) {
                $faltantes[] = $tabla;
            }
        }

        if ($faltantes !== []) {
            throw new PlantillaInservible(sprintf(
                'La plantilla «%s» produjo un inquilino sin %s. Reconstruila con '.
                '`php artisan demo:plantilla:construir` antes de invitar a nadie.',
                $tenant->template_version,
                implode(', ', $faltantes),
            ));
        }
    }

    /**
     * Copia la plantilla, con la copia serializada por un cerrojo.
     *
     * Postgres rechaza copiar una plantilla que tenga cualquier conexión
     * encima, así que dos altas simultáneas hacen fallar a la segunda.
     */
    private function copiarPlantilla(string $destino): void
    {
        $plantilla = (string) Config::get('tenancy.plantilla_vigente');
        $clave = (int) Config::get('tenancy.cerrojo.clave');

        $this->tomarCerrojo($clave);

        try {
            // El dueño se declara al CREAR y no se transfiere después: entre
            // crear y transferir habría una ventana en la que la base existe y
            // la aplicación no puede usarla, y un fallo en el medio la dejaría
            // así para siempre.
            DB::connection('maintenance')->statement(sprintf(
                'CREATE DATABASE %s TEMPLATE %s%s',
                $this->citar($destino),
                $this->citar($plantilla),
                $this->clausulaDeDueno(),
            ));
        } finally {
            // EN `finally`, NO en el camino feliz. `pg_advisory_lock` se ata a
            // la sesión, y la sesión de un worker no se cierra entre trabajos:
            // una excepción entre tomar y soltar dejaría el cerrojo puesto
            // mientras el proceso siga vivo, colgando todas las altas
            // siguientes sin un solo error que lo delate.
            DB::connection('central')->select('SELECT pg_advisory_unlock(?)', [$clave]);
        }
    }

    /**
     * Con `pg_try_advisory_lock` en un bucle acotado, nunca con la variante que
     * espera sin límite: un cerrojo trabado tiene que dar un mensaje, no
     * lentitud sin causa.
     *
     * No hay barrido de cerrojos huérfanos y es deliberado: si el proceso muere,
     * su sesión se cierra y Postgres los suelta solo.
     */
    private function tomarCerrojo(int $clave): void
    {
        $intentos = max(1, (int) Config::get('tenancy.cerrojo.intentos', 10));
        $espera = max(0, (int) Config::get('tenancy.cerrojo.espera_ms', 300));

        for ($i = 0; $i < $intentos; $i++) {
            $tomado = DB::connection('central')
                ->selectOne('SELECT pg_try_advisory_lock(?) AS ok', [$clave])->ok;

            if ($tomado) {
                return;
            }

            usleep($espera * 1000);
        }

        throw new CerrojoOcupado(
            "No se pudo tomar el cerrojo de aprovisionamiento tras {$intentos} intentos. ".
            'Hay otra alta en curso o quedó tomado por un proceso vivo.',
        );
    }

    private function slugLibre(): string
    {
        for ($i = 0; $i < 10; $i++) {
            $slug = GeneradorDeSlug::generar();

            if (! Tenant::query()->where('slug', $slug)->exists()) {
                return $slug;
            }
        }

        throw new \RuntimeException('No se pudo generar un slug libre.');
    }

    /**
     * Los ARCHIVOS de la plantilla, al directorio del inquilino.
     *
     * Copiar la base no alcanza: las filas de la librería de medios viajan, los
     * archivos no. Y la ruta se deriva del inquilino en tiempo de LECTURA, así
     * que un inquilino recién nacido pediría `tenants/{slug}/1/foto.jpg` mientras
     * el archivo sigue en `plantillas/{version}/1/foto.jpg`.
     *
     * El síntoma sería cruel: el alta reporta éxito, el panel muestra los
     * inmuebles publicados, y sólo las imágenes salen rotas.
     *
     * Espeja lo que ya hace el borrado, que elimina `tenants/{slug}`.
     *
     * No es fatal si la plantilla no tiene archivos: hubo plantillas sin
     * imágenes y seguirán siendo válidas.
     */
    private function copiarArchivosDeLaPlantilla(Tenant $tenant): void
    {
        $disco = Storage::disk((string) Config::get('media-library.disk_name', 'public'));

        $origen = 'plantillas/'.$tenant->template_version;

        if (! $disco->directoryExists($origen)) {
            return;
        }

        $destino = 'tenants/'.$tenant->slug;

        foreach ($disco->allFiles($origen) as $archivo) {
            $disco->writeStream(
                $destino.mb_substr($archivo, mb_strlen($origen)),
                $disco->readStream($archivo),
            );
        }
    }

    private function conexionA(string $base): Connection
    {
        Config::set('database.connections.'.self::CONEXION, array_merge(
            Config::get('database.connections.pgsql'),
            ['database' => $base],
        ));
        DB::purge(self::CONEXION);

        return DB::connection(self::CONEXION);
    }

    private function borrarBase(string $base): void
    {
        // Hay que soltar la conexión antes: Postgres no borra una base con
        // sesiones vivas, y la nuestra es una de ellas.
        DB::purge(self::CONEXION);

        DB::connection('maintenance')->statement('DROP DATABASE IF EXISTS '.$this->citar($base));
    }

    private function marcarFallido(Tenant $tenant, Throwable $e): void
    {
        $tenant->forceFill([
            'estado' => TenantEstado::Fallido,
            'motivo_falla' => mb_substr($e->getMessage(), 0, 2000),
        ])->save();
    }

    /**
     * `OWNER` explícito, o nada si no hay rol declarado.
     *
     * Quien crea la base es el rol de aprovisionamiento, el único con CREATEDB.
     * Pero quien tiene que USARLA es el rol de la aplicación. Sin esta cláusula
     * la base queda del creador y el primer request del inquilino falla por
     * permisos — con el alta reportando éxito.
     */
    private function clausulaDeDueno(): string
    {
        $rol = (string) Config::get('tenancy.rol_aplicacion');

        return $rol === '' ? '' : ' OWNER '.$this->citar($rol);
    }

    private function citar(string $identificador): string
    {
        return '"'.str_replace('"', '""', $identificador).'"';
    }
}
