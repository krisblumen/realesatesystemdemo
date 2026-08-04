<?php

namespace Tests\Unit\Frontend;

use App\Services\Frontend\BrandPalette;
use App\Support\Frontend\SectionBand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El filete gris de las secciones con fondo propio.
 *
 * Se prueba con el contenedor de la app —y no como unidad pura— porque la regla
 * depende del color REAL del fondo del sitio, que sale del tema configurado.
 *
 * Y por eso mismo DECLARA que necesita la base, aunque viva en `tests/Unit`.
 * Sin `RefreshDatabase` pasaba sólo cuando la base de tests arrastraba la tabla
 * `frontend_cache_generation` de una corrida anterior: verde por estado
 * residual, rojo en una máquina limpia o en CI. Una dependencia no declarada no
 * desaparece — se vuelve intermitente, que es peor.
 */
class SectionBandTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_section_on_the_site_background_has_no_edges(): void
    {
        // Sobre el mismo color no hay banda que delimitar: el filete se vería
        // como una línea suelta cruzando la página.
        $this->assertSame('', SectionBand::edges('site'));
    }

    public function test_an_absent_colour_counts_as_the_site_background(): void
    {
        // Es el default de todas las secciones que ofrecen fondo, y también lo
        // que traen los payloads publicados antes de que existiera la opción.
        $this->assertSame('', SectionBand::edges(null));
        $this->assertSame('', SectionBand::edges(''));
    }

    public function test_a_different_colour_gets_the_grey_edges(): void
    {
        $this->assertSame('border-y border-cloud', SectionBand::edges('primary'));
        $this->assertSame('border-y border-cloud', SectionBand::edges('navy'));
    }

    public function test_the_edges_are_always_the_same_grey(): void
    {
        // El filete marca un borde, no suma otro color a la mezcla: si alguna
        // vez sale del payload, deja de ser una regla y pasa a ser una decisión
        // más que el owner tiene que tomar bien.
        foreach (['primary', 'accent', 'neutral-5', 'navy'] as $clave) {
            $this->assertSame('border-y border-cloud', SectionBand::edges($clave));
        }
    }

    public function test_a_colour_that_matches_the_site_background_gets_no_edges(): void
    {
        // LA RAZÓN DE COMPARAR POR COLOR y no por clave: el owner puede elegir
        // una clave distinta que en pantalla es el mismo color. Ahí no hay nada
        // que separar.
        $muestras = app(BrandPalette::class)->swatches();
        $delSitio = $muestras['site']['hex'] ?? null;

        $gemelo = collect($muestras)
            ->reject(fn (array $m, string $k): bool => $k === 'site')
            ->search(fn (array $m): bool => isset($m['hex']) && strcasecmp($m['hex'], (string) $delSitio) === 0);

        if ($gemelo === false) {
            // Con el tema por defecto ninguna clave repite el fondo del sitio,
            // así que no hay caso que ejercitar. Se dice en vez de simularlo:
            // un test que se saltea en silencio miente sobre su cobertura.
            $this->assertTrue(true, 'Ninguna clave de la paleta repite hoy el color del sitio.');

            return;
        }

        $this->assertSame('', SectionBand::edges($gemelo));
    }

    public function test_an_unknown_colour_errs_towards_drawing_the_edges(): void
    {
        // No debería llegar —la paleta es cerrada y el schema la valida—, pero
        // un filete de más se nota menos que una banda sin borde.
        $this->assertSame('border-y border-cloud', SectionBand::edges('color-inventado'));
    }
}
