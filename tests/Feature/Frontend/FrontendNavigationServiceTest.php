<?php

namespace Tests\Feature\Frontend;

use App\Services\Frontend\FrontendCacheGeneration;
use App\Services\Frontend\FrontendNavigationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * navigation() and footer() read models (RFC-073, §16.3).
 *
 * Like the theme service, this is the render half of a double boundary: the
 * form validates on save, but imports, manual SQL and legacy rows can hold
 * anything, so every stored link is re-checked here before it can reach Blade.
 * Writes go straight to SQL to exercise exactly that.
 */
class FrontendNavigationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function persist(array $columns): void
    {
        DB::table('frontend_settings')->updateOrInsert(
            ['singleton_key' => 'default'],
            array_merge(['site_name' => 'Landra', 'created_at' => now(), 'updated_at' => now()], array_map(
                fn ($v) => is_array($v) ? json_encode($v) : $v,
                $columns,
            )),
        );

        app(FrontendCacheGeneration::class)->bump();
    }

    private function nav(): array
    {
        return app(FrontendNavigationService::class)->navigation();
    }

    private function footer(): array
    {
        return app(FrontendNavigationService::class)->footer();
    }

    public function test_without_configuration_navigation_is_the_documented_fallback(): void
    {
        $links = $this->nav()['links'];

        $this->assertSame(
            ['home', 'nosotros', 'servicios', 'proyectos', 'inmuebles', 'inversionistas', 'contacto'],
            array_column($links, 'key'),
        );
        $this->assertSame('Inicio', $links[0]['label']);
        $this->assertSame(route('proyectos'), $links[3]['url']);
        $this->assertSame('inmuebles*', $links[4]['active_pattern']);
    }

    public function test_the_fallback_primary_cta_is_agenda_una_cita(): void
    {
        $primary = $this->nav()['ctas']['primary'];

        $this->assertSame('Agenda una cita', $primary['label']);
        $this->assertSame(route('leads.create'), $primary['url']);
        $this->assertNull($this->nav()['ctas']['secondary']);
    }

    public function test_configured_links_are_relabelled_reordered_and_filtered(): void
    {
        $this->persist(['navigation' => [
            ['key' => 'contacto', 'label' => 'Hablemos', 'enabled' => true, 'sort_order' => 1],
            ['key' => 'home', 'label' => 'Portada', 'enabled' => true, 'sort_order' => 0],
            ['key' => 'servicios', 'label' => 'Servicios', 'enabled' => false, 'sort_order' => 2],
        ]]);

        $links = $this->nav()['links'];

        // Order by sort_order, disabled dropped, labels honoured, url from key.
        $this->assertSame(['home', 'contacto'], array_column($links, 'key'));
        $this->assertSame('Portada', $links[0]['label']);
        $this->assertSame('Hablemos', $links[1]['label']);
    }

    public function test_an_unknown_or_repointed_key_cannot_enter_navigation(): void
    {
        // The owner may relabel, never repoint: a bogus key and any stray `url`
        // are ignored; the URL always derives from the allowlisted key.
        $this->persist(['navigation' => [
            ['key' => 'wp-admin', 'label' => 'Hack', 'enabled' => true, 'sort_order' => 0],
            ['key' => 'home', 'label' => 'Inicio', 'enabled' => true, 'sort_order' => 1, 'url' => 'https://evil.example'],
        ]]);

        $links = $this->nav()['links'];

        $this->assertSame(['home'], array_column($links, 'key'));
        $this->assertSame(url('/'), $links[0]['url']);
    }

    public function test_navigation_never_renders_empty(): void
    {
        // RFC-073: if every link ends up disabled, keep at least Inicio and
        // Contacto so the site stays navigable.
        $this->persist(['navigation' => [
            ['key' => 'home', 'label' => 'Inicio', 'enabled' => false, 'sort_order' => 0],
        ]]);

        $keys = array_column($this->nav()['links'], 'key');

        $this->assertContains('home', $keys);
        $this->assertContains('contacto', $keys);
    }

    public function test_each_nav_link_carries_the_full_schema_with_open_in_new_tab_forced_false(): void
    {
        // RFC-073 / T-13h: the DTO shape is {key,label,url,active_pattern,
        // sort_order,open_in_new_tab}, and v1 forces open_in_new_tab false even
        // if a true was persisted (no external nav destinations exist).
        $this->persist(['navigation' => [
            ['key' => 'home', 'label' => 'Inicio', 'enabled' => true, 'sort_order' => 0, 'open_in_new_tab' => true],
        ]]);

        $link = $this->nav()['links'][0];

        $this->assertSame(
            ['key', 'label', 'url', 'active_pattern', 'sort_order', 'open_in_new_tab'],
            array_keys($link),
        );
        $this->assertFalse($link['open_in_new_tab'], 'A persisted true must be normalized to false.');
    }

    public function test_a_configured_primary_cta_resolves_through_the_resolver(): void
    {
        $this->persist(['primary_cta' => ['label' => 'Ver proyectos', 'type' => 'route', 'target' => 'proyectos']]);

        $this->assertSame('Ver proyectos', $this->nav()['ctas']['primary']['label']);
        $this->assertSame(route('proyectos'), $this->nav()['ctas']['primary']['url']);
    }

    public function test_an_invalid_primary_cta_falls_back_instead_of_disappearing(): void
    {
        $this->persist(['primary_cta' => ['label' => 'Evil', 'type' => 'url', 'target' => 'javascript:alert(1)']]);

        $primary = $this->nav()['ctas']['primary'];

        $this->assertSame('Agenda una cita', $primary['label']);
        $this->assertSame(route('leads.create'), $primary['url']);
    }

    public function test_footer_omits_disabled_and_invalid_links_but_keeps_the_rest(): void
    {
        $this->persist(['footer' => [
            'columns' => [[
                'title' => 'Enlaces',
                'links' => [
                    ['label' => 'Nosotros', 'type' => 'route', 'target' => 'nosotros', 'enabled' => true],
                    ['label' => 'Oculto', 'type' => 'route', 'target' => 'contacto', 'enabled' => false],
                    ['label' => 'Roto', 'type' => 'url', 'target' => 'javascript:alert(1)', 'enabled' => true],
                ],
            ]],
            'legal_text' => '© Landra',
        ]]);

        $links = $this->footer()['columns'][0]['links'];
        $labels = array_column($links, 'label');

        // Valid enabled link stays; the disabled one is exposed as disabled;
        // the unsafe one is dropped entirely (never reaches Blade).
        $this->assertContains('Nosotros', $labels);
        $this->assertNotContains('Roto', $labels);

        $disabled = collect($links)->firstWhere('label', 'Oculto');
        $this->assertNotNull($disabled, 'A disabled link stays in footer() with enabled=false for the editor.');
        $this->assertFalse($disabled['enabled']);
    }

    public function test_footer_without_configuration_has_no_hash_destinations(): void
    {
        // RFC-073: a `#` is never a valid final destination — the fallback uses
        // real routes or hides the link.
        foreach ($this->footer()['columns'] as $column) {
            foreach ($column['links'] as $link) {
                $this->assertNotSame('#', $link['url']);
                $this->assertNotEmpty($link['url']);
            }
        }
    }
}
