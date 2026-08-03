<?php

namespace Tests\Feature\Frontend;

use App\Filament\Forms\Sections\SectionPayloadCompiler;
use App\Filament\Resources\FrontendPageResource\Pages\EditFrontendPage;
use App\Models\FrontendPage;
use App\Models\FrontendSection;
use App\Models\User;
use App\Services\Frontend\FrontendSectionSchema;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Frontend\Concerns\MountsSectionEditor;
use Tests\TestCase;

/**
 * Épica 12.1, Lote B — TB-1, TB-2, TB-3, TB-12: the friendly hero editor.
 *
 * The point of the whole increment is here: the owner of an inmobiliaria edits a
 * headline without knowing what a JSON is. The form is bound straight to
 * `payload.*`, so what it produces IS the canonical payload of SPECS['hero'] —
 * there is no translation layer that could drift from the schema.
 */
class FrontendHeroEditorTest extends TestCase
{
    use MountsSectionEditor;
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

    private function page(string $key = 'home'): FrontendPage
    {
        return FrontendPage::query()->where('key', $key)->firstOrFail();
    }

    private function hero(string $pageKey = 'home'): FrontendSection
    {
        return $this->section('hero', $pageKey);
    }

    private function section(string $sectionKey, string $pageKey = 'home'): FrontendSection
    {
        return $this->page($pageKey)->sections()->where('section_key', $sectionKey)->firstOrFail();
    }

    // -------------------------------------------------------------- TB-1 ----

    public function test_the_hero_shows_fields_not_json(): void
    {
        $this->get(EditFrontendPage::getUrl(['record' => $this->page()->getKey()]))->assertOk();

        $manager = $this->sectionEditor($this->section('hero'));

        // The hero opens the FRIENDLY editor: fields with plain-language help,
        // no JSON anywhere.
        $manager->mountTableAction('edit', $this->hero()->getKey())
            ->assertSee('Fotos de fondo')
            ->assertSee('Alineación')
            ->assertSee('Mostrar logotipo')
            ->assertDontSee('Contenido (JSON)');

        // Y los campos del hero son SUYOS: otra sección no los hereda.
        $this->sectionEditor($this->section('services_list', 'servicios'))
            ->mountTableAction('edit', $this->section('services_list', 'servicios')->getKey())
            ->assertDontSee('Fotos de fondo');
    }

    // -------------------------------------------------------------- TB-2 ----

    public function test_the_form_state_compiles_into_the_canonical_hero_payload(): void
    {
        $section = $this->section('hero');

        $section->update(['payload' => [
            'title' => 'Encuentra tu próximo hogar',
            'subtitle' => 'Te acompañamos de principio a fin.',
            'eyebrow' => 'Inmobiliaria',
            'text_align' => 'center',
            'logo_enabled' => true,
            'logo_size' => 'lg',
            'primary_cta' => ['label' => 'Ver Propiedades', 'type' => 'route', 'target' => 'inmuebles'],
            'slides' => [],
        ]]);

        // The schema is the gate: if the shape the form produces were not
        // canonical, this would reject it.
        $this->assertSame([], app(FrontendSectionSchema::class)->validate('hero', $section->fresh()->payload));
    }

    public function test_the_new_presentation_fields_round_trip_through_the_schema(): void
    {
        $schema = app(FrontendSectionSchema::class);

        foreach (['left', 'center', 'right'] as $align) {
            $this->assertSame([], $schema->validate('hero', ['title' => 'T', 'text_align' => $align]));
        }

        foreach (['sm', 'md', 'lg', 'xl'] as $size) {
            $this->assertSame([], $schema->validate('hero', ['title' => 'T', 'logo_size' => $size, 'logo_enabled' => true]));
        }
    }

    // -------------------------------------------------------------- TB-3 ----

    public function test_values_outside_the_allowlist_are_rejected(): void
    {
        $schema = app(FrontendSectionSchema::class);

        $this->assertNotSame([], $schema->validate('hero', ['title' => 'T', 'text_align' => 'justify']));
        $this->assertNotSame([], $schema->validate('hero', ['title' => 'T', 'logo_size' => 'gigante']));

        // An unknown key is still rejected: the payload cannot smuggle fields no
        // renderer reads.
        $this->assertNotSame([], $schema->validate('hero', ['title' => 'T', 'inventado' => 'x']));

        // And HTML never gets through, new fields or not.
        $this->assertNotSame([], $schema->validate('hero', ['title' => '<script>alert(1)</script>']));
    }

    public function test_a_hero_published_before_this_increment_stays_valid(): void
    {
        // Backwards compatibility is the reason the three fields are optional:
        // every snapshot published before 12.1 must keep validating untouched.
        $legacy = [
            'eyebrow' => 'Inmobiliaria',
            'title' => 'Encuentra la propiedad ideal',
            'subtitle' => 'Acompañamos tu decisión.',
            'slides' => [],
        ];

        $this->assertSame([], app(FrontendSectionSchema::class)->validate('hero', $legacy));
    }

    // ------------------------------------------------------- alt/decorative --

