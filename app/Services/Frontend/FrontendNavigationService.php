<?php

namespace App\Services\Frontend;

use App\Models\FrontendSetting;
use App\Support\Frontend\CtaResolver;
use App\Support\Frontend\PublicRoutes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * navigation() and footer() read models (RFC-073, §16.3) — the render half of
 * the double boundary, with the exact fallbacks of §16.7.
 *
 * The owner may relabel and reorder links, never repoint them: every URL is
 * DERIVED from an allowlisted key (nav) or a CtaResolver-validated target
 * (CTAs / footer), so a persisted `url` or a bogus key can never reach Blade.
 * Same cache discipline as the settings/theme services — generation key plus a
 * SHAPE version, cache guarded so a store outage degrades to a direct read.
 */
class FrontendNavigationService
{
    private const TTL_SECONDS = 300;

    /** Bump when the structure returned by build() changes (see theme service). */
    private const SHAPE = 1;

    private const FALLBACK_CTA_LABEL = 'Agenda una cita';

    public function __construct(
        private readonly FrontendCacheGeneration $generation,
        private readonly CtaResolver $resolver,
    ) {}

    public function navigation(): array
    {
        return $this->remember('navigation', fn (): array => $this->buildNavigation());
    }

    public function footer(): array
    {
        return $this->remember('footer', fn (): array => $this->buildFooter());
    }

    /**
     * @param  callable(): array  $build
     */
    private function remember(string $domain, callable $build): array
    {
        $key = sprintf('frontend:g%d:%s:v%d', $this->generation->current(), $domain, self::SHAPE);

        try {
            $cached = Cache::get($key);
        } catch (Throwable $e) {
            $this->reportCacheFailure($key, $e);
            $cached = null;
        }

        if (is_array($cached)) {
            return $cached;
        }

        // Outside the cache try/catch on purpose: a real error must surface.
        $value = $build();

        try {
            Cache::put($key, $value, self::TTL_SECONDS);
        } catch (Throwable $e) {
            $this->reportCacheFailure($key, $e);
        }

        return $value;
    }

    private function buildNavigation(): array
    {
        $setting = FrontendSetting::query()->where('singleton_key', 'default')->first();

        return [
            'links' => $this->links(is_array($setting?->navigation) ? $setting->navigation : null),
            'ctas' => [
                'primary' => $this->cta($setting?->primary_cta) ?? $this->fallbackPrimaryCta(),
                'secondary' => $this->cta($setting?->secondary_cta),
            ],
        ];
    }

    /**
     * @param  array<int, mixed>|null  $stored
     * @return list<array{key: string, label: string, url: string, active_pattern: string, sort_order: int, open_in_new_tab: bool}>
     */
    private function links(?array $stored): array
    {
        // No configuration at all: the exact nav the site shipped before the CMS.
        if ($stored === null || $stored === []) {
            return $this->defaultLinks(array_keys(PublicRoutes::ALLOWLIST));
        }

        $links = [];
        foreach ($stored as $item) {
            $key = is_array($item) ? ($item['key'] ?? null) : null;

            // Only allowlisted, enabled keys survive; a repointed `url` is ignored.
            if (! PublicRoutes::isKey($key) || ($item['enabled'] ?? false) !== true) {
                continue;
            }

            $label = is_string($item['label'] ?? null) && trim($item['label']) !== ''
                ? trim($item['label'])
                : PublicRoutes::defaultLabel($key);

            $links[] = [
                'key' => $key,
                'label' => $label,
                'url' => route(PublicRoutes::routeName($key)),
                'active_pattern' => PublicRoutes::pattern($key),
                'sort_order' => is_numeric($item['sort_order'] ?? null) ? (int) $item['sort_order'] : 0,
                // v1 has no external nav destinations: the field is part of the
                // normative schema but is always forced false at the boundary,
                // so a persisted `true` can never open a new tab (Mn-C1).
                'open_in_new_tab' => false,
            ];
        }

        // Configured but everything disabled/invalid: keep the site navigable.
        if ($links === []) {
            return $this->defaultLinks(['home', 'contacto']);
        }

        usort($links, fn (array $a, array $b): int => $a['sort_order'] <=> $b['sort_order']);

        return array_values($links);
    }

    /**
     * @param  list<string>  $keys
     * @return list<array{key: string, label: string, url: string, active_pattern: string, sort_order: int, open_in_new_tab: bool}>
     */
    private function defaultLinks(array $keys): array
    {
        $links = [];
        foreach (array_values($keys) as $order => $key) {
            $links[] = [
                'key' => $key,
                'label' => PublicRoutes::defaultLabel($key),
                'url' => route(PublicRoutes::routeName($key)),
                'active_pattern' => PublicRoutes::pattern($key),
                'sort_order' => $order,
                'open_in_new_tab' => false,
            ];
        }

        return $links;
    }

