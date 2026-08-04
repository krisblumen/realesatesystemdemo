<?php

namespace Tests\Feature\Frontend;

use App\Models\FrontendPage;
use App\Models\FrontendSection;
use App\Models\User;
use App\Services\Frontend\FrontendPageContentService;
use App\Services\Frontend\FrontendPagePublisher;
use App\Services\Frontend\FrontendSectionSchema;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * The page content engine (RFC-075): per-type validation, atomic draft edits
 * that bump draft_revision, page(key) reading only the published snapshot with a
 * fallback, and the optimistic publish that refuses a stale revision.
 */
class FrontendPageEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    private function schema(): FrontendSectionSchema
    {
        return app(FrontendSectionSchema::class);
    }

    private function content(): FrontendPageContentService
    {
        return app(FrontendPageContentService::class);
    }

    private function heroSection(): FrontendSection
    {
        return FrontendPage::query()->where('key', 'home')->firstOrFail()
            ->sections()->where('section_key', 'hero')->firstOrFail();
    }

    public function test_the_registry_seeded_every_canonical_section(): void
    {
        foreach ((array) config('frontend-sections.pages') as $pageKey => $sections) {
            $page = FrontendPage::query()->where('key', $pageKey)->firstOrFail();
            foreach ($sections as $sectionKey => $type) {
                $row = $page->sections()->where('section_key', $sectionKey)->first();
                $this->assertNotNull($row, "{$pageKey}.{$sectionKey} must be seeded.");
                $this->assertSame($type, $row->type);
            }
        }
    }

    public function test_an_unknown_type_and_free_html_are_rejected(): void
    {
        $this->assertNotEmpty($this->schema()->validate('iframe', ['x' => 1]));
        $this->assertNotEmpty($this->schema()->validate('rich_text', ['body' => 'Hola <script>alert(1)</script>']));
        $this->assertSame([], $this->schema()->validate('rich_text', ['body' => 'Texto plano y seguro.']));
    }

    public function test_a_flat_or_unsafe_cta_is_rejected_but_a_nested_one_passes(): void
    {
        // Flat legacy shape (a string, not {label,type,target}) is rejected.
        $this->assertNotEmpty($this->schema()->validate('hero', ['title' => 'X', 'primary_cta' => 'inmuebles.index']));
        // Unsafe target rejected.
        $this->assertNotEmpty($this->schema()->validate('cta', ['primary_cta' => ['label' => 'X', 'type' => 'url', 'target' => 'javascript:alert(1)']]));
        // Nested, safe route CTA passes.
        $this->assertSame([], $this->schema()->validate('hero', [
            'title' => 'Encuentra tu propiedad',
            'primary_cta' => ['label' => 'Ver', 'type' => 'route', 'target' => 'inmuebles'],
        ]));
    }

    public function test_a_feature_sequence_layout_must_be_allowlisted(): void
    {
        $this->assertNotEmpty($this->schema()->validate('feature_sequence', ['items' => [['title' => 'A', 'media_id' => '11111111-1111-1111-1111-111111111111', 'alt' => 'foto', 'layout' => 'carousel_3d']]]));
        $this->assertSame([], $this->schema()->validate('feature_sequence', ['items' => [['title' => 'A', 'media_id' => '11111111-1111-1111-1111-111111111111', 'alt' => 'foto', 'layout' => 'split_media_end']]]));
    }

    public function test_editing_a_section_validates_and_bumps_draft_revision(): void
    {
        $section = $this->heroSection();
        $before = $section->page->draft_revision;

        $this->content()->updateSectionPayload($section, ['title' => 'Nuevo título']);

        $this->assertSame('Nuevo título', $section->fresh()->payload['title']);
        $this->assertSame($before + 1, $section->page->fresh()->draft_revision);
    }

    public function test_editing_a_section_with_an_invalid_payload_throws_and_does_not_bump(): void
    {
        $section = $this->heroSection();
        $before = $section->page->draft_revision;

        try {
            $this->content()->updateSectionPayload($section, ['title' => 'X', 'primary_cta' => ['label' => 'E', 'type' => 'url', 'target' => 'javascript:alert(1)']]);
            $this->fail('An invalid payload must throw.');
        } catch (ValidationException) {
            // expected
        }

        $this->assertSame($before, $section->page->fresh()->draft_revision, 'A rejected edit must not bump the revision.');
    }

    public function test_page_returns_the_fallback_until_published_then_the_snapshot(): void
    {
        $this->assertTrue($this->content()->page('home')['fallback'], 'Unpublished page falls back.');

        $section = $this->heroSection();
        $this->content()->updateSectionPayload($section, ['title' => 'Home publicado']);

        $page = $section->page->fresh();
        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, User::factory()->withRole('owner')->create());

        $rendered = $this->content()->page('home');
        $this->assertFalse($rendered['fallback']);
        $keys = array_column($rendered['sections'], 'section_key');
        $this->assertContains('hero', $keys);
    }

    public function test_publishing_a_stale_draft_revision_is_rejected(): void
    {
        $page = FrontendPage::query()->where('key', 'home')->firstOrFail();
        $owner = User::factory()->withRole('owner')->create();

        // Someone edits the draft after the UI loaded revision N.
        $stale = $page->draft_revision;
        $this->content()->updateSectionPayload($this->heroSection(), ['title' => 'Cambio concurrente']);

        $this->expectException(ValidationException::class);
        app(FrontendPagePublisher::class)->publish($page->fresh(), $stale, $owner);
    }
}
