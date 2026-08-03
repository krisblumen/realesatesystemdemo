<?php

namespace App\Services\Frontend;

use App\Models\FrontendPage;
use App\Models\FrontendSection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Draft editing of page sections and the page(key) read model (RFC-075).
 *
 * Every draft mutation runs inside a transaction that (1) sets READ COMMITTED as
 * its first statement, (2) locks the FrontendPage then the affected sections by
 * id ASC — one deterministic order shared with the publisher, so concurrent
 * edits serialize and never deadlock — (3) validates the payload by type and its
 * media references, then (4) writes and bumps draft_revision atomically.
 *
 * The public render reads ONLY the published snapshot: page(key) returns
 * published_revision, or the §16.7 fallback marker until the first publish.
 */
class FrontendPageContentService
{
    private const TTL_SECONDS = 300;

    private const SHAPE = 1;

    public function __construct(
        private readonly FrontendSectionSchema $schema,
        private readonly FrontendMediaReference $references,
        private readonly FrontendCacheGeneration $generation,
    ) {}

    /**
     * The render read model, cached per generation. Reads the published snapshot
     * only; an unpublished page falls back (§16.7) so the deploy never leaves a
     * blank page.
     *
     * @return array<string, mixed>
     */
    public function page(string $key): array
    {
        $cacheKey = sprintf('frontend:g%d:page:%s:v%d', $this->generation->current(), $key, self::SHAPE);

        try {
            $cached = Cache::get($cacheKey);
        } catch (Throwable $e) {
            $this->reportCacheFailure($cacheKey, $e);
            $cached = null;
        }

        if (is_array($cached)) {
            return $cached;
        }

        $page = $this->build($key);

        try {
            Cache::put($cacheKey, $page, self::TTL_SECONDS);
        } catch (Throwable $e) {
            $this->reportCacheFailure($cacheKey, $e);
        }

        return $page;
    }

    /**
     * @return array<string, mixed>
     */
    private function build(string $key): array
    {
        $page = FrontendPage::query()->where('key', $key)->first();
        $snapshot = $page?->published_revision;

        // The render reads ONLY the published snapshot (C-E3). A page with no
        // snapshot falls back (§16.7); every other field — is_enabled, seo,
        // sections — comes from the snapshot, never the live draft columns, so a
        // draft edit can never change the public site without a publish.
        if ($page === null || ! is_array($snapshot)) {
            return ['key' => $key, 'fallback' => true, 'sections' => []];
        }

        // A snapshot published while disabled also falls back.
        if (($snapshot['is_enabled'] ?? true) !== true) {
            return ['key' => $key, 'fallback' => true, 'sections' => []];
        }

        // A structurally corrupt snapshot (C-F1) — `sections` that is not even a
        // list (a scalar, null, or an associative object), from an import,
        // manual SQL or a future bug — is untrustworthy wholesale: serve the
        // hardcoded fallback (§16.7), never a blank page or a 500. A legitimately
        // empty publish (`sections: []`) is still a valid list and renders.
        $sections = $snapshot['sections'] ?? null;
        if (! is_array($sections) || ! array_is_list($sections)) {
            return ['key' => $key, 'fallback' => true, 'sections' => []];
        }

        // Normalize what IS served before it is cached: the SEO fields must be
        // scalar strings; a non-string is dropped so the settings fallback
        // applies instead of handing Blade a non-scalar to escape.
        return [
            'key' => $key,
            'fallback' => false,
            'enabled' => true,
            'seo' => $this->safeSeo($snapshot['seo'] ?? null),
            'sections' => $sections,
        ];
    }

    /**
     * The published SEO reduced to its known scalar-string fields. A non-string
     * value (array, object, number, bool) is dropped so the layout applies the
     * settings() fallback instead of handing Blade a non-scalar to escape.
     *
     * @return array<string, string>|null
     */
    private function safeSeo(mixed $seo): ?array
    {
        if (! is_array($seo)) {
            return null;
        }

        $clean = [];
        foreach (['meta_title', 'meta_description', 'og_title', 'og_description'] as $field) {
            if (is_string($seo[$field] ?? null) && trim($seo[$field]) !== '') {
                $clean[$field] = $seo[$field];
            }
        }

        return $clean === [] ? null : $clean;
    }

