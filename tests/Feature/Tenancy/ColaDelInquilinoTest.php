<?php

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use App\Tenancy\InquilinoActual;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\UsaBaseCentral;
use Tests\Support\Tenancy\AvisoDePrueba;
use Tests\Support\Tenancy\ContratoDePrueba;
use Tests\Support\Tenancy\TrabajoQueMira;
use Tests\TestCase;

/**
 * Un trabajo encolado vuelve a la base del inquilino que lo encoló.
 *
 * EL DEFECTO QUE ESTO CIERRA APARECIÓ EN PRODUCCIÓN, no en un test. Un agente
 * envía el contrato a su cliente, la notificación se encola, y el worker muere
 * con «relation "contratos_intermediacion" does not exist». El correo no sale y
 * el panel no muestra nada: el agente cree que lo mandó.
 *
 * La causa es de orden. Las notificaciones de Laravel usan `SerializesModels`,
 * así que el modelo se RE-CONSULTA al deserializar —dentro de
 * `CallQueuedHandler::getCommand()`— y eso pasa antes de que exista una
 * instancia del trabajo sobre la cual correr middleware. El mecanismo anterior,
 * `UsaConexionDeInquilino`, no estaba mal escrito: llegaba tarde por diseño.
 *
 * Por eso estos tests procesan con el WORKER DE VERDAD (`queue:work --once`) en
 * vez de llamar a `handle()`. Llamar a `handle()` saltea la deserialización, que
 * es justo donde vivía el defecto: un test así habría estado en verde todo el
 * tiempo.
 */
class ColaDelInquilinoTest extends TestCase
{
    use UsaBaseCentral;

    private const BASE = 'demo_probe_cola';

    private const OTRA = 'demo_probe_cola_b';

    private string $delWorker;

    private ?string $cacheDelWorker = null;

    protected function setUp(): void
    {
        parent::setUp();

        // `sync` correría el trabajo en el momento y sin serializar: no habría
        // cola, ni payload, ni deserialización. O sea, ningún defecto que ver.
        Config::set('queue.default', 'database');

        $this->delWorker = (string) Config::get('database.connections.pgsql.database');
        $this->cacheDelWorker = Config::get('cache.prefix');

        foreach ([self::BASE => 'F-001', self::OTRA => 'F-002'] as $base => $folio) {
            DB::connection('maintenance')->statement('DROP DATABASE IF EXISTS "'.$base.'"');
            DB::connection('maintenance')->statement('CREATE DATABASE "'.$base.'"');

            $this->enElInquilino(function () use ($folio): void {
                DB::statement('CREATE TABLE probe_contratos (id serial primary key, folio text)');
                DB::table('probe_contratos')->insert(['id' => 1, 'folio' => $folio]);
            }, $base);
        }

        TrabajoQueMira::olvidar();
    }

    protected function tearDown(): void
    {
        $this->apuntarA($this->delWorker);

        foreach ([self::BASE, self::OTRA] as $base) {
            DB::connection('maintenance')->statement('DROP DATABASE IF EXISTS "'.$base.'"');
        }

        parent::tearDown();
    }

    /**
     * Hace algo COMO SI fuera una petición de ese inquilino, y vuelve.
     */
    private function enElInquilino(callable $fn, string $base = self::BASE): void
    {
        $this->apuntarA($base);

        $slug = 'probe'.str_replace('_', '', $base);

        app(InquilinoActual::class)->fijar(new Tenant([
            'slug' => $slug,
            'database' => $base,
        ]));
        Config::set('cache.prefix', 't_'.$slug.'_');

        try {
            $fn();
        } finally {
            $this->apuntarA($this->delWorker);
            Config::set('cache.prefix', $this->cacheDelWorker);

            // Y SE OLVIDA EL INQUILINO, que es lo que hace la petición al
            // terminar. Sin esto la simulación miente: el inquilino quedaba
            // fijado y los tests que dicen «sin inquilino» encolaban sellados.
            app()->forgetInstance(InquilinoActual::class);
        }
    }

    private function apuntarA(string $base): void
    {
        Config::set('database.connections.pgsql.database', $base);
        DB::purge('pgsql');
    }

    private function correrElWorker(): void
    {
        // `--memory` ALTO A PROPÓSITO, y no es un número mágico.
        //
        // `queue:work` asume 128 MB y para con salida 12 cuando los pasa. Acá el
        // worker corre DENTRO del proceso de la suite, que después de 1700 tests
        // ya lleva bastante más — así que se detenía tras el primer trabajo.
        //
        // El síntoma era desconcertante: el test pasaba solo y fallaba en la
        // suite, con el segundo trabajo intacto en la cola y sin ningún error.
        $this->artisan('queue:work', [
            '--stop-when-empty' => true,
            '--tries' => 1,
            '--memory' => 4096,
        ])->run();
    }

    private function elFallo(): ?string
    {
        $fila = DB::connection('central')->table('failed_jobs')->latest('failed_at')->first();

        return $fila === null ? null : substr((string) $fila->exception, 0, 300);
    }

