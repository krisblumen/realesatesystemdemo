<?php

namespace Tests\Feature\Frontend;

use App\Models\FrontendSetting;
use App\Services\Frontend\FrontendCacheGeneration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public layout consumes the centralized navigation (RFC-073): header and
 * mobile drawer share one source, an unconfigured site is unchanged, and a
 * relabel/reorder/disable is honoured in the rendered HTML — the layout can no
 * longer satisfy the contract away with its old hardcoded array.
 *
 * Assertions scope to the two <nav> blocks so footer labels (which legitimately
 * repeat page names) never mask a navigation regression.
 */
class FrontendNavigationRenderTest extends TestCase
{
    use RefreshDatabase;

    private function configure(array $columns): void
    {
        $setting = FrontendSetting::current();
        foreach ($columns as $key => $value) {
            $setting->{$key} = $value;
        }
        $setting->save();

        app(FrontendCacheGeneration::class)->bump();
    }

    /** The desktop + mobile <nav> regions, where the nav contract lives. */
    private function navRegions(string $html): string
    {
        preg_match_all('/<nav\b[^>]*aria-label="Navegaci[^"]*"[^>]*>(.*?)<\/nav>/s', $html, $m);

        return implode("\n", $m[1]);
    }

    public function test_an_unconfigured_site_renders_the_current_navigation(): void
    {
        $nav = $this->navRegions($this->get('/')->assertOk()->getContent());

        foreach (['Inicio', 'Nosotros', 'Servicios', 'Proyectos', 'Inmobiliaria', 'Inversionistas', 'Contacto'] as $label) {
            $this->assertStringContainsString($label, $nav, "The fallback nav must keep `{$label}`.");
        }
    }

    public function test_the_same_nav_appears_for_desktop_and_mobile(): void
    {
        // Both menus read $navigation['links']: a relabel shows up in both nav
        // regions, proving there is no second hardcoded source.
        $this->configure(['navigation' => [
            ['key' => 'home', 'label' => 'Portada', 'enabled' => true, 'sort_order' => 0],
            ['key' => 'contacto', 'label' => 'Hablemos', 'enabled' => true, 'sort_order' => 1],
        ]]);

        $nav = $this->navRegions($this->get('/')->assertOk()->getContent());

        $this->assertSame(2, substr_count($nav, 'Portada'), 'Header and drawer must both render the label.');
        $this->assertStringContainsString('Hablemos', $nav);
        $this->assertStringNotContainsString('Nosotros', $nav, 'A page left out of the config must not appear in nav.');
    }

    public function test_a_disabled_link_disappears_from_the_rendered_nav(): void
    {
        $this->configure(['navigation' => [
            ['key' => 'home', 'label' => 'Inicio', 'enabled' => true, 'sort_order' => 0],
            ['key' => 'servicios', 'label' => 'Servicios', 'enabled' => false, 'sort_order' => 1],
        ]]);

        $nav = $this->navRegions($this->get('/')->assertOk()->getContent());

        $this->assertStringContainsString('Inicio', $nav);
        $this->assertStringNotContainsString('Servicios', $nav);
    }

    public function test_a_configured_primary_cta_reaches_the_menus(): void
    {
        $this->configure(['primary_cta' => ['label' => 'Conócenos', 'type' => 'route', 'target' => 'nosotros']]);

        $html = $this->get('/')->assertOk()->getContent();

        // The header CTA and the drawer CTA both resolve the configured value.
        $this->assertGreaterThanOrEqual(2, substr_count($html, 'Conócenos'));
        $this->assertStringContainsString('href="'.route('nosotros').'"', $html);
    }
}
