<?php

namespace Tests\Feature\Tenancy;

use App\Enums\TenantEstado;
use App\Models\Tenant;
use App\Tenancy\BorraInquilinos;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\UsaBaseCentral;
use Tests\TestCase;

/**
 * Borrar un inquilino: su base, sus archivos, y nada más.
 *
 * El orden no es negociable y cada paso está por un motivo distinto. Lo que se
 * prueba acá no es que borre —eso es fácil— sino que borre CUANDO ALGO SE
 * INTERPONE: una pestaña abierta, un borrado a medias, un intento de tocar lo
 * que no debe.
 */
class BorradoDeInquilinoTest extends TestCase
{
    use UsaBaseCentral;

    private array $creadas = [];

    protected function tearDown(): void
    {
        foreach ($this->creadas as $base) {
            // Sólo si sigue existiendo: el propio test puede haberla borrado, y
            // ALTER DATABASE sobre una base inexistente es un error, no un
            // no-op.
            if ($this->existe($base)) {
                DB::connection('maintenance')->statement('ALTER DATABASE "'.$base.'" CONNECTION LIMIT -1');
                DB::connection('maintenance')->statement('DROP DATABASE IF EXISTS "'.$base.'"');
            }
        }

        parent::tearDown();
    }

    private function inquilino(string $slug, TenantEstado $estado = TenantEstado::Expirado): Tenant
    {
        $base = config('tenancy.prefijo_pruebas').$slug;

        DB::connection('maintenance')->statement('DROP DATABASE IF EXISTS "'.$base.'"');
        DB::connection('maintenance')->statement('CREATE DATABASE "'.$base.'"');
        $this->creadas[] = $base;

        return Tenant::create([
            'slug' => $slug.'aaaa',
            'database' => $base,
            'email' => $slug.'@ejemplo.com',
            'template_version' => 'demo_template',
            'expira_en' => now()->subDay(),
            'estado' => $estado,
        ]);
    }

    private function existe(string $base): bool
    {
        return DB::connection('maintenance')
            ->selectOne('SELECT 1 AS x FROM pg_database WHERE datname = ?', [$base]) !== null;
    }

    private function borrador(): BorraInquilinos
    {
        return app(BorraInquilinos::class);
    }

    public function test_a_deleted_tenant_leaves_no_database_and_no_files(): void
    {
        Storage::fake('public');
        $tenant = $this->inquilino('borrable');
        Storage::disk('public')->put('tenants/'.$tenant->slug.'/1/foto.webp', 'bytes');

        $this->borrador()->borrar($tenant);

        $this->assertFalse($this->existe($tenant->database));
        $this->assertFalse(Storage::disk('public')->exists('tenants/'.$tenant->slug.'/1/foto.webp'));
    }

    public function test_the_row_survives_the_database_it_pointed_at(): void
    {
        // Se conserva a propósito: sirve para medir el uso del demo y para que
        // el padrón pueda mostrar qué pasó con cada inquilino.
        $tenant = $this->inquilino('sobrevive');

        $this->borrador()->borrar($tenant);

        $fresco = Tenant::query()->firstWhere('slug', $tenant->slug);

        $this->assertNotNull($fresco);
        $this->assertSame(TenantEstado::Borrado, $fresco->estado);
        $this->assertNotNull($fresco->borrado_en);
    }

    public function test_an_open_session_does_not_stop_the_deletion(): void
    {
        // EL DEFECTO QUE ESTE TEST PREVIENE (hallazgo M-1 de la auditoría):
        // terminar las sesiones sin cerrar la puerta antes deja una ventana en
        // la que el navegador reconecta y el DROP falla. Con pestañas abiertas
        // eso no es raro: es lo normal.
        $tenant = $this->inquilino('conpestania');

        $sesion = new \PDO(
            'pgsql:host='.env('DB_HOST', '127.0.0.1').';port='.env('DB_PORT', '5432').';dbname='.$tenant->database,
            env('DB_USERNAME', 'postgres'),
            (string) env('DB_PASSWORD', ''),
        );
        $sesion->query('SELECT 1')->fetch();

        $this->borrador()->borrar($tenant);
        $sesion = null;

        $this->assertFalse($this->existe($tenant->database));
    }