    public function test_a_queued_notification_rehydrates_its_model_from_the_right_database(): void
    {
        // ESTE ES EL DEFECTO DE PRODUCCIÓN, reproducido.
        //
        // `probe_contratos` existe SÓLO en la base del inquilino. Si la
        // deserialización va a la del worker, falla igual que
        // `contratos_intermediacion`.
        $this->enElInquilino(function (): void {
            $contrato = ContratoDePrueba::query()->findOrFail(1);

            Notification::route('mail', 'cliente@ejemplo.com')
                ->notify(new AvisoDePrueba($contrato));
        });

        // Acá ya no hay inquilino resuelto: es exactamente lo que ve un worker,
        // que no tiene subdominio del cual deducir nada.
        $this->correrElWorker();

        $this->assertNull(
            $this->elFallo(),
            'La notificación tiene que rehidratar su modelo contra la base de su inquilino.',
        );
    }

    public function test_the_job_runs_against_its_tenants_database_and_not_the_workers(): void
    {
        // Mirado DESDE ADENTRO del trabajo. Que al final la conexión quede bien
        // no dice nada sobre dónde escribió mientras corría — y escribir en la
        // base equivocada no lanza ninguna excepción.
        $this->enElInquilino(fn () => TrabajoQueMira::dispatch());

        $this->correrElWorker();

        $this->assertSame(self::BASE, TrabajoQueMira::$baseQueVio, $this->elFallo() ?? '');
    }

    public function test_the_connection_does_not_leak_to_the_next_job(): void
    {
        // El modo de falla más silencioso: un worker atiende inquilinos
        // distintos uno detrás del otro, y si un trabajo deja la conexión
        // movida, el siguiente escribe —bien— en la base del anterior.
        $this->enElInquilino(fn () => TrabajoQueMira::dispatch());

        $this->correrElWorker();

        $this->assertSame(
            $this->delWorker,
            Config::get('database.connections.pgsql.database'),
            'Después de un trabajo, la conexión tiene que quedar donde estaba.',
        );
    }

    public function test_a_job_queued_without_a_tenant_is_not_pointed_anywhere(): void
    {
        // El alta de un inquilino se encola cuando su base TODAVÍA NO EXISTE, y
        // el registro público corre en el host central, que no tiene inquilino.
        // Sellar esos trabajos los mandaría contra una base equivocada o
        // inexistente.
        TrabajoQueMira::dispatch();

        $this->correrElWorker();

        $this->assertSame($this->delWorker, TrabajoQueMira::$baseQueVio, $this->elFallo() ?? '');
    }

    public function test_a_sync_job_leaves_the_request_where_it_found_it(): void
    {
        // CON `sync` EL TRABAJO CORRE DENTRO DE LA PETICIÓN, y ahí restaurar «al
        // valor con el que arrancó el proceso» sería un desastre: devolvería la
        // conexión al estado ANTERIOR a resolver el inquilino y se llevaría
        // puesto el resto de la petición.
        //
        // Por eso se restaura a lo que había justo antes del trabajo.
        Config::set('queue.default', 'sync');

        $this->enElInquilino(function (): void {
            TrabajoQueMira::dispatch();

            $this->assertSame(
                self::BASE,
                Config::get('database.connections.pgsql.database'),
                'Un trabajo síncrono no puede dejar la petición apuntando a otra base.',
            );
        });
    }

    public function test_two_tenants_in_a_row_do_not_share_a_live_connection(): void
    {
        // EL MODO DE FALLA MÁS SILENCIOSO DE TODOS, y el único que necesita DOS
        // trabajos para aparecer.
        //
        // Un worker es un proceso largo. Cuando llega el segundo trabajo ya hay
        // una conexión ABIERTA contra la base del primero, y mover la
        // configuración no mueve una conexión viva: hay que descartarla.
        //
        // Sin ese descarte no hay excepción, no hay log, no hay nada. El segundo
        // trabajo lee —y escribe— en la base del inquilino anterior, y se
        // descubre cuando alguien ve datos ajenos, semanas después.
        //
        // Con un solo trabajo por test esto pasaba en verde: nunca había una
        // conexión previa que heredar.
        $this->enElInquilino(fn () => TrabajoQueMira::dispatch(), self::BASE);
        $this->enElInquilino(fn () => TrabajoQueMira::dispatch(), self::OTRA);

        $this->correrElWorker();

        $this->assertSame(
            ['F-001', 'F-002'],
            TrabajoQueMira::$folios,
            'Cada trabajo tiene que LEER de la base de su inquilino, no sólo tenerla configurada.',
        );
    }

    public function test_a_job_without_a_tenant_does_not_touch_the_open_connection(): void
    {
        // LA REGRESIÓN QUE ESTE TEST FIJA, y la introduje yo escribiendo esto.
        //
        // La primera versión restauraba al terminar CUALQUIER trabajo, sellado o
        // no. Parece inofensivo —se restaura al mismo valor— pero restaurar
        // descarta la conexión viva, y con `sync` el trabajo corre dentro de la
        // petición. En la suite se llevó puesta la transacción de
        // `RefreshDatabase` en 20 tests de contratos: los datos sembrados
        // desaparecían a mitad del test, sin ningún error que lo explicara.
        //
        // Una transacción abierta es la forma más directa de verlo: si alguien
        // descarta la conexión, se pierde.
        Config::set('queue.default', 'sync');

        DB::connection('pgsql')->beginTransaction();

        try {
            TrabajoQueMira::dispatch();

            $this->assertSame(
                1,
                DB::connection('pgsql')->transactionLevel(),
                'Un trabajo sin inquilino no tiene nada que restaurar, y descartar la conexión rompe lo que la esté usando.',
            );
        } finally {
            if (DB::connection('pgsql')->transactionLevel() > 0) {
                DB::connection('pgsql')->rollBack();
            }
        }
    }
}