    public function test_the_decorative_and_alt_pairing_is_still_enforced(): void
    {
        $schema = app(FrontendSectionSchema::class);
        $uuid = '11111111-2222-4333-8444-555555555555';

        // Meaningful image without alt → rejected.
        $this->assertNotSame([], $schema->validate('hero', [
            'title' => 'T',
            'slides' => [['media_id' => $uuid, 'alt' => null, 'decorative' => false, 'sort_order' => 0]],
        ]));

        // Decorative with alt → rejected.
        $this->assertNotSame([], $schema->validate('hero', [
            'title' => 'T',
            'slides' => [['media_id' => $uuid, 'alt' => 'algo', 'decorative' => true, 'sort_order' => 0]],
        ]));

        // Decorative without alt → fine.
        $this->assertSame([], $schema->validate('hero', [
            'title' => 'T',
            'slides' => [['media_id' => $uuid, 'alt' => null, 'decorative' => true, 'sort_order' => 0]],
        ]));
    }

    public function test_more_than_six_slides_is_rejected(): void
    {
        $slides = [];
        for ($i = 0; $i < 7; $i++) {
            $slides[] = [
                'media_id' => sprintf('11111111-2222-4333-8444-%012d', $i),
                'alt' => null, 'decorative' => true, 'sort_order' => $i,
            ];
        }

        $this->assertNotSame([], app(FrontendSectionSchema::class)->validate('hero', ['title' => 'T', 'slides' => $slides]));
    }

    // ------------------------------------------------------------- media ----

    public function test_an_uploaded_slide_lands_private_and_is_referenced_by_uuid(): void
    {
        $section = $this->section('hero');

        $media = $section->addMedia(UploadedFile::fake()->image('slide.png', 1200, 675))
            ->toMediaCollection('images');

        $section->update(['payload' => [
            'title' => 'T',
            'slides' => [['media_id' => (string) $media->uuid, 'alt' => null, 'decorative' => true, 'sort_order' => 0]],
        ]]);

        // The payload references the file by uuid — never a path or a url — and
        // the bytes stay private until a publish promotes them.
        $this->assertSame((string) $media->uuid, $section->fresh()->payload['slides'][0]['media_id']);
        $this->assertSame('frontend-private', $media->disk);
        $this->assertSame([], app(FrontendSectionSchema::class)->validate('hero', $section->fresh()->payload));
    }

    // ------------------------------------------------- hero-logo-propio -----

    private function compile(FrontendSection $section, array $state): ?array
    {
        return app(SectionPayloadCompiler::class)->compile($section, $state);
    }

    public function test_an_uploaded_logo_compiles_into_media_id_and_alt(): void
    {
        $section = $this->section('hero');

        $payload = $this->compile($section, [
            'title' => 'T',
            'logo' => [
                'upload' => [UploadedFile::fake()->image('logo.png', 400, 200)->store('', 'frontend-private')],
                'alt' => 'A-74 Arquitectura',
            ],
        ]);

        $this->assertArrayHasKey('logo', $payload, 'El compilador no adjuntó el logo.');
        $this->assertNotEmpty($payload['logo']['media_id'] ?? null);
        $this->assertSame('A-74 Arquitectura', $payload['logo']['alt'] ?? null);
        $this->assertSame([], app(FrontendSectionSchema::class)->validate('hero', $payload));
    }

    public function test_an_empty_logo_omits_the_key_entirely(): void
    {
        // «No inicializado» es distinto de «publicado vacío» (D4): un objeto
        // vacío fijaría la clave y competiría con el fallback de la página sin
        // que el owner lo haya decidido — misma regla que `spotlight`.
        $section = $this->section('hero');

        $payload = $this->compile($section, ['title' => 'T', 'logo' => ['media_id' => null, 'alt' => '']]);

        $this->assertArrayNotHasKey('logo', $payload, 'Un logo vacío no debe fijar la clave.');
    }

    public function test_the_own_logo_fieldset_appears_and_sibling_fields_still_hydrate(): void
    {
        // Guardia de la regresión de Filament (#1081): un Fieldset con
        // statePath() Y visible($record) hermano de otro con rutas absolutas
        // al mismo payload corrompe la hidratación del hermano. El fix (D9)
        // es un Fieldset INCONDICIONAL, sin visible(). El walk manual de
        // FrontendDynamicSectionEditorTest no resuelve visible($record) fuera
        // de un render real, así que esta prueba usa el HTML servido de
        // verdad, como editorHtml() en FrontendFeaturedProjectsCtaTest.
        $section = $this->section('hero');
        $media = $section->addMedia(UploadedFile::fake()->image('slide.png', 1600, 900))->toMediaCollection('images');
        $section->update(['payload' => [
            'title' => 'T',
            'text_align' => 'right',
            'slides' => [['media_id' => (string) $media->uuid, 'alt' => null, 'decorative' => true, 'sort_order' => 0]],
        ]]);

        $editor = $this->sectionEditor($section)->mountTableAction('edit', $section->getKey());

        $this->assertStringContainsString('Logo propio', $editor->html());

        $state = $editor->get('mountedTableActionsData')[0];
        $this->assertCount(1, $state['payload']['slides'] ?? [], 'El Fieldset del logo corrompió la hidratación de las slides.');
        $this->assertSame('right', $state['payload']['text_align'] ?? null, 'El Fieldset del logo corrompió text_align.');
    }
}
