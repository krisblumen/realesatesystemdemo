<?php

namespace Tests\Feature\Frontend;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El logo del inquilino entra sea cual sea su forma.
 *
 * EL DEFECTO QUE ESTE TEST PREVIENE. El sitio dibujaba el logo con altura fija y
 * ancho libre (`h-11 w-auto`), y la ficha de subida pedía «horizontal, ~400×120».
 * O sea: asumía que todos los logos son horizontales.
 *
 * No lo son. A 44 píxeles de alto, uno VERTICAL queda de unos 30 de ancho —
 * ilegible— y uno MUY ANCHO se estira hasta empujar el menú. Es un producto que
 * cada cliente marca con lo suyo: la forma del logo no la elige el sistema.
 *
 * La regla es una CAJA y no una dimensión: alto máximo y ancho máximo, con la
 * imagen ajustándose adentro sin deformarse. Al horizontal lo limita el ancho, al
 * vertical el alto, y el cuadrado entra por cualquiera de los dos.
 *
 * El test mira las clases porque son el mecanismo: una altura fija reaparecería
 * como `h-11` y volvería a romper a quien suba un logo vertical.
 */
class FrontendLogoDeCualquierFormaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    public static function lugaresDondeApareceElLogo(): array
    {
        return [
            'encabezado' => ['<header', 'logo_light'],
            'pie' => ['<footer', 'logo_dark'],
        ];
    }

    private function etiquetasDeLogo(string $html): array
    {
        preg_match_all('/<img[^>]+images\/brand\/[^>]*>/i', $html, $m);

        return $m[0];
    }

    public function test_the_logo_is_bounded_on_both_axes_and_never_deformed(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $etiquetas = $this->etiquetasDeLogo($html);

        $this->assertNotEmpty($etiquetas, 'El sitio tiene que dibujar el logo en alguna parte.');

        foreach ($etiquetas as $etiqueta) {
            $this->assertMatchesRegularExpression(
                '/class="[^"]*\bmax-h-/',
                $etiqueta,
                "Sin alto máximo, un logo vertical se sale: {$etiqueta}",
            );

            $this->assertMatchesRegularExpression(
                '/class="[^"]*\bmax-w-/',
                $etiqueta,
                "Sin ancho máximo, un logo muy ancho empuja el resto: {$etiqueta}",
            );

            $this->assertMatchesRegularExpression(
                '/class="[^"]*\bobject-contain\b/',
                $etiqueta,
                "Sin `object-contain` la imagen se deforma al entrar en la caja: {$etiqueta}",
            );

            $this->assertDoesNotMatchRegularExpression(
                // El grupo previo excluye `max-h-`: sin él, `\b` casa entre el
                // guión y la `h` y el test se rechaza a sí mismo.
                '/class="[^"]*(?<![\w-])h-\d/',
                $etiqueta,
                "Una altura fija vuelve a asumir que el logo es horizontal: {$etiqueta}",
            );
        }
    }

    public function test_the_upload_hint_does_not_demand_a_horizontal_logo(): void
    {
        // La ficha de subida decía «horizontal, ~400×120 px». Pedirle una forma a
        // quien sube su propia marca es pedirle que la rehaga: la mayoría no
        // puede, y sube lo que tiene. El sistema se acomoda, no al revés.
        $pagina = file_get_contents(app_path('Filament/Pages/FrontendSettingsPage.php'));

        $this->assertStringNotContainsString(
            'horizontal, ~400×120 px',
            (string) $pagina,
            'La ficha de subida no puede exigir una forma de logo.',
        );
    }
}
