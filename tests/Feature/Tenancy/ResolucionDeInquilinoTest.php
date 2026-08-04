<?php

namespace Tests\Feature\Tenancy;

use App\Enums\TenantEstado;
use App\Http\Middleware\CierraElDemo;
use App\Http\Middleware\ResolveTenant;
use App\Models\Tenant;
use App\Tenancy\InquilinoActual;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\UsaBaseCentral;
use Tests\TestCase;

/**
 * De qué inquilino es cada petición.
 *
 * Se resuelve del `Host` y no de la sesión, porque la sesión es circular: vive
 * en la base de datos, así que para leerla hay que saber a qué base conectarse,
 * y para saberlo hay que leer la sesión. El encabezado llega antes de que corra
 * un solo middleware y rompe el ciclo.
 */
class ResolucionDeInquilinoTest extends TestCase
{
    use UsaBaseCentral;

    private const BASE_A = 'demo_probe_res_a';

    private const BASE_B = 'demo_probe_res_b';

    private string $original;

    protected function setUp(): void
    {
        parent::setUp();

        // Se apaga el cierre a propósito: acá se prueba a QUÉ BASE resuelve una
        // petición, no quién puede verla. Con el cierre puesto, la sonda
        // devolvería una redirección al login y el test no podría mirar la
        // conexión. El cierre tiene sus propios tests.
        $this->withoutMiddleware(CierraElDemo::class);

        $this->original = config('database.connections.pgsql.database');

        config(['tenancy.dominio_base' => 'demo.test']);

        foreach ([self::BASE_A, self::BASE_B] as $base) {
            DB::connection('maintenance')->statement('DROP DATABASE IF EXISTS "'.$base.'"');
            DB::connection('maintenance')->statement('CREATE DATABASE "'.$base.'"');
        }

        // Se informa la base de la conexión POR DEFECTO, no la de `pgsql`: en
        // el host central la por defecto es `central`, y preguntar por `pgsql`
        // devolvería un valor que en ese modo no se usa para nada.
        Route::middleware('web')->get('/_sonda', fn () => response()->json([
            'base' => DB::connection()->getDatabaseName(),
            'slug' => app(InquilinoActual::class)->slug(),
        ]));
    }

    protected function tearDown(): void
    {
        config(['database.connections.pgsql.database' => $this->original]);
        DB::purge('pgsql');

        foreach ([self::BASE_A, self::BASE_B] as $base) {
            DB::connection('maintenance')->statement('DROP DATABASE IF EXISTS "'.$base.'"');
        }

        parent::tearDown();
    }

    private function inquilino(string $slug, string $base, TenantEstado $estado = TenantEstado::Activo): Tenant
    {
        return Tenant::create([
            'slug' => $slug,
            'database' => $base,
            'email' => $slug.'@ejemplo.com',
            'template_version' => 'demo_template',
            'expira_en' => now()->addDays(30),
            'estado' => $estado,
        ]);
    }

    public function test_the_middleware_runs_before_the_session_starts(): void
    {
        // NO ES PREFERENCIA, ES REQUISITO: la sesión se guarda en base de datos,
        // así que arrancarla antes de resolver la leería de la base equivocada.
        //
        // Se prueba el ORDEN y no sólo el comportamiento, porque cualquier
        // paquete que se agregue después y se registre antes lo rompe en
        // silencio.
        $prioridad = app(Kernel::class)->getMiddlewarePriority();

        $this->assertContains(ResolveTenant::class, $prioridad);
        $this->assertLessThan(
            array_search(StartSession::class, $prioridad, true),
            array_search(ResolveTenant::class, $prioridad, true),
            'ResolveTenant tiene que correr antes que StartSession.',
        );
    }

    public function test_two_hosts_reach_two_different_databases(): void
    {
        $this->inquilino('aaaabbbbcccc', self::BASE_A);
        $this->inquilino('ddddeeeeffff', self::BASE_B);

        $a = $this->get('http://aaaabbbbcccc.demo.test/_sonda')->json();
        $b = $this->get('http://ddddeeeeffff.demo.test/_sonda')->json();

        $this->assertSame(self::BASE_A, $a['base']);
        $this->assertSame(self::BASE_B, $b['base']);
        $this->assertSame('aaaabbbbcccc', $a['slug']);
    }

    public function test_a_host_outside_the_demo_domain_is_left_completely_alone(): void
    {
        // EL DEFECTO QUE ESTE TEST PREVIENE, y que cometí: tratar cualquier host
        // desconocido como «el host central». Eso le cambiaba la conexión por
        // defecto a TODA la aplicación —incluidas las 1600 pruebas existentes,
        // que corren contra `localhost`— y las mandaba a leer a una base sin sus
        // tablas. La suite entera quedó colgada.
        //
        // Un host ajeno al dominio del demo no es central: es ajeno, y no se
        // toca.
        $antes = DB::connection()->getDatabaseName();

        $respuesta = $this->get('http://localhost/_sonda')->json();

        $this->assertSame($antes, $respuesta['base'], 'Un host ajeno no cambia la conexión de nadie.');
        $this->assertNull($respuesta['slug']);
    }

    public function test_the_central_host_resolves_no_tenant(): void
    {
        // En el host central la conexión por defecto es la central, y ningún
        // modelo de inquilino tiene dónde leer. Es lo que hace que «el host
        // central no toca datos de inquilino» lo cumpla el motor y no la
        // disciplina.
        $respuesta = $this->get('http://demo.test/_sonda')->json();

        $this->assertNull($respuesta['slug']);
        $this->assertSame(config('database.connections.central.database'), $respuesta['base']);
    }

    public function test_unknown_expired_and_malformed_all_answer_the_same(): void
    {
        // Distinguirlos permitiría averiguar qué inquilinos existieron.
        $this->inquilino('gggghhhhiiii', self::BASE_A, TenantEstado::Expirado);

        $inexistente = $this->get('http://jjjjkkkkllll.demo.test/_sonda');
        $expirado = $this->get('http://gggghhhhiiii.demo.test/_sonda');
        $malformado = $this->get('http://AB.demo.test/_sonda');

        $this->assertSame(404, $inexistente->status());
        $this->assertSame(404, $expirado->status());
        $this->assertSame(404, $malformado->status());
    }

    public function test_a_malformed_host_never_reaches_the_database(): void
    {
        // El formato se comprueba ANTES de consultar. Un host que no cumple no
        // llega siquiera a ser una consulta con un parámetro raro.
        DB::connection('central')->enableQueryLog();

        $this->get('http://no-es-un-slug-valido-porque-tiene-guiones.demo.test/_sonda')
            ->assertNotFound();

        $consultas = collect(DB::connection('central')->getQueryLog())
            ->filter(fn (array $q): bool => str_contains($q['query'], 'tenants'));

        $this->assertCount(0, $consultas, 'Un host malformado no tiene por qué consultar el padrón.');
    }

    public function test_a_tenant_connection_does_not_leak_into_the_next_request(): void
    {
        // El centinela vive en el `.env` del demo (`DB_DATABASE`), no en el
        // código: en desarrollo y en los tests esa misma conexión apunta a una
        // base real y tiene que seguir haciéndolo. Lo que el código garantiza es
        // esto otro, que es lo que importa: una petición no hereda la base de la
        // anterior.
        $this->inquilino('mmmmnnnnoooo', self::BASE_A);

        $this->get('http://mmmmnnnnoooo.demo.test/_sonda')->assertOk();
        $central = $this->get('http://demo.test/_sonda')->json();

        $this->assertNotSame(self::BASE_A, $central['base']);
    }
}
