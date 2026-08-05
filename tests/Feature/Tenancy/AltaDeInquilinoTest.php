<?php

namespace Tests\Feature\Tenancy;

use App\Enums\TenantEstado;
use App\Models\Tenant;
use App\Tenancy\AprovisionaInquilinos;
use App\Tenancy\CerrojoOcupado;
use App\Tenancy\CreadorDeOwner;
use App\Tenancy\PlantillaInservible;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\Concerns\UsaBaseCentral;
use Tests\TestCase;

/**
 * El alta de un inquilino: copiar la plantilla y dejarlo listo para entrar.
 *
 * Dos cosas se prueban acá y no se pueden simular. La primera es que la copia
 * quede serializada: Postgres rechaza copiar una plantilla que tenga cualquier
 * conexión encima, así que dos altas a la vez hacen fallar a la segunda. La
 * segunda es qué queda cuando el alta se cae a la mitad — porque a partir del
 * `CREATE DATABASE` ya hay una base viva que alguien tiene que borrar.
 */
class AltaDeInquilinoTest extends TestCase
{
    use UsaBaseCentral;

    private const PLANTILLA = 'demo_probe_alta_tpl';

    private static bool $plantillaLista = false;

    private array $creadas = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (! self::$plantillaLista) {
            $this->artisan('demo:plantilla:construir', ['nombre' => self::PLANTILLA, '--force' => true])
                ->assertSuccessful();
            self::$plantillaLista = true;
        }

