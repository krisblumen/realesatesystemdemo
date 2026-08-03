<?php

namespace App\Services\Frontend;

use App\Models\FrontendService;
use App\Services\Frontend\Media\MediaPromotionState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * services(location) and the lead-eligibility check (§16.6, RFC-074) — the ONE
 * place the eligibility rule lives, so render and lead capture can never drift:
 *
 *   visible in L  ⇔ ServiceType.active AND FrontendService.show_in_L
 *   lead-eligible ⇔ ServiceType.active AND FrontendService.allow_leads
 *
 * Fail-closed: `active=false` always wins and a missing FrontendService grants
 * nothing. `services()` is cached per generation (render path); isLeadEligible()
 * is a direct query — it gates a write and must read fresh, never a stale cache.
 */
class FrontendServicesService
{
    private const TTL_SECONDS = 300;

    private const SHAPE = 1;

    // The public location key is the canonical `home | servicios` of §16.8 /
    // RFC-076 (NOT the English `services`); the DB column keeps its own name.
    private const LOCATIONS = ['home' => 'show_in_home', 'servicios' => 'show_in_services'];

    public function __construct(
        private readonly FrontendCacheGeneration $generation,
        private readonly FrontendMediaReference $references,
        private readonly MediaPromotionState $promotion = new MediaPromotionState,
    ) {}

    /**
     * Eligible services for a public location, ordered, with a derived CTA.
     *
     * @return list<array<string, mixed>>
     */
    public function services(string $location): array
    {
        $column = self::LOCATIONS[$location] ?? null;
        if ($column === null) {
            return [];
        }

        $key = sprintf('frontend:g%d:services:%s:v%d', $this->generation->current(), $location, self::SHAPE);

        try {
            $cached = Cache::get($key);
        } catch (Throwable $e) {
            $this->reportCacheFailure($key, $e);
            $cached = null;
        }

        if (is_array($cached)) {
            return $cached;
        }

        $services = $this->build($column);

        try {
            Cache::put($key, $services, self::TTL_SECONDS);
        } catch (Throwable $e) {
            $this->reportCacheFailure($key, $e);
        }

        return $services;
    }

    /**
     * Fail-closed lead eligibility, read fresh (no cache): a service accepts a
     * lead only when its type is active AND it allows leads.
     */
    public function isLeadEligible(string $code): bool
    {
        return $this->eligibleQuery('allow_leads')
            ->where('frontend_services.service_type_code', $code)
            ->exists();
    }

