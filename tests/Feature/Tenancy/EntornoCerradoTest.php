<?php

namespace Tests\Feature\Tenancy;

use App\Enums\TenantEstado;
use App\Http\Middleware\CierraElDemo;
use App\Models\Tenant;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\UsaBaseCentral;
use Tests\TestCase;

/**
 * El sitio del inquilino exige su sesión.
 *
 * Cerrado no significa recortado: el inquilino recorre su sitio entero, con el
 * mismo render y el mismo caché que vería un visitante. Lo que cambia es que
 * nadie de afuera puede navegarlo.
 */
class EntornoCerradoTest extends TestCase
{
    use UsaBaseCentral;

    private const BASE = 'demo_probe_cerrado';

    private string $original;

    protected function setUp(): void
    {
        parent::setUp();

        $this->original = config('database.connections.pgsql.database');
        config(['tenancy.dominio_base' => 'demo.test']);

        DB::connection('maintenance')->statement('DROP DATABASE IF EXISTS "'.self::BASE.'"');
        // Base VACÍA, no una copia de `demo_test`: copiarla fallaría con
        // «source database is being accessed by other users» porque este mismo
        // test está conectado a ella. Las rutas de sonda no consultan nada.
        DB::connection('maintenance')->statement('CREATE DATABASE "'.self::BASE.'"');

        Tenant::create([
            'slug' => 'cerradodemo1',
            'database' => self::BASE,
            'email' => 'cerrado@ejemplo.com',
            'template_version' => 'demo_template',
            'expira_en' => now()->addDays(30),
            'estado' => TenantEstado::Activo,
        ]);

        Route::middleware('web')->get('/_publica', fn () => 'contenido')->name('sonda.publica');
        Route::middleware('web')->get('/_token', fn () => 'firma')->name('contratos.publico.sonda');
    }

    protected function tearDown(): void
    {
        config(['database.connections.pgsql.database' => $this->original]);
        DB::purge('pgsql');
        DB::connection('maintenance')->statement('DROP DATABASE IF EXISTS "'.self::BASE.'"');

        parent::tearDown();
    }

    public function test_every_open_route_name_belongs_to_a_real_route(): void
    {
        // EL AGUJERO QUE ESTE TEST TAPA, y costó un defecto en producción.
        //
        // Los tests de abajo registran rutas de SONDA con nombres elegidos para
        // que coincidan con la lista. Eso prueba que el middleware compara bien
        // los prefijos — y no prueba nada sobre si esos nombres existen.
        //
        // `contratos.verificacion` estuvo en la lista sin ser el nombre de
        // ninguna ruta: las de verdad se llaman `contratos.verificar`. La lista
        // no abría nada, y la página de verificación de un contrato —la que
        // existe para que un tercero compruebe la integridad SIN tener cuenta—
        // mandaba al login.
        //
        // Un nombre que no existe no rompe nada visible: simplemente no abre. Es
        // el modo de falla más silencioso de una lista blanca.
        $registrados = collect(Route::getRoutes())
            ->map(fn ($ruta) => $ruta->getName())
            ->filter()
            ->values();

        $abiertas = (new \ReflectionClass(CierraElDemo::class))->getConstant('ABIERTAS');

        foreach ($abiertas as $prefijo) {
            $this->assertTrue(
                $registrados->contains(fn (string $nombre) => str_starts_with($nombre, $prefijo)),
                "«{$prefijo}» está en la lista de rutas abiertas y no es el nombre de ninguna ruta. ".
                'No abre nada, y nadie se entera hasta que alguien la necesita.',
            );
        }
    }

    public function test_without_a_session_a_tenant_route_gives_no_content(): void
    {
        $respuesta = $this->get('http://cerradodemo1.demo.test/_publica');

        $respuesta->assertRedirect();
        $this->assertStringNotContainsString('contenido', $respuesta->getContent());
    }

    public function test_the_contract_signing_link_keeps_working_without_a_session(): void
    {
        // Es lo que el demo quiere lucir: un cliente recibe un enlace y firma
        // sin tener cuenta. El control ahí no es la sesión sino el token, que es
        // de un solo uso y con límite de frecuencia.
        $this->get('http://cerradodemo1.demo.test/_token')
            ->assertOk()
            ->assertSee('firma');
    }

    public function test_livewire_is_not_blocked_because_it_is_the_transport_of_the_login(): void
    {
        // EL DEFECTO QUE ESTE TEST PREVIENE.
        //
        // El formulario de acceso de Filament no se envía por HTTP normal: lo
        // manda Livewire a `POST livewire/update`, que vive en el grupo `web` y
        // por lo tanto pasa por este cierre. Sin excepción, el cierre lo
        // redirigía al login por no haber sesión — o sea, impedía iniciar la
        // sesión que él mismo exige. Nadie puede entrar nunca, y no queda rastro
        // en el log, porque un 302 no es un error.
        //
        // Se compara por RUTA y no por nombre porque los nombres de las rutas de
        // Livewire cambian con el panel que las registra, y porque la ruta que
        // sirve su JavaScript no tiene nombre.
        //
        // Se desactiva SÓLO la verificación de CSRF: corre antes que el cierre,
        // así que sin quitarla el 419 taparía lo que este test mide.
        $respuesta = $this->withoutMiddleware(VerifyCsrfToken::class)
            ->post('http://cerradodemo1.demo.test/livewire/update', []);

        $this->assertNotEquals(
            route('filament.admin.auth.login'),
            $respuesta->headers->get('location'),
            'El cierre está mandando al login el transporte con el que se inicia sesión.',
        );
    }

    public function test_the_closure_does_not_touch_anything_outside_a_tenant(): void
    {
        // Se activa cuando HAY inquilino, no con una bandera: una bandera es
        // algo que alguien puede olvidarse de encender, y el síntoma de
        // olvidarla es un demo abierto que parece cerrado.
        //
        // Se usa un host AJENO y ya no el central: desde que el host central
        // sirve su propia portada, la petición no llega al cierre y este test
        // dejaría de medir lo que dice medir. Un host ajeno sigue siendo el caso
        // que importa — una instalación de la plataforma sin inquilinos, que no
        // tiene por qué quedar cerrada.
        $this->get('http://localhost/_publica')
            ->assertOk()
            ->assertSee('contenido');
    }

    public function test_a_tenant_response_asks_not_to_be_indexed(): void
    {
        $this->get('http://cerradodemo1.demo.test/_token')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }
}
