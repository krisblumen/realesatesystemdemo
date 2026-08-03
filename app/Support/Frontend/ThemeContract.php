<?php

namespace App\Support\Frontend;

/**
 * Authoritative schema of `frontend_settings.theme` (§16.5).
 *
 * Single source for both boundaries: Filament validates with it on save, and
 * the render service re-normalizes with it. That is deliberate — the form is
 * not the only writer, so anything the render emits must be re-checked here
 * before reaching a `<style>` block.
 *
 * Colours are `#rrggbb` only. That strictness is also the XSS defence: a value
 * like `#000}</style><script>` cannot pass the regex, so it can never break
 * out of the style tag.
 */
class ThemeContract
{
    /**
     * Fonts the owner can choose. Poppins was retired (B-8).
     *
     * Two kinds live in the same list on purpose — para quien elige son lo
     * mismo, un nombre de tipografía:
     *
     *   descargadas  las que compila Vite con `bunny()` (vite.config.js). Si se
     *                agrega una acá SIN agregarla allá, el sitio cae al fallback
     *                y el owner elige algo que nunca ve.
     *   del sistema  Arial y Georgia. No pesan nada porque ya están en la
     *                máquina de quien visita, y por eso valen la pena aunque no
     *                sean tipografías de marca.
     */
    public const FONTS = [
        'Montserrat', 'Inter', 'Playfair Display', 'Lora', 'Space Grotesk', 'Caveat',
        'Arial', 'Georgia',
    ];

    /** Las de FONTS que NO se descargan: ya están en el sistema. */
    public const SYSTEM_FONTS = ['Arial', 'Georgia'];

    public const RADII = ['none', 'soft', 'medium', 'rounded', 'xl'];

    /** WCAG AA for normal text. */
    public const MIN_CONTRAST = 4.5;

    /**
     * WCAG minimum for non-text UI such as focus rings and outlines
     * (RFC-072:137). Lower than text on purpose: a 3:1 ring is perceivable
     * without forcing the owner into a narrow palette.
     */
    public const MIN_FOCUS_CONTRAST = 3.0;

    /** Fallbacks mirror resources/css/app.css. */
    public const DEFAULTS = [
        'primary' => '#091a5b',
        'on_primary' => '#ffffff',
        'accent' => '#f6a300',
        'on_accent' => '#111111',
        'background' => '#f7f7f7',
        'text' => '#111111',
        'heading_font' => 'Montserrat',
        'body_font' => 'Inter',
        // El antetítulo tiene tipografía PROPIA. Antes heredaba la de títulos
        // por accidente —`.eyebrow` apuntaba al token de display en vez de al
        // del tema—, así que cambiar la de títulos se lo llevaba puesto.
        'eyebrow_font' => 'Montserrat',
        // El peso por defecto de cada uno. Es lo que se ve hoy: los títulos en
        // negrita y el antetítulo en semibold, que acá es «no negrita».
        'heading_bold' => true,
        'eyebrow_bold' => false,
        'radius' => 'medium',
    ];

    /**
     * Closed expansion table of §16.5: the stored preset is never emitted as a
     * single variable, and the three values are fixed server-side so no CSS
     * arithmetic depends on the browser.
     *
     * `medium` reproduces the current tokens of app.css and is the fallback.
     */
    public const RADIUS_SCALE = [
        'none' => ['md' => '2px', 'lg' => '2px', 'xl' => '4px'],
        'soft' => ['md' => '8px', 'lg' => '12px', 'xl' => '16px'],
        'medium' => ['md' => '12px', 'lg' => '16px', 'xl' => '24px'],
        'rounded' => ['md' => '16px', 'lg' => '24px', 'xl' => '32px'],
        'xl' => ['md' => '24px', 'lg' => '32px', 'xl' => '44px'],
    ];

    /** Pairs that must meet AA: [foreground, background]. */
    public const CONTRAST_PAIRS = [
        ['on_primary', 'primary'],
        ['on_accent', 'accent'],
        ['text', 'background'],
    ];

    public static function isHex(?string $value): bool
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1;
    }

    public static function isFont(?string $value): bool
    {
        return in_array($value, self::FONTS, true);
    }

    public static function isRadius(?string $value): bool
    {
        return in_array($value, self::RADII, true);
    }

    /**
     * @return array{md: string, lg: string, xl: string}
     */
    public static function expandRadius(?string $preset): array
    {
        return self::RADIUS_SCALE[$preset] ?? self::RADIUS_SCALE['medium'];
    }

    /**
     * WCAG 2.1 contrast ratio: (L1 + 0.05) / (L2 + 0.05), lighter over darker.
     * Symmetric by construction.
     */
    public static function contrastRatio(string $a, string $b): float
    {
        $la = self::relativeLuminance($a);
        $lb = self::relativeLuminance($b);

        $lighter = max($la, $lb);
        $darker = min($la, $lb);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    public static function meetsAa(string $foreground, string $background): bool
    {
        if (! self::isHex($foreground) || ! self::isHex($background)) {
            return false;
        }

        return self::contrastRatio($foreground, $background) >= self::MIN_CONTRAST;
    }

    /** WCAG relative luminance, with the sRGB gamma expansion. */
    private static function relativeLuminance(string $hex): float
    {
        [$r, $g, $b] = array_map(
            static fn (string $channel): float => self::linearize(hexdec($channel) / 255),
            str_split(ltrim($hex, '#'), 2),
        );

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    private static function linearize(float $channel): float
    {
        return $channel <= 0.03928
            ? $channel / 12.92
            : (($channel + 0.055) / 1.055) ** 2.4;
    }
}
