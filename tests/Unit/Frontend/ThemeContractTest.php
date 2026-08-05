<?php

namespace Tests\Unit\Frontend;

use App\Support\Frontend\ThemeContract;
use PHPUnit\Framework\TestCase;

/**
 * Authoritative theme schema of §16.5: strict hex, font/radius enums and the
 * three WCAG AA contrast pairs. Pure logic, no database.
 *
 * The expected ratios below are the canonical WCAG values, so a regression in
 * the luminance formula fails loudly instead of silently letting an
 * unreadable palette through.
 */
class ThemeContractTest extends TestCase
{
    public function test_contrast_ratio_matches_the_canonical_wcag_values(): void
    {
        // Black on white is the maximum possible ratio: exactly 21:1.
        $this->assertSame(21.0, round(ThemeContract::contrastRatio('#000000', '#ffffff'), 2));

        // Identical colours have no contrast at all: 1:1.
        $this->assertSame(1.0, round(ThemeContract::contrastRatio('#7f7f7f', '#7f7f7f'), 2));

        // Order must not matter — the ratio is symmetric.
        $this->assertSame(
            round(ThemeContract::contrastRatio('#2e3842', '#ffffff'), 4),
            round(ThemeContract::contrastRatio('#ffffff', '#2e3842'), 4),
        );
    }

    public function test_the_project_defaults_pass_wcag_aa(): void
    {
        // The shipped fallbacks must satisfy the very rule we enforce on the
        // owner; otherwise the default site would be non-compliant.
        $this->assertTrue(ThemeContract::meetsAa('#ffffff', '#2e3842'), 'white on navy');
        $this->assertTrue(ThemeContract::meetsAa('#171d23', '#f5a624'), 'ink on orange');
        $this->assertTrue(ThemeContract::meetsAa('#171d23', '#f2f4f6'), 'ink on canvas');
    }

    public function test_low_contrast_pairs_are_rejected(): void
    {
        // Classic trap: white text on the brand accent looks fine in a mockup
        // and is unreadable in daylight.
        $this->assertFalse(ThemeContract::meetsAa('#ffffff', '#f5a624'));
        $this->assertFalse(ThemeContract::meetsAa('#cccccc', '#ffffff'));
    }

    public function test_hex_validation_is_strict(): void
    {
        $this->assertTrue(ThemeContract::isHex('#2e3842'));
        $this->assertTrue(ThemeContract::isHex('#FFFFFF'));

        // Shorthand, missing hash, wrong length and injection attempts all fail.
        $this->assertFalse(ThemeContract::isHex('#fff'));
        $this->assertFalse(ThemeContract::isHex('091a5b'));
        $this->assertFalse(ThemeContract::isHex('#2e3842b'));
        $this->assertFalse(ThemeContract::isHex('#000}</style><script>alert(1)</script>'));
        $this->assertFalse(ThemeContract::isHex(null));
    }

    public function test_font_and_radius_enums_are_closed(): void
    {
        $this->assertTrue(ThemeContract::isFont('Montserrat'));
        $this->assertTrue(ThemeContract::isFont('Inter'));

        // Poppins was retired (B-8): the runtime toggle must never name a font
        // that Vite did not compile.
        $this->assertFalse(ThemeContract::isFont('Poppins'));
        $this->assertFalse(ThemeContract::isFont('Comic Sans MS'));

        $this->assertTrue(ThemeContract::isRadius('soft'));
        $this->assertTrue(ThemeContract::isRadius('medium'));
        $this->assertTrue(ThemeContract::isRadius('rounded'));
        $this->assertFalse(ThemeContract::isRadius('sharp'));
    }

    public function test_radius_presets_expand_to_the_exact_documented_values(): void
    {
        // Closed table of §16.5 — no CSS arithmetic, no browser dependency.
        $this->assertSame(
            ['md' => '8px', 'lg' => '12px', 'xl' => '16px'],
            ThemeContract::expandRadius('soft'),
        );
        $this->assertSame(
            ['md' => '12px', 'lg' => '16px', 'xl' => '24px'],
            ThemeContract::expandRadius('medium'),
        );
        $this->assertSame(
            ['md' => '16px', 'lg' => '24px', 'xl' => '32px'],
            ThemeContract::expandRadius('rounded'),
        );

        // An unknown preset degrades to medium, which reproduces app.css today.
        $this->assertSame(
            ThemeContract::expandRadius('medium'),
            ThemeContract::expandRadius('nonsense'),
        );
    }
}
