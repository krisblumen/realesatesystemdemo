<?php

namespace Tests\Feature\Frontend;

use App\Models\FrontendService;
use App\Models\ServiceType;
use App\Services\Frontend\FrontendCacheGeneration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Home and the services page render eligible services from configuration
 * (RFC-074): the hardcoded arrays are gone, an unconfigured site is unchanged
 * (the backfill preserves it), and a disabled service disappears from its
 * location.
 */
class FrontendServicesRenderTest extends TestCase
{
    use RefreshDatabase;

    private function bump(): void
    {
        app(FrontendCacheGeneration::class)->bump();
    }

    public function test_home_renders_the_backfilled_services(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        foreach (['Arquitectura', 'Construcción', 'Comercialización', 'Inversión'] as $title) {
            $this->assertStringContainsString($title, $html);
        }
    }

    public function test_a_service_hidden_from_home_disappears_there(): void
    {
        FrontendService::query()->where('service_type_code', 'arquitectura')->update(['show_in_home' => false]);
        $this->bump();

        $home = $this->get('/')->assertOk()->getContent();
        // The services grid section no longer lists it (still on the services page).
        $section = substr($home, strpos($home, 'Cuatro disciplinas'), 2000);
        $this->assertStringNotContainsString('Arquitectura', $section);
    }

    public function test_an_inactive_type_removes_the_service_from_the_services_page(): void
    {
        ServiceType::query()->where('code', 'construccion')->update(['active' => false]);
        $this->bump();

        $html = $this->get('/servicios')->assertOk()->getContent();
        $section = substr($html, (int) strpos($html, 'space-y-20'));
        $this->assertStringNotContainsString('Construcción', $section);
    }

    public function test_an_info_only_service_renders_without_a_lead_cta(): void
    {
        // inversion: show_in_services true, allow_leads false → shown, no forced CTA.
        $html = $this->get('/servicios')->assertOk()->getContent();

        $this->assertStringContainsString('Inversión inmobiliaria', $html);
        $this->assertStringNotContainsString('leads.create'.'?service=inversion', $html);
    }
}
