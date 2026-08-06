<?php

namespace Tests\Feature\Tenancy;

use App\Enums\TenantEstado;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\UsaBaseCentral;
use Tests\TestCase;

/**
 * Qué sirve el dominio base cuando no hay subdominio.
 *
 * EL 500 NO ERA UN BUG: era una decisión que nunca se tomó. El host central
 * apunta su conexión por defecto a la base central A PROPÓSITO, para que no
 * pueda tocar datos de ningún inquilino. Y esa base sólo tiene el padrón, las
 * sesiones y la cola — no tiene páginas. Así que cualquier ruta del sitio moría
 * ahí buscando tablas del CMS que no existen ni deben existir.
 *
 * La decisión: el host central sirve una página propia, mínima y sin base de
 * datos. Y si hay un sitio promocional configurado, redirige.
 *
 * ESE ORDEN IMPORTA. Al revés —redirigir siempre— el host central quedaría
 * atado a que exista otra cosa: mientras la landing no esté lista, cambiaríamos
 * un 500 por el 500 del otro dominio. Con la página propia como piso, la
 * redirección es una mejora y no un requisito.
 */
class HostCentralTest extends TestCase
{
    use UsaBaseCentral;

    private const BASE = 'demo_probe_central_host';

    protected function setUp(): void
    {
        parent::setUp();

        config(['tenancy.dominio_base' => 'demo.test']);
        config(['tenancy.sitio_promocional' => null]);

        DB::connection('maintenance')->statement('DROP DATABASE IF EXISTS "'.self::BASE.'"');
        DB::connection('maintenance')->statement('CREATE DATABASE "'.self::BASE.'"');

        Route::middleware('web')->get('/_sonda', fn () => 'contenido del inquilino');
    }

    protected function tearDown(): void
    {
        DB::purge('pgsql');
        DB::connection('maintenance')->statement('DROP DATABASE IF EXISTS "'.self::BASE.'"');

        parent::tearDown();
    }

    public function test_the_central_host_answers_without_touching_the_cms(): void
    {
        // La base central NO tiene las tablas del CMS, y no debe tenerlas. Si
        // esto devuelve 200, es prueba de que no las consultó: cualquier intento
        // moriría con «relation does not exist».
        $this->get('http://demo.test/')
            ->assertOk()
            ->assertSee('Landra');
    }

    public function test_no_route_of_the_central_host_falls_into_the_cms(): void
    {
        // No alcanza con atender la portada: el 500 aparecía en cualquier ruta,
        // y alguien que llega con un enlace viejo cae en una cualquiera.
        foreach (['/', '/nosotros', '/inmuebles'] as $ruta) {
            $this->get('http://demo.test'.$ruta)
                ->assertOk();
        }

        // Una ruta que no existe en NINGÚN host devuelve 404, y está bien: el
        // middleware del grupo `web` ni siquiera corre para una ruta sin
        // resolver. Lo que importa es que no sea un error del servidor —404 es
        // una respuesta honesta, 500 es una promesa rota.
        $this->get('http://demo.test/una-que-no-existe')
            ->assertNotFound();
    }

    public function test_with_a_promotional_site_configured_it_redirects_there(): void
    {
        config(['tenancy.sitio_promocional' => 'https://www.ejemplo.com']);

        $this->get('http://demo.test/')
            ->assertRedirect('https://www.ejemplo.com');
    }

    public function test_it_does_not_touch_a_tenant(): void
    {
        Tenant::create([
            'slug' => 'aaaabbbbcccc',
            'database' => self::BASE,
            'email' => 'central@ejemplo.com',
            'template_version' => 'demo_template',
            'expira_en' => now()->addDays(30),
            'estado' => TenantEstado::Activo,
        ]);

        config(['tenancy.sitio_promocional' => 'https://www.ejemplo.com']);

        // El inquilino sigue sirviendo lo suyo. Su cierre lo manda al login por
        // no haber sesión — lo que importa es que NO va al sitio promocional.
        $respuesta = $this->get('http://aaaabbbbcccc.demo.test/_sonda');

        $this->assertNotEquals('https://www.ejemplo.com', $respuesta->headers->get('location'));
    }

    public function test_it_does_not_touch_a_foreign_host(): void
    {
        // Un host ajeno al dominio del demo no es el central. Confundirlos fue
        // el error que rompió las 1600 pruebas existentes en el lote D.
        config(['tenancy.sitio_promocional' => 'https://www.ejemplo.com']);

        $this->get('http://localhost/_sonda')
            ->assertOk()
            ->assertSee('contenido del inquilino');
    }
}