    /**
     * @return array{label: string, url: string, external: bool}|null
     */
    private function cta(mixed $stored): ?array
    {
        return $this->resolver->resolve($stored);
    }

    private function fallbackPrimaryCta(): array
    {
        return ['label' => self::FALLBACK_CTA_LABEL, 'url' => route('leads.create'), 'external' => false];
    }

    private function buildFooter(): array
    {
        $setting = FrontendSetting::query()->where('singleton_key', 'default')->first();
        $stored = is_array($setting?->footer) ? $setting->footer : null;

        // Social profiles are independent of the footer columns: the owner can
        // set an Instagram URL without ever touching the link columns, so they
        // are resolved here and carried into BOTH the configured and the
        // fallback footer.
        $social = $this->social($setting);

        if ($stored === null || $stored === []) {
            return $this->fallbackFooter($social);
        }

        // Every level is type-checked before iterating: an import, manual SQL
        // or a legacy row can persist a string where a list is expected, and a
        // `foreach` over that would 500 the public home (M-C1). Anything of the
        // wrong shape is dropped, never thrown on.
        $columns = [];
        foreach ($this->asList($stored['columns'] ?? null) as $column) {
            if (! is_array($column)) {
                continue;
            }

            $links = [];
            foreach ($this->asList($column['links'] ?? null) as $link) {
                // resolve() already rejects non-arrays and unsafe targets.
                $resolved = $this->resolver->resolve($link);

                if ($resolved === null) {
                    continue;
                }

                // A disabled link stays for the editor but is flagged so Blade
                // omits it; a fallback never revives it (§16.7 / RFC-073).
                $links[] = [...$resolved, 'enabled' => (is_array($link) ? ($link['enabled'] ?? false) : false) === true];
            }

            if ($links !== []) {
                $columns[] = [
                    'title' => is_string($column['title'] ?? null) ? $column['title'] : '',
                    'links' => $links,
                ];
            }
        }

        return [
            'columns' => $columns,
            'legal_text' => is_string($stored['legal_text'] ?? null) ? $stored['legal_text'] : $this->fallbackLegalText(),
            'social' => $social,
        ];
    }

    /**
     * @param  list<array{network: string, label: string, url: string, external: bool}>  $social
     */
    private function fallbackFooter(array $social = []): array
    {
        $link = fn (string $key): array => [
            'label' => PublicRoutes::defaultLabel($key),
            'url' => route(PublicRoutes::routeName($key)),
            'external' => false,
            'enabled' => true,
        ];

        return [
            'columns' => [
                ['title' => 'Enlaces', 'links' => array_map($link, ['nosotros', 'servicios', 'proyectos', 'inmuebles'])],
                ['title' => 'Compañía', 'links' => array_map($link, ['inversionistas', 'contacto'])],
            ],
            'legal_text' => $this->fallbackLegalText(),
            'social' => $social,
        ];
    }

    private function fallbackLegalText(): string
    {
        return '© '.date('Y').' Landra. Todos los derechos reservados.';
    }

    /**
     * Coerce a persisted value into something safe to `foreach` over: an array
     * passes through, anything else becomes an empty list. This is the guard
     * that keeps a malformed footer/social payload from 500-ing the site.
     *
     * @return array<int|string, mixed>
     */
    private function asList(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * Social links come from `settings.social_links`, a map keyed by network
     * (`instagram`, `tiktok`, `facebook`). A network only appears when it holds a
     * valid https URL — an empty or unsafe value is simply not rendered, so the
     * icon shows only once the owner sets that profile. The `network` key lets the
     * render pick the right brand icon.
     *
     * @return list<array{network: string, label: string, url: string, external: bool}>
     */
    private const SOCIAL_NETWORKS = [
        'instagram' => 'Instagram',
        'tiktok' => 'TikTok',
        'facebook' => 'Facebook',
    ];

    private function social(?FrontendSetting $setting): array
    {
        $stored = is_array($setting?->social_links) ? $setting->social_links : [];

        $social = [];
        foreach (self::SOCIAL_NETWORKS as $network => $label) {
            $url = is_string($stored[$network] ?? null) ? trim($stored[$network]) : '';

            if (preg_match('#^https://#i', $url) === 1 && filter_var($url, FILTER_VALIDATE_URL) !== false) {
                $social[] = ['network' => $network, 'label' => $label, 'url' => $url, 'external' => true];
            }
        }

        return $social;
    }

    private function reportCacheFailure(string $key, Throwable $e): void
    {
        Log::warning('Frontend navigation cache unavailable, serving straight from the database.', [
            'key' => $key,
            'exception' => $e::class,
            'message' => $e->getMessage(),
        ]);
    }
}
