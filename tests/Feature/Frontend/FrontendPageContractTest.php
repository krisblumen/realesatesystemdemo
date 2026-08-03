<?php

namespace Tests\Feature\Frontend;

use App\Models\FrontendPage;
use App\Models\FrontendSection;
use App\Models\User;
use App\Services\Frontend\FrontendCacheGeneration;
use App\Services\Frontend\FrontendPageContentService;
use App\Services\Frontend\FrontendPagePublisher;
use App\Services\Frontend\FrontendSectionSchema;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * The three contract guarantees the Lote E audit found missing:
 *   C-E1 — the schema is closed per type (unknown keys / wrong types / over-cardinality rejected);
 *   C-E2 — only canonical registry sections can be edited or published;
 *   C-E3 — the render reads ONLY the published snapshot, never live draft columns.
 */
class FrontendPageContractTest extends TestCase
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

    private function homePage(): FrontendPage
    {
        return FrontendPage::query()->where('key', 'home')->firstOrFail();
    }

    // ----- C-E1: closed per-type schema -----

    public function test_unknown_keys_wrong_types_and_over_cardinality_are_rejected(): void
    {
        $this->assertNotEmpty($this->schema()->validate('rich_text', ['body' => 'Texto', 'rogue' => 'x']), 'unknown key');
        $this->assertNotEmpty($this->schema()->validate('hero', ['title' => 123]), 'wrong type');
        $this->assertNotEmpty($this->schema()->validate('hero', ['title' => 'X', 'slides' => array_fill(0, 7, ['media_id' => '11111111-1111-1111-1111-111111111111'])]), '7 slides');
        $this->assertNotEmpty($this->schema()->validate('metrics', ['items' => [['label' => 'A']]]), 'missing required value');
        $this->assertNotEmpty($this->schema()->validate('featured_properties', ['limit' => 3, 'ids' => [1, 2]]), 'dynamic type rejects arbitrary params');
    }

    public function test_required_composites_media_and_accessibility_rules_are_enforced(): void
    {
        // C-E1-R: audience_outcomes.result is required.
        $this->assertNotEmpty($this->schema()->validate('audience_outcomes', ['title' => 'x', 'audience_items' => ['a']]), 'result required');

        // A slide must name its image and order.
        $this->assertNotEmpty($this->schema()->validate('hero', ['title' => 'X', 'slides' => [['alt' => 'foto', 'decorative' => false]]]), 'slide needs media_id + sort_order');

        // decorative/alt pairing.
        $this->assertNotEmpty($this->schema()->validate('hero', ['title' => 'X', 'slides' => [['media_id' => '11111111-1111-1111-1111-111111111111', 'sort_order' => 0, 'decorative' => false]]]), 'non-decorative needs alt');
        $this->assertNotEmpty($this->schema()->validate('hero', ['title' => 'X', 'slides' => [['media_id' => '11111111-1111-1111-1111-111111111111', 'sort_order' => 0, 'decorative' => true, 'alt' => 'x']]]), 'decorative must have empty alt');
        $this->assertSame([], $this->schema()->validate('hero', ['title' => 'X', 'slides' => [['media_id' => '11111111-1111-1111-1111-111111111111', 'sort_order' => 0, 'decorative' => true, 'alt' => null]]]));
        $this->assertNotEmpty($this->schema()->validate('hero', ['title' => 'X', 'slides' => [['media_id' => '11111111-1111-1111-1111-111111111111', 'sort_order' => -1, 'decorative' => true, 'alt' => null]]]), 'sort_order >= 0');
    }

    public function test_every_editorial_type_closes_its_nested_shape_and_media(): void
    {
        // C-E1-R2: the remaining nested/media gaps across editorial types.
        $this->assertNotEmpty($this->schema()->validate('audience_outcomes', ['result' => ['items' => ['a']]]), 'audience_items required');
        $this->assertNotEmpty($this->schema()->validate('audience_outcomes', ['audience_items' => ['a'], 'result' => ['title' => 'r']]), 'result.items required');
        $this->assertNotEmpty($this->schema()->validate('feature_sequence', ['title' => 'x']), 'feature_sequence items required');
        $this->assertNotEmpty($this->schema()->validate('feature_sequence', ['items' => [['title' => 'A']]]), 'feature item needs captioned media');
        $this->assertNotEmpty($this->schema()->validate('team', ['members' => [['name' => 'Ana', 'media_id' => '11111111-1111-1111-1111-111111111111']]]), 'team member image needs alt');

        // Positive: fully-formed payloads pass.
        $this->assertSame([], $this->schema()->validate('audience_outcomes', [
            'audience_items' => ['Inversionistas'],
            'result' => ['title' => 'Resultado', 'items' => ['Claridad total']],
        ]));
        $this->assertSame([], $this->schema()->validate('feature_sequence', [
            'items' => [['title' => 'Paso', 'media_id' => '11111111-1111-1111-1111-111111111111', 'alt' => 'Foto', 'layout' => 'split_media_end']],
        ]));
    }

    public function test_the_write_path_rejects_an_audience_outcomes_with_incomplete_result(): void
    {
        // The exact payload the auditor persisted in the second re-audit.
        $section = FrontendPage::query()->where('key', 'inversionistas')->firstOrFail()
            ->sections()->where('section_key', 'audience_outcomes')->firstOrFail();
        $before = $section->page->draft_revision;

        $this->expectException(ValidationException::class);
        try {
            $this->content()->updateSectionPayload($section, ['title' => 'x', 'result' => ['title' => 'r']]);
        } finally {
            $this->assertNull($section->fresh()->payload);
            $this->assertSame($before, $section->page->fresh()->draft_revision);
        }
    }

    public function test_the_write_path_rejects_an_incomplete_audience_outcomes(): void
    {
        // The auditor persisted an audience_outcomes without result THROUGH the
        // service. That must now be rejected before it can be stored.
        $section = FrontendPage::query()->where('key', 'inversionistas')->firstOrFail()
            ->sections()->where('section_key', 'audience_outcomes')->firstOrFail();
        $before = $section->page->draft_revision;

        $this->expectException(ValidationException::class);
        try {
            $this->content()->updateSectionPayload($section, ['title' => 'x', 'audience_items' => ['a']]);
        } finally {
            $this->assertNull($section->fresh()->payload, 'The incomplete payload must not persist.');
            $this->assertSame($before, $section->page->fresh()->draft_revision);
        }
    }

    public function test_a_valid_full_hero_payload_passes(): void
    {
        $this->assertSame([], $this->schema()->validate('hero', [
            'eyebrow' => 'Inmobiliaria',
            'title' => 'Encuentra tu propiedad',
            'subtitle' => 'Te acompañamos.',
            'primary_cta' => ['label' => 'Ver', 'type' => 'route', 'target' => 'inmuebles'],
            'secondary_cta' => ['label' => 'Proyectos', 'type' => 'route', 'target' => 'proyectos'],
            'slides' => [['media_id' => '11111111-1111-1111-1111-111111111111', 'alt' => null, 'decorative' => true, 'sort_order' => 0]],
        ]));
    }

    public function test_hero_logo_is_optional_and_requires_alt_when_present(): void
    {
        // `logo` ausente sigue siendo un hero válido: ninguna sección publicada
        // antes de este cambio la tiene (compatibilidad hacia atrás, dominio
        // hero-logo-propio).
        $this->assertSame([], $this->schema()->validate('hero', ['title' => 'X']));

        // Misma regla universal de accesibilidad que cualquier otro media_id del
        // payload (§16.1.1) — `logo` no tiene `decorative`, no hay escape.
        $this->assertNotEmpty($this->schema()->validate('hero', [
            'title' => 'X',
            'logo' => ['media_id' => '11111111-1111-1111-1111-111111111111'],
        ]), 'logo.media_id sin alt debe rechazarse');

        // Bien formado, con su alt, pasa.
        $this->assertSame([], $this->schema()->validate('hero', [
            'title' => 'X',
            'logo' => ['media_id' => '11111111-1111-1111-1111-111111111111', 'alt' => 'A-74 Arquitectura'],
        ]));

        // Una clave desconocida dentro de `logo` se rechaza, igual que en
        // cualquier otro objeto anidado del schema (C-E1).
        $this->assertNotEmpty($this->schema()->validate('hero', [
            'title' => 'X',
            'logo' => ['media_id' => '11111111-1111-1111-1111-111111111111', 'alt' => 'x', 'src' => 'x'],
        ]), 'una clave desconocida dentro de logo debe rechazarse');
    }

    public function test_feature_sequence_layout_is_required_and_allowlisted(): void
    {
        // C-E1-R3: a panel without a layout variant is rejected.
        $this->assertNotEmpty($this->schema()->validate('feature_sequence', ['items' => [['title' => 'A', 'media_id' => '11111111-1111-1111-1111-111111111111', 'alt' => 'f']]]), 'layout required');
        $this->assertNotEmpty($this->schema()->validate('feature_sequence', ['items' => [['title' => 'A', 'media_id' => '11111111-1111-1111-1111-111111111111', 'alt' => 'f', 'layout' => 'zzz']]]), 'layout allowlisted');

        foreach (['split_media_end', 'split_media_start', 'full_overlay'] as $layout) {
            $this->assertSame([], $this->schema()->validate('feature_sequence', ['items' => [['title' => 'A', 'media_id' => '11111111-1111-1111-1111-111111111111', 'alt' => 'f', 'layout' => $layout]]]), $layout);
        }
    }

    public function test_the_write_path_rejects_a_feature_sequence_without_layout(): void
    {
        $section = FrontendPage::query()->where('key', 'inversionistas')->firstOrFail()
            ->sections()->where('section_key', 'investment_path')->firstOrFail();

        $this->expectException(ValidationException::class);
        $this->content()->updateSectionPayload($section, ['items' => [['title' => 'Panel', 'media_id' => '11111111-1111-1111-1111-111111111111', 'alt' => 'f']]]);
    }

    // ----- M-E3: the snapshot is the complete publishable state -----

    public function test_the_snapshot_carries_every_section_with_is_enabled_and_dynamic_ids(): void
    {
        $page = $this->homePage();
        $owner = User::factory()->withRole('owner')->create();

        // Disable one canonical section before publishing.
        $partners = $page->sections()->where('section_key', 'partners')->firstOrFail();
        $this->content()->saveSectionDraft($partners, ['is_enabled' => false]);

        $page = $page->fresh();
        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, $owner);

        $snapshot = $this->homePage()->fresh()->published_revision;

        // Every canonical section is present, each with its is_enabled flag.
        $byKey = collect($snapshot['sections'])->keyBy('section_key');
        $this->assertArrayHasKey('is_enabled', $snapshot['sections'][0]);
        $this->assertTrue($byKey['hero']['is_enabled']);
        $this->assertFalse($byKey['partners']['is_enabled'], 'A disabled section is kept, marked disabled — not dropped.');

        // The dynamic inventory is captured.
        $this->assertArrayHasKey('generated_from_ids', $snapshot);
        $dynamicKeys = array_column($snapshot['generated_from_ids'], 'section_key');
        $this->assertContains('featured_properties', $dynamicKeys);
        $this->assertContains('featured_projects', $dynamicKeys);
    }

    public function test_a_malformed_media_id_is_a_clean_validation_error_not_a_db_crash(): void
    {
        // C-E4: the schema rejects a non-uuid media_id, and the write path must
        // NOT run the eligibility query on it — no QueryException, no 500.
        $section = FrontendPage::query()->where('key', 'nosotros')->firstOrFail()
            ->sections()->where('section_key', 'team')->firstOrFail();
        $before = $section->page->draft_revision;

        try {
            $this->content()->updateSectionPayload($section, ['members' => [['name' => 'Ana', 'media_id' => 'not-a-uuid', 'alt' => 'foto']]]);
            $this->fail('A malformed media_id must be rejected.');
        } catch (ValidationException) {
            // expected — a controlled validation error, not a DB exception.
        }

        $this->assertNull($section->fresh()->payload);
        $this->assertSame($before, $section->page->fresh()->draft_revision);
    }

    public function test_a_rogue_dynamic_section_never_reaches_generated_from_ids(): void
    {
        // M-E4: a directly-inserted dynamic row must be absent from BOTH the
        // sections snapshot and the dynamic inventory.
        FrontendSection::query()->create([
            'frontend_page_id' => $this->homePage()->id,
            'section_key' => 'rogue_dynamic', 'type' => 'featured_properties', 'is_enabled' => true, 'sort_order' => 98,
        ]);

        $page = $this->homePage()->fresh();
        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, User::factory()->withRole('owner')->create());

        $snapshot = $this->homePage()->fresh()->published_revision;
        $this->assertNotContains('rogue_dynamic', array_column($snapshot['sections'], 'section_key'));
        $this->assertNotContains('rogue_dynamic', array_column($snapshot['generated_from_ids'], 'section_key'));
    }

    // ----- C-E2: registry enforcement -----

    public function test_a_non_canonical_section_cannot_be_edited(): void
    {
        $rogue = FrontendSection::query()->create([
            'frontend_page_id' => $this->homePage()->id,
            'section_key' => 'rogue', 'type' => 'hero', 'is_enabled' => true, 'sort_order' => 99,
        ]);

        $this->expectException(ValidationException::class);
        $this->content()->updateSectionPayload($rogue, ['title' => 'X']);
    }

    public function test_a_non_canonical_section_never_reaches_the_published_snapshot(): void
    {
        FrontendSection::query()->create([
            'frontend_page_id' => $this->homePage()->id,
            'section_key' => 'rogue', 'type' => 'hero', 'is_enabled' => true, 'sort_order' => 99,
        ]);

        $page = $this->homePage()->fresh();
        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, User::factory()->withRole('owner')->create());

        $keys = array_column($this->homePage()->fresh()->published_revision['sections'], 'section_key');
        $this->assertNotContains('rogue', $keys, 'A section outside the registry must not publish.');
        $this->assertContains('hero', $keys);
    }

    // ----- C-E3: snapshot isolation -----

    public function test_draft_seo_and_is_enabled_changes_do_not_affect_the_render_until_published(): void
    {
        $page = $this->homePage();
        $owner = User::factory()->withRole('owner')->create();
        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, $owner);

        $before = $this->content()->page('home');
        $this->assertFalse($before['fallback']);

        // Mutate the DRAFT columns directly, without publishing.
        $page->fresh()->update(['seo' => ['title' => 'draft-only'], 'is_enabled' => false]);
        app(FrontendCacheGeneration::class)->bump();

        $after = $this->content()->page('home');
        $this->assertFalse($after['fallback'], 'A draft is_enabled=false must not disable the published page.');
        $this->assertNull($after['seo'] ?? null, 'Draft seo must not leak into the render.');
    }

    public function test_the_snapshot_carries_the_full_publishable_state(): void
    {
        $page = $this->homePage();
        DB::table('frontend_pages')->where('id', $page->id)->update(['seo' => json_encode(['title' => 'SEO publicado'])]);
        $page = $page->fresh();

        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, User::factory()->withRole('owner')->create());

        $snapshot = $this->homePage()->fresh()->published_revision;
        $this->assertArrayHasKey('is_enabled', $snapshot);
        $this->assertArrayHasKey('seo', $snapshot);
        $this->assertArrayHasKey('sections', $snapshot);
        $this->assertSame('SEO publicado', $snapshot['seo']['title']);
    }
}