    /**
     * Edit a section's draft payload atomically (RFC-075). Rejects an invalid
     * payload or an ineligible media reference before writing, and bumps the
     * page's draft_revision so a stale publish is later refused.
     *
     * @param  array<string, mixed>|null  $payload
     */
    public function updateSectionPayload(FrontendSection $section, ?array $payload): void
    {
        DB::transaction(function () use ($section, $payload): void {
            DB::statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');

            $page = FrontendPage::query()->whereKey($section->frontend_page_id)->lockForUpdate()->firstOrFail();
            $locked = FrontendSection::query()->whereKey($section->getKey())->lockForUpdate()->firstOrFail();

            // Registry boundary (C-E2): only a canonical (page, key, type) section
            // may be edited — never a row outside the closed registry.
            if (! $this->schema->isCanonicalSection($page->key, $locked->section_key, $locked->type)) {
                throw ValidationException::withMessages(['section' => 'Esta sección no pertenece al registro de la página.']);
            }

            // Schema first, and SHORT-CIRCUIT (C-E4): if the payload is invalid,
            // throw before any eligibility query runs — a malformed media_id is a
            // schema error, so the uuid column is never queried with garbage.
            $errors = $this->schema->validate($locked->type, $payload);
            if ($errors !== []) {
                throw ValidationException::withMessages(['payload' => $errors]);
            }

            foreach ($this->mediaIds($payload) as $uuid) {
                if (! $this->references->isEligible($uuid, $locked, 'images')) {
                    $errors[] = 'Una imagen referenciada no es válida para esta sección.';
                }
            }

            if ($errors !== []) {
                throw ValidationException::withMessages(['payload' => $errors]);
            }

            $locked->update(['payload' => $payload]);
            $page->increment('draft_revision');

            DB::afterCommit(fn () => $this->generation->bump());
        });
    }

    /**
     * Edit a section's draft (payload, enabled, order) atomically, routed through
     * the same lock + validation + draft_revision bump as a payload edit. Every
     * owner-facing section change goes through here so a draft edit and a publish
     * always serialize on the page row.
     *
     * El ORDEN no se toca acá: se cambia sólo con `moveSectionDraft`, que es
     * quien sabe mantener la portada primero y los valores sin huecos.
     *
     * @param  array{payload?: array<string, mixed>|null, is_enabled?: bool}  $data
     */
    public function saveSectionDraft(FrontendSection $section, array $data): void
    {
        DB::transaction(function () use ($section, $data): void {
            DB::statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');

            $page = FrontendPage::query()->whereKey($section->frontend_page_id)->lockForUpdate()->firstOrFail();
            $locked = FrontendSection::query()->whereKey($section->getKey())->lockForUpdate()->firstOrFail();

            if (! $this->schema->isCanonicalSection($page->key, $locked->section_key, $locked->type)) {
                throw ValidationException::withMessages(['section' => 'Esta sección no pertenece al registro de la página.']);
            }

            $payload = array_key_exists('payload', $data) ? $data['payload'] : $locked->payload;

            // Short-circuit (C-E4): schema first, no eligibility query on a
            // payload the schema already rejected.
            $errors = $this->schema->validate($locked->type, $payload);
            if ($errors !== []) {
                throw ValidationException::withMessages(['payload' => $errors]);
            }

            foreach ($this->mediaIds($payload) as $uuid) {
                if (! $this->references->isEligible($uuid, $locked, 'images')) {
                    $errors[] = 'Una imagen referenciada no es válida para esta sección.';
                }
            }

            if ($errors !== []) {
                throw ValidationException::withMessages(['payload' => $errors]);
            }

            $locked->update(array_filter([
                'payload' => $payload,
                'is_enabled' => $data['is_enabled'] ?? $locked->is_enabled,
            ], fn ($v, $k) => array_key_exists($k, $data) || $k === 'payload', ARRAY_FILTER_USE_BOTH));

            $page->increment('draft_revision');

            DB::afterCommit(fn () => $this->generation->bump());
        });
    }

