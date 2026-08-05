<?php

namespace Tests\Feature\Frontend;

use App\Services\Frontend\FrontendCacheGeneration;
use App\Services\Frontend\FrontendThemeService;
use App\Support\Frontend\ThemeContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * §16.5 double boundary: hard validation on save AND defensive normalization at
 * render. This class covers the render half — everything here writes straight
 * to SQL to bypass Filament, because the form is not the only writer (imports,
 * manual fixes, legacy rows, future bugs).
 *
 * T-8b lives here: a malicious persisted colour must never reach the HTML.
 */
class FrontendThemeServiceTest extends TestCase
{
    use RefreshDatabase;

    private function persistTheme(array $theme): void
    {
        DB::table('frontend_settings')->updateOrInsert(
            ['singleton_key' => 'default'],
            ['site_name' => 'Landra', 'theme' => json_encode($theme), 'created_at' => now(), 'updated_at' => now()],
        );
    }

    public function test_without_configuration_it_returns_the_documented_defaults(): void
    {
        $theme = app(FrontendThemeService::class)->theme();

        $this->assertSame('#2e3842', $theme['primary']);
        $this->assertSame('#ffffff', $theme['on_primary']);
        $this->assertSame('#f5a624', $theme['accent']);
        $this->assertSame('#171d23', $theme['on_accent']);
        $this->assertSame('#f2f4f6', $theme['background']);
        $this->assertSame('#171d23', $theme['text']);
        $this->assertSame('Montserrat', $theme['heading_font']);
        $this->assertSame('Inter', $theme['body_font']);
    }

    public function test_valid_persisted_values_are_respected(): void
    {
        $this->persistTheme([
            'primary' => '#123456',
            'on_primary' => '#ffffff',
            'heading_font' => 'Inter',
            'radius' => 'rounded',
        ]);

        $theme = app(FrontendThemeService::class)->theme();

        $this->assertSame('#123456', $theme['primary']);
        $this->assertSame('Inter', $theme['heading_font']);
        $this->assertSame(['md' => '16px', 'lg' => '24px', 'xl' => '32px'], $theme['radius_scale']);
    }

    public function test_a_malicious_persisted_colour_is_discarded_at_the_render_boundary(): void
    {
        // T-8b: the payload tries to close the <style> block and inject a script.
        $this->persistTheme(['primary' => '#000}</style><script>alert(1)</script>']);

        $theme = app(FrontendThemeService::class)->theme();

        $this->assertSame('#2e3842', $theme['primary'], 'An invalid colour must fall back.');
        $this->assertStringNotContainsString('<script>', json_encode($theme));
        $this->assertStringNotContainsString('</style>', json_encode($theme));
    }

    public function test_each_invalid_field_falls_back_independently(): void
    {
        // A single bad value must not discard the whole theme: the owner keeps
        // whatever is still valid.
        $this->persistTheme([
            'primary' => '#123456',
            'accent' => 'rgb(255,0,0)',
            'heading_font' => 'Poppins',
            'radius' => 'sharp',
        ]);

        $theme = app(FrontendThemeService::class)->theme();

        $this->assertSame('#123456', $theme['primary'], 'The valid value survives.');
        $this->assertSame('#f5a624', $theme['accent'], 'Non-hex falls back.');
        $this->assertSame('Montserrat', $theme['heading_font'], 'A font outside the allowlist falls back.');
        $this->assertSame(ThemeContract::expandRadius('medium'), $theme['radius_scale']);
    }

    public function test_a_persisted_pair_below_aa_falls_back_to_a_readable_one(): void
    {
        // White on the brand accent is the classic unreadable CTA. If it ever
        // reaches the database, the render must not publish it.
        $this->persistTheme(['accent' => '#f5a624', 'on_accent' => '#ffffff']);

        $theme = app(FrontendThemeService::class)->theme();

        $this->assertTrue(
            ThemeContract::meetsAa($theme['on_accent'], $theme['accent']),
            'The rendered pair must always meet AA, whatever is stored.'
        );
    }

    public function test_the_owner_override_keeps_a_low_contrast_pair_at_render(): void
    {
        // With the explicit owner override, the render must NOT revert the pair:
        // the brand colour (orange text on white) reaches the site as chosen.
        $this->persistTheme(['background' => '#ffffff', 'text' => '#ff9100', 'allow_low_contrast' => true]);

        $theme = app(FrontendThemeService::class)->theme();

        $this->assertSame('#ff9100', $theme['text'], 'The override keeps the owner\'s low-contrast colour.');
        $this->assertFalse(ThemeContract::meetsAa($theme['text'], $theme['background']));
    }

    public function test_without_the_override_the_low_contrast_pair_is_still_reverted(): void
    {
        // Default (no flag): the safety revert stays in force.
        $this->persistTheme(['background' => '#ffffff', 'text' => '#ff9100']);

        $theme = app(FrontendThemeService::class)->theme();

        $this->assertNotSame('#ff9100', $theme['text'], 'Without the override the unreadable text is reverted.');
        $this->assertTrue(ThemeContract::meetsAa($theme['text'], $theme['background']));
    }

