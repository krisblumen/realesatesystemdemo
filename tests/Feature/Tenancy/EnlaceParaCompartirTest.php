<?php

namespace Tests\Feature\Tenancy;

use App\Enums\TenantEstado;
use App\Models\EnlaceDeMuestra;
use App\Models\Tenant;
use App\Tenancy\CompartirElSitio;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\UsaBaseCentral;
use Tests\TestCase;

/**
 * El enlace con el que un inquilino le muestra su sitio a otra persona.
 *
 * POR QUÉ EXISTE. El entorno queda cerrado: sin sesión no se ve nada. Pero un
 * prospecto que armó su demo quiere enseñárselo a su socio o a su jefe, y ese es
 * el momento en que se decide una venta. Sin salida, la única forma sería
 * prestarle su cuenta — que es peor de todas las maneras.
 *
 * POR QUÉ NO ABRIMOS EL SITIO. Porque el prospecto carga datos REALES: inmuebles
 * suyos, teléfonos de clientes de verdad. Abrir el entorno protegería al que
 * piensa en esto y dejaría expuesto al que no, que son casi todos. Así la
 * decisión de mostrar la toma quien cargó los datos.
 *
 * Reusa la maquinaria de los contratos —SHA-256 del token, el claro nunca se
 * guarda— porque ya sobrevivió a una auditoría.
 */
class EnlaceParaCompartirTest extends TestCase
{
    use UsaBaseCentral;

    /**
     * NO se usa `RefreshDatabase`, y el trait de la central explica por qué:
     * envuelve la conexión POR DEFECTO en una transacción, y en esta épica esa
     * conexión cambia a mitad de petición. `ResolveTenant` la purga al resolver
     * y se lleva la transacción puesta — con las filas que el test acababa de
     * crear adentro.
     *
     * Costó un rato de depuración: el código funcionaba fuera de una petición y
     * fallaba dentro. Se limpia a mano.
     */
    protected function setUp(): void
    {
        parent::setUp();

        DB::table('enlaces_de_muestra')->delete();

        config(['tenancy.dominio_base' => 'demo.test']);

        Tenant::create([
            'slug' => 'aaaabbbbcccc',
            'database' => config('database.connections.pgsql.database'),
            'email' => 'muestra@ejemplo.com',
            'template_version' => 'demo_template',
            'expira_en' => now()->addDays(30),
            'estado' => TenantEstado::Activo,
        ]);

        Route::middleware('web')->get('/_publica', fn () => 'contenido del sitio')->name('sonda.publica');
    }

    protected function tearDown(): void
    {
        DB::table('enlaces_de_muestra')->delete();

        parent::tearDown();
    }

    private function abrir(string $token): TestResponse
    {
        return $this->get('http://aaaabbbbcccc.demo.test/muestra/'.$token);
    }

    public function test_the_link_opens_the_site_without_a_session(): void
    {
        $token = app(CompartirElSitio::class)->generar();

        $this->abrir($token)->assertRedirect();

        $this->get('http://aaaabbbbcccc.demo.test/_publica')
            ->assertOk()
            ->assertSee('contenido del sitio');
    }

    public function test_the_redemption_route_is_not_blocked_by_the_closure(): void
    {
        // LA TRAMPA. El cierre exige sesión, y quien llega con el enlace todavía
        // no la tiene: si la ruta que la crea también quedara cerrada, nadie
        // podría canjear nunca. Es el mismo defecto que tuvo el transporte de
        // Livewire, que impedía iniciar la sesión que el propio cierre exige.
        $token = app(CompartirElSitio::class)->generar();

        $this->assertNotEquals(
            route('filament.admin.auth.login'),
            $this->abrir($token)->headers->get('location'),
        );
    }

    public function test_the_plain_token_is_never_stored(): void
    {
        $token = app(CompartirElSitio::class)->generar();

        $this->assertFalse(
            DB::table('enlaces_de_muestra')->where('token_hash', $token)->exists(),
            'El token en claro nunca se guarda: si la base se filtra, los enlaces no sirven.',
        );
        $this->assertSame(1, EnlaceDeMuestra::query()->count());
    }

    public function test_generating_a_new_one_kills_the_previous(): void
    {
        // Uno solo a la vez: varios enlaces activos son una lista que alguien
        // tiene que administrar, y nadie lo va a hacer en un demo de dos días.
        $viejo = app(CompartirElSitio::class)->generar();
        $nuevo = app(CompartirElSitio::class)->generar();

        $this->abrir($viejo);
        $this->get('http://aaaabbbbcccc.demo.test/_publica')->assertRedirect();

        $this->abrir($nuevo);
        $this->get('http://aaaabbbbcccc.demo.test/_publica')->assertOk();
    }

    public function test_a_revoked_link_stops_working(): void
    {
        $token = app(CompartirElSitio::class)->generar();

        app(CompartirElSitio::class)->revocar();

        $this->abrir($token);
        $this->get('http://aaaabbbbcccc.demo.test/_publica')->assertRedirect();
    }

    public function test_an_expired_link_stops_working(): void
    {
        $token = app(CompartirElSitio::class)->generar();

        DB::table('enlaces_de_muestra')->update(['expira_en' => now()->subMinute()]);

        $this->abrir($token);
        $this->get('http://aaaabbbbcccc.demo.test/_publica')->assertRedirect();
    }

    public function test_an_invented_token_does_nothing(): void
    {
        $this->abrir(str_repeat('a', 40));

        $this->get('http://aaaabbbbcccc.demo.test/_publica')->assertRedirect();
    }

    public function test_it_never_opens_the_panel(): void
    {
        // Compartir el sitio NO es compartir la cuenta. El panel tiene su propia
        // autenticación y esto no crea ningún usuario: quien entra con el enlace
        // ve lo que vería un visitante, nada más.
        $token = app(CompartirElSitio::class)->generar();

        $this->abrir($token);

        $this->get('http://aaaabbbbcccc.demo.test/admin')
            ->assertRedirect();

        $this->assertGuest();
    }
}
