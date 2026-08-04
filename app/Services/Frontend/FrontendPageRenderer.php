<?php

namespace App\Services\Frontend;

use App\Models\FrontendPage;
use App\Models\FrontendSection;
use App\Models\Project;
use App\Models\Property;
use App\Support\Frontend\CtaResolver;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * The public render presenter (RFC-076). It turns the published snapshot that
 * page(key) returns into a SAFE, view-ready structure so the Blade partials stay
 * dumb: they display, they never resolve.
 *
 * Every unsafe edge is closed HERE, once:
 *   - CTAs (`primary_cta`, `secondary_cta`) resolve through {@see CtaResolver};
 *     an invalid one becomes `null` and the button is simply dropped.
 *   - `media_id` references resolve through {@see FrontendMediaReference} against
 *     the owning section, so an ineligible uuid degrades to no image instead of
 *     leaking another record's file.
 *   - `dynamic` sections resolve their items from the kernel authorities
 *     (Property / Project / ServiceType), never from stored ids.
 *
 * A missing or malformed field never throws: the presenter returns the fallback
 * marker or an empty section, and the render keeps going (§16.7, RFC-076 error
 * handling). The public site never shows a stack trace.
 */
class FrontendPageRenderer
{
    /**
     * Which pages carry a dynamic service_list, and the canonical location key
     * they read (§16.8 / RFC-076: `home | servicios`). The page key IS the
     * location — there is no alias; a page not listed here has no service_list.
     */
    private const SERVICE_LOCATION = ['home' => 'home', 'servicios' => 'servicios'];

    public function __construct(
        private readonly FrontendPageContentService $content,
        private readonly FrontendMediaReference $references,
        private readonly FrontendServicesService $services,
        private readonly CtaResolver $cta,
        private readonly PublishedMediaReference $publishedMedia,
        private readonly FrontendSettingsService $settings,
    ) {}

    /**
     * @return array{fallback: bool, seo: array<string, mixed>|null, sections: list<array{key: string, type: string, data: array<string, mixed>}>}
     */
    public function render(string $pageKey): array
    {
        $page = $this->content->page($pageKey);

        if (($page['fallback'] ?? true) === true) {
            // The hero is resolved by the presenter EVEN without a snapshot
            // (C-B-1). Leaving it to the page's Blade produced a second renderer
            // for the same block: one with the per-page fallback, the A/B modes
            // and no inline surface, and another — the one a fresh install
            // actually sees — with none of that. The parent Blade keeps
            // rendering its non-hero content, but it no longer owns the hero.
            return [
                'fallback' => true,
                'seo' => null,
                'sections' => [],
                'hero' => $this->presentHero($pageKey, $this->fallbackHeroPayload($pageKey), fn (): ?string => null),
            ];
        }

        // Defense in depth (C-F1): page() already normalizes `sections` to a
        // list, but the render frontier never assumes it — a non-array container
        // degrades to no sections instead of crashing the foreach.
        $rawSections = is_array($page['sections'] ?? null) ? $page['sections'] : [];

        // The published SEO (M-F1): only the snapshot's seo, never draft columns.
        // The layout applies the settings() fallback when a field is missing.
        return [
            'fallback' => false,
            'seo' => is_array($page['seo'] ?? null) ? $page['seo'] : null,
            'sections' => $this->buildSections($pageKey, $rawSections, published: true),
        ];
    }

    /**
     * Render the WORKING DRAFT of a page for the owner-only preview (RFC-077):
     * the live `FrontendSection` rows, never the published snapshot. It uses the
     * exact same presenter as the public render, so the preview is faithful — the
     * only difference is the source (draft rows vs the published revision). A page
     * that is not a canonical key returns null so the caller can 404 uniformly.
     *
     * The preview carries the COMPLETE working state (M-G-2): the draft SEO and
     * the draft `is_enabled`, not only the sections — so the owner sees exactly
     * what publishing would produce, including the title/meta and whether the
     * page is currently disabled. It reads the live draft columns (never the
     * published snapshot); the public render is unaffected.
     *
     * @return array{enabled: bool, seo: array<string, mixed>|null, sections: list<array{key: string, type: string, data: array<string, mixed>}>}|null
     */
    public function renderDraft(string $pageKey): ?array
    {
        $page = FrontendPage::query()->where('key', $pageKey)->first();
        if ($page === null) {
            return null;
        }

        $rawSections = $page->sections()->orderBy('sort_order')->get()
            ->map(fn (FrontendSection $s): array => [
                'section_key' => $s->section_key,
                'type' => $s->type,
                'is_enabled' => (bool) $s->is_enabled,
                'payload' => $s->payload,
            ])->all();

        return [
            'enabled' => (bool) $page->is_enabled,
            'seo' => is_array($page->seo) ? $page->seo : null,
            'sections' => $this->buildSections($pageKey, $rawSections, published: false),
        ];
    }

