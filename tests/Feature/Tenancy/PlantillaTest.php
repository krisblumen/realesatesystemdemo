<?php

namespace Tests\Feature\Tenancy;

use Database\Seeders\DemoTemplateSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * La plantilla desde la que nace cada inquilino.
 *
 * Se construye UNA vez y se copia; por eso lo que esté mal acá está mal en cada
 * inquilino que nazca después, y en un lugar que no señala a la plantilla.
 *
 * Estos tests construyen una plantilla de verdad —no la simulan— porque las dos
 * cosas que pueden salir mal sólo se ven contra Postgres: que traiga usuarios
 * que no debería, y que NO traiga el contenido que hace que el demo se vea vivo.
 */
class PlantillaTest extends TestCase
{
    private const PLANTILLA = 'demo_probe_tpl';

    private static bool $construida = false;

    private string $original;

    protected function setUp(): void
    {
        parent::setUp();

        $this->original = config('database.connections.pgsql.database');

        if (! self::$construida) {
            $this->artisan('demo:plantilla:construir', [
                'nombre' => self::PLANTILLA,
                '--force' => true,
            ])->assertSuccessful();

            self::$construida = true;
        }
    }

    protected function tearDown(): void
    {
        config(['database.connections.pgsql.database' => $this->original]);
        DB::purge('pgsql');

        parent::tearDown();
    }

    /**
     * Se borra con PDO crudo y no con la fachada, a propósito.
     *
     * `tearDownAfterClass` corre DESPUÉS de que se destruye la aplicación: el
     * contenedor ya no está y `DB::` no resuelve. Usar la fachada acá deja un
     * error al final de la clase que no señala a nada — los cinco tests pasan y
     * la corrida igual sale en rojo.
     */
    public static function tearDownAfterClass(): void
    {
        if (self::$construida) {
            $pdo = new \PDO(
                'pgsql:host='.env('DB_HOST', '127.0.0.1').';port='.env('DB_PORT', '5432').';dbname=postgres',
                env('DB_USERNAME', 'postgres'),
                (string) env('DB_PASSWORD', ''),
            );
            $pdo->exec('DROP DATABASE IF EXISTS "'.self::PLANTILLA.'"');

            self::$construida = false;
        }

        parent::tearDownAfterClass();
    }

    private function enLaPlantilla(callable $fn): mixed
    {
        config(['database.connections.pgsql.database' => self::PLANTILLA]);
        DB::purge('pgsql');

        try {
            return $fn();
        } finally {
            config(['database.connections.pgsql.database' => $this->original]);
            DB::purge('pgsql');
        }
    }

    public function test_the_template_carries_no_owner_because_each_tenant_gets_its_own(): void
    {
        // EL DEFECTO QUE ESTE TEST PREVIENE: `DatabaseSeeder` termina llamando a
        // OwnerSeeder, que crea un usuario con correo FIJO. Una plantilla con
        // ese usuario adentro hace que cada inquilino nazca con un `owner` que
        // no es de nadie y con una contraseña que alguien más conoce.
        //
        // El owner del inquilino se crea en el alta, con contraseña generada.
        $owners = $this->enLaPlantilla(fn () => DB::table('users')
            ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', 'owner')
            ->count());

        $this->assertSame(0, $owners, 'La plantilla no puede traer un owner: cada inquilino crea el suyo.');
    }

    public function test_the_template_arrives_alive_and_not_empty(): void
    {
        // EL DEMO SE JUEGA EN EL PRIMER MINUTO. Un inquilino que abre el panel y
        // ve listas en cero tiene que cargar datos para recién entonces empezar
        // a mirar, y no lo va a hacer. El contenido viaja en la plantilla porque
        // así se copia en vez de recalcularse: cuesta cero por alta.
        $conteos = $this->enLaPlantilla(fn (): array => [
            'zonas' => DB::table('zones')->count(),
            'inmuebles' => DB::table('properties')->count(),
            'clientes' => DB::table('property_owners')->count(),
            'agentes' => DB::table('users')->count(),
        ]);

        $this->assertGreaterThan(0, $conteos['zonas'], 'Sin zonas no se puede cargar un inmueble.');
        $this->assertGreaterThan(0, $conteos['inmuebles'], 'Un panel de inmuebles vacío no muestra el producto.');
        $this->assertGreaterThan(0, $conteos['clientes']);
        $this->assertGreaterThan(0, $conteos['agentes'], 'Los agentes de muestra son contenido, no un descuido.');
    }

    public function test_the_template_carries_the_catalogue_and_the_cms(): void
    {
        $estado = $this->enLaPlantilla(fn (): array => [
            'paginas' => DB::table('frontend_pages')->orderBy('id')->pluck('key')->all(),
            'estados' => DB::table('states')->count(),
            'codigos_postales' => DB::table('postal_codes')->count(),
            'caracteristicas' => DB::table('features')->count(),
            'roles' => DB::table('roles')->count(),
            'postgis' => DB::selectOne('SELECT postgis_version() AS v')->v,
        ]);

        $this->assertSame(
            ['home', 'nosotros', 'servicios', 'inversionistas', 'contacto', 'proyectos'],
            $estado['paginas'],
        );
        $this->assertGreaterThan(0, $estado['estados']);
        $this->assertGreaterThan(0, $estado['codigos_postales']);
        $this->assertGreaterThan(0, $estado['caracteristicas']);
        $this->assertGreaterThan(0, $estado['roles'], 'Los roles tienen que existir antes de crear al owner del inquilino.');
        $this->assertNotEmpty($estado['postgis'], 'Sin PostGIS las zonas no funcionan.');
    }

