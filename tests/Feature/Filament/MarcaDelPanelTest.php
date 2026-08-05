<?php

namespace Tests\Feature\Filament;

use Filament\Facades\Filament;
use Tests\TestCase;

/**
 * La marca del panel, y por qué el acceso lleva otra que el resto.
 *
 * Son dos composiciones distintas del mismo logo: el panel usa la vertical, que
 * es la que entra en una barra superior; el acceso usa la horizontal con la
 * bajada —«Sistema de administración inmobiliaria»— porque ahí hay espacio y es
 * el único momento en que alguien que no conoce el producto lo está mirando.
 *
 * Filament no distingue esas dos pantallas por sí solo: usa el mismo logo en
 * todas. Se resuelve pasando una función que mira la ruta.
 */
class MarcaDelPanelTest extends TestCase
{
    public function test_the_login_page_uses_the_lockup_with_the_descriptor(): void
    {
        $respuesta = $this->get('/admin/login');

        $respuesta->assertOk();
        $respuesta->assertSee('login-logo-on-light.png', escape: false);
        $respuesta->assertSee('login-logo-on-dark.png', escape: false);
    }

    public function test_outside_the_login_the_panel_uses_the_compact_mark(): void
    {
        // Se pregunta al panel y no a una pantalla: pedir el tablero exigiría una
        // sesión, y lo que se quiere fijar acá es qué logo resuelve la función
        // cuando la ruta NO es la de acceso.
        $this->assertStringContainsString(
            'logo-on-light.svg',
            (string) Filament::getPanel('admin')->getBrandLogo(),
        );

        $this->assertStringContainsString(
            'logo-on-dark.svg',
            (string) Filament::getPanel('admin')->getDarkModeBrandLogo(),
        );
    }

    public function test_the_favicon_is_landras(): void
    {
        // Chico y fácil de olvidar: es lo único de la marca que sobrevive cuando
        // alguien deja la pestaña abierta entre otras veinte.
        $this->assertStringContainsString(
            'landra-core.ico',
            (string) Filament::getPanel('admin')->getFavicon(),
        );
    }
}
