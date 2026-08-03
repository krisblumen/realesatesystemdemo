<?php

namespace Tests\Feature\Frontend;

use App\Filament\Forms\Sections\SectionPayloadCompiler;
use App\Filament\Resources\FrontendPageResource\Pages\EditFrontendPage;
use App\Filament\Resources\FrontendPageResource\RelationManagers\SectionsRelationManager;
use App\Models\FrontendPage;
use App\Models\FrontendSection;
use App\Models\Project;
use App\Models\User;
use App\Services\Frontend\FrontendPagePublisher;
use App\Services\Frontend\FrontendSectionSchema;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El listado de proyectos destacados lleva al catálogo completo de proyectos.
 *
 * Es el mismo patrón que ya tienen `featured_properties` y
 * `opportunity_properties` (FrontendFeaturedPropertiesCtaTest): sin este botón
 * la sección es un callejón, muestra unos pocos proyectos y no dice que haya
 * más.
 *
 * EL DESTINO NO SE PREGUNTA. «El catálogo de proyectos» es una sola página del
 * sitio (`proyectos`), así que ofrecer elegirla sería ofrecer equivocarse en
 * algo que no tiene alternativa. Lo único que el owner elige es cómo se llama
 * el botón.
 */
class FrontendFeaturedProjectsCtaTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('frontend-private');

        $this->seed(PermissionSeeder::class);
        $this->owner = User::factory()->withRole('owner')->create();
        $this->actingAs($this->owner);
    }

    private function section(): FrontendSection
    {
        return FrontendPage::query()->where('key', 'home')->firstOrFail()
            ->sections()->where('section_key', 'featured_projects')->firstOrFail();
    }

    private function compile(array $state): array
    {
        return app(SectionPayloadCompiler::class)->compile($this->section(), $state);
    }

    /** Un proyecto destacado, para que la sección tenga qué mostrar. */
    private function conProyectoDestacado(): Project
    {
        return Project::query()->create([
            'title' => 'Torres Alameda',
            'description' => 'Residencial de uso mixto en Zibatá.',
            'is_featured' => true,
        ]);
    }

    private function publicar(array $payload): string
    {
        $section = $this->section();
        $section->forceFill(['payload' => $payload])->saveQuietly();

        $page = $section->page->fresh();
        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, $this->owner);

        return $this->get('/')->assertOk()->getContent();
    }

    // ----------------------------------------------------------- el destino ----

    public function test_the_destination_is_set_by_the_compiler_not_the_owner(): void
    {
        $payload = $this->compile(['title' => 'Proyectos destacados', 'cta_label' => 'Ver todo']);

        $this->assertSame('route', $payload['primary_cta']['type']);
        $this->assertSame('proyectos', $payload['primary_cta']['target']);
        $this->assertSame('Ver todo', $payload['primary_cta']['label']);
    }

    public function test_a_target_sent_by_hand_is_ignored(): void
    {
        // El formulario no ofrece el destino, pero la regla no puede depender de
        // eso: un POST armado a mano tampoco debe reapuntar el botón.
        $payload = $this->compile([
            'title' => 'Proyectos destacados',
            'cta_label' => 'Ver todo',
            'primary_cta' => ['label' => 'Ir a otro lado', 'type' => 'url', 'target' => 'https://ejemplo.test'],
        ]);

        $this->assertSame('proyectos', $payload['primary_cta']['target']);
        $this->assertSame('route', $payload['primary_cta']['type']);
    }

    public function test_an_empty_label_falls_back_to_a_readable_one(): void
    {
        // Un botón sin texto es un botón invisible.
        $payload = $this->compile(['title' => 'Proyectos destacados', 'cta_label' => '   ']);

        $this->assertSame('Ver todos los proyectos', $payload['primary_cta']['label']);
    }

    public function test_the_payload_stays_valid(): void
    {
        $this->assertSame([], app(FrontendSectionSchema::class)->validate(
            'featured_projects',
            $this->compile(['title' => 'Proyectos destacados', 'cta_label' => 'Ver todo']),
        ));
    }

    // ------------------------------------------------------------ el render ----

    public function test_the_button_points_at_the_projects_catalogue(): void
    {
        $this->conProyectoDestacado();

        $html = $this->publicar([
            'title' => 'Proyectos destacados',
            'primary_cta' => ['label' => 'Ver todos los proyectos', 'type' => 'route', 'target' => 'proyectos'],
        ]);

        $this->assertStringContainsString('Ver todos los proyectos', $html);
        $this->assertStringContainsString(route('proyectos'), $html);
    }

    public function test_the_button_sits_beside_the_title_in_the_brand_colour(): void
    {
        // Mismo patrón que featured_properties: arriba a la derecha, a la altura
        // del título, en el color principal del tema.
        $this->conProyectoDestacado();

        $html = $this->publicar([
            'title' => 'Proyectos destacados',
            'primary_cta' => ['label' => 'Ver todos los proyectos', 'type' => 'route', 'target' => 'proyectos'],
        ]);

        $this->assertMatchesRegularExpression(
            '/flex flex-wrap items-end justify-between.*?Proyectos destacados.*?Ver todos los proyectos/s',
            $html,
            'El botón dejó de compartir la línea del título.',
        );

        $this->assertMatchesRegularExpression(
            '/<a[^>]*bg-brand-primary[^>]*text-on-brand-primary[^>]*>[^<]*Ver todos los proyectos/s',
            $html,
            'El botón no usa el color principal del tema.',
        );
    }

    public function test_a_section_published_before_this_existed_simply_has_no_button(): void
    {
        // LO QUE NO DEBE ROMPERSE: los snapshots publicados sin `primary_cta`
        // siguen renderizando, sin un botón vacío ni un enlace muerto.
        $this->conProyectoDestacado();

        $html = $this->publicar(['title' => 'Proyectos destacados']);

        $this->assertStringContainsString('Proyectos destacados', $html);
        $this->assertStringNotContainsString('Ver todos los proyectos', $html);
    }

    // -------------------------------------------------------- el formulario ----

    public function test_the_cta_label_field_is_offered_for_this_type(): void
    {
        // Antes de este cambio, «Texto del botón» no aparecía para
        // featured_projects: la lista que gobierna su visibilidad no lo incluía.
        $html = Livewire::test(SectionsRelationManager::class, [
            'ownerRecord' => $this->section()->page,
            'pageClass' => EditFrontendPage::class,
        ])->mountTableAction('edit', $this->section()->getKey())->html();

        $this->assertStringContainsString('Texto del botón', $html);
        $this->assertStringContainsString('Ver todos los proyectos', $html, 'El placeholder no es el de proyectos.');
    }

    public function test_the_label_survives_opening_and_saving_the_editor(): void
    {
        // La regresión que ya nos mordió con otras secciones dinámicas: el
        // payload guarda el CTA entero y el formulario muestra sólo su etiqueta.
        // Sin hidratarla, el editor abre en blanco y guardar la reemplaza por el
        // texto de ejemplo, borrando en silencio lo que el owner escribió.
        $section = $this->section();
        $section->forceFill(['payload' => [
            'title' => 'Destacados',
            'primary_cta' => ['label' => 'Mirá todos los proyectos', 'type' => 'route', 'target' => 'proyectos'],
        ]])->saveQuietly();

        $editor = Livewire::test(SectionsRelationManager::class, [
            'ownerRecord' => $section->page,
            'pageClass' => EditFrontendPage::class,
        ])->mountTableAction('edit', $section->getKey());

        $this->assertSame(
            'Mirá todos los proyectos',
            $editor->get('mountedTableActionsData')[0]['payload']['cta_label'] ?? null,
            'El editor abrió sin el texto que estaba guardado.',
        );

        $editor->callMountedTableAction()->assertHasNoTableActionErrors();

        $this->assertSame('Mirá todos los proyectos', $section->fresh()->payload['primary_cta']['label']);
    }

    // ----------------------------------------------- la tarjeta destacado ----

    private function editorHtml(string $sectionKey, string $pageKey = 'home'): string
    {
        $section = FrontendPage::query()->where('key', $pageKey)->firstOrFail()
            ->sections()->where('section_key', $sectionKey)->firstOrFail();

        return Livewire::test(SectionsRelationManager::class, [
            'ownerRecord' => $section->page,
            'pageClass' => EditFrontendPage::class,
        ])->mountTableAction('edit', $section->getKey())->html();
    }

    public function test_the_author_logo_field_only_appears_for_featured_projects(): void
    {
        // El campo del logo (Fieldset::visible()) depende de $record, y la
        // caminata manual de FrontendDynamicSectionEditorTest no lo resuelve
        // fuera de un render real — por eso esta prueba usa el HTML servido de
        // verdad y no una inspección del árbol de componentes.
        $this->assertStringContainsString('Autor de los proyectos', $this->editorHtml('featured_projects'));

        foreach (['featured_properties', 'opportunity_properties'] as $sectionKey) {
            $this->assertStringNotContainsString(
                'Autor de los proyectos',
                $this->editorHtml($sectionKey),
                "«{$sectionKey}» no debería ofrecer el logo de autor.",
            );
        }
    }

    public function test_without_a_logo_the_header_renders_exactly_as_before(): void
    {
        // Ninguna sección publicada antes de este cambio tiene media_id: sin
        // logo, la banda gris y la tarjeta blanca no deben aparecer.
        $this->conProyectoDestacado();

        $html = $this->publicar([
            'title' => 'Proyectos destacados',
            'primary_cta' => ['label' => 'Ver todos los proyectos', 'type' => 'route', 'target' => 'proyectos'],
        ]);

        $this->assertStringNotContainsString('bg-fog', $html);
        // El botón sigue siendo el sólido de siempre, no el link con flecha.
        $this->assertMatchesRegularExpression(
            '/<a[^>]*bg-brand-primary[^>]*>[^<]*Ver todos los proyectos/s',
            $html,
        );
    }

    // -------------------------------------------------- el color del fondo ----

    public function test_the_owner_can_choose_the_section_background(): void
    {
        $this->conProyectoDestacado();

        $payload = $this->compile([
            'title' => 'Proyectos destacados',
            'background_color' => 'primary-l2',
        ]);

        $this->assertSame('primary-l2', $payload['background_color']);
        $this->assertSame([], app(FrontendSectionSchema::class)->validate('featured_projects', $payload));
        $this->assertStringContainsString('bg-brand-primary-l2', $this->publicar($payload));
    }

    public function test_a_colour_outside_the_palette_is_rejected(): void
    {
        // La paleta es CERRADA: un valor inventado no puede llegar a un nombre
        // de clase, o el payload elegiría el CSS del sitio.
        $errores = app(FrontendSectionSchema::class)->validate('featured_projects', [
            'title' => 'Proyectos destacados',
            'background_color' => 'verde-fluor',
        ]);

        $this->assertNotSame([], $errores);
    }

    public function test_without_a_choice_the_background_follows_whether_there_is_a_logo(): void
    {
        // El gesto que ya tenía la sección antes de que el selector existiera:
        // gris para separar la tarjeta blanca, fondo del sitio si no hay logo.
        $this->assertSame('site', $this->compile(['title' => 'Proyectos destacados'])['background_color']);
    }

    public function test_with_a_logo_the_header_becomes_a_spotlight_card_on_a_grey_band(): void
    {
        $this->conProyectoDestacado();

        // El mismo camino real que usa el formulario: el compilador convierte
        // la ruta que deja un upload de Filament en un media_id de verdad.
        $payload = $this->compile([
            'title' => 'Proyectos destacados',
            'eyebrow' => 'DESPACHO DE ARQUITECTURA · A74',
            'primary_cta' => ['label' => 'Ver todos los proyectos', 'type' => 'route', 'target' => 'proyectos'],
            'upload' => [UploadedFile::fake()->image('logo.png', 400, 160)->store('', 'frontend-private')],
            'alt' => 'Logo de A-74 Arquitectura',
        ]);

        $this->assertArrayHasKey('media_id', $payload, 'El compilador no adjuntó el logo.');

        $html = $this->publicar($payload);

        $this->assertStringContainsString('bg-fog', $html);
        $this->assertStringContainsString('alt="Logo de A-74 Arquitectura"', $html);
        // El botón, ahora como enlace de texto con flecha — no el sólido.
        $this->assertMatchesRegularExpression(
            '/text-brand-primary-ink[^>]*>Ver todos los proyectos<\/a>\s*<span[^>]*>→/s',
            $html,
        );
    }
}
