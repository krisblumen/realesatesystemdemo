<?php

namespace Tests\Feature\Frontend;

use App\Models\FrontendPage;
use App\Models\User;
use App\Services\Frontend\FrontendPageContentService;
use App\Services\Frontend\FrontendPagePublisher;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Publish observability (RFC-077, Lote G): a successful publish records actor and
 * timestamp on the row AND emits a `frontend.published` log with the actor and
 * entity; a refused publish emits `frontend.publish_failed`. Content is never
 * logged in full.
 */
class FrontendPublishObservabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_a_successful_publish_records_actor_timestamp_and_logs_the_event(): void
    {
        Log::spy();

        $content = app(FrontendPageContentService::class);
        $page = FrontendPage::query()->where('key', 'servicios')->firstOrFail();
        $hero = $page->sections()->where('section_key', 'hero')->firstOrFail();
        $content->updateSectionPayload($hero, ['title' => 'Publicado con log']);

        $page = $page->fresh();
        $owner = User::factory()->withRole('owner')->create();
        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, $owner);

        $page->refresh();
        $this->assertSame($owner->getKey(), $page->published_by);
        $this->assertNotNull($page->published_at);

        // The logged revision must equal the PERSISTED revision, not one more
        // (audit G recommendation #1: update() mutates the in-memory attribute).
        Log::shouldHaveReceived('info')->withArgs(fn (string $msg, array $ctx = []) => $msg === 'frontend.published'
            && ($ctx['actor'] ?? null) === $owner->getKey()
            && ($ctx['entity'] ?? null) === 'page:servicios'
            && ($ctx['revision'] ?? null) === $page->revision)->once();
    }

    public function test_a_refused_publish_logs_the_failure(): void
    {
        Log::spy();

        $content = app(FrontendPageContentService::class);
        $page = FrontendPage::query()->where('key', 'servicios')->firstOrFail();
        $hero = $page->sections()->where('section_key', 'hero')->firstOrFail();
        // Disabled hero on an enabled page → preflight failure (no H1).
        $content->updateSectionPayload($hero, ['title' => 'Hero']);
        $content->saveSectionDraft($hero->fresh(), ['is_enabled' => false]);

        $page = $page->fresh();

        try {
            app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, User::factory()->withRole('owner')->create());
        } catch (ValidationException) {
            // expected
        }

        Log::shouldHaveReceived('warning')->withArgs(fn (string $msg, array $ctx = []) => $msg === 'frontend.publish_failed'
            && ($ctx['entity'] ?? null) === 'page:servicios')->once();
    }
}