    /**
     * Turn raw section rows (published snapshot arrays OR live draft rows) into
     * the view-ready list. One implementation feeds both the public render and
     * the preview, so they can never drift.
     *
     * @param  list<mixed>  $rawSections
     * @return list<array{key: string, type: string, data: array<string, mixed>}>
     */
    private function buildSections(string $pageKey, array $rawSections, bool $published): array
    {
        $page = FrontendPage::query()->where('key', $pageKey)->first();

        // Live owners, keyed by section_key — used ONLY by the draft preview,
        // where the key is unambiguous: the partial unique index guarantees one
        // LIVE row per (page, key). The published path must never resolve this
        // way: a soft-deleted owner can be shadowed by a recreated key, binding
        // an old snapshot to the wrong section (Épica 12.1 §7.11).
        $owners = $published
            ? collect()
            : ($page?->sections()->get()->keyBy('section_key') ?? collect());

        $sections = [];
        foreach ($rawSections as $raw) {
            if (! is_array($raw) || ($raw['is_enabled'] ?? false) !== true) {
                continue;
            }

            $type = is_string($raw['type'] ?? null) ? $raw['type'] : '';
            $key = is_string($raw['section_key'] ?? null) ? $raw['section_key'] : '';

            $owner = $owners->get($key);

            // One resolver per mode, so resolveTree() stays dumb about WHERE a
            // url comes from: promoted public file vs owner-only private route.
            $mediaUrl = $published
                ? fn (string $uuid): ?string => $this->publishedMediaUrl($uuid, $page)
                : fn (string $uuid): ?string => $this->draftMediaUrl($uuid, $owner);

            $sections[] = [
                'key' => $key,
                'type' => $type,
                'data' => $this->present($pageKey, $type, is_array($raw['payload'] ?? null) ? $raw['payload'] : [], $mediaUrl),
            ];
        }

        return $sections;
    }

