<?php

namespace App\Services\Frontend;

use App\Models\FrontendSetting;
use App\Support\Frontend\ThemeContract;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Runtime theme read model (§16.5), second half of the double boundary.
 *
 * Filament validates hard on save, but the form is not the only writer: an
 * import, a manual fix or a legacy row can hold anything. So EVERY value is
 * re-checked here before it can reach a `<style>` block — field by field, so a
 * single bad colour does not discard the whole theme.
 *
 * The strict `#rrggbb` regex is also the XSS defence: a payload trying to close
 * the style tag simply fails validation and never gets emitted.
 *
 * The stored radius preset is expanded server-side into three fixed values;
 * there is no singular `--nh-radius` in the emitted contract.
 */
class FrontendThemeService
{
    private const TTL_SECONDS = 300;

    /**
     * Shape version of the cached array. The generation counter invalidates on
     * DATA changes, but a deploy can change the SHAPE — adding a key like
     * `accent_on_primary` — while entries cached by the previous release are
     * still warm. Reading those would blow up on a missing key, taking the
     * public site down right after a deploy.
     *
     * Bump this whenever the structure returned by build() changes.
     */
    private const SHAPE = 3;

    public function __construct(private readonly FrontendCacheGeneration $generation) {}

    /**
     * @return array<string, mixed> validated theme plus the expanded radius scale
     */
    public function theme(): array
    {
        $key = sprintf('frontend:g%d:theme:v%d', $this->generation->current(), self::SHAPE);

        try {
            $cached = Cache::get($key);
        } catch (Throwable $e) {
            $this->reportCacheFailure($key, $e);
            $cached = null;
        }

        if (is_array($cached)) {
            return $cached;
        }

        // Outside any cache try/catch: a real error here must surface, not be
        // mistaken for a store failure (same rule as FrontendSettingsService).
        $theme = $this->build();

        try {
            Cache::put($key, $theme, self::TTL_SECONDS);
        } catch (Throwable $e) {
            $this->reportCacheFailure($key, $e);
        }

        return $theme;
    }

    /**
     * The `--nh-*` custom properties injected into `:root` by the public layout.
     *
     * @return array<string, string>
     */
    public function cssVariables(): array
    {
        $theme = $this->theme();

        return [
            '--nh-primary' => $theme['primary'],
            '--nh-on-primary' => $theme['on_primary'],
            '--nh-accent' => $theme['accent'],
            '--nh-on-accent' => $theme['on_accent'],
            '--nh-bg' => $theme['background'],
            '--nh-text' => $theme['text'],
            // SIN comillas, aunque haya familias con espacio («Playfair Display»,
            // «Space Grotesk»). El layout emite estos valores con `{{ }}`, que
            // escapa la comilla simple a `&#039;` y deja el `font-family` entero
            // inválido: el navegador descarta la declaración y el sitio cae a la
            // tipografía heredada. CSS acepta el nombre como secuencia de
            // identificadores, y la lista cerrada garantiza que no entre nada más.
            '--nh-font-heading' => $theme['heading_font'],
            '--nh-font-body' => $theme['body_font'],
            '--nh-font-eyebrow' => $theme['eyebrow_font'],
            // El peso viaja como número y no como clase: es UN default que el
            // sitio entero hereda, y una sección puede pisarlo con su propia
            // clase sin que esto tenga que saberlo.
            '--nh-weight-heading' => $theme['heading_bold'] ? '700' : '400',
            '--nh-weight-eyebrow' => $theme['eyebrow_bold'] ? '700' : '600',
            '--nh-focus' => $theme['focus'],
            '--nh-accent-on-primary' => $theme['accent_on_primary'],
            '--nh-primary-ink' => $theme['primary_ink'],
            '--nh-accent-ink' => $theme['accent_ink'],
            '--nh-radius-md' => $theme['radius_scale']['md'],
            '--nh-radius-lg' => $theme['radius_scale']['lg'],
            '--nh-radius-xl' => $theme['radius_scale']['xl'],
        ];
    }

    /**
     * Los alias de Vite de las tipografías que este sitio usa de verdad.
     *
     * Se pasan a `Vite::fonts()` para que la página cargue SÓLO esas tres y no
     * las seis del catálogo: ofrecerle variedad al owner no puede costarle
     * descargas a quien visita el sitio.
     *
     * Las del sistema quedan afuera —no tienen archivo que cargar, y pasarle a
     * `Vite::fonts()` un alias que no está en su manifiesto es una excepción, no
     * un descarte silencioso.
     *
     * @return list<string>
     */
    public function fontAliases(): array
    {
        $familias = [];

        foreach (['heading_font', 'body_font', 'eyebrow_font'] as $clave) {
            $familia = $this->theme()[$clave];

            if (! in_array($familia, ThemeContract::SYSTEM_FONTS, true)) {
                $familias[] = Str::slug($familia);
            }
        }

        return array_values(array_unique($familias));
    }

    private function build(): array
    {
        $stored = FrontendSetting::query()->where('singleton_key', 'default')->value('theme') ?? [];

        return $this->normalize(is_array($stored) ? $stored : []);
    }