    public function test_the_zones_look_like_the_ones_the_application_produces(): void
    {
        // La lección de la ZoneFactory: una factory que produce datos que la
        // aplicación nunca produce deja pasar bugs con la suite en verde. Las
        // zonas de la plantilla llevan municipio y código postal, como las que
        // se crean desde el panel.
        $zonas = $this->enLaPlantilla(fn () => DB::table('zones')
            ->whereNotNull('municipality_id')
            ->whereNotNull('postal_code')
            ->count());

        $this->assertGreaterThan(0, $zonas, 'Una zona sin municipio ni CP no es una zona que el panel sepa crear.');
    }

    public function test_the_template_builds_from_the_real_cli_with_the_sentinel(): void
    {
        // EL DEFECTO QUE ESTE TEST PREVIENE, encontrado en el servidor y no acá.
        //
        // En producción la conexión por defecto apunta al CENTINELA: una base
        // que no existe a propósito, para que una consulta anterior a resolver
        // el inquilino muera ruidosamente en vez de escribir en otro lado. La
        // construcción de la plantilla fallaba ahí, porque el almacén de caché
        // ya estaba resuelto contra esa conexión y una migración de permisos lo
        // vacía.
        //
        // SE CORRE LA CLI DE VERDAD, en un proceso aparte, y no `$this->artisan()`.
        // Probé tres formas de reproducirlo dentro del proceso de pruebas y
        // ninguna lo logró: el arranque del harness resuelve las cosas en otro
        // orden. Un test que no reproduce el fallo no protege de él, por más
        // que parezca cubrirlo — así que se paga el costo de arrancar un
        // proceso.
        $base = 'demo_probe_tpl_cli';

        $resultado = Process::path(base_path())
            ->env(['DB_DATABASE' => 'demo_no_existe_a_proposito', 'CACHE_STORE' => 'database'])
            ->timeout(600)
            ->run('php artisan demo:plantilla:construir '.$base.' --force');

        DB::connection('maintenance')->statement('DROP DATABASE IF EXISTS "'.$base.'"');

        $this->assertTrue(
            $resultado->successful(),
            "La plantilla no se construye con la conexión por defecto apuntando al centinela:\n".$resultado->output(),
        );
    }

    private function enLaPlantillaLlamada(string $base, callable $fn): mixed
    {
        config(['database.connections.probe_tpl' => array_merge(
            config('database.connections.central'),
            ['database' => $base],
        )]);
        DB::purge('probe_tpl');

        try {
            return $fn(DB::connection('probe_tpl'));
        } finally {
            DB::purge('probe_tpl');
        }
    }

    public function test_no_template_seeder_depends_on_a_dev_only_dependency(): void
    {
        // EL DEFECTO QUE ESTE TEST PREVIENE, encontrado en el servidor.
        //
        // `fake()` viene de `fakerphp/faker`, que es dependencia de DESARROLLO.
        // La plantilla se construye en el servidor, instalado con `--no-dev`, y
        // ahí esa función no existe: el sembrado muere con «Call to undefined
        // function fake()» después de cargar trece mil registros de geografía.
        //
        // Acá nunca falla —en desarrollo Faker está— así que el único guardián
        // posible es este: revisar que ningún sembrador de la lista lo use.
        $usados = (new \ReflectionClass(DemoTemplateSeeder::class))
            ->getFileName();

        $lista = file_get_contents($usados);
        preg_match_all('/(\w+Seeder)::class/', $lista, $coincidencias);

        $culpables = [];

        foreach (array_unique($coincidencias[1]) as $seeder) {
            $archivo = database_path('seeders/'.$seeder.'.php');

            if (! is_file($archivo)) {
                continue;
            }

            // Se ignoran los comentarios: lo que importa es el código.
            $codigo = preg_replace('#/\*.*?\*/|//.*$#ms', '', (string) file_get_contents($archivo));

            if (preg_match('/\bfake\(\)|::factory\(/', (string) $codigo) === 1) {
                $culpables[] = $seeder;
            }
        }

        $this->assertSame(
            [],
            $culpables,
            'Estos sembradores de la plantilla usan Faker o factories, y no existen en un servidor instalado con --no-dev: '
            .implode(', ', $culpables),
        );
    }

    public function test_building_a_template_does_not_change_the_one_in_use(): void
    {
        // Son dos actos separados a propósito: se puede construir la siguiente
        // mientras la actual sigue sirviendo altas. Si construir cambiara la
        // vigente, habría una ventana en la que las altas usan una plantilla que
        // todavía no terminó de sembrarse.
        $this->assertNotSame(self::PLANTILLA, config('tenancy.plantilla_vigente'));
    }
}