        config(['tenancy.plantilla_vigente' => self::PLANTILLA]);
    }

    protected function tearDown(): void
    {
        foreach ($this->creadas as $base) {
            DB::connection('maintenance')->statement('DROP DATABASE IF EXISTS "'.$base.'"');
        }

        parent::tearDown();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$plantillaLista) {
            $pdo = new \PDO(
                'pgsql:host='.env('DB_HOST', '127.0.0.1').';port='.env('DB_PORT', '5432').';dbname=postgres',
                env('DB_USERNAME', 'postgres'),
                (string) env('DB_PASSWORD', ''),
            );
            $pdo->exec('DROP DATABASE IF EXISTS "'.self::PLANTILLA.'"');
            self::$plantillaLista = false;
        }

        parent::tearDownAfterClass();
    }

    private function alta(): AprovisionaInquilinos
    {
        return app(AprovisionaInquilinos::class);
    }

    private function enElInquilino(Tenant $tenant, callable $fn): mixed
    {
        // Conexión propia y efímera, NUNCA reapuntando la por defecto: en el
        // lote B, mutar `pgsql` hizo que el registro de las migraciones fuera a
        // una base y el DDL a otra, y la plantilla nació rota diciendo estar
        // completa. Un nombre propio no deja lugar a esa ambigüedad.
        config(['database.connections.probe_inquilino' => array_merge(
            config('database.connections.pgsql'),
            ['database' => $tenant->database],
        )]);
        DB::purge('probe_inquilino');

        try {
            return $fn(DB::connection('probe_inquilino'));
        } finally {
            DB::purge('probe_inquilino');
        }
    }

    /**
     * Intercambia el creador del owner por uno que revienta.
     *
     * Es el primer paso del alta que puede fallar cuando YA existe una base, y
     * hay que poder probar qué queda cuando eso pasa. Se hace con inyección y no
     * con un método de prueba en el servicio: una costura de test en código de
     * producción es código que existe sólo para los tests y que alguien va a
     * llamar por error.
     */
    private function haceFallarLaCreacionDelOwner(): void
    {
        $this->app->bind(CreadorDeOwner::class, fn () => new class extends CreadorDeOwner
        {
            public function crear(Connection $conexion, string $email): string
            {
                throw new RuntimeException('revienta después del CREATE DATABASE');
            }
        });
    }

    public function test_a_tenant_is_born_ready_to_be_used(): void
    {
        $resultado = $this->alta()->crear('nuevo@ejemplo.com');
        $this->creadas[] = $resultado->tenant->database;

        $tenant = $resultado->tenant->fresh();

        $this->assertSame(TenantEstado::Activo, $tenant->estado);
        $this->assertSame(self::PLANTILLA, $tenant->template_version);
        $this->assertNotNull($tenant->expira_en, 'Un inquilino sin vencimiento es un inquilino eterno.');

        $contenido = $this->enElInquilino($tenant, fn ($c): array => [
            'paginas' => $c->table('frontend_pages')->count(),
            'inmuebles' => $c->table('properties')->count(),
        ]);

        $this->assertSame(6, $contenido['paginas']);
        $this->assertGreaterThan(0, $contenido['inmuebles'], 'El demo se juega en el primer minuto.');
    }

    public function test_the_owner_lives_inside_the_tenant_and_can_sign_in(): void
    {
        $resultado = $this->alta()->crear('duenio@ejemplo.com');
        $this->creadas[] = $resultado->tenant->database;

        $usuario = $this->enElInquilino($resultado->tenant, fn ($c) => $c->table('users')
            ->where('email', 'duenio@ejemplo.com')->first());

        $this->assertNotNull($usuario, 'El owner del inquilino vive en SU base, no en la central.');
        $this->assertTrue(
            Hash::check($resultado->password, $usuario->password),
            'La contraseña que se imprime tiene que ser la que abre la cuenta.',
        );

        $roles = $this->enElInquilino($resultado->tenant, fn ($c) => $c->table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $usuario->id)
            ->pluck('roles.name')->all());

        $this->assertSame(['owner'], $roles);
    }

    public function test_the_tenant_database_belongs_to_the_application_role_not_the_creator(): void
    {
        // DEUDA DE LA AUDITORÍA (M-1) QUE NO ESTABA IMPLEMENTADA, y que apareció
        // recién en el servidor.
        //
        // Quien crea la base es el rol de aprovisionamiento, el único con
        // CREATEDB. Pero quien tiene que USARLA es el rol con el que la
        // aplicación atiende peticiones. Sin declarar el dueño, la base queda
        // del creador: el alta reporta éxito y el PRIMER request del inquilino
        // falla por permisos.
        //
        // Y la salida apurada sería darle CREATEDB al rol de la aplicación, que
        // destruye la separación que protege el DDL.
        DB::connection('maintenance')->statement('DROP ROLE IF EXISTS demo_probe_dueno');
        DB::connection('maintenance')->statement('CREATE ROLE demo_probe_dueno');

        config(['tenancy.rol_aplicacion' => 'demo_probe_dueno']);

        try {
            $resultado = $this->alta()->crear('dueno@ejemplo.com');
            $this->creadas[] = $resultado->tenant->database;

            $dueno = DB::connection('maintenance')->selectOne(
                'SELECT pg_get_userbyid(datdba) AS rol FROM pg_database WHERE datname = ?',
                [$resultado->tenant->database],
            )->rol;

            $this->assertSame('demo_probe_dueno', $dueno, 'La base tiene que quedar del rol de la aplicación.');
        } finally {
            config(['tenancy.rol_aplicacion' => null]);

            foreach ($this->creadas as $base) {
                DB::connection('maintenance')->statement('DROP DATABASE IF EXISTS "'.$base.'"');
            }
            $this->creadas = [];

            DB::connection('maintenance')->statement('DROP ROLE IF EXISTS demo_probe_dueno');
        }
    }

    public function test_two_tenants_in_a_row_do_not_collide(): void
    {
        // Prueba que el cerrojo se SUELTA. Si quedara tomado, la segunda alta
        // esperaría hasta agotar los intentos y fallaría.
        $a = $this->alta()->crear('a@ejemplo.com');
        $this->creadas[] = $a->tenant->database;

        $b = $this->alta()->crear('b@ejemplo.com');
        $this->creadas[] = $b->tenant->database;

        $this->assertNotSame($a->tenant->slug, $b->tenant->slug);
        $this->assertNotSame($a->tenant->database, $b->tenant->database);
        $this->assertSame(2, Tenant::query()->where('estado', TenantEstado::Activo)->count());
    }

    public function test_a_taken_lock_fails_with_a_message_instead_of_hanging(): void
    {
        // Con `pg_advisory_lock` a secas, una alta bloqueada no da error: da
        // lentitud. Y la lentitud sin causa es lo último que alguien mira.
        //
        // El cerrojo se toma desde OTRA SESIÓN, con PDO crudo. Los cerrojos de
        // aviso son reentrantes dentro de la misma sesión: tomarlo con la misma
        // conexión que usa el alta no bloquearía nada y el test pasaría sin
        // probar nada.
        $otraSesion = new \PDO(
            'pgsql:host='.env('DB_HOST', '127.0.0.1').';port='.env('DB_PORT', '5432').';dbname='.config('database.connections.central.database'),
            env('DB_USERNAME', 'postgres'),
            (string) env('DB_PASSWORD', ''),
        );
        $otraSesion->query('SELECT pg_advisory_lock('.(int) config('tenancy.cerrojo.clave').')')->fetch();

        try {
            config(['tenancy.cerrojo.intentos' => 2, 'tenancy.cerrojo.espera_ms' => 10]);

            $this->expectException(CerrojoOcupado::class);
            $this->alta()->crear('bloqueado@ejemplo.com');
        } finally {
            $otraSesion->query('SELECT pg_advisory_unlock('.(int) config('tenancy.cerrojo.clave').')')->fetch();
            $otraSesion = null;
        }
    }

    public function test_an_alta_that_dies_after_creating_the_database_does_not_leave_it_behind(): void
    {
        // El paso que crea el `owner` es el primero que puede dejar basura: ya
        // hay una base viva. Si nadie la borra, queda ocupando conexiones y
        // disco, y el padrón la muestra como si no existiera.
        $this->haceFallarLaCreacionDelOwner();

        try {
            $this->alta()->crear('roto@ejemplo.com');
            $this->fail('El fallo tiene que propagarse.');
        } catch (RuntimeException) {
            // esperado
        }

        $tenant = Tenant::query()->firstWhere('email', 'roto@ejemplo.com');

        $this->assertNotNull($tenant);
        $this->assertSame(TenantEstado::Fallido, $tenant->estado);
        $this->assertNotNull($tenant->motivo_falla);

        $existe = DB::connection('maintenance')
            ->selectOne('SELECT 1 AS x FROM pg_database WHERE datname = ?', [$tenant->database]);

        $this->assertNull($existe, 'La base a medias tiene que quedar borrada.');
    }

    public function test_a_failure_insid_e_the_lock_still_releases_it(): void
    {
        // ESTE es el test del `finally`, y el anterior que escribí NO lo era: su
        // fallo ocurría después de soltar el cerrojo, así que la mutación de
        // sacar el `finally` no lo hacía caer. Lo delató la prueba por mutación.
        //
        // Acá el fallo ocurre DENTRO de la sección protegida: la plantilla no
        // existe, así que `CREATE DATABASE ... TEMPLATE` revienta entre tomar y
        // soltar. Sin `finally`, el cerrojo queda puesto y la siguiente alta se
        // cuelga hasta agotar intentos — sin un solo error que lo delate.
        config(['tenancy.plantilla_vigente' => 'demo_plantilla_que_no_existe']);

        try {
            $this->alta()->crear('adentro@ejemplo.com');
            $this->fail('Copiar una plantilla inexistente tiene que fallar.');
        } catch (\Throwable) {
            // esperado
        }

        config([
            'tenancy.plantilla_vigente' => self::PLANTILLA,
            'tenancy.cerrojo.intentos' => 2,
            'tenancy.cerrojo.espera_ms' => 10,
        ]);

        $ok = $this->alta()->crear('despues@ejemplo.com');
        $this->creadas[] = $ok->tenant->database;

        $this->assertSame(TenantEstado::Activo, $ok->tenant->fresh()->estado);
    }

    public function test_a_stale_template_is_caught_before_anyone_is_invited(): void
    {
        // CASO REAL, no hipotético: la plantilla vigente por defecto estaba sólo
        // migrada y sin sembrar. El alta terminaba «bien» y el inquilino entraba
        // a un panel con cero inmuebles. Nadie se enteraba hasta que la persona
        // invitada ya se había ido.
        //
        // La plantilla no se puede inspeccionar —abrirle una conexión rompe la
        // copia siguiente— así que se verifica el inquilino recién creado, que
        // es una copia idéntica y a la que ya estamos conectados.
        $soloMigrada = 'demo_probe_tpl_pelada';
        DB::connection('maintenance')->statement('DROP DATABASE IF EXISTS "'.$soloMigrada.'"');
        DB::connection('maintenance')->statement('CREATE DATABASE "'.$soloMigrada.'" TEMPLATE "'.self::PLANTILLA.'"');

        // Se le vacía el contenido: queda el esquema, sin datos de muestra.
        config(['database.connections.probe_pelada' => array_merge(
            config('database.connections.pgsql'), ['database' => $soloMigrada],
        )]);
        DB::purge('probe_pelada');
        DB::connection('probe_pelada')->statement('TRUNCATE properties, property_owners CASCADE');
        DB::purge('probe_pelada');

        config(['tenancy.plantilla_vigente' => $soloMigrada]);

        try {
            $this->alta()->crear('vacio@ejemplo.com');
            $this->fail('Una plantilla sin contenido no puede dar un alta exitosa.');
        } catch (PlantillaInservible $e) {
            $this->assertStringContainsString('properties', $e->getMessage());
            $this->assertStringContainsString('demo:plantilla:construir', $e->getMessage());
        } finally {
            DB::connection('maintenance')->statement('DROP DATABASE IF EXISTS "'.$soloMigrada.'"');
        }

        $tenant = Tenant::query()->firstWhere('email', 'vacio@ejemplo.com');
        $this->assertSame(TenantEstado::Fallido, $tenant->estado);

        $existe = DB::connection('maintenance')
            ->selectOne('SELECT 1 AS x FROM pg_database WHERE datname = ?', [$tenant->database]);
        $this->assertNull($existe, 'La base del inquilino inservible tiene que quedar borrada.');
    }

    public function test_a_failed_alta_does_not_block_the_next_one(): void
    {
        // El `finally` que suelta el cerrojo. Sin él, una excepción lo deja
        // tomado mientras el proceso siga vivo y TODAS las altas siguientes se
        // cuelgan — sin error que lo delate.
        $this->haceFallarLaCreacionDelOwner();

        try {
            $this->alta()->crear('primero@ejemplo.com');
        } catch (RuntimeException) {
            // esperado
        }

        // Se devuelve el creador real: lo que se prueba acá es que el CERROJO
        // quedó libre, no que el segundo intento también reviente.
        $this->app->bind(CreadorDeOwner::class, fn () => new CreadorDeOwner);

        config(['tenancy.cerrojo.intentos' => 2, 'tenancy.cerrojo.espera_ms' => 10]);

        $ok = $this->alta()->crear('segundo@ejemplo.com');
        $this->creadas[] = $ok->tenant->database;

        $this->assertSame(TenantEstado::Activo, $ok->tenant->fresh()->estado);
    }
}
