<?php

namespace Tests\Feature\Frontend;

use App\Filament\Forms\Sections\SectionPayloadCompiler;
use App\Filament\Resources\FrontendPageResource\Pages\EditFrontendPage;
use App\Filament\Resources\FrontendPageResource\RelationManagers\SectionsRelationManager;
use App\Models\FrontendPage;
use App\Models\FrontendSection;
use App\Models\User;
use App\Services\Frontend\BrandPalette;
use App\Services\Frontend\FrontendPagePublisher;
use App\Services\Frontend\FrontendSectionSchema;
use App\Services\Frontend\FrontendThemeService;
use Database\Seeders\PermissionSeeder;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ViewField;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * La sección «Qué hacemos» de la home.
 *
 * Reemplaza al listado de servicios, que en la home renderizaba el formato largo
 * de `/servicios` —un servicio con sus bullets y un botón— cuando lo que el sitio
 * publicado muestra es un encabezado con tarjetas.
 *
 * Lo que más se prueba acá es el REPARTO DE ANCHO, porque es la regla que el
 * owner no puede verificar sin publicar: el ancho de cada tarjeta depende de
 * cuántas haya, y una cantidad mal repartida deja un hueco al final de la fila.
 */
class FrontendCapabilityCardsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->actingAs(User::factory()->withRole('owner')->create());
    }

    private function section(): FrontendSection
    {
        return FrontendPage::query()->where('key', 'home')->firstOrFail()
            ->sections()->where('section_key', 'what_we_do')->firstOrFail();
    }

    /** Publica `n` tarjetas y devuelve el HTML de la home. */
    private function renderWithCards(int $n): string
    {
        $items = [];

        for ($i = 1; $i <= $n; $i++) {
            $items[] = ['title' => "Tarjeta {$i}", 'description' => "Descripción {$i}."];
        }

        $section = $this->section();
        $section->forceFill(['payload' => [
            'eyebrow' => 'QUÉ HACEMOS',
            'title' => 'Cuatro disciplinas, un solo equipo',
            'body' => 'Del terreno a la entrega de llaves.',
            'items' => $items,
        ]])->saveQuietly();

        $page = $section->page->fresh();
        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, User::factory()->withRole('owner')->create());

        return $this->get('/')->assertOk()->getContent();
    }

    /** @return list<string> los spans en el orden en que aparecen */
    private function spans(string $html): array
    {
        preg_match_all('/sm:col-span-(\d+)/', $html, $m);

        return $m[1];
    }

    // ------------------------------------------------ reparto de ancho ----

    #[DataProvider('widthDistribution')]
    public function test_the_width_is_split_by_how_many_cards_there_are(int $cards, array $expected): void
    {
        $this->assertSame(
            $expected,
            $this->spans($this->renderWithCards($cards)),
            "Con {$cards} tarjetas el ancho no se repartió como corresponde.",
        );
    }

    public static function widthDistribution(): array
    {
        return [
            'una ocupa todo' => [1, ['12']],
            'dos mitades' => [2, ['6', '6']],
            'tres tercios' => [3, ['4', '4', '4']],
            'cuatro cuartos' => [4, ['3', '3', '3', '3']],
            'cinco: 4 + 1 ancha' => [5, ['3', '3', '3', '3', '12']],
            'seis: 4 + 2 mitades' => [6, ['3', '3', '3', '3', '6', '6']],
            'siete: 4 + 3 tercios' => [7, ['3', '3', '3', '3', '4', '4', '4']],
            'ocho: 4 + 4' => [8, ['3', '3', '3', '3', '3', '3', '3', '3']],
        ];
    }

    public function test_more_than_eight_cards_are_rejected_by_the_schema(): void
    {
        $items = array_map(fn (int $i): array => ['title' => "T{$i}"], range(1, 9));

        $this->assertNotSame([], app(FrontendSectionSchema::class)->validate('capability_cards', ['items' => $items]));
    }

    public function test_at_least_one_card_is_required(): void
    {
        $this->assertNotSame([], app(FrontendSectionSchema::class)->validate('capability_cards', ['items' => []]));
    }

    // ------------------------------------------------------------ íconos --

    public function test_an_icon_outside_the_allowlist_is_rejected(): void
    {
        // El render mapea la clave a un path FIJO: uno desconocido dibujaría un
        // hueco, y un path libre sería inyección de SVG.
        $schema = app(FrontendSectionSchema::class);

        $this->assertNotSame([], $schema->validate('capability_cards', [
            'items' => [['title' => 'T', 'icon' => 'inventado']],
        ]));

        $this->assertSame([], $schema->validate('capability_cards', [
            'items' => [['title' => 'T', 'icon' => 'home']],
        ]));
    }

    public function test_a_card_without_icon_is_valid(): void
    {
        $this->assertSame([], app(FrontendSectionSchema::class)->validate('capability_cards', [
            'items' => [['title' => 'Sin ícono']],
        ]));
    }

    public function test_the_icon_is_rendered_from_the_allowlist_path(): void
    {
        $section = $this->section();
        $section->forceFill(['payload' => [
            'items' => [['title' => 'Arquitectura', 'icon' => 'ruler']],
        ]])->saveQuietly();

        $page = $section->page->fresh();
        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, User::factory()->withRole('owner')->create());

        $html = $this->get('/')->assertOk()->getContent();
        $path = config('frontend-sections.card_icons.ruler.path');

        $this->assertStringContainsString($path, $html);
    }

    public function test_the_form_offers_exactly_the_allowlisted_icons(): void
    {
        // Si el selector ofreciera un ícono que el schema rechaza, el owner lo
        // elegiría y perdería el guardado sin entender por qué.
        //
        // El selector es un `ViewField` (icon-picker.blade.php) y no un
        // `Select`: el catálogo que dibuja no vive en sus opciones sino en el
        // `viewData` que se le pasa. Comprobar eso es justamente lo que este
        // test verifica ahora.
        $section = $this->section();

        $editor = Livewire::test(SectionsRelationManager::class, [
            'ownerRecord' => $section->page,
            'pageClass' => EditFrontendPage::class,
        ])->mountTableAction('edit', $section->getKey());

        $campo = null;
        $walk = function ($components) use (&$walk, &$campo): void {
            foreach ($components as $child) {
                if ($child instanceof ViewField && $child->getName() === 'icon') {
                    $campo = $child;
                }
                if ($child instanceof Component) {
                    $walk($child->getChildComponents());
                }
            }
        };
        $walk($editor->instance()->getMountedTableActionForm()->getComponents());

        $this->assertNotNull($campo, 'Las tarjetas no ofrecen selector de ícono.');
        $this->assertSame('filament.forms.icon-picker', $campo->getView());
        $this->assertSame(
            array_keys((array) config('frontend-sections.card_icons')),
            array_keys($campo->getViewData()['iconos']),
        );
    }

    public function test_the_editor_shows_the_icon_gallery(): void
    {
        // La galería es la única referencia visual: el selector muestra nombres,
        // así que sin ella habría que elegir «Respaldo» a ciegas.
        $section = $this->section();

        $html = Livewire::test(SectionsRelationManager::class, [
            'ownerRecord' => $section->page,
            'pageClass' => EditFrontendPage::class,
        ])->mountTableAction('edit', $section->getKey())->html();

        $this->assertStringContainsString('Íconos disponibles', $html);

        foreach ((array) config('frontend-sections.card_icons') as $icono) {
            $this->assertStringContainsString($icono['label'], $html);
            $this->assertStringContainsString($icono['path'], $html);
        }
    }

    // ------------------------------------------------------- alineación --

    #[DataProvider('alignments')]
    public function test_the_header_can_be_aligned(?string $align, string $esperado): void
    {
        $section = $this->section();
        $payload = ['eyebrow' => 'QUÉ HACEMOS', 'title' => 'Un título', 'items' => [['title' => 'Una tarjeta']]];

        if ($align !== null) {
            $payload['text_align'] = $align;
        }

        $section->forceFill(['payload' => $payload])->saveQuietly();
        $page = $section->page->fresh();
        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, User::factory()->withRole('owner')->create());

        $this->get('/')->assertOk()->assertSee($esperado, escape: false);
    }

    public static function alignments(): array
    {
        return [
            'izquierda' => ['left', 'text-left'],
            'centro' => ['center', 'text-center'],
            'derecha' => ['right', 'text-right'],
            // Sin elegir nada, va centrado: es como se ve el sitio publicado, y
            // un encabezado no debería cambiar de aspecto sólo por guardarse.
            'sin elegir → centrado' => [null, 'text-center'],
        ];
    }

    public function test_an_alignment_outside_the_allowlist_is_rejected(): void
    {
        $this->assertNotSame([], app(FrontendSectionSchema::class)->validate('capability_cards', [
            'items' => [['title' => 'T']],
            'text_align' => 'justificado',
        ]));
    }

    public function test_the_cards_keep_their_own_layout(): void
    {
        // Alinear el encabezado a la derecha NO debe arrastrar las tarjetas: son
        // bloques con composición propia y moverlas rompe la retícula.
        $section = $this->section();
        $section->forceFill(['payload' => [
            'title' => 'Alineado a la derecha',
            'text_align' => 'right',
            'items' => [['title' => 'Tarjeta 1'], ['title' => 'Tarjeta 2']],
        ]])->saveQuietly();

        $page = $section->page->fresh();
        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, User::factory()->withRole('owner')->create());

        $html = $this->get('/')->assertOk()->getContent();

        // El reparto de ancho de las tarjetas no cambió.
        $this->assertSame(['6', '6'], $this->spans($html));
    }

    public function test_the_form_defaults_the_alignment_to_center(): void
    {
        $section = $this->section();
        $campo = null;

        $walk = function ($components) use (&$walk, &$campo): void {
            foreach ($components as $child) {
                if ($child instanceof Select && $child->getStatePath(false) === 'payload.text_align') {
                    $campo = $child;
                }
                if ($child instanceof Component) {
                    $walk($child->getChildComponents());
                }
            }
        };

        $walk(Livewire::test(SectionsRelationManager::class, [
            'ownerRecord' => $section->page,
            'pageClass' => EditFrontendPage::class,
        ])->mountTableAction('edit', $section->getKey())->instance()->getMountedTableActionForm()->getComponents());

        $this->assertNotNull($campo, 'El encabezado no ofrece alineación.');
        $this->assertSame('center', $campo->getDefaultState());
        $this->assertSame(['left', 'center', 'right'], array_keys($campo->getOptions()));
    }

    // ------------------------------------------------------------ borde --

    /** Publica el payload dado y devuelve el HTML de la home. */
    private function renderWith(array $payload): string
    {
        $section = $this->section();
        $section->forceFill(['payload' => $payload])->saveQuietly();
        $page = $section->page->fresh();
        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, User::factory()->withRole('owner')->create());

        return $this->get('/')->assertOk()->getContent();
    }

    #[DataProvider('borderWidths')]
    public function test_the_border_uses_the_chosen_thickness(int $width): void
    {
        $html = $this->renderWith([
            'title' => 'Con borde',
            'card_border' => true,
            'card_border_width' => $width,
            'items' => [['title' => 'Una tarjeta']],
        ]);

        $this->assertStringContainsString("border-[{$width}px]", $html);
    }

    #[DataProvider('borderColors')]
    public function test_the_border_uses_the_chosen_color(string $clave, string $clase): void
    {
        $html = $this->renderWith([
            'title' => 'Con borde',
            'card_border' => true,
            'card_border_color' => $clave,
            'items' => [['title' => 'Una tarjeta']],
        ]);

        $this->assertStringContainsString($clase, $html);
    }

    /**
     * Las diez opciones, ESCRITAS y no leídas de la configuración.
     *
     * Un provider que lee `config()` probaría que la configuración es igual a sí
     * misma —y además no puede: los data providers corren antes de que exista el
     * contenedor de Laravel—. Enumerarlas acá hace que un cambio en la paleta
     * tenga que declararse también en la prueba.
     */
    public static function borderColors(): array
    {
        return [
            'acento muy oscuro' => ['accent-d2', 'border-brand-accent-d2'],
            'acento oscuro' => ['accent-d1', 'border-brand-accent-d1'],
            'acento' => ['accent', 'border-brand-accent'],
            'acento claro' => ['accent-l1', 'border-brand-accent-l1'],
            'acento muy claro' => ['accent-l2', 'border-brand-accent-l2'],
            'principal muy oscuro' => ['primary-d2', 'border-brand-primary-d2'],
            'principal oscuro' => ['primary-d1', 'border-brand-primary-d1'],
            'principal' => ['primary', 'border-brand-primary'],
            'principal claro' => ['primary-l1', 'border-brand-primary-l1'],
            'principal muy claro' => ['primary-l2', 'border-brand-primary-l2'],
        ];
    }

    public function test_a_color_outside_the_palette_is_rejected(): void
    {
        // Paleta CERRADA: sin esto el owner podría poner un verde en un sitio
        // azul y ámbar, que es justo lo que se quiso evitar al no ofrecer un
        // selector de color abierto.
        $this->assertNotSame([], app(FrontendSectionSchema::class)->validate('capability_cards', [
            'items' => [['title' => 'T']],
            'card_border_color' => 'verde-fluor',
        ]));
    }

    public function test_the_palette_covers_both_brand_colors_with_two_shades_each_way(): void
    {
        $colores = (array) config('frontend-sections.brand_palette');

        // Dos colores de marca × (base + 2 oscuras + 2 claras). Se cuentan SÓLO
        // los derivados de la marca: la paleta lleva además una escala de
        // neutros y el fondo del sitio —para fondos de sección y colores de
        // título—, que no se derivan y por eso no responden a esta regla.
        $marca = array_filter(
            $colores,
            fn (string $k): bool => str_starts_with($k, 'accent') || str_starts_with($k, 'primary'),
            ARRAY_FILTER_USE_KEY,
        );
        $this->assertCount(10, $marca);

        foreach (['accent', 'primary'] as $base) {
            foreach (['-d2', '-d1', '', '-l1', '-l2'] as $variante) {
                $this->assertArrayHasKey($base.$variante, $colores);
            }
        }
    }

    public function test_the_neutral_shades_carry_their_own_hex(): void
    {
        // Los neutros NO se derivan del acento ni del primario: son la escala de
        // grises del sitio. Sin hexadecimal propio, la muestra del panel saldría
        // negra, porque su base no existe entre los colores de marca.
        $neutros = array_filter(
            (array) config('frontend-sections.brand_palette'),
            fn (string $k): bool => str_starts_with($k, 'neutral-'),
            ARRAY_FILTER_USE_KEY,
        );

        $this->assertNotEmpty($neutros);

        foreach ($neutros as $clave => $color) {
            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', $color['hex'] ?? '', "«{$clave}» no declara su color.");
        }

        $muestras = app(BrandPalette::class)->swatches();
        $this->assertSame('#f2f2f2', $muestras['neutral-1']['hex']);
    }

    public function test_every_palette_class_is_compiled_into_the_bundle(): void
    {
        // Tailwind sólo genera las clases que ve escritas. Una que quede fuera
        // del bundle no da error: dibuja un borde SIN color, y sólo se nota
        // mirando la página.
        $bundle = collect(glob(public_path('build/assets/*.css')))
            ->map(fn (string $f): string => file_get_contents($f))
            ->implode('');

        foreach ((array) config('frontend-sections.brand_palette') as $clave => $color) {
            $this->assertStringContainsString(
                $color['border'],
                $bundle,
                "La clase de «{$clave}» no está compilada: el borde saldría sin color.",
            );
        }
    }

    public function test_the_variants_are_derived_from_the_theme_variables(): void
    {
        // Lo que hace que la paleta SIGA al tema: las variantes se calculan con
        // `color-mix` sobre la variable que el cliente configura, no son valores
        // fijos. Si se reemplazaran por hexadecimales, cambiar el acento dejaría
        // las cuatro variantes apuntando al color viejo.
        $css = file_get_contents(resource_path('css/app.css'));

        foreach (['--color-brand-accent-d1', '--color-brand-accent-l2', '--color-brand-primary-d1'] as $variable) {
            $this->assertMatchesRegularExpression(
                '/'.preg_quote($variable, '/').':\s*color-mix\(/',
                $css,
                "«{$variable}» dejó de derivarse del tema.",
            );
        }
    }

    public static function borderWidths(): array
    {
        return ['1 px' => [1], '2 px' => [2], '3 px' => [3], '4 px' => [4]];
    }

    public function test_without_the_border_no_border_class_is_emitted(): void
    {
        $html = $this->renderWith([
            'title' => 'Sin borde',
            'card_border' => false,
            'card_border_width' => 3,
            'items' => [['title' => 'Una tarjeta']],
        ]);

        // Ni siquiera el grosor guardado se dibuja: el toggle manda.
        $this->assertStringNotContainsString('border-[3px]', $html);
        $this->assertStringNotContainsString('border-brand-accent', $html);
    }

    public function test_the_thickness_survives_turning_the_border_off_and_on(): void
    {
        // El grosor se guarda aunque el borde esté apagado: apagar y volver a
        // encender no debería perder la elección del owner.
        $compilado = app(SectionPayloadCompiler::class)
            ->compile($this->section(), [
                'title' => 'T',
                'card_border' => false,
                'card_border_width' => 4,
                'items' => [['title' => 'Una']],
            ]);

        $this->assertFalse($compilado['card_border']);
        $this->assertSame(4, $compilado['card_border_width']);
    }

    public function test_a_thickness_outside_the_allowlist_is_rejected(): void
    {
        // El render mapea el grosor a una clase FIJA: uno fuera de la lista
        // dibujaría un borde que Tailwind nunca generó, o sea ninguno.
        $schema = app(FrontendSectionSchema::class);

        foreach ([0, 5, 12] as $invalido) {
            $this->assertNotSame([], $schema->validate('capability_cards', [
                'items' => [['title' => 'T']],
                'card_border_width' => $invalido,
            ]), "Se aceptó un grosor de {$invalido}.");
        }
    }

    public function test_the_thickness_selector_only_shows_with_the_border_on(): void
    {
        // Un grosor que no se ve en ningún lado es una pregunta sin consecuencia.
        $section = $this->section();
        $section->forceFill(['payload' => [
            'title' => 'T', 'card_border' => false, 'items' => [['title' => 'Una']],
        ]])->saveQuietly();

        $editor = Livewire::test(SectionsRelationManager::class, [
            'ownerRecord' => $section->page,
            'pageClass' => EditFrontendPage::class,
        ])->mountTableAction('edit', $section->getKey());

        $editor->assertDontSee('Grosor del borde');

        $editor->set('mountedTableActionsData.0.payload.card_border', true)
            ->assertSee('Grosor del borde');
    }

    // ------------------------------------------------ paleta de muestras --

    public function test_the_palette_shows_the_real_brand_colors(): void
    {
        // Se pintan con el valor REAL de la marca, calculado en PHP: dentro del
        // panel no existen las variables CSS del sitio, así que sin esto las
        // muestras saldrían en blanco o con el color equivocado.
        $muestras = app(BrandPalette::class)->swatches();

        // Cada color de la paleta tiene su muestra: los diez de marca y los
        // neutros. Se compara contra la lista real en vez de un número fijo —
        // así agregar un color obliga a que resuelva, no a tocar este número.
        $this->assertSame(
            array_keys((array) config('frontend-sections.brand_palette')),
            array_keys($muestras),
        );

        foreach ($muestras as $clave => $m) {
            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $m['hex'], "«{$clave}» no resolvió a un color.");
            $this->assertNotSame('', trim($m['label']));
        }

        // La base es el acento configurado, sin mezclar.
        $this->assertSame(strtolower(app(FrontendThemeService::class)->theme()['accent']), $muestras['accent']['hex']);
    }

    public function test_the_dark_variants_are_darker_and_the_light_ones_lighter(): void
    {
        // Si las proporciones se invirtieran, «Acento oscuro» pintaría más claro
        // que el acento y el owner elegiría a ciegas.
        $m = app(BrandPalette::class)->swatches();

        $luz = fn (string $hex): int => array_sum(sscanf($hex, '#%02x%02x%02x'));

        foreach (['accent', 'primary'] as $base) {
            $this->assertLessThan($luz($m[$base]['hex']), $luz($m[$base.'-d1']['hex']));
            $this->assertLessThan($luz($m[$base.'-d1']['hex']), $luz($m[$base.'-d2']['hex']));
            $this->assertGreaterThan($luz($m[$base]['hex']), $luz($m[$base.'-l1']['hex']));
            $this->assertGreaterThan($luz($m[$base.'-l1']['hex']), $luz($m[$base.'-l2']['hex']));
        }
    }

    public function test_the_php_mix_matches_the_css_one(): void
    {
        // El panel calcula en PHP lo que el navegador resuelve con `color-mix`.
        // Si los porcentajes dejaran de coincidir, el owner elegiría un color en
        // el panel y vería otro en su sitio.
        $css = file_get_contents(resource_path('css/app.css'));
        $servicio = file_get_contents(app_path('Services/Frontend/BrandPalette.php'));

        foreach ([['-d2', '55%', '0.55'], ['-d1', '78%', '0.78'], ['-l1', '55%', '0.55'], ['-l2', '25%', '0.25']] as [$variante, $enCss, $enPhp]) {
            $this->assertStringContainsString("--color-brand-accent{$variante}: color-mix(in srgb, var(--theme-accent, #f5a624) {$enCss}", $css);
            $this->assertStringContainsString("'{$variante}' => [{$enPhp},", $servicio);
        }
    }

    public function test_the_editor_renders_the_palette_instead_of_a_dropdown(): void
    {
        $section = $this->section();
        $section->forceFill(['payload' => [
            'title' => 'T', 'card_border' => true, 'items' => [['title' => 'Una']],
        ]])->saveQuietly();

        $html = Livewire::test(SectionsRelationManager::class, [
            'ownerRecord' => $section->page,
            'pageClass' => EditFrontendPage::class,
        ])->mountTableAction('edit', $section->getKey())->html();

        // Cada muestra aparece con su color y su nombre.
        foreach (app(BrandPalette::class)->swatches() as $m) {
            $this->assertStringContainsString($m['hex'], $html);
            $this->assertStringContainsString($m['label'], $html);
        }
    }

    // ----------------------------------------------------------- íconos --

    public function test_there_are_enough_icons_for_a_real_estate_site(): void
    {
        // Con ocho no alcanzaba para cubrir servicios, asesoría, obra y
        // comercialización sin repetir el mismo dibujo en tarjetas distintas.
        $iconos = (array) config('frontend-sections.card_icons');

        $this->assertGreaterThanOrEqual(16, count($iconos));

        foreach ($iconos as $clave => $icono) {
            $this->assertNotSame('', trim($icono['label'] ?? ''), "El ícono «{$clave}» no tiene nombre.");
            $this->assertNotSame('', trim($icono['path'] ?? ''), "El ícono «{$clave}» no tiene dibujo.");
        }
    }

    public function test_no_two_icons_share_the_same_drawing(): void
    {
        // Dos claves con el mismo path serían dos nombres para el mismo dibujo:
        // el owner elegiría «Obra» y «Certificación» y vería lo mismo.
        $paths = array_column((array) config('frontend-sections.card_icons'), 'path');

        $this->assertSame(count($paths), count(array_unique($paths)));
    }

    // ------------------------------------------------- la home ya no lista --

    public function test_the_home_no_longer_renders_the_services_list_format(): void
    {
        $html = $this->renderWithCards(4);

        // El formato largo de /servicios numeraba cada bloque: «01 · TÍTULO».
        $this->assertDoesNotMatchRegularExpression('/\d{2}\s*·\s*[A-ZÁÉÍÓÚ]/u', $html);
        $this->assertStringContainsString('QUÉ HACEMOS', $html);
    }

    public function test_the_services_page_still_lists_services(): void
    {
        // El cambio es de la home: /servicios conserva su listado dinámico.
        $this->get('/servicios')->assertOk()->assertSee('Arquitectura', escape: false);
    }

    public function test_the_section_is_registered_as_canonical(): void
    {
        $this->assertSame('capability_cards', config('frontend-sections.pages.home.what_we_do'));
        $this->assertArrayHasKey('capability_cards', (array) config('frontend-sections.types'));
        $this->assertSame('Qué hacemos', config('frontend-sections.section_labels.what_we_do'));
    }

    public function test_adding_a_colour_never_costs_the_form_more_height(): void
    {
        // LA REGRESIÓN QUE ESTO EVITA: la paleta estaba SIEMPRE desplegada, así
        // que cada color nuevo le sumaba alto a cada selector — y hay formularios
        // con tres. Primero se arregló repartiendo las fichas por ancho mínimo;
        // ahora el selector va PLEGADO, y desplegado no cuesta nada porque el
        // popover flota sobre el formulario en vez de empujarlo.
        //
        // Se comprueba la garantía, no la implementación de turno: cerrado no se
        // dibuja la paleta, y lo que la despliega está fuera del flujo.
        $vista = file_get_contents(resource_path('views/filament/forms/color-palette.blade.php'));

        $this->assertStringContainsString('abierto: false', $vista, 'El selector ya no arranca plegado.');
        $this->assertStringContainsString('x-show="abierto"', $vista, 'La paleta se dibuja siempre.');
        $this->assertStringContainsString('position:absolute', $vista, 'El popover empuja el formulario en vez de flotar.');
    }

    public function test_the_collapsed_selector_says_which_colour_is_set(): void
    {
        // Plegar no puede costar saber qué hay puesto: si hubiera que abrir cada
        // selector para averiguarlo, plegarlos habría empeorado el formulario.
        $vista = file_get_contents(resource_path('views/filament/forms/color-palette.blade.php'));

        $this->assertStringContainsString('x-text="nombreActual"', $vista);
        $this->assertStringContainsString('backgroundColor: hexActual', $vista);
    }

    public function test_the_swatches_never_lose_their_own_colour_to_alpine(): void
    {
        // `x-bind:style` con una CADENA reemplaza el atributo entero: las fichas
        // perdían tamaño y color y quedaban en tiras de 6px. Con un objeto,
        // Alpine fusiona sobre lo que ya está escrito.
        foreach (['color-palette', 'icon-gallery'] as $vista) {
            $fuente = file_get_contents(resource_path("views/filament/forms/{$vista}.blade.php"));

            $this->assertDoesNotMatchRegularExpression(
                '/x-bind:style="\s*[\'\{]?\s*\'/',
                $fuente,
                "{$vista} liga el estilo con una cadena: borraría el `style` del elemento.",
            );
        }
    }

    public function test_every_swatch_still_names_its_colour(): void
    {
        // Achicar la ficha no puede costar saber qué color es: el nombre queda a
        // la vista y el hexadecimal pasó al tooltip.
        $vista = file_get_contents(resource_path('views/filament/forms/color-palette.blade.php'));

        $this->assertStringContainsString("{{ \$muestra['label'] }}", $vista);
        $this->assertStringContainsString("title=\"{{ \$muestra['label'] }} · {{ strtoupper(\$muestra['hex']) }}\"", $vista);
    }
}