    /**
     * The hero, fully resolved for Blade (Épica 12.1 §8, §9). Everything the
     * partial would otherwise have to decide is decided HERE, once:
     *
     *  - slides ordered by `(sort_order, media_id)` — never by array position,
     *    and with a stable tiebreak so duplicate orders are deterministic;
     *  - the per-page fallback, and the difference between «never initialised»
     *    and «deliberately emptied»;
     *  - the presentation defaults, so preview and public render identically;
     *  - the A/B mode, so the partial never has to reconcile ARIA itself.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function presentHero(string $pageKey, array $payload, callable $mediaUrl): array
    {
        // A hero that was never edited (`payload: null`) is «not initialised»,
        // exactly like a missing `slides` key — so it falls back to the page's
        // own hero, whole. Publishing a page whose hero was never touched used
        // to render a backdrop with no text and, worse, no <h1> at all.
        if ($payload === []) {
            $payload = $this->fallbackHeroPayload($pageKey);
        }

        $data = $this->resolveTree($payload, $mediaUrl);

        // Only slides that actually resolved to a url survive: an ineligible
        // uuid, or one whose media is not promoted yet, is OMITTED (§7.8).
        $slides = array_values(array_filter(
            is_array($data['slides'] ?? null) ? $data['slides'] : [],
            fn ($slide): bool => is_array($slide) && is_string($slide['media_url'] ?? null) && $slide['media_url'] !== '',
        ));

        usort($slides, fn (array $a, array $b): int => [$a['sort_order'] ?? 0, (string) ($a['media_id'] ?? '')]
            <=> [$b['sort_order'] ?? 0, (string) ($b['media_id'] ?? '')]);

        // «No inicializado» (the key was never published) falls back to the
        // page's own background. A published `slides: []` — or slides that all
        // dropped out — means the owner deliberately published without an
        // image, and no fallback revives that (§16.1.1).
        if ($slides === [] && ! array_key_exists('slides', $payload)) {
            $slides = $this->fallbackSlides($pageKey);
        }

        // Mode B wins as soon as ONE slide carries meaning: an image with alt
        // must not rotate silently, and only it is rendered (§9.3).
        $informative = null;
        foreach ($slides as $slide) {
            if (($slide['decorative'] ?? true) === false) {
                $informative = $slide;

                break;
            }
        }

        $data['slides'] = $informative === null ? $slides : [$informative];
        $data['mode'] = $informative === null ? 'decorative' : 'informative';

        // Defaults materialised here — the same values for preview and public.
        // Re-normalised rather than trusted: the schema validates on save, but a
        // legacy or hand-edited snapshot must degrade, not emit a bogus class.
        // Per-page visual treatment. NOT part of the payload and not editable:
        // it is how each page looks today, and routing the fallback through the
        // shared partial must not change the site's appearance.
        $variant = config("frontend-sections.hero_variants.{$pageKey}");
        $data['variant'] = in_array($variant, ['featured', 'compact'], true) ? $variant : 'standard';

        $data['text_align'] = $this->allowed($payload['text_align'] ?? null, 'hero_text_aligns', 'left');
        $data['logo_size'] = $this->allowed($payload['logo_size'] ?? null, 'hero_logo_sizes', 'md');
        $data['logo_enabled'] = ($payload['logo_enabled'] ?? false) === true;

        // El logo PROPIO (hero-logo-propio, cambio cms-pagina-proyectos):
        // resuelto por resolveTree() si el payload trae la clave, o el
        // fallback por página (D3/D4) si no la trae — ausente es «no
        // inicializado», idéntico a `slides`. Queda expuesto en `logo` para
        // que un partial futuro (el chip A-74) lo use sin resolver nada de
        // nuevo.
        $data['logo'] = array_key_exists('logo', $payload)
            ? ($data['logo'] ?? null)
            : $this->fallbackHeroLogo($pageKey);

        // Resolved HERE, not in Blade: this presenter's contract is that the
        // partials display and never resolve.
        //
        // Precedencia (decisión #1090 — gana el design sobre el spec):
        // `logo_enabled` es el ÚNICO interruptor y gobierna los DOS logos.
        // Prendido, se muestra el propio si `media_url` resolvió (subido Y
        // promovido); si no, el de marca — la MISMA imagen que usa el
        // footer. Apagado, ninguno de los dos se muestra: es justo lo que la
        // regla del spec no podía expresar (borrar la imagen propia revivía
        // el logo por el fallback §16.7 sin que el interruptor lo evitara).
        if ($data['logo_enabled']) {
            $ownUrl = is_string($data['logo']['media_url'] ?? null) && $data['logo']['media_url'] !== ''
                ? $data['logo']['media_url']
                : null;

            if ($ownUrl !== null) {
                $data['logo_url'] = $ownUrl;
            } else {
                $brand = $this->settings->settings();
                $data['logo_url'] = $brand['brand']['logo_dark_url'] ?? null;
                $data['site_name'] = $brand['site_name'] ?? '';
            }
        }

        return $data;
    }

    /**
     * The hero each page shows today, as a payload (§16.7 + §18.18). Deliberately
     * WITHOUT a `slides` key: that absence is what makes presentHero() apply the
     * page's fallback backdrop, and it keeps «never initialised» distinguishable
     * from «published empty».
     *
     * @return array<string, mixed>
     */
    private function fallbackHeroPayload(string $pageKey): array
    {
        $configured = (array) (config("frontend-sections.hero_fallback.{$pageKey}") ?? []);

        // Simetría exacta con `slides`: el `logo` crudo del fallback
        // (`src`/`alt`, no `media_id`) no es la forma que resolveTree()
        // espera, así que se saca de acá y se resuelve aparte con
        // fallbackHeroLogo() — presentHero() lo pide cuando la clave queda
        // AUSENTE del payload, igual que con slides.
        unset($configured['slides'], $configured['logo']);

        return $configured;
    }

    /**
     * The hardcoded background each page shows today (§16.7 + §18.18). Absolute
     * urls pass through; project-relative paths become asset urls.
     *
     * @return list<array<string, mixed>>
     */
    private function fallbackSlides(string $pageKey): array
    {
        $configured = (array) (config("frontend-sections.hero_fallback.{$pageKey}.slides") ?? []);

        return array_values(array_map(fn (string $src): array => [
            'media_url' => str_starts_with($src, 'http') ? $src : asset($src),
            'alt' => null,
            // Fallbacks are backdrops under the overlay: decorative by
            // definition, which also keeps them in mode A.
            'decorative' => true,
        ], array_filter($configured, 'is_string')));
    }

