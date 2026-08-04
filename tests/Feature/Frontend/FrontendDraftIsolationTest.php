<?php

namespace Tests\Feature\Frontend;

use App\Models\FrontendPage;
use App\Models\User;
use App\Services\Frontend\FrontendPageContentService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Draft isolation (RFC-077, Lote G): a draft edit that has NOT been published
 * must never reach the public routes, but the owner preview must show it. The
 * public site reads only the published snapshot; the preview reads the working
 * draft. They cannot leak into each other.
 */
class FrontendDraftIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_an_unpublished_draft_edit_shows_in_preview_but_not_in_public(): void
    {
        $content = app(FrontendPageContentService::class);
        $hero = FrontendPage::query()->where('key', 'servicios')->firstOrFail()
            ->sections()->where('section_key', 'hero')->firstOrFail();

        // Edit the draft — NO publish.
        $content->updateSectionPayload($hero, ['title' => 'BORRADOR-SIN-PUBLICAR']);

        // The public route must still serve the hardcoded fallback, never the draft.
        $this->get('/servicios')
            ->assertOk()
            ->assertSee('Del terreno a la entrega de llaves.')
            ->assertDontSee('BORRADOR-SIN-PUBLICAR');

        // The owner preview shows the working draft.
        $owner = User::factory()->withRole('owner')->create();
        $this->actingAs($owner)->get('/admin/frontend/preview/servicios')
            ->assertOk()
            ->assertSee('BORRADOR-SIN-PUBLICAR');
    }

    public function test_the_preview_carries_the_draft_seo_and_flags_a_disabled_page(): void
    {
        // M-G-2: the preview reflects the COMPLETE working state — the draft SEO
        // title and the disabled flag — so it matches what a publish would emit.
        $page = FrontendPage::query()->where('key', 'servicios')->firstOrFail();
        $page->forceFill(['is_enabled' => false, 'seo' => ['meta_title' => 'SEO-DRAFT-PREVIEW']])->save();

        $owner = User::factory()->withRole('owner')->create();
        $html = $this->actingAs($owner)->get('/admin/frontend/preview/servicios')
            ->assertOk()->getContent();

        // The draft SEO reaches the preview <title>, and the disabled note shows.
        $this->assertStringContainsString('<title>SEO-DRAFT-PREVIEW</title>', $html);
        $this->assertStringContainsString('deshabilitada', $html);
    }
}
