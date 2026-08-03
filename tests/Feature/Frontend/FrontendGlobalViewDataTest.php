<?php

namespace Tests\Feature\Frontend;

use App\Models\FrontendPage;
use App\Models\FrontendSetting;
use App\Models\User;
use App\Services\Frontend\FrontendCacheGeneration;
use App\Services\Frontend\FrontendPageContentService;
use App\Services\Frontend\FrontendPagePublisher;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every public page is wrapped by the layout, which exposes the global kernel
 * data (RFC-076): the site name in structured data, a route-derived canonical,
 * and the JSON-LD Organization/WebSite block — all served from settings() with
 * the shipped fallbacks, so a fresh install still renders complete SEO.
 */
class FrontendGlobalViewDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    /** Publish the servicios page carrying a given seo snapshot. */
    private function publishServiciosWithSeo(array $seo): void
    {
        $content = app(FrontendPageContentService::class);
        $page = FrontendPage::query()->where('key', 'servicios')->firstOrFail();
        $hero = $page->sections()->where('section_key', 'hero')->firstOrFail();
        $content->updateSectionPayload($hero, ['title' => 'Servicios']);

        $page = $page->fresh();
        $page->forceFill(['seo' => $seo])->save();
        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, User::factory()->withRole('owner')->create());
    }

    public function test_published_page_seo_reaches_the_head(): void
    {
        $this->publishServiciosWithSeo([
            'meta_title' => 'SEO-F-CUSTOM',
            'meta_description' => 'DESCRIPCION-F-CUSTOM',
            'og_title' => 'OG-F-CUSTOM',
        ]);

        $html = $this->get('/servicios')->assertOk()->getContent();

        $this->assertStringContainsString('<title>SEO-F-CUSTOM</title>', $html);
        $this->assertStringContainsString('DESCRIPCION-F-CUSTOM', $html);
        $this->assertStringContainsString('content="OG-F-CUSTOM"', $html);
    }

    public function test_unpublished_page_falls_back_to_the_static_title(): void
    {
        // No publish ⇒ the per-page prop title is used, never a blank title.
        $html = $this->get('/servicios')->assertOk()->getContent();

        $this->assertStringContainsString('<title>Servicios · New Hauz</title>', $html);
    }

    public function test_the_layout_emits_canonical_and_structured_data(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // Route-derived canonical, no query string.
        $this->assertStringContainsString('<link rel="canonical"', $html);

        // JSON-LD Organization + WebSite, built from settings() (fallback name).
        $this->assertStringContainsString('application/ld+json', $html);
        $this->assertStringContainsString('"@type":"Organization"', $html);
        $this->assertStringContainsString('"@type":"WebSite"', $html);
        $this->assertStringContainsString('New Hauz', $html);
    }

    public function test_the_canonical_of_an_inner_page_derives_from_its_path(): void
    {
        $html = $this->get('/nosotros')->assertOk()->getContent();

        $this->assertStringContainsString(url('/nosotros'), $html);
    }

    public function test_the_layout_consumes_the_profile_dto_for_contact_and_whatsapp(): void
    {
        // M-F3: editing the profile must reach every public surface, not only
        // the JSON-LD. Custom contact data appears in the footer and the
        // floating WhatsApp href.
        FrontendSetting::current()->update([
            'public_phone' => '+52 442 999 88 77',
            'whatsapp_phone' => '524429998877',
            'public_address' => 'DIRECCION-F-LIVE',
        ]);
        app(FrontendCacheGeneration::class)->bump();

        $html = $this->get('/nosotros')->assertOk()->getContent();

        $this->assertStringContainsString('DIRECCION-F-LIVE', $html);
        $this->assertStringContainsString('+52 442 999 88 77', $html);
        $this->assertStringContainsString('https://wa.me/524429998877', $html);
    }

    public function test_without_configuration_the_layout_keeps_the_shipped_fallbacks(): void
    {
        // No custom profile ⇒ the exact hardcoded fallbacks must survive.
        $html = $this->get('/nosotros')->assertOk()->getContent();

        $this->assertStringContainsString('Alamos Querétaro Qro.', $html);
        $this->assertStringContainsString('+52 442 272 26 23', $html);
    }
}
