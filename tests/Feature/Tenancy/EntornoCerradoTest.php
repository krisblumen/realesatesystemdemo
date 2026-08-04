<?php

namespace Tests\Feature\Tenancy;

use App\Enums\TenantEstado;
use App\Models\Tenant;
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

    public function test_the_closure_does_not_touch_anything_outside_a_tenant(): void
    {
        // Se activa cuando HAY inquilino, no con una bandera: una bandera es
        // algo que alguien puede olvidarse de encender, y el síntoma de
        // olvidarla es un demo abierto que parece cerrado.
        $this->get('http://demo.test/_publica')
            ->assertOk()
            ->assertSee('contenido');
    }

    public function test_a_tenant_response_asks_not_to_be_indexed(): void
    {
        $this->get('http://cerradodemo1.demo.test/_token')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }
}