    /**
     * El logo PROPIO que cada página muestra hoy, si tiene uno (§16.7 +
     * §18.18, hero-logo-propio) — mismo espíritu que fallbackSlides(), pero
     * para D4. Sólo `proyectos` trae uno hoy (A-74 Arquitectura); el resto no
     * tiene esta clave en su config, así que devuelve null: «sin logo
     * propio», igual que cualquier hero publicado antes de que este campo
     * existiera.
     *
     * Lleva `from_fallback` para que el partial sepa el ORIGEN del logo, que es
     * lo único que distingue dos casos que se ven iguales en el payload. El
     * distintivo blanquea el logo con `brightness-0 invert`: sobre este —el que
     * la página ya mostraba— es lo correcto y §16.7 manda conservarlo, pero
     * sobre el que sube el owner le borraría su color de marca, que es
     * justamente lo que «logo propio» existe para mostrar.
     *
     * @return array{media_url: string, alt: ?string, from_fallback: true}|null
     */
    private function fallbackHeroLogo(string $pageKey): ?array
    {
        $configured = config("frontend-sections.hero_fallback.{$pageKey}.logo");

        if (! is_array($configured) || ! is_string($configured['src'] ?? null) || $configured['src'] === '') {
            return null;
        }

        return [
            'media_url' => str_starts_with($configured['src'], 'http') ? $configured['src'] : asset($configured['src']),
            'alt' => is_string($configured['alt'] ?? null) ? $configured['alt'] : null,
            'from_fallback' => true,
        ];
    }

    /** A value from a closed allowlist, or the default. */
    private function allowed(mixed $value, string $configKey, string $default): string
    {
        return is_string($value) && in_array($value, (array) config("frontend-sections.{$configKey}"), true)
            ? $value
            : $default;
    }

    /**
     * PUBLISHED media: resolved through its OWN row (§7.11) and emitted only
     * once PROMOTED (§7.8).
     *
     * Identity comes from `Media.model_id`, never from `section_key`, so a
     * soft-deleted or recreated section cannot rebind or hide it. Media that is
     * still private (not promoted yet) is OMITTED — never a private URL, never a
     * placeholder, never a "previous version": those do not exist in the
     * snapshot, which carries a single `media_id`.
     */
    private function publishedMediaUrl(string $uuid, ?FrontendPage $page): ?string
    {
        $media = $this->publishedMedia->resolvePublished($uuid, $page);

        return $media !== null && $this->publishedMedia->isPromoted($media)
            ? $media->getUrl()
            : null;
    }

