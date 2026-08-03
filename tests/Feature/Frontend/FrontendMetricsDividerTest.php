<?php

namespace Tests\Feature\Frontend;

use App\Models\FrontendPage;
use App\Models\User;
use App\Services\Frontend\FrontendPagePublisher;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Las métricas van separadas por una regla dentro de la misma tarjeta, y la
 * regla cambia de eje con el layout: horizontal mientras se apilan, vertical
 * cuando se acomodan en columnas.
 *
 * Lo que se prueba acá no es que los PNG aparezcan —eso se ve a simple vista—
 * sino QUIÉN los lleva: la regla se cuelga a la izquierda (o arriba) de su
 * métrica, así que cualquiera que ABRA fila la dibujaría flotando en el margen
 * de la tarjeta. Y quién abre fila cambia con el breakpoint, porque la grilla
 * pasa de 1 a 2 a 4 columnas. Con cuatro métricas el error no se ve; con cinco
 * o más, sí.
 */
class FrontendMetricsDividerTest extends TestCase
{
    use RefreshDatabase;

    /** Publica `n` métricas —con los colores dados, si los hay— y devuelve /nosotros. */
    private function renderWith(int $cuantas, array $colores = []): string
    {
        $this->seed(PermissionSeeder::class);
        $owner = User::factory()->withRole('owner')->create();
        $this->actingAs($owner);

        $items = [];

        for ($i = 1; $i <= $cuantas; $i++) {
            $items[] = ['value' => "+{$i}0", 'label' => "Métrica {$i}"];
        }

        $section = FrontendPage::query()->where('key', 'nosotros')->firstOrFail()
            ->sections()->where('type', 'metrics')->firstOrFail();
        $section->forceFill(['payload' => $colores + ['items' => $items]])->saveQuietly();

        $page = $section->page->fresh();
        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, $owner);