    /**
     * Mueve una sección un lugar arriba (-1) o abajo (+1) dentro de su página.
     *
     * Es la ÚNICA puerta al orden, y por eso las reglas se pueden garantizar en
     * vez de sólo pedir. Antes el orden era un número que el owner escribía a
     * mano, y ese número tenía tres formas de salir mal:
     *
     *  - repetir uno ocupado reventaba contra el índice único (página, orden) y
     *    le mostraba un SQLSTATE 23505 crudo;
     *  - uno enorme se pasaba del rango de la columna (SQLSTATE 22003);
     *  - uno NEGATIVO se guardaba sin chistar —en PostgreSQL un
     *    `unsignedInteger` es un `integer` con signo— y dejaba esa sección
     *    dibujada ARRIBA de la portada, que es justo lo que el candado del hero
     *    intentaba impedir. El candado sólo miraba el valor del propio hero.
     *
     * Moverse entre vecinos no puede expresar ninguno de los tres. Además cada
     * movimiento reescribe la página entera como 0..N-1, así que huecos,
     * repetidos y negativos que hubieran quedado de antes se sanean solos.
     */
    public function moveSectionDraft(FrontendSection $section, int $direction): void
    {
        if ($direction === 0) {
            return;
        }

        DB::transaction(function () use ($section, $direction): void {
            DB::statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');

            $page = FrontendPage::query()->whereKey($section->frontend_page_id)->lockForUpdate()->firstOrFail();

            // Se lockea por id ASC —la misma secuencia que usa el publisher— y
            // recién después se ordena en PHP por posición. Lockear directamente
            // en el orden visible usaría una secuencia distinta a la del
            // publisher, y dos operaciones concurrentes podrían trabarse entre sí.
            $locked = FrontendSection::query()
                ->where('frontend_page_id', $page->getKey())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $visible = $locked->sortBy([['sort_order', 'asc'], ['id', 'asc']])->values();

            // La portada queda fuera del juego: ni se mueve ella ni nadie le
            // pasa por encima, porque no participa de la lista que se permuta.
            $hero = $visible->firstWhere('type', 'hero');
            $movibles = $visible->reject(fn (FrontendSection $s): bool => $s->type === 'hero')->values()->all();

            $desde = null;

            foreach ($movibles as $i => $candidata) {
                if ($candidata->getKey() === $section->getKey()) {
                    $desde = $i;
                }
            }

            // El hero, o una sección que dejó de estar viva mientras tanto.
            if ($desde === null) {
                return;
            }

            $hasta = $desde + ($direction < 0 ? -1 : 1);

            // Ya está en el extremo: no es un error, simplemente no hay a dónde.
            if ($hasta < 0 || $hasta >= count($movibles)) {
                return;
            }

            [$movibles[$desde], $movibles[$hasta]] = [$movibles[$hasta], $movibles[$desde]];

            $this->writeSectionOrder($hero instanceof FrontendSection
                ? array_merge([$hero], $movibles)
                : $movibles);

            $page->increment('draft_revision');

            DB::afterCommit(fn () => $this->generation->bump());
        });
    }

    /**
     * Escribe 0..N-1 sobre las filas dadas, en dos pasos.
     *
     * El índice único (página, orden) NO se puede diferir: con SoftDeletes tiene
     * que ser un índice PARCIAL sobre las filas vivas, y PostgreSQL sólo difiere
     * CONSTRAINTs, nunca índices. Escribir los valores finales de a uno chocaría
     * contra la fila que todavía ocupa ese lugar.
     *
     * Por eso primero se corren TODAS a una banda libre. El corrimiento es una
     * constante —suma que preserva que sigan siendo distintas entre sí— elegida
     * para que la banda quede por encima tanto de los órdenes de hoy como de los
     * finales; si sólo esquivara los de hoy, el segundo paso chocaría contra la
     * propia banda. Recién ahí se escriben los definitivos.
     *
     * @param  list<FrontendSection>  $ordenadas
     */
    private function writeSectionOrder(array $ordenadas): void
    {
        if ($ordenadas === []) {
            return;
        }

        $actuales = array_map(fn (FrontendSection $s): int => (int) $s->sort_order, $ordenadas);
        $salto = max(max($actuales), count($ordenadas) - 1) - min($actuales) + 1;

        FrontendSection::query()
            ->whereKey(array_map(fn (FrontendSection $s) => $s->getKey(), $ordenadas))
            ->update(['sort_order' => DB::raw('sort_order + '.$salto)]);

        foreach ($ordenadas as $indice => $seccion) {
            FrontendSection::query()->whereKey($seccion->getKey())->update(['sort_order' => $indice]);
        }
    }

    /**
     * Every media_id anywhere in a payload (§16.4 references).
     *
     * @param  array<string, mixed>|null  $payload
     * @return list<string>
     */
    public function mediaIds(?array $payload): array
    {
        $ids = [];
        $walk = function ($value) use (&$walk, &$ids): void {
            if (is_array($value)) {
                foreach ($value as $key => $item) {
                    if ($key === 'media_id' && is_string($item) && $item !== '') {
                        $ids[] = $item;
                    } else {
                        $walk($item);
                    }
                }
            }
        };
        $walk($payload);

        return array_values(array_unique($ids));
    }

    private function reportCacheFailure(string $key, Throwable $e): void
    {
        Log::warning('Frontend page cache unavailable, serving straight from the database.', [
            'key' => $key,
            'exception' => $e::class,
            'message' => $e->getMessage(),
        ]);
    }
}
