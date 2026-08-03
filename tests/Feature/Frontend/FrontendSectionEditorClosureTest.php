<?php

namespace Tests\Feature\Frontend;

use App\Filament\Forms\Sections\SectionPayloadCompiler;
use App\Filament\Resources\FrontendPageResource\Pages\EditFrontendPage;
use App\Filament\Resources\FrontendPageResource\RelationManagers\SectionsRelationManager;
use App\Models\FrontendPage;
use App\Models\Project;
use App\Models\Property;
use App\Models\User;
use App\Services\Frontend\FrontendSectionSchema;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Épica 12.2, Lote D — TB2D-1…TB2D-4: the raw JSON editor is gone.
 *
 * The Textarea was never a safety net. While it existed, anyone with this screen
 * could paste a payload by hand and bypass the validated UI entirely — a
 * permanent bypass sitting next to the forms built to prevent exactly that. It
 * only survived while some type still had no form of its own.
 *
 * `gallery` was retired with it (decision recorded in §7.2 of the contract): it
 * was an executable type no page could reach.
 */
class FrontendSectionEditorClosureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->actingAs(User::factory()->withRole('owner')->create());
    }

    // ------------------------------------------------------------ TB2D-1 ----

    public function test_every_canonical_section_shows_a_form(): void
    {
        $compiler = app(SectionPayloadCompiler::class);
        $checked = 0;

        foreach ((array) config('frontend-sections.pages') as $pageKey => $sections) {
            $page = FrontendPage::query()->where('key', $pageKey)->firstOrFail();

            foreach ($sections as $sectionKey => $type) {
                $this->assertTrue(
                    $compiler->handles($type),
                    "El tipo «{$type}» de {$pageKey}/{$sectionKey} no tiene formulario.",
                );

                $section = $page->sections()->where('section_key', $sectionKey)->firstOrFail();

                Livewire::test(SectionsRelationManager::class, [
                    'ownerRecord' => $page,
                    'pageClass' => EditFrontendPage::class,
                ])
                    ->mountTableAction('edit', $section->getKey())
                    ->assertDontSee('Contenido (JSON)')
                    ->assertHasNoTableActionErrors();

                $checked++;
            }
        }

        // Un recorrido que no recorriera nada pasaría en silencio. 24 de las
        // cinco páginas originales + 3 de `proyectos` (cambio
        // cms-pagina-proyectos, Work Unit 1: hero, projects_list, final_cta).
        $this->assertSame(27, $checked, 'Cambió la cantidad de secciones canónicas.');
    }

    public function test_every_allowlisted_type_has_a_form(): void
    {
        // No alcanza con cubrir las secciones que hoy existen: un tipo permitido
        // sin formulario volvería a ser inalcanzable, que es justo lo que llevó a
        // retirar `gallery`.
        $compiler = app(SectionPayloadCompiler::class);

        foreach (array_keys((array) config('frontend-sections.types')) as $type) {
            $this->assertTrue($compiler->handles($type), "El tipo permitido «{$type}» no tiene formulario.");
        }
    }

    public function test_every_canonical_section_has_a_human_name(): void
    {
        // `investment_path` o `audience_outcomes` son identificadores del
        // registro: estables y sin significado para quien administra el sitio.
        // Sin este test, agregar una sección al registro la deja mostrando su
        // clave interna en el panel y nadie se entera.
        $etiquetas = (array) config('frontend-sections.section_labels');

        foreach ((array) config('frontend-sections.pages') as $pageKey => $sections) {
            foreach (array_keys($sections) as $sectionKey) {
                $this->assertArrayHasKey(
                    $sectionKey,
                    $etiquetas,
                    "La sección «{$sectionKey}» de {$pageKey} no tiene nombre humano.",
                );
                $this->assertNotSame('', trim((string) $etiquetas[$sectionKey]));
            }
        }
    }

    public function test_the_editor_shows_the_human_name_not_the_internal_key(): void
    {
        $page = FrontendPage::query()->where('key', 'inversionistas')->firstOrFail();
        $section = $page->sections()->where('section_key', 'investment_path')->firstOrFail();

        Livewire::test(SectionsRelationManager::class, [
            'ownerRecord' => $page,
            'pageClass' => EditFrontendPage::class,
        ])
            ->mountTableAction('edit', $section->getKey())
            ->assertSee('Ruta de inversión')
            ->assertDontSee('Editar frontend section');
    }

    // ------------------------------------------------------------ TB2D-2 ----

    public function test_no_ui_path_writes_the_payload_as_free_text(): void
    {
        $source = file_get_contents(app_path(
            'Filament/Resources/FrontendPageResource/RelationManagers/SectionsRelationManager.php',
        ));

        // Un guard sobre el fuente y no sobre el DOM a propósito: el DOM sólo
        // muestra lo que hoy está visible, y el bypass volvería justamente por
        // un campo que alguien reintroduce «temporalmente».
        $this->assertStringNotContainsString('payload_json', $source);
        $this->assertStringNotContainsString('decodePayload', $source);
        $this->assertStringNotContainsString('json_decode', $source);
    }

    public function test_the_saved_payload_always_comes_from_the_compiler(): void
    {
        // El compilador cubre TODOS los tipos canónicos, así que ya no existe la
        // rama «este tipo no tiene formulario» que caía en texto libre.
        $compiler = app(SectionPayloadCompiler::class);

        foreach ((array) config('frontend-sections.pages') as $sections) {
            foreach ($sections as $type) {
                $this->assertTrue($compiler->handles($type));
            }
        }
    }

    // ------------------------------------------------------------ TB2D-3 ----

    public function test_the_gallery_type_was_retired(): void
    {
        $schema = app(FrontendSectionSchema::class);

        $this->assertFalse($schema->isAllowedType('gallery'));
        $this->assertArrayNotHasKey('gallery', (array) config('frontend-sections.types'));

        // Y su schema tampoco quedó colgando: validar contra él ya no es válido.
        $this->assertNotSame([], $schema->validate('gallery', ['items' => []]));
    }

    public function test_no_section_partial_survives_without_an_allowlisted_type(): void
    {
        // Al retirar `gallery` quedó su partial versionado. El dispatcher exige
        // allowlist Y existencia de vista, así que no era alcanzable — pero es la
        // misma incoherencia que motivó el retiro: código ejecutable sin punto de
        // entrada. Este test la cierra para el próximo tipo que se retire.
        $permitidos = array_keys((array) config('frontend-sections.types'));

        foreach (glob(resource_path('views/frontend/sections/*.blade.php')) as $partial) {
            $tipo = str_replace('.blade', '', pathinfo($partial, PATHINFO_FILENAME));

            $this->assertContains(
                $tipo,
                $permitidos,
                "El partial «{$tipo}.blade.php» no corresponde a ningún tipo permitido.",
            );
        }
    }

    public function test_retiring_it_left_the_property_and_project_galleries_untouched(): void
    {
        // El tipo de sección y la colección de media de Spatie se llaman igual y
        // no tienen NINGUNA relación: las fotos de un inmueble nunca pasaron por
        // el schema de secciones. Esto lo deja escrito para el próximo que lea.
        $this->assertContains('gallery', array_map(
            fn ($collection) => $collection->name,
            (new Property)->getRegisteredMediaCollections()->all(),
        ));

        $this->assertContains('gallery', array_map(
            fn ($collection) => $collection->name,
            (new Project)->getRegisteredMediaCollections()->all(),
        ));
    }

    // ------------------------------------------------------------ TB2D-4 ----

    public function test_the_five_public_pages_still_render(): void
    {
        foreach (['/' => 'Construimos patrimonio',
            '/nosotros' => 'Construimos patrimonio que trasciende',
            '/servicios' => 'Del terreno a la entrega',
            '/inversionistas' => 'De la oportunidad al desarrollo',
            '/contacto' => 'Estamos para asesorarte'] as $path => $h1) {
            $response = $this->get($path);

            $response->assertOk();
            $this->assertStringContainsString($h1, $response->getContent(), "Cambió el render de {$path}.");
        }
    }
}
