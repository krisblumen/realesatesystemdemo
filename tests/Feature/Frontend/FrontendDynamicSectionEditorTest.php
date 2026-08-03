<?php

namespace Tests\Feature\Frontend;

use App\Filament\Resources\FrontendPageResource\Pages\EditFrontendPage;
use App\Filament\Resources\FrontendPageResource\RelationManagers\SectionsRelationManager;
use App\Models\FrontendPage;
use App\Models\FrontendSection;
use App\Models\Property;
use App\Models\ServiceType;
use App\Models\User;
use App\Services\Frontend\FrontendPagePublisher;
use App\Services\Frontend\FrontendPageRenderer;
use App\Services\Frontend\FrontendSectionSchema;
use Database\Seeders\PermissionSeeder;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Field;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Épica 12.2, Lote C — TB2C-1…TB2C-6: the four dynamic types.
 *
 * The risk of this lote is not that a field is missing — it is that a field
 * exists that should not. These sections list what the kernel says is featured,
 * resolved on EVERY render; the payload only carries a heading and a count. A
 * form that let the owner pin items would turn a live listing into a snapshot
 * that silently rots, so most of what is asserted here is an ABSENCE.
 */
class FrontendDynamicSectionEditorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->actingAs(User::factory()->withRole('owner')->create());
    }

    private function page(string $key): FrontendPage
    {
        return FrontendPage::query()->where('key', $key)->firstOrFail();
    }

    private function section(string $pageKey, string $sectionKey): FrontendSection
    {
        return $this->page($pageKey)->sections()->where('section_key', $sectionKey)->firstOrFail();
    }

    private function editor(FrontendSection $section): Testable
    {
        return Livewire::test(SectionsRelationManager::class, [
            'ownerRecord' => $section->page,
            'pageClass' => EditFrontendPage::class,
        ])->mountTableAction('edit', $section->getKey());
    }

    public static function dynamicSections(): array
    {
        return [
            'service_list' => ['servicios', 'services_list'],
            'featured_properties' => ['home', 'featured_properties'],
            'opportunity_properties' => ['home', 'opportunity_properties'],
            'featured_projects' => ['home', 'featured_projects'],
        ];
    }

    // ------------------------------------------------------------ TB2C-1 ----

    #[DataProvider('dynamicSections')]
    public function test_the_form_exposes_no_item_id_or_query_field(string $pageKey, string $sectionKey): void
    {
        $section = $this->section($pageKey, $sectionKey);
        $names = $this->fieldNames($section);

        // Whatever else changes, ONLY these may ever appear. Todos son de
        // PRESENTACIÓN: cómo se ve el listado, nunca qué entra en él.
        //
        // `cta_label` es el texto del botón al catálogo y `body` el párrafo bajo
        // el título. Entran en la lista porque son exactamente eso —texto—, y
        // el destino del botón NO se expone: lo fija el compilador, según se
        // comprueba abajo.
        //
        // `title_bold` y `eyebrow_bold` son el grosor del encabezado: presentación
        // pura, del mismo orden que `limit`. No tocan qué entra en el listado.
        //
        // `background_color` es el fondo de la banda, de la paleta cerrada: la
        // definición misma de «cómo se ve». `media_id`, `upload` y `alt` son el
        // logo del autor que `featured_projects` dibuja sobre su encabezado —una
        // imagen del encabezado, no un ítem del listado.
        foreach ($names as $name) {
            $this->assertContains(
                $name,
                [
                    'payload.eyebrow', 'payload.title', 'payload.body', 'payload.limit',
                    'payload.cta_label', 'payload.title_bold', 'payload.eyebrow_bold',
                    'payload.background_color',
                    'payload.media_id', 'payload.upload', 'payload.alt',
                ],
                "El formulario de {$sectionKey} expone «{$name}», que no es un parámetro de presentación.",
            );
        }

        // LO QUE DE VERDAD NO PUEDE APARECER: el destino del botón. Un campo
        // así convertiría un listado en un enlace a cualquier parte, que es
        // justo lo que este tipo de sección no debe permitir.
        foreach ($names as $name) {
            $this->assertStringNotContainsString('target', $name, "«{$name}» deja elegir a dónde apunta el listado.");
            $this->assertStringNotContainsString('type', $name);
            $this->assertStringNotContainsString('url', $name);
        }

        $this->editor($section)->assertDontSee('Contenido (JSON)');
    }

    /**
     * Los nombres de payload que el formulario expone, contenedores incluidos.
     *
     * Se lee la ruta ABSOLUTA y se recorta desde `payload.`, en vez de usar
     * `getStatePath(false)`: ese sólo quita el prefijo de Livewire y NO el
     * `statePath()` del contenedor, así que un campo dentro de un
     * `Fieldset->statePath('payload')` devolvía su nombre suelto —`media_id`—,
     * no empezaba con «payload» y el filtro lo salteaba. El guardián quedaba
     * ciego justo a los campos anidados, que es donde más fácil se cuela uno.
     *
     * @return list<string>
     */
    private function fieldNames(FrontendSection $section): array
    {
        $names = [];

        $walk = function ($components) use (&$walk, &$names): void {
            foreach ($components as $child) {
                if ($child instanceof Component && ! $child->isVisible()) {
                    continue;
                }

                // A Field is anything the owner can type into; a Placeholder is
                // read-only text and cannot carry a value into the payload.
                if ($child instanceof Field) {
                    $path = $child->getStatePath();

                    if (($corte = strpos($path, 'payload.')) !== false) {
                        $names[] = substr($path, $corte);
                    }
                }

                if ($child instanceof Component) {
                    $walk($child->getChildComponents());
                }
            }
        };

        $walk($this->editor($section)->instance()->getMountedTableActionForm()->getComponents());

        return $names;
    }

    // ------------------------------------------------------------ TB2C-2 ----

    #[DataProvider('dynamicTypes')]
    public function test_a_payload_carrying_items_or_ids_is_rejected(string $type): void
    {
        $schema = app(FrontendSectionSchema::class);

        foreach ([
            ['items' => [['id' => 1]]],
            ['ids' => [1, 2, 3]],
            ['property_ids' => [1]],
            ['query' => 'where featured = true'],
        ] as $smuggled) {
            $this->assertNotSame(
                [],
                $schema->validate($type, ['title' => 'Destacados'] + $smuggled),
                "El schema de {$type} aceptó ".json_encode(array_key_first($smuggled)),
            );
        }
    }

    public static function dynamicTypes(): array
    {
        return [
            'service_list' => ['service_list'],
            'featured_properties' => ['featured_properties'],
            'opportunity_properties' => ['opportunity_properties'],
            'featured_projects' => ['featured_projects'],
        ];
    }

    // ------------------------------------------------------------ TB2C-3 ----

    public function test_the_listed_items_come_from_the_kernel_not_the_payload(): void
    {
        $section = $this->section('home', 'featured_properties');

        $this->editor($section)
            ->setTableActionData(['payload' => ['title' => 'Destacadas']])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        // La propiedad se crea acá y no se depende del seed: un test que se
        // saltea cuando el fixture no está no prueba nada.
        $property = Property::factory()->published()->create([
            'title' => 'Casa de prueba en Juriquilla',
            'is_featured' => true,
        ]);

        $this->get('/')->assertOk()->assertSee($property->title, escape: false);

        // Se le quita el destacado — y NADIE republica la página.
        $property->update(['is_featured' => false]);

        $this->get('/')->assertOk()->assertDontSee($property->title, escape: false);
    }

    // ------------------------------------------------------------ TB2C-4 ----

    #[DataProvider('limits')]
    public function test_a_limit_out_of_range_falls_back_to_the_bounded_default(mixed $limit, int $expected): void
    {
        $renderer = app(FrontendPageRenderer::class);
        $method = new \ReflectionMethod($renderer, 'limit');

        $this->assertSame($expected, $method->invoke($renderer, $limit));
    }

    public static function limits(): array
    {
        return [
            'ausente' => [null, 12],
            'cero' => [0, 12],
            'negativo' => [-5, 12],
            'excesivo' => [9999, 12],
            'no numérico' => ['muchos', 12],
            'en rango' => [6, 6],
            'el máximo' => [24, 24],
        ];
    }

    public function test_the_form_offers_only_the_bounded_range(): void
    {
        // Un campo sin tope dejaría al owner pedir 500 y ver 12 sin explicación.
        $section = $this->section('home', 'featured_properties');
        $limit = null;

        $walk = function ($components) use (&$walk, &$limit): void {
            foreach ($components as $child) {
                if ($child instanceof Component && ! $child->isVisible()) {
                    continue;
                }

                if ($child instanceof Field && $child->getStatePath(false) === 'payload.limit') {
                    $limit = $child;
                }

                if ($child instanceof Component) {
                    $walk($child->getChildComponents());
                }
            }
        };

        $walk($this->editor($section)->instance()->getMountedTableActionForm()->getComponents());

        $this->assertNotNull($limit, 'El listado no ofrece «cuántos mostrar».');
        $this->assertSame(1, $limit->getMinValue());
        $this->assertSame(24, $limit->getMaxValue());
    }

    public function test_service_list_has_no_limit_field(): void
    {
        // Su SPECS no lo tiene: los servicios activos se listan todos.
        $this->assertNotContains('payload.limit', $this->fieldNames($this->section('servicios', 'services_list')));
    }

    // ------------------------------------------------------------ TB2C-5 ----

    public function test_publishing_still_records_the_inventory_in_generated_from_ids(): void
    {
        $page = $this->page('home');

        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, User::factory()->withRole('owner')->create());

        $inventory = collect($page->fresh()->published_revision['generated_from_ids'] ?? [])
            ->firstWhere('section_key', 'featured_properties');

        $this->assertNotNull($inventory, 'La revisión publicada no registró el inventario del listado destacado.');
        $this->assertEqualsCanonicalizing(
            Property::query()->published()->featured()->pluck('id')->all(),
            $inventory['ids'],
        );
    }

    // ------------------------------------------------------------ TB2C-6 ----

    public function test_an_ineligible_service_still_never_appears(): void
    {
        $service = ServiceType::query()->first();

        if ($service === null) {
            $this->markTestSkipped('El seed no dejó ningún servicio.');
        }

        $this->get('/servicios')->assertOk()->assertSee($service->name, escape: false);

        // Fail-closed (RFC-074): dado de baja, desaparece — no queda un hueco ni
        // un ítem huérfano.
        $service->update(['is_active' => false]);

        $this->get('/servicios')->assertOk()->assertDontSee($service->name, escape: false);
    }
}
