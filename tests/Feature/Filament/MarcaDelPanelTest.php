<?php

namespace Tests\Feature\Filament;

use Filament\Facades\Filament;
use Tests\TestCase;

/**
 * La marca del panel.
 *
 * El panel lleva la marca de LANDRA y no la del inquilino, y la distinción
 * importa: la que sube cada cliente es para su sitio público, que es lo que el
 * demo existe para lucir. El panel es el producto que se está mostrando.
 *
 * Se usa la composición horizontal completa en todas las pantallas. La vertical
 * queda para el sitio, como respaldo de quien no subió la suya: en una barra
 * superior sale alta, angosta y con el nombre ilegible.
 *
 * Lo único que cambia entre pantallas es la altura, y Filament no distingue el
 * acceso del resto por sí solo — de ahí la función que mira la ruta.
 */
class MarcaDelPanelTest extends TestCase
{
    public function test_the_login_page_shows_landras_lockup(): void
    {
        $respuesta = $this->get('/admin/login');

        $respuesta->assertOk();
        $respuesta->assertSee('logo-lockup-on-light.png', escape: false);
        $respuesta->assertSee('logo-lockup-on-dark.png', escape: false);
    }

    public function test_the_same_lockup_serves_the_rest_of_the_panel(): void
    {
        // Se pregunta al panel y no a una pantalla: pedir el tablero exigiría una
        // sesión, y lo que se fija acá es qué resuelve la configuración.
        $this->assertStringContainsString(
            'logo-lockup-on-light.png',
            (string) Filament::getPanel('admin')->getBrandLogo(),
        );

        $this->assertStringContainsString(
            'logo-lockup-on-dark.png',
            (string) Filament::getPanel('admin')->getDarkModeBrandLogo(),
        );
    }

    public function test_the_login_gives_the_logo_more_room_than_the_topbar(): void
    {
        // La barra superior no tiene el alto del acceso. Con una sola altura,
        // una de las dos pantallas queda mal — y la que se rompe en silencio es
        // la barra, porque nadie la mira dos veces.
        $enElPanel = (string) Filament::getPanel('admin')->getBrandLogoHeight();

        $this->assertSame('2rem', $enElPanel);

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('2.75rem', escape: false);
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
