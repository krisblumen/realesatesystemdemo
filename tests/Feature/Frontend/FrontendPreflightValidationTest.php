<?php

namespace Tests\Feature\Frontend;

use App\Models\FrontendPage;
use App\Models\User;
use App\Services\Frontend\FrontendPageContentService;
use App\Services\Frontend\FrontendPagePublisher;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Preflight validation at publish (RFC-077, Lote G): page-level composition rules
 * the per-section schema cannot express. An enabled page whose hero was disabled
 * has no H1 and is refused before the snapshot is written.
 */
class FrontendPreflightValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_publishing_an_enabled_page_with_a_disabled_hero_is_rejected(): void
    {
        $content = app(FrontendPageContentService::class);
        $page = FrontendPage::query()->where('key', 'servicios')->firstOrFail();
        $hero = $page->sections()->where('section_key', 'hero')->firstOrFail();

        $content->updateSectionPayload($hero, ['title' => 'Un hero válido']);
        // Turn the hero OFF: the enabled page would render with no <h1>.
        $content->saveSectionDraft($hero->fresh(), ['is_enabled' => false]);

        $page = $page->fresh();
        $owner = User::factory()->withRole('owner')->create();

        try {
            app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, $owner);
            $this->fail('Publishing an enabled page without an active hero must be rejected.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('hero', collect($e->errors())->flatten()->first());
        }

        // The snapshot was never written — the page still falls back.
        $this->assertTrue($content->page('servicios')['fallback']);
    }

    public function test_a_page_with_an_active_hero_publishes(): void
    {
        $content = app(FrontendPageContentService::class);
        $page = FrontendPage::query()->where('key', 'servicios')->firstOrFail();
        $hero = $page->sections()->where('section_key', 'hero')->firstOrFail();

        $content->updateSectionPayload($hero, ['title' => 'Con título válido']);

        $page = $page->fresh();
        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, User::factory()->withRole('owner')->create());

        $this->assertFalse($content->page('servicios')['fallback']);
    }
}
