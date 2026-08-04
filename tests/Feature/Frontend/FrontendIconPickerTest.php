<?php

namespace Tests\Feature\Frontend;

use App\Filament\Resources\FrontendPageResource\Pages\EditFrontendPage;
use App\Filament\Resources\FrontendPageResource\RelationManagers\SectionsRelationManager;
use App\Filament\Resources\FrontendServiceResource\Pages\EditFrontendService;
use App\Models\FrontendPage;
use App\Models\FrontendService;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\ViewField;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * El selector de ícono es el mismo mecanismo que el selector de color
 * (`color-palette.blade.php`), aplicado a íconos: un disparador que muestra el
 * dibujo elegido, y un popover con cada dibujo real en vez de sólo su nombre.
 *
 * Antes era un `Select` nativo con únicamente el `label` de cada ícono
 * («Certificación», «Obra»): para saber qué dibujo elegía, el owner tenía que
 * abrir la galería de referencia aparte. El selector nuevo ES la referencia.
 */
class FrontendIconPickerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->actingAs(User::factory()->withRole('owner')->create());
    }

    // ------------------------------------------- catálogo de cada usuario --

    /** @return array<string, array{string, string, string}> */
    public static function sections(): array
    {
        return [
            'valores' => ['nosotros', 'values', 'card_icons'],
            'qué hacemos' => ['home', 'what_we_do', 'card_icons'],
        ];
    }

    #[DataProvider('sections')]
    public function test_the_section_picker_offers_exactly_its_own_catalog(
        string $pageKey,
        string $sectionKey,
        string $catalogo,
    ): void {
        $section = FrontendPage::query()->where('key', $pageKey)->firstOrFail()
            ->sections()->where('section_key', $sectionKey)->firstOrFail();

        $editor = Livewire::test(SectionsRelationManager::class, [
            'ownerRecord' => $section->page,
            'pageClass' => EditFrontendPage::class,
        ])->mountTableAction('edit', $section->getKey());

        $campo = $this->findViewField($editor->instance()->getMountedTableActionForm()->getComponents(), 'icon');

        $this->assertNotNull($campo, "«{$sectionKey}» no ofrece selector de ícono.");
        $this->assertSame('filament.forms.icon-picker', $campo->getView());
        $this->assertSame(
            array_keys((array) config("frontend-sections.{$catalogo}")),
            array_keys($campo->getViewData()['iconos']),
        );
    }

    public function test_the_service_picker_offers_only_its_own_three_icons(): void
    {
        // `FrontendService` usa un catálogo APARTE de `card_icons` (§ comentario
        // en config/frontend-sections.php): mezclarlos dejaría elegir «Obra»
        // donde el sitio sólo sabe dibujar tres.
        $service = FrontendService::query()->firstOrFail();

        $editor = Livewire::test(EditFrontendService::class, ['record' => $service->getKey()]);

        $campo = $this->findViewField($editor->instance()->form->getComponents(), 'icon');

        $this->assertNotNull($campo, 'El formulario de servicios no ofrece selector de ícono.');
        $this->assertSame('filament.forms.icon-picker', $campo->getView());
        $this->assertSame(
            ['home', 'building', 'trending-up'],
            array_keys($campo->getViewData()['iconos']),
        );

        // Y NINGUNO de los 16 íconos de secciones se cuela acá.
        $this->assertEmpty(array_intersect(
            array_keys($campo->getViewData()['iconos']),
            array_diff(array_keys((array) config('frontend-sections.card_icons')), ['home', 'building', 'trending-up']),
        ));
    }

    /** @return list<Component> */
    private function findViewField(array $components, string $name): ?ViewField
    {
        foreach ($components as $child) {
            if ($child instanceof ViewField && $child->getName() === $name) {
                return $child;
            }

            if ($child instanceof Component) {
                $encontrado = $this->findViewField($child->getChildComponents(), $name);

                if ($encontrado !== null) {
                    return $encontrado;
                }
            }
        }

        return null;
    }

    // ---------------------------------------------- elegir y guardar (E2E) --

    public function test_choosing_an_icon_through_the_picker_saves_and_hydrates_back(): void
    {
        // El picker escribe sobre el MISMO state path que escribía el `Select`
        // viejo — Alpine sólo cambia cómo se elige, no dónde vive el dato—, así
        // que el guardado corre por el mismo camino que ya prueba el resto de
        // la suite: `setTableActionData()` sobre el formulario real.
        $page = FrontendPage::query()->where('key', 'nosotros')->firstOrFail();
        $section = $page->sections()->where('type', 'values')->firstOrFail();

        $editor = fn () => Livewire::test(SectionsRelationManager::class, [
            'ownerRecord' => $page,
            'pageClass' => EditFrontendPage::class,
        ])->mountTableAction('edit', $section->getKey());

        $editor()
            ->setTableActionData(['payload' => [
                'title' => 'Nuestros valores',
                'items' => [['title' => 'Confianza', 'description' => 'Cumplimos.', 'icon' => 'shield']],
            ]])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertSame('shield', $section->fresh()->payload['items'][0]['icon']);

        // Y vuelve a aparecer elegido al reabrir — el propio bug que le dio
        // origen a esta clase de tests (FrontendSectionHydrationTest).
        $reabierto = $editor()->get('mountedTableActionsData')[0]['payload']['items'] ?? [];

        $this->assertSame('shield', array_values($reabierto)[0]['icon'] ?? null);
    }

    public function test_the_service_form_still_saves_its_icon_through_the_picker(): void
    {
        $service = FrontendService::query()->firstOrFail();

        Livewire::test(EditFrontendService::class, ['record' => $service->getKey()])
            ->set('data.icon', 'building')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('building', $service->fresh()->icon);
    }

    // ----------------------------------------------- el defecto ya conocido --

    public function test_the_icon_picker_never_loses_its_own_style_to_alpine(): void
    {
        // `x-bind:style` con una CADENA reemplaza el atributo `style` entero —
        // ya rompió el selector de color y la galería de íconos en vivo. Este
        // componente nació DESPUÉS de encontrar ese defecto y tiene que nacer
        // ya inmune.
        $fuente = (string) file_get_contents(resource_path('views/filament/forms/icon-picker.blade.php'));

        $this->assertDoesNotMatchRegularExpression(
            '/x-bind:style="\s*[\'{]?\s*\'/',
            $fuente,
            'icon-picker liga el estilo con una cadena: borraría el `style` inline del elemento.',
        );

        // Y el bind de la grilla del popover no vive en el mismo nodo que
        // `x-show` — mostrarlo pone `display:\'\'` y borra un `display:grid`
        // escrito ahí. La grilla real es el primer `<div style="display:grid…`
        // que aparece DESPUÉS del `x-show`.
        $this->assertMatchesRegularExpression(
            '/x-show="abierto"[\s\S]*?<div\s+style="display:grid/',
            $fuente,
            'La grilla del popover vive en el mismo nodo que x-show: se aplanaría al abrir.',
        );
    }

    // --------------------------------------------------- catálogo de servicio --

    public function test_service_icons_and_card_icons_stay_independent_catalogs(): void
    {
        // Puede coincidir el NOMBRE de una clave («home», «building») entre los
        // dos catálogos sin que el DIBUJO tenga que coincidir: son modelos
        // distintos y cada uno decide su propio trazo.
        $servicio = (array) config('frontend-sections.service_icons');
        $secciones = (array) config('frontend-sections.card_icons');

        $this->assertCount(3, $servicio);
        $this->assertGreaterThanOrEqual(16, count($secciones));

        foreach (['home', 'building', 'trending-up'] as $clave) {
            $this->assertArrayHasKey($clave, $servicio);
            $this->assertArrayHasKey($clave, $secciones, "«{$clave}» dejó de existir en card_icons; revisar welcome.blade.php.");
        }
    }

    public function test_the_home_fallback_draws_the_configured_service_icon(): void
    {
        // El fallback de la home (welcome.blade.php, sin CMS publicado) lee del
        // MISMO config que el selector — antes tenía un array duplicado a mano,
        // y las dos copias podían desincronizarse en silencio.
        //
        // `SeedInversionService` ya deja un `FrontendService` con
        // `show_in_home = true`; se le cambia sólo el ícono.
        $servicio = FrontendService::query()->firstOrFail();
        $servicio->forceFill(['icon' => 'building'])->save();

        $html = $this->get('/')->assertOk()->getContent();

        $path = config('frontend-sections.service_icons.building.path');
        $this->assertNotEmpty($path);
        $this->assertStringContainsString($path, $html);
    }
}
