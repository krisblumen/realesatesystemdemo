<?php

namespace Tests\Feature\Frontend;

use App\Models\FrontendPage;
use App\Models\Property;
use App\Models\User;
use App\Services\Frontend\FrontendPageContentService;
use App\Services\Frontend\FrontendPagePublisher;
use App\Services\Frontend\FrontendPageRenderer;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public render cutover (RFC-076): every institutional route reads its page
 * through page(key). Until the owner publishes, the route serves the original
 * hardcoded fallback unchanged (§16.7 REGLA DE ORO). Once published, the route
 * renders the CMS snapshot instead — a hero title set in the CMS reaches the DOM.
 */
class FrontendRenderFallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    private function publishServiciosHero(string $title): void
    {
        $content = app(FrontendPageContentService::class);
        $page = FrontendPage::query()->where('key', 'servicios')->firstOrFail();
        $hero = $page->sections()->where('section_key', 'hero')->firstOrFail();

        $content->updateSectionPayload($hero, ['title' => $title]);

        $page = $page->fresh();
        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, User::factory()->withRole('owner')->create());
    }

    public function test_unpublished_route_serves_the_hardcoded_fallback(): void
    {
        // The servicios page ships with this hero copy hardcoded in the Blade.
        $this->get('/servicios')
            ->assertOk()
            ->assertSee('Del terreno a la entrega de llaves.');
    }

    public function test_published_route_renders_the_cms_hero_title(): void
    {
        $this->publishServiciosHero('SERVICIOS-DESDE-EL-CMS');

        $this->get('/servicios')
            ->assertOk()
            ->assertSee('SERVICIOS-DESDE-EL-CMS')
            ->assertDontSee('Del terreno a la entrega de llaves.');
    }

    public function test_dynamic_sections_hand_arrays_not_eloquent_models_to_blade(): void
    {
        // Mn-F1: the presenter normalizes Property/Project to view-ready arrays;
        // the Blade partials never touch a live model.
        Property::factory()->published()->create(['is_featured' => true]);

        $content = app(FrontendPageContentService::class);
        $page = FrontendPage::query()->where('key', 'home')->firstOrFail();
        $section = $page->sections()->where('section_key', 'featured_properties')->firstOrFail();
        $content->updateSectionPayload($section, ['title' => 'Destacados']);

        $page = $page->fresh();
        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, User::factory()->withRole('owner')->create());

        $rendered = app(FrontendPageRenderer::class)->render('home');
        $featured = collect($rendered['sections'])->firstWhere('key', 'featured_properties');

        $this->assertNotNull($featured);
        $this->assertNotEmpty($featured['data']['items']);
        foreach ($featured['data']['items'] as $item) {
            $this->assertIsArray($item, 'A dynamic item must be a normalized array, not an Eloquent model.');
            $this->assertArrayHasKey('href', $item);
        }
    }

    public function test_a_disabled_section_is_not_rendered(): void
    {
        $content = app(FrontendPageContentService::class);
        $page = FrontendPage::query()->where('key', 'servicios')->firstOrFail();

        // The hero stays enabled (preflight requires the H1); a DIFFERENT section
        // is disabled to prove a disabled section is omitted from the render.
        $hero = $page->sections()->where('section_key', 'hero')->firstOrFail();
        $content->updateSectionPayload($hero, ['title' => 'Servicios visibles']);

        $cta = $page->sections()->where('section_key', 'final_cta')->firstOrFail();
        $content->updateSectionPayload($cta, ['title' => 'CTA-OCULTO']);
        $content->saveSectionDraft($cta->fresh(), ['is_enabled' => false]);

        $page = $page->fresh();
        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, User::factory()->withRole('owner')->create());

        $this->get('/servicios')
            ->assertOk()
            ->assertSee('Servicios visibles')
            ->assertDontSee('CTA-OCULTO');
    }
}