    public function test_closing_the_door_actually_refuses_new_connections(): void
    {
        // Prueba EL MECANISMO que cierra la carrera, porque la carrera misma no
        // se puede reproducir dentro del proceso: haría falta un cliente que se
        // reconecte solo entre el terminate y el DROP, que es lo que hace un
        // navegador y no hace una sesión PDO de un test.
        //
        // Lo verificable es esto: después de cerrar la puerta, una conexión
        // nueva se rechaza. Con eso, la ventana deja de existir.
        // SE PRUEBA CON UN ROL NORMAL, Y NO CON `postgres`, PORQUE
        // `CONNECTION LIMIT` NO APLICA A SUPERUSUARIOS. Probarlo como superusuario
        // daba falso negativo: la conexión entraba igual y parecía que el cierre
        // no servía.
        //
        // De ahí sale un requisito de despliegue que estaba implícito: el rol con
        // el que la aplicación atiende peticiones NO puede ser superusuario, o
        // este mecanismo no protege nada el día que haga falta.
        $tenant = $this->inquilino('puertacerr');

        DB::connection('maintenance')->statement('DROP ROLE IF EXISTS demo_probe_rol');
        DB::connection('maintenance')->statement("CREATE ROLE demo_probe_rol LOGIN PASSWORD 'probe'");
        DB::connection('maintenance')->statement('GRANT CONNECT ON DATABASE "'.$tenant->database.'" TO demo_probe_rol');

        try {
            $this->borrador()->cerrarLaPuerta($tenant);

            try {
                new \PDO(
                    'pgsql:host='.env('DB_HOST', '127.0.0.1').';port='.env('DB_PORT', '5432').';dbname='.$tenant->database,
                    'demo_probe_rol',
                    'probe',
                );
                $this->fail('Con la puerta cerrada, una conexión nueva tiene que ser rechazada.');
            } catch (\PDOException $e) {
                $this->assertStringContainsString('too many connections', mb_strtolower($e->getMessage()));
            }
        } finally {
            DB::connection('maintenance')->statement('ALTER DATABASE "'.$tenant->database.'" CONNECTION LIMIT -1');
            // Revocar antes de borrar el rol: Postgres no borra un rol del que
            // todavía cuelgan privilegios.
            DB::connection('maintenance')->statement('REVOKE ALL ON DATABASE "'.$tenant->database.'" FROM demo_probe_rol');
            DB::connection('maintenance')->statement('DROP ROLE IF EXISTS demo_probe_rol');
        }
    }

    public function test_the_door_is_closed_first_then_sessions_are_terminated_then_the_drop(): void
    {
        // EL CONTRATO QUE EL DISEÑO LLAMA NO NEGOCIABLE, probado como orden.
        //
        // La carrera real —un navegador que reconecta entre `pg_terminate_backend`
        // y el DROP— no se puede reproducir dentro del proceso. Pero el orden SÍ
        // se puede observar, y el orden es justamente lo que la cierra.
        //
        // El test anterior («una sesión abierta no impide el borrado») parecía
        // cubrir esto y NO lo cubría: seguía verde con el cierre de puerta
        // quitado. Lo destapó la auditoría de implementación.
        $tenant = $this->inquilino('ordencorr');

        DB::connection('maintenance')->flushQueryLog();
        DB::connection('maintenance')->enableQueryLog();

        $this->borrador()->borrar($tenant);

        $sentencias = collect(DB::connection('maintenance')->getQueryLog())
            ->pluck('query')
            ->values();

        $cierre = $sentencias->search(fn (string $q): bool => str_contains($q, 'CONNECTION LIMIT 0'));
        $mata = $sentencias->search(fn (string $q): bool => str_contains($q, 'pg_terminate_backend'));
        $borra = $sentencias->search(fn (string $q): bool => str_contains($q, 'DROP DATABASE'));

        $this->assertNotFalse($cierre, 'Falta cerrar la puerta: sin eso queda la ventana para reconectar.');
        $this->assertNotFalse($mata);
        $this->assertNotFalse($borra);

        $this->assertLessThan($mata, $cierre, 'Cerrar la puerta va ANTES de terminar sesiones.');
        $this->assertLessThan($borra, $mata, 'Terminar sesiones va ANTES del DROP.');
    }