    /**
     * DRAFT media: served by the owner-only route (§7.7). Draft files live on
     * `frontend-private` and have no public URL until a publish promotes them,
     * so the preview must not call getUrl().
     */
    private function draftMediaUrl(string $uuid, ?FrontendSection $owner): ?string
    {
        if ($owner === null || $this->references->resolve($uuid, $owner, 'images') === null) {
            return null;
        }

        return route('frontend.sections.media', ['section' => $owner->getKey(), 'uuid' => $uuid]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function present(string $pageKey, string $type, array $payload, callable $mediaUrl): array
    {
        if ($type === 'hero') {
            return $this->presentHero($pageKey, $payload, $mediaUrl);
        }

        $data = $this->resolveTree($payload, $mediaUrl);

        // Dynamic types carry only presentation params; their items come from the
        // kernel authority at render, never from stored ids.
        $data['items'] = match ($type) {
            'service_list' => $this->serviceItems($pageKey),
            'featured_properties' => $this->properties(fn ($q) => $q->featured(), $payload['limit'] ?? null),
            'opportunity_properties' => $this->properties(fn ($q) => $q->opportunity(), $payload['limit'] ?? null),
            'featured_projects' => $this->projects($pageKey, $payload['limit'] ?? null),
            default => $data['items'] ?? null,
        };

        // Presentación por página (design D6, cambio cms-pagina-proyectos):
        // igual mecanismo que `hero_variants`, pero para `featured_projects`.
        // `catalog` decide en el PARTIAL el layout (carrusel), el estado
        // vacío y el fondo por defecto — la autoridad de datos ya se decidió
        // arriba, en projects(). Cualquier otra página (sin entrada, o con
        // una entrada que no sea `catalog`) sigue viendo el resumen de
        // siempre, sin cambios.
        if ($type === 'featured_projects') {
            $data['variant'] = config("frontend-sections.project_list_variants.{$pageKey}") === 'catalog' ? 'catalog' : 'default';
        }

        return $data;
    }

    /**
     * Recursively resolve CTAs and media references anywhere in the payload.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function resolveTree(array $node, callable $mediaUrl): array
    {
        $out = [];
        foreach ($node as $key => $value) {
            if (($key === 'primary_cta' || $key === 'secondary_cta') && is_array($value)) {
                $out[$key] = $this->cta->resolve($value);

                continue;
            }

            if (is_array($value)) {
                $out[$key] = $this->resolveTree($value, $mediaUrl);

                continue;
            }

            $out[$key] = $value;
        }

        // Any object carrying a media_id gets a resolved url, or null. The uuid
        // FORMAT is validated inside the resolution frontier (§7.10), not here:
        // this used to keep its own regex, which is exactly the drift that left
        // new callers unprotected against SQLSTATE 22P02.
        if (array_key_exists('media_id', $node)) {
            $mediaId = $node['media_id'] ?? null;
            $out['media_url'] = is_string($mediaId) ? $mediaUrl($mediaId) : null;
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serviceItems(string $pageKey): array
    {
        $location = self::SERVICE_LOCATION[$pageKey] ?? null;

        return $location === null ? [] : $this->services->services($location);
    }

    /**
     * @param  callable(Builder): mixed  $scope
     * @return list<array<string, mixed>>
     */
    private function properties(callable $scope, mixed $limit): array
    {
        $query = Property::query()->published();
        $scope($query);

        // Normalize to arrays (Mn-F1 / RFC-076): the Blade partials receive a
        // view-ready DTO, never a live Eloquent model — the render stays
        // decoupled from Property's methods, relations and media API.
        return $query->take($this->limit($limit))->get()->map(fn (Property $p): array => [
            'title' => $p->title,
            'zone' => $p->zone?->name ?? 'Querétaro',
            'price' => $p->priceLabel(),
            'operation' => $p->operation_type->label(),
            'beds' => $p->bedrooms,
            'baths' => $p->bathrooms,
            'area' => $p->displayArea(),
            'parking' => $p->parking_spaces,
            'href' => route('inmuebles.show', $p->slug),
            'image' => $p->getFirstMediaUrl('cover', 'web') ?: null,
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function projects(string $pageKey, mixed $limit): array
    {
        // El mapeo de abajo toca DOS relaciones por ítem —`projectType` para la
        // etiqueta y la media para la portada—, así que sin esto el listado
        // dispara dos consultas extra POR PROYECTO. El controlador al que este
        // render reemplaza ya eager cargaba `projectType`
        // (`ProjectController@index`): perderlo en el cutover era una
        // regresión, y duele sobre todo en `catalog`, que sin `limit` no tiene
        // cota.
        $query = Project::query()->with(['projectType', 'media']);

        // Autoridad por página (design D6, hallazgo #2 del owner): `catalog`
        // es el listado COMPLETO que hoy usa `ProjectController@index` —
        // TODOS los proyectos, nunca sólo los destacados. Reusar
        // `featured_projects` tal cual habría sido una regresión de
        // CONTENIDO real en el cutover, no sólo visual: cualquier proyecto
        // no destacado habría desaparecido en silencio del catálogo.
        if (config("frontend-sections.project_list_variants.{$pageKey}") === 'catalog') {
            $query->latest();

            // Sin `limit`, ilimitado — igual que el controlador hoy (status
            // quo, D6). Con uno elegido (1–24, ya lo valida el schema), se
            // acota sin adoptar el tope de 12 que sí aplica al resumen de
            // home.
            if (is_int($limit) && $limit > 0 && $limit <= 24) {
                $query->take($limit);
            }
        } else {
            $query->where('is_featured', true)->take($this->limit($limit));
        }

        return $query->get()->map(fn (Project $p): array => [
            'title' => $p->title,
            'type' => $p->projectType?->label,
            'description' => $p->description,
            'href' => route('proyectos.show', $p->slug),
            'cover' => $p->getFirstMediaUrl('cover', 'web') ?: null,
        ])->all();
    }

    private function limit(mixed $limit): int
    {
        return is_int($limit) && $limit > 0 && $limit <= 24 ? $limit : 12;
    }
}