    public function test_css_variables_carry_the_expanded_radius_and_no_singular_token(): void
    {
        $this->persistTheme(['radius' => 'soft']);

        $variables = app(FrontendThemeService::class)->cssVariables();

        $this->assertSame('8px', $variables['--nh-radius-md']);
        $this->assertSame('12px', $variables['--nh-radius-lg']);
        $this->assertSame('16px', $variables['--nh-radius-xl']);
        $this->assertArrayNotHasKey('--nh-radius', $variables, '§16.5 forbids a singular radius token.');

        foreach (['--nh-primary', '--nh-on-primary', '--nh-accent', '--nh-on-accent', '--nh-bg', '--nh-text', '--nh-font-heading', '--nh-font-body'] as $token) {
            $this->assertArrayHasKey($token, $variables);
        }
    }

    public function test_the_focus_ring_always_clears_the_three_to_one_minimum(): void
    {
        // RFC-072:137. A pale accent on a pale background would make the ring
        // invisible, so the service must not simply hand the accent through.
        $this->persistTheme([
            'accent' => '#fff7e8',
            'on_accent' => '#171d23',
            'background' => '#ffffff',
            'text' => '#171d23',
        ]);

        $theme = app(FrontendThemeService::class)->theme();

        $this->assertNotSame('#fff7e8', $theme['focus'], 'A 1.1:1 accent cannot be the focus ring.');
        $this->assertGreaterThanOrEqual(
            ThemeContract::MIN_FOCUS_CONTRAST,
            ThemeContract::contrastRatio($theme['focus'], $theme['background']),
        );
    }

    public function test_a_contrasting_accent_is_used_as_the_focus_ring(): void
    {
        $this->persistTheme([
            'accent' => '#be123c',
            'on_accent' => '#ffffff',
            'background' => '#ffffff',
        ]);

        $this->assertSame('#be123c', app(FrontendThemeService::class)->theme()['focus']);
    }

    public function test_the_focus_variable_is_emitted(): void
    {
        $this->assertArrayHasKey('--nh-focus', app(FrontendThemeService::class)->cssVariables());
    }

    public function test_brand_ink_stays_the_brand_colour_when_it_reads_on_the_background(): void
    {
        // A dark brand primary over the light default background is legible, so
        // headings keep the brand colour — the theme is not flattened to black.
        $this->persistTheme(['primary' => '#2e3842', 'accent' => '#8a5a00', 'background' => '#ffffff']);

        $theme = app(FrontendThemeService::class)->theme();

        $this->assertSame('#2e3842', $theme['primary_ink'], 'A legible primary is kept as ink.');
        $this->assertSame('#8a5a00', $theme['accent_ink'], 'A legible accent is kept as ink.');
    }

    public function test_brand_ink_degrades_when_the_brand_colour_is_illegible_on_the_background(): void
    {
        // C-B2: a pale primary/accent used as TEXT over a white background is
        // the audit's 1.16:1 / 1.25:1 case. `primary`/`accent` are valid as
        // surfaces, so the palette is not narrowed; the FOREGROUND role degrades
        // to a colour the contract already guarantees over the background.
        $this->persistTheme([
            'primary' => '#fef08a',
            'on_primary' => '#171d23',
            'accent' => '#fde68a',
            'on_accent' => '#171d23',
            'background' => '#ffffff',
            'text' => '#171d23',
        ]);

        $theme = app(FrontendThemeService::class)->theme();

        $this->assertNotSame('#fef08a', $theme['primary_ink'], 'A 1.16:1 primary cannot be body text.');
        $this->assertNotSame('#fde68a', $theme['accent_ink'], 'A 1.25:1 accent cannot be body text.');
        $this->assertTrue(ThemeContract::meetsAa($theme['primary_ink'], $theme['background']));
        $this->assertTrue(ThemeContract::meetsAa($theme['accent_ink'], $theme['background']));
    }

    public function test_the_ink_variables_are_emitted(): void
    {
        $variables = app(FrontendThemeService::class)->cssVariables();

        $this->assertArrayHasKey('--nh-primary-ink', $variables);
        $this->assertArrayHasKey('--nh-accent-ink', $variables);
    }

    public function test_a_cache_entry_from_a_previous_release_cannot_break_the_site(): void
    {
        // Found by looking at the browser, not by a test: after a deploy that
        // adds a key to the cached array, entries written by the OLD release
        // are still warm. Reading one used to throw "Undefined array key" and
        // 500 the public site. The key carries a shape version so a changed
        // structure simply misses the cache instead of exploding.
        config(['cache.default' => 'array']);

        $generation = app(FrontendCacheGeneration::class)->current();

        // Simulate the previous release: same generation, old shape, no version.
        Cache::put(
            sprintf('frontend:g%d:theme', $generation),
            ['primary' => '#123456'],
            300,
        );

        $theme = app(FrontendThemeService::class)->theme();

        $this->assertArrayHasKey('focus', $theme);
        $this->assertArrayHasKey('accent_on_primary', $theme);
        $this->assertArrayHasKey('radius_scale', $theme);
    }
}