    public function test_a_half_finished_deletion_can_be_retried(): void
    {
        // Un borrado a medias no puede quedar en un estado que sólo se arregle a
        // mano. Cada paso comprueba si ya está hecho antes de hacerlo.
        $tenant = $this->inquilino('reintento');

        DB::connection('maintenance')->statement('DROP DATABASE "'.$tenant->database.'"');

        $this->borrador()->borrar($tenant);

        $this->assertSame(TenantEstado::Borrado, $tenant->fresh()->estado);
    }

    public function test_aborting_after_closing_the_door_leaves_the_tenant_reachable(): void
    {
        // Si el operador cierra la puerta y después aborta —por un reclamo, por
        // ejemplo— la base queda con CONNECTION LIMIT 0 y NADIE puede entrar
        // aunque el inquilino siga activo. El padrón lo mostraría sano y no lo
        // estaría.
        $tenant = $this->inquilino('abortado', TenantEstado::Activo);

        $this->borrador()->cerrarLaPuerta($tenant);
        $this->assertSame(0, $this->limiteDe($tenant->database));

        $this->borrador()->abortar($tenant);

        $this->assertSame(-1, $this->limiteDe($tenant->database));
    }

    public function test_outside_the_testing_environment_the_probe_prefix_is_refused(): void
    {
        // T-1 de la reauditoría: el guard existía y NINGÚN test caía si alguien
        // lo revertía. Un contrato sin test es un contrato abierto en CI.
        //
        // El prefijo de pruebas vale sólo en `testing`. Aceptarlo siempre
        // ampliaría en producción la lista de bases que la última red deja
        // borrar: una fila central mal cargada con ese prefijo pasaría el
        // control aunque no sea una base de inquilino.
        $tenant = $this->inquilino('conprefpru');

        app()->detectEnvironment(fn (): string => 'production');

        try {
            $this->borrador()->borrar($tenant);
            $this->fail('Fuera de testing, el prefijo de pruebas no puede pasar la red.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('prefijo', $e->getMessage());
        } finally {
            app()->detectEnvironment(fn (): string => 'testing');
        }
    }

    public function test_aborting_a_stalled_deletion_has_an_operable_command(): void
    {
        // M-2 de la reauditoría: `abortar()` existía como método y no había
        // camino para usarlo. Si el borrado muere entre cerrar la puerta y el
        // DROP, la base queda viva e inalcanzable — el padrón muestra un
        // inquilino sano que no abre, que es el peor estado porque no parece un
        // error.
        $tenant = $this->inquilino('atascado', TenantEstado::Activo);

        $this->borrador()->cerrarLaPuerta($tenant);
        $this->assertSame(0, $this->limiteDe($tenant->database));

        $this->artisan('demo:abortar-borrado', ['--slug' => $tenant->slug])->assertSuccessful();

        $this->assertSame(-1, $this->limiteDe($tenant->database));
        $this->assertSame(TenantEstado::Activo, $tenant->fresh()->estado, 'Abortar no cambia el estado.');
    }

    public function test_the_deletion_refuses_to_name_the_central_or_a_template(): void
    {
        // La última red. Un borrado que pudiera nombrar la central se llevaría
        // el padrón entero; uno que nombrara la plantilla dejaría al demo sin
        // poder dar de alta a nadie.
        foreach ([config('database.connections.central.database'), config('tenancy.plantilla_vigente')] as $prohibida) {
            $tenant = Tenant::create([
                'slug' => 'prohibida'.substr(md5($prohibida), 0, 3),
                'database' => $prohibida,
                'email' => 'x@ejemplo.com',
                'template_version' => 'demo_template',
                'expira_en' => now()->subDay(),
                'estado' => TenantEstado::Expirado,
            ]);

            try {
                $this->borrador()->borrar($tenant);
                $this->fail("Debió negarse a borrar «{$prohibida}».");
            } catch (DomainException $e) {
                $this->assertStringContainsString($prohibida, $e->getMessage());
            }
        }
    }

    private function limiteDe(string $base): int
    {
        return (int) DB::connection('maintenance')
            ->selectOne('SELECT datconnlimit AS l FROM pg_database WHERE datname = ?', [$base])->l;
    }
}
