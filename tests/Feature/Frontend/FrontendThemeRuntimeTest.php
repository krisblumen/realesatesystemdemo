<?php

namespace Tests\Feature\Frontend;

use App\Services\Frontend\FrontendCacheGeneration;
use App\Support\Frontend\ThemeContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * T-8c: the theme must actually reach the browser. Declaring `--nh-*` changes
 * nothing on its own — the public layout has to emit them into `:root`, and
 * app.css has to bridge them to semantic utilities so components resolve the
 * runtime value instead of a compiled constant.
 */
class FrontendThemeRuntimeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Writes straight to SQL to bypass every application guard — which also
     * bypasses the model observer, so nothing invalidates the cache. That is
     * the designed behaviour (§16.8: invalidation belongs to the observers),
     * so the test bumps the generation itself through the documented protocol.
     */
    private function persistTheme(array $theme): void
    {
        DB::table('frontend_settings')->updateOrInsert(
            ['singleton_key' => 'default'],
            ['site_name' => 'New Hauz', 'theme' => json_encode($theme), 'created_at' => now(), 'updated_at' => now()],
        );

        app(FrontendCacheGeneration::class)->bump();
    }

    public function test_the_home_page_emits_every_runtime_variable(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        foreach ([
            '--nh-primary', '--nh-on-primary', '--nh-accent', '--nh-on-accent',
            '--nh-bg', '--nh-text', '--nh-font-heading', '--nh-font-body',
            '--nh-radius-md', '--nh-radius-lg', '--nh-radius-xl',
        ] as $token) {
            $this->assertStringContainsString($token, $html, "Missing runtime token {$token}.");
        }

        // §16.5 forbids a singular radius token: only the expanded three exist.
        $this->assertStringNotContainsString('--nh-radius:', $html);
    }

    public function test_a_saved_palette_reaches_the_public_html(): void
    {
        $this->persistTheme(['primary' => '#123456', 'on_primary' => '#ffffff']);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('--nh-primary: #123456', $html);
    }

    public function test_each_radius_preset_expands_to_its_exact_three_values(): void
    {
        $expected = [
            'none' => ['2px', '2px', '4px'],
            'soft' => ['8px', '12px', '16px'],
            'medium' => ['12px', '16px', '24px'],
            'rounded' => ['16px', '24px', '32px'],
            'xl' => ['24px', '32px', '44px'],
        ];

        foreach ($expected as $preset => [$md, $lg, $xl]) {
            $this->persistTheme(['radius' => $preset]);

            $html = $this->get('/')->assertOk()->getContent();

            $this->assertStringContainsString("--nh-radius-md: {$md}", $html, "preset {$preset}");
            $this->assertStringContainsString("--nh-radius-lg: {$lg}", $html, "preset {$preset}");
            $this->assertStringContainsString("--nh-radius-xl: {$xl}", $html, "preset {$preset}");
        }
    }

    public function test_a_malicious_persisted_colour_never_reaches_the_html(): void
    {
        // T-8b end to end: the payload tries to close the <style> block.
        $this->persistTheme(['primary' => '#000}</style><script>alert(1)</script>']);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('alert(1)', $html);
        $this->assertStringContainsString('--nh-primary: #091a5b', $html, 'It must fall back to the default.');
    }

    public function test_the_brand_ink_served_for_a_hostile_light_theme_clears_aa(): void
    {
        // C-B2 end to end. This is the exact theme the audit used to reach
        // 1.16:1 (primary) and 1.25:1 (accent) as text over white. The ink
        // tokens the HTML actually serves must clear AA against the background.
        $this->persistTheme([
            'primary' => '#fef08a',
            'on_primary' => '#111111',
            'accent' => '#fde68a',
            'on_accent' => '#111111',
            'background' => '#ffffff',
            'text' => '#111111',
        ]);

        $html = $this->get('/')->assertOk()->getContent();

        foreach (['--nh-primary-ink', '--nh-accent-ink'] as $token) {
            preg_match('/'.preg_quote($token, '/').':\s*(#[0-9a-f]{6})/i', $html, $m);
            $this->assertNotEmpty($m, "The HTML must serve {$token}.");
            $this->assertTrue(
                ThemeContract::meetsAa($m[1], '#ffffff'),
                "{$token} = {$m[1]} is used as text over the background and must clear AA (C-B2)."
            );
        }

        // The raw pale brand colours must never be served as a text foreground.
        $this->assertStringNotContainsString('--nh-primary-ink: #fef08a', $html);
        $this->assertStringNotContainsString('--nh-accent-ink: #fde68a', $html);
    }

    public function test_app_css_bridges_the_runtime_tokens_to_semantic_utilities(): void
    {
        // Without this bridge the variables would be emitted and ignored:
        // components would still resolve compiled constants.
        $css = file_get_contents(resource_path('css/app.css'));

        foreach ([
            '--color-brand-primary: var(--nh-primary',
            '--color-on-brand-primary: var(--nh-on-primary',
            '--color-brand-accent: var(--nh-accent',
            '--color-on-brand-accent: var(--nh-on-accent',
            '--color-site-background: var(--nh-bg',
            '--color-site-text: var(--nh-text',
            '--color-brand-primary-ink: var(--nh-primary-ink',
            '--color-brand-accent-ink: var(--nh-accent-ink',
            '--font-brand-heading: var(--nh-font-heading',
            '--font-brand-body: var(--nh-font-body',
            '--radius-brand-md: var(--nh-radius-md',
            '--radius-brand-lg: var(--nh-radius-lg',
            '--radius-brand-xl: var(--nh-radius-xl',
        ] as $bridge) {
            $this->assertStringContainsString($bridge, $css, "Missing bridge: {$bridge}");
        }
    }
}