        return $this->get('/nosotros')->assertOk()->getContent();
    }

    /** La clase de la banda que envuelve las métricas. */
    private function band(string $html): string
    {
        preg_match('~<div class="(grid gap-8 rounded-brand-xl[^"]*)"~', $html, $m);

        $this->assertNotEmpty($m, 'No se encontró la banda de métricas.');

        return $m[1];
    }

    /** @return list<string> las celdas de la banda, en orden */
    private function cells(string $html): array
    {
        preg_match_all('~<div class="relative text-center">(.*?)\n                </div>~s', $html, $celdas);

        $this->assertNotEmpty($celdas[1], 'No se encontró ninguna métrica en el HTML.');

        return $celdas[1];
    }

    /**
     * Los breakpoints en que se muestra el divisor `$eje` de cada celda, o
     * `null` si esa celda no lo lleva.
     *
     * @return list<string|null>
     */
    private function dividers(string $html, string $eje): array
    {
        return array_map(function (string $celda) use ($eje): ?string {
            if (! preg_match('~'.$eje.'_divider\.png"[^>]*class="([^"]*)"~s', $celda, $m)) {
                return null;
            }

            preg_match_all('~(?:hidden|sm:hidden|sm:block|lg:block)~', $m[1], $clases);

            return implode(' ', $clases[0]);
        }, $this->cells($html));
    }

    // ------------------------------------------------- la regla vertical --

    public function test_four_metrics_get_a_vertical_rule_on_every_one_but_the_first(): void
    {
        $this->assertSame([
            null,                   // abre la fila en los tres layouts
            'hidden sm:block',      // segunda columna: existe desde `sm`
            'hidden lg:block',      // tercera: en `sm` abriría fila, así que sólo en `lg`
            'hidden sm:block',      // cuarta
        ], $this->dividers($this->renderWith(4), 'v'));
    }

    public function test_a_metric_that_opens_a_row_in_four_columns_has_no_vertical_rule(): void
    {
        // Con ocho métricas hay dos filas completas en `lg`: la quinta arranca la
        // segunda y no puede llevar regla a su izquierda. En `sm` pasa lo mismo
        // con todas las pares, y por eso ninguna de ellas se muestra ahí.
        $this->assertSame([
            null,
            'hidden sm:block',
            'hidden lg:block',
            'hidden sm:block',
            null,                   // quinta: abre fila en `lg` Y en `sm`
            'hidden sm:block',
            'hidden lg:block',
            'hidden sm:block',
        ], $this->dividers($this->renderWith(8), 'v'));
    }

    #[DataProvider('counts')]
    public function test_no_vertical_rule_ever_shows_in_a_single_column(int $cuantas): void
    {
        // Apiladas no hay nada a los costados que separar: todas arrancan en
        // `hidden`, que es la clase de base y sólo la levanta un breakpoint.
        foreach (array_filter($this->dividers($this->renderWith($cuantas), 'v')) as $i => $clases) {
            $this->assertStringStartsWith('hidden', $clases, "La regla vertical {$i} se vería apilada.");
        }
    }

    // ----------------------------------------------- la regla horizontal --

    #[DataProvider('counts')]
    public function test_stacked_metrics_are_separated_by_the_horizontal_rule(int $cuantas): void
    {
        $reglas = $this->dividers($this->renderWith($cuantas), 'h');

        // La primera no lleva —no hay nada arriba de ella—; todas las demás sí,
        // sin importar la columna que les tocaría en otro breakpoint.
        $this->assertNull($reglas[0], 'La primera métrica no tiene nada arriba que separar.');

        foreach (array_slice($reglas, 1) as $i => $clases) {
            $this->assertSame('sm:hidden', $clases, 'La métrica '.($i + 1).' no separa al apilarse.');
        }
    }

    #[DataProvider('counts')]
    public function test_the_two_rules_are_never_visible_at_the_same_time(int $cuantas): void
    {
        // La horizontal muere en `sm` y la vertical nace ahí: si alguna vez se
        // solaparan, la tarjeta mostraría una cruz alrededor de la métrica.
        $html = $this->renderWith($cuantas);
        $horizontales = $this->dividers($html, 'h');
        $verticales = $this->dividers($html, 'v');

        foreach ($horizontales as $i => $h) {
            if ($h === null) {
                continue;
            }

            $this->assertSame('sm:hidden', $h);
            $this->assertTrue(
                $verticales[$i] === null || str_starts_with($verticales[$i], 'hidden'),
                "La métrica {$i} mostraría las dos reglas a la vez.",
            );
        }
    }

    // ----------------------------------------------------------- colores --

    public function test_a_band_nobody_touched_keeps_the_colours_it_always_had(): void
    {
        // El selector es nuevo; la banda no. Sin colores elegidos tiene que salir
        // con el azulado y el primario de siempre.
        $html = $this->renderWith(4);

        $this->assertStringContainsString('bg-navy-50', $this->band($html));
        $this->assertStringContainsString('text-brand-primary">+10<', $html);
    }

    public function test_the_owner_can_paint_the_card_and_the_figures(): void
    {
        $html = $this->renderWith(4, ['background_color' => 'primary', 'value_color' => 'accent']);

        $this->assertStringContainsString('bg-brand-primary', $this->band($html));
        $this->assertStringContainsString('text-brand-accent">+10<', $html);
    }

    public function test_a_dark_card_never_swallows_the_figures(): void
    {
        // El caso que rompía: elegir tarjeta oscura y NADA más. El primario es
        // tinta oscura, así que el número entero desaparecía sobre el fondo. Sin
        // color elegido la cifra sigue al fondo, y usa el foreground que el
        // contrato garantiza legible en vez de un blanco fijo — el cliente puede
        // tener un primario claro.
        $html = $this->renderWith(4, ['background_color' => 'primary']);

        $this->assertStringContainsString('text-on-brand-primary">+10<', $html);
        $this->assertStringNotContainsString('text-brand-primary">+10<', $html);
    }

    public function test_an_explicit_choice_still_wins_over_the_automatic_one(): void
    {
        // La deducción es un DEFAULT, no una tutela: si el owner pidió el
        // primario sobre una tarjeta oscura, es su decisión y se respeta.
        $html = $this->renderWith(4, ['background_color' => 'primary', 'value_color' => 'primary']);

        $this->assertStringContainsString('text-brand-primary">+10<', $html);
    }

    /** @return array<string, array{string, string}> */
    public static function backgrounds(): array
    {
        return [
            // fondo claro → la etiqueta se queda en el gris de siempre
            'azul muy claro' => ['navy', 'text-stone'],
            'blanco' => ['neutral-0', 'text-stone'],
            // el acento por defecto es un ámbar: contra blanco no llega a 4.5:1
            'acento' => ['accent', 'text-stone'],
            // fondo oscuro → la etiqueta se invierte sola
            'principal' => ['primary', 'text-on-brand-primary/75'],
            'negro' => ['neutral-5', 'text-on-brand-primary/75'],
        ];
    }

    #[DataProvider('backgrounds')]
    public function test_the_label_flips_by_itself_so_it_never_disappears(string $fondo, string $tinta): void
    {
        // Es lo único que el owner NO elige: si quedara fijo, el gris de siempre
        // se perdería sobre cualquier fondo de marca y el selector de tarjeta
        // estaría prometiendo algo que no puede cumplir.
        $html = $this->renderWith(4, ['background_color' => $fondo]);

        $this->assertStringContainsString('tracking-wide '.$tinta.'"', $html);
    }

    // ------------------------------------------------------------ ambas --

    /** @return array<string, array{int}> */
    public static function counts(): array
    {
        return ['tres' => [3], 'cuatro' => [4], 'cinco' => [5], 'ocho' => [8]];
    }

    public function test_both_divider_assets_exist(): void
    {
        // Si falta el PNG, la banda sale con huecos y nada que los explique.
        $this->assertFileExists(public_path('images/assets/v_divider.png'));
        $this->assertFileExists(public_path('images/assets/h_divider.png'));
    }

    public function test_the_rules_are_decorative_for_a_screen_reader(): void
    {
        $html = $this->renderWith(4);

        preg_match_all('~<img src="[^"]*(?:v|h)_divider\.png"([^>]*)>~', $html, $imgs);

        $this->assertNotEmpty($imgs[1], 'No se renderizó ninguna regla.');

        foreach ($imgs[1] as $attrs) {
            $this->assertStringContainsString('alt=""', $attrs);
            $this->assertStringContainsString('aria-hidden="true"', $attrs);
        }
    }
}