    /**
     * Un tema crudo convertido en el que el sitio va a usar de verdad.
     *
     * Es público y recibe el array por parámetro para que la VISTA PREVIA del
     * panel pueda pedirle exactamente lo mismo sobre valores todavía sin
     * guardar. Si el preview repitiera estas reglas por su cuenta, mostraría el
     * color que el owner eligió y el sitio publicaría otro cada vez que un par
     * no llega a AA — que es justo el caso en que mirar la vista previa importa.
     *
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    public function normalize(array $stored): array
    {
        $theme = ThemeContract::DEFAULTS;

        // Field by field: one invalid value must not cost the owner the rest.
        foreach (['primary', 'on_primary', 'accent', 'on_accent', 'background', 'text'] as $colour) {
            if (ThemeContract::isHex($stored[$colour] ?? null)) {
                $theme[$colour] = strtolower($stored[$colour]);
            }
        }

        foreach (['heading_font', 'body_font', 'eyebrow_font'] as $font) {
            if (ThemeContract::isFont($stored[$font] ?? null)) {
                $theme[$font] = $stored[$font];
            }
        }

        foreach (['heading_bold', 'eyebrow_bold'] as $peso) {
            if (isset($stored[$peso])) {
                $theme[$peso] = (bool) $stored[$peso];
            }
        }

        if (ThemeContract::isRadius($stored['radius'] ?? null)) {
            $theme['radius'] = $stored['radius'];
        }

        // A pair below AA is unreadable regardless of how it got persisted, so
        // the foreground reverts to a colour known to work over that background —
        // UNLESS the owner explicitly opted into low contrast (their call, set in
        // the same theme). The save boundary honours the same flag.
        if (empty($stored['allow_low_contrast'])) {
            foreach (ThemeContract::CONTRAST_PAIRS as [$foreground, $background]) {
                if (! ThemeContract::meetsAa($theme[$foreground], $theme[$background])) {
                    $theme[$foreground] = $this->readableOver($theme[$background]);
                }
            }
        }

        $theme['radius_scale'] = ThemeContract::expandRadius($theme['radius']);
        $theme['focus'] = $this->focusRingOver($theme['accent'], $theme['background'], $theme['text']);
        $theme['accent_on_primary'] = $this->accentOver($theme['accent'], $theme['primary'], $theme['on_primary']);
        $theme['primary_ink'] = $this->inkOver($theme['primary'], $theme['background'], $theme['text']);
        $theme['accent_ink'] = $this->inkOver($theme['accent'], $theme['background'], $theme['text']);

        return $theme;
    }

    /**
     * Accents that sit ON a primary surface — hero eyebrows, small highlights.
     *
     * The contract validates accent against its OWN text, not against the
     * primary surface, so this pair is not covered by §16.5. It used to be safe
     * only because the surface was always navy; once the surface became
     * themeable, an owner could land on crimson-over-teal and lose the label.
     *
     * The accent is kept whenever it is legible over the surface, and otherwise
     * degrades to `on_primary`, which the contract already guarantees at 4.5:1
     * against that exact surface.
     */
    private function accentOver(string $accent, string $primary, string $onPrimary): string
    {
        return ThemeContract::meetsAa($accent, $primary) ? $accent : $onPrimary;
    }

    /**
     * The brand colour as INK — used as text over the base background (headings,
     * eyebrows, active nav, links), not as a surface (C-B2).
     *
     * `primary`/`accent` are valid surfaces even when pale: a light primary with
     * a dark `on_primary` is a perfectly legible hero. So the contract cannot
     * forbid a pale brand colour on save without punishing that valid use. The
     * foreground role is guaranteed HERE instead: the brand colour is kept when
     * it reads on the background, and otherwise degrades to `text`, which the
     * contract already validates at 4.5:1 against that same background.
     *
     * Same shape as accentOver()/focusRingOver(): the palette stays open, the
     * legibility guarantee moves to render.
     */
    private function inkOver(string $brand, string $background, string $text): string
    {
        return ThemeContract::meetsAa($brand, $background) ? $brand : $text;
    }

    /**
     * RFC-072:137 requires focus/outline states to reach 3:1 against their
     * immediate background. There is no separate focus token — the ring uses
     * the accent so it belongs to the palette — but a pale accent on a pale
     * background would make it invisible.
     *
     * Rather than constraining the owner's palette on save, the minimum is
     * guaranteed HERE: if the accent does not reach 3:1 the ring falls back to
     * the text colour, which the contract already validates at 4.5:1 against
     * the same background and therefore always clears 3:1.
     */
    private function focusRingOver(string $accent, string $background, string $text): string
    {
        return ThemeContract::contrastRatio($accent, $background) >= ThemeContract::MIN_FOCUS_CONTRAST
            ? $accent
            : $text;
    }

    /** Black or white, whichever contrasts more against the given background. */
    private function readableOver(string $background): string
    {
        return ThemeContract::contrastRatio('#111111', $background)
            >= ThemeContract::contrastRatio('#ffffff', $background)
            ? '#111111'
            : '#ffffff';
    }

    private function reportCacheFailure(string $key, Throwable $e): void
    {
        Log::warning('Frontend theme cache unavailable, serving straight from the database.', [
            'key' => $key,
            'exception' => $e::class,
            'message' => $e->getMessage(),
        ]);
    }
}