    /**
     * The lead-eligible services as `code => label`, from the SAME fail-closed
     * rule as isLeadEligible() and the submit lock (M-D1). The public form
     * renders these options so an ineligible service (inactive, info-only, or
     * without a live FrontendService) never even appears as selectable — the
     * form can no longer diverge from what the server will accept.
     *
     * @return array<string, string>
     */
    public function leadOptions(): array
    {
        return $this->eligibleQuery('allow_leads')
            // Override the base select('frontend_services.*') so the joined label
            // is actually in the result set for pluck.
            ->select('frontend_services.service_type_code as code', 'service_types.label as label')
            ->orderBy('frontend_services.sort_order')
            ->pluck('label', 'code')
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function build(string $column): array
    {
        // Tri-state (§16.7): an UNINITIALIZED table (no rows at all) falls back
        // to the current hardcoded services; an initialized table with nothing
        // eligible returns empty — a fallback never revives a deliberately
        // disabled service.
        if (! FrontendService::query()->exists()) {
            return $this->fallback();
        }

        return $this->eligibleQuery($column)
            ->orderBy('frontend_services.sort_order')
            ->get()
            ->map(fn (FrontendService $service): array => $this->present($service))
            ->all();
    }

    /**
     * The eligibility join: active type INNER JOIN the toggle column. One query
     * shape for render and for leads, so the rule cannot diverge.
     */
    private function eligibleQuery(string $column): Builder
    {
        return FrontendService::query()
            ->select('frontend_services.*')
            ->join('service_types', 'service_types.code', '=', 'frontend_services.service_type_code')
            ->where('service_types.active', true)
            ->where("frontend_services.{$column}", true);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(FrontendService $service): array
    {
        $code = $service->service_type_code;

        return [
            'code' => $code,
            'title' => (string) $service->title,
            'short_description' => (string) $service->short_description,
            'long_description' => (string) $service->long_description,
            'bullets' => is_array($service->bullets) ? array_values($service->bullets) : [],
            'icon' => $service->icon,
            'image_url' => $this->imageUrl($service),
            'image_alt' => $service->image_alt ?: $service->title,
            // Derived, never editable (RFC-074): only a lead-eligible service
            // gets the forced-lead CTA.
            'cta' => $service->allow_leads
                ? ['label' => 'Solicitar información', 'url' => route('leads.create', ['service' => $code])]
                : null,
        ];
    }

    /**
     * The exact services the frontend shipped before the CMS (§16.7), used only
     * when the table is uninitialized. Same order/content for home and services;
     * the per-location filtering does not apply to a fallback.
     *
     * @return list<array<string, mixed>>
     */
    private function fallback(): array
    {
        $leadCta = fn (string $code): array => ['label' => 'Solicitar información', 'url' => route('leads.create', ['service' => $code])];

        return [
            ['code' => 'arquitectura', 'title' => 'Arquitectura', 'short_description' => 'Diseño a la medida que equilibra estética, función y valor a largo plazo.', 'long_description' => 'Diseño a la medida que equilibra estética, función y valor a largo plazo.', 'bullets' => ['Proyecto arquitectónico', 'Diseño a la medida', 'Valor a largo plazo'], 'icon' => 'home', 'image_url' => null, 'image_alt' => 'Arquitectura', 'cta' => $leadCta('arquitectura')],
            ['code' => 'construccion', 'title' => 'Construcción', 'short_description' => 'Ejecución de obra con control de calidad, tiempos y presupuesto.', 'long_description' => 'Ejecución de obra con control de calidad, tiempos y presupuesto.', 'bullets' => ['Control de calidad', 'Tiempos y presupuesto', 'Construcción residencial'], 'icon' => 'building', 'image_url' => null, 'image_alt' => 'Construcción', 'cta' => $leadCta('construccion')],
            ['code' => 'comercializacion', 'title' => 'Comercialización', 'short_description' => 'Vendemos y rentamos tu propiedad con estrategia, foto profesional y leads calificados.', 'long_description' => 'Vendemos y rentamos tu propiedad con estrategia, foto profesional y leads calificados.', 'bullets' => ['Estrategia de venta', 'Fotografía profesional', 'Leads calificados'], 'icon' => 'trending-up', 'image_url' => null, 'image_alt' => 'Comercialización', 'cta' => $leadCta('comercializacion')],
            ['code' => 'inversion', 'title' => 'Inversión inmobiliaria', 'short_description' => 'Oportunidades opcionadas con potencial de plusvalía en zonas de alto crecimiento.', 'long_description' => 'Asesoría para inversionistas con visión de futuro.', 'bullets' => ['Análisis de plusvalía', 'Zonas de alto crecimiento', 'Acompañamiento integral'], 'icon' => 'trending-up', 'image_url' => null, 'image_alt' => 'Inversión inmobiliaria', 'cta' => null],
        ];
    }

    /**
     * La URL pública de la imagen de un servicio, o null (Épica 12.3 §6).
     *
     * UNA sola regla: **sólo una media `promoted` se emite**. Todo lo demás
     * —pendiente de promoción, inválida, ajena, ausente o con el archivo
     * faltante— cae al fallback, que es la ausencia de imagen.
     *
     * No hace falta un caso «legacy»: la migración de 12.3 reconoció como
     * `promoted` todo lo que ya estaba sirviéndose en público y vigente, así que
     * el render no necesita saber qué es viejo y qué es nuevo.
     *
     * Fail-closed a propósito: entre que se guarda una imagen y que el job la
     * promueve hay una ventana, y en esa ventana es preferible un servicio sin
     * foto a una URL rota o a la ruta de un archivo privado en el HTML público.
     */
    private function imageUrl(FrontendService $service): ?string
    {
        if ($service->image_media_id === null) {
            return null;
        }

        // §16.4: resolve through the validated uuid boundary, never getFirstMedia.
        $media = $this->references->resolve($service->image_media_id, $service, 'image');

        return $media !== null && $this->promotion->isPromoted($media)
            ? $media->getUrl()
            : null;
    }

    private function reportCacheFailure(string $key, Throwable $e): void
    {
        Log::warning('Frontend services cache unavailable, serving straight from the database.', [
            'key' => $key,
            'exception' => $e::class,
            'message' => $e->getMessage(),
        ]);
    }
}
