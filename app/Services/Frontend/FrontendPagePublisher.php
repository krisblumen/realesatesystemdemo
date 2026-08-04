<?php

namespace App\Services\Frontend;

use App\Jobs\PromoteFrontendMedia;
use App\Models\FrontendPage;
use App\Models\FrontendSection;
use App\Models\Project;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * Atomic publish of a page's draft into its published snapshot (RFC-075).
 *
 * Optimistic concurrency: the UI sends the draft_revision it loaded; if another
 * connection committed a draft mutation since, the publish ends in CONFLICT
 * without touching the snapshot. The lock order matches the content service
 * (page → sections id ASC) so the two can never deadlock, and READ COMMITTED is
 * set as the transaction's first statement.
 *
 * Media VALIDATION needs no lock: in v1 nothing deletes media (§16.4), so a
 * validated uuid cannot dangle. Publishing does LOCK the affected media rows,
 * for a different reason (Épica 12.1 §7.12, §18.18 punto 4): the promotion
 * state (`pending_promotion` / `promoted`) is read-modify-written here and by
 * PromoteFrontendMedia, so both must serialize on the row and MERGE the JSON
 * instead of overwriting it. Lock order stays the global one — page → sections
 * (id ASC) → media (uuid ASC) — shared with the job, so nothing deadlocks.
 */
class FrontendPagePublisher
{
    public function __construct(
        private readonly FrontendSectionSchema $schema,
        private readonly FrontendMediaReference $references,
        private readonly FrontendPageContentService $content,
        private readonly FrontendCacheGeneration $generation,
        private readonly FrontendPreflightValidator $preflight,
        private readonly PublishedMediaReference $publishedMedia,
    ) {}

    public function publish(FrontendPage $page, int $expectedDraftRevision, User $publisher): void
    {
        try {
            $this->publishLocked($page, $expectedDraftRevision, $publisher);
        } catch (ValidationException $e) {
            // Observability (RFC-077): a refused publish is logged with actor and
            // reason, never the full content.
            Log::warning('frontend.publish_failed', [
                'actor' => $publisher->getKey(),
                'entity' => "page:{$page->key}",
                'reason' => collect($e->errors())->flatten()->first(),
            ]);

            throw $e;
        }
    }

    private function publishLocked(FrontendPage $page, int $expectedDraftRevision, User $publisher): void
    {
        DB::transaction(function () use ($page, $expectedDraftRevision, $publisher): void {
            DB::statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');

            $locked = FrontendPage::query()->whereKey($page->getKey())->lockForUpdate()->firstOrFail();

            // A stale UI must not overwrite a concurrent draft change.
            if ($locked->draft_revision !== $expectedDraftRevision) {
                throw ValidationException::withMessages([
                    'expected_draft_revision' => 'El contenido cambió desde que abriste la página. Recarga y vuelve a publicar.',
                ]);
            }

            $sections = FrontendSection::query()
                ->where('frontend_page_id', $locked->getKey())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            // Preflight (RFC-077): page-level composition rules the per-section
            // schema cannot express (e.g. the hero H1). Runs under lock, before
            // any snapshot is written.
            $preflightErrors = $this->preflight->validatePage($locked, $sections);
            if ($preflightErrors !== []) {
                throw ValidationException::withMessages(['preflight' => $preflightErrors]);
            }

            $snapshot = [];
            foreach ($sections as $section) {
                // Registry boundary (C-E2): a section whose (page, key, type) is
                // not canonical — e.g. a row inserted directly by SQL — can never
                // reach the published snapshot.
                if (! $this->schema->isCanonicalSection($locked->key, $section->section_key, $section->type)) {
                    continue;
                }

                // The snapshot carries EVERY canonical section with its
                // is_enabled flag (M-E3), so a consumer can tell an explicitly
                // disabled section from an absent one. Only an ENABLED section
                // will render, so only it is re-validated under lock; a disabled
                // section's draft payload travels as-is.
                if ($section->is_enabled) {
                    // Short-circuit (C-E4): schema first; no eligibility query on
                    // a payload the schema already rejected (a malformed media_id
                    // is a schema error, so the uuid column never sees garbage).
                    $errors = $this->schema->validate($section->type, $section->payload);
                    if ($errors !== []) {
                        throw ValidationException::withMessages(["section.{$section->section_key}" => $errors]);
                    }

                    foreach ($this->content->mediaIds($section->payload) as $uuid) {
                        if (! $this->references->isEligible($uuid, $section, 'images')) {
                            $errors[] = "La sección «{$section->section_key}» referencia una imagen inválida.";
                        }
                    }

                    if ($errors !== []) {
                        throw ValidationException::withMessages(["section.{$section->section_key}" => $errors]);
                    }
                }

                $snapshot[] = [
                    'section_key' => $section->section_key,
                    'type' => $section->type,
                    'sort_order' => $section->sort_order,
                    'is_enabled' => (bool) $section->is_enabled,
                    'payload' => $section->payload,
                ];
            }

            usort($snapshot, fn (array $a, array $b): int => $a['sort_order'] <=> $b['sort_order']);

            // Computed ONCE, before the update: `update()` mutates the in-memory
            // attribute, so reading `$locked->revision + 1` again after it would
            // be off by one — which is exactly what the published log used to
            // report (audit G recommendation #1). The row and the log now agree.
            $newRevision = $locked->revision + 1;

            // §7.12 pasos 4/7/8 — promotion bookkeeping, additive to the publish.
            // The PREVIOUS snapshot is read HERE, under the page lock: reading it
            // from a model loaded before the lock would make the diff race with a
            // concurrent publish.
            $previousIds = $this->publishedMedia->mediaIdsOf($locked);
            $newIds = $this->content->mediaIds($snapshot);

            $added = array_values(array_diff($newIds, $previousIds));
            $removed = array_values(array_diff($previousIds, $newIds));

            // Lock the affected rows in uuid ASC — the tail of the global lock
            // order — and MERGE their state. `added` that is already `promoted`
            // is left untouched (terminal, invariant 1); `removed` that is still
            // `pending` goes back to draft (invariant 2, the M-2 cancellation).
            $touched = array_values(array_unique(array_merge($added, $removed)));
            sort($touched);

            foreach ($touched as $uuid) {
                $lockedMedia = Media::query()->where('uuid', $uuid)->lockForUpdate()->first();

                if ($lockedMedia === null) {
                    continue;
                }

                if (in_array($uuid, $added, true)) {
                    $this->publishedMedia->markPending($lockedMedia, $newRevision);
                } else {
                    $this->publishedMedia->clearPending($lockedMedia);
                }
            }

            // The snapshot is the COMPLETE publishable state (C-E3 / M-E3):
            // is_enabled, seo, every section with its own is_enabled, and the
            // inventory of dynamic entities resolved at publish. The public
            // render derives every field from here.
            $locked->update([
                'published_revision' => [
                    'is_enabled' => (bool) $locked->is_enabled,
                    'seo' => is_array($locked->seo) ? $locked->seo : null,
                    'sections' => $snapshot,
                    'generated_from_ids' => $this->generatedFromIds($sections, $locked->key),
                ],
                'published_at' => now(),
                'published_by' => $publisher->getKey(),
                'revision' => $newRevision,
                // QUÉ borrador quedó publicado. Es lo que permite decir después
                // si hay cambios pendientes: `revision` cuenta publicaciones y
                // `draft_revision` ediciones, así que compararlos no dice nada.
                'published_draft_revision' => $locked->draft_revision,
            ]);

            // Observability (RFC-077): who published what and when — the actor and
            // entity, never the content. The logged revision matches the row.
            Log::info('frontend.published', [
                'actor' => $publisher->getKey(),
                'entity' => "page:{$locked->key}",
                'revision' => $newRevision,
            ]);

            DB::afterCommit(function () use ($locked, $added): void {
                $this->generation->bump();
                Log::info('frontend.cache_generation_bumped', ['entity' => "page:{$locked->key}"]);

                // §7.12 paso 9 — la promoción corre FUERA de la transacción: el
                // sistema de archivos no participa de un rollback de PostgreSQL,
                // así que copiar adentro dejaría archivos públicos huérfanos ante
                // un fallo. Un rollback nunca llega a este callback.
                //
                // SÍNCRONA y no encolada (decisión del owner, 2026-07-28). Con
                // cola, publicar no bastaba: si no había un worker vivo la foto
                // no aparecía nunca y nada lo avisaba —pasó dos veces seguidas en
                // uso real—. Copiar cuesta milisegundos por imagen (medido: 11 a
                // 44 ms), muchísimo menos que el problema que evitaba.
                //
                // Cada promoción va en su propio try: un fallo de copia NO puede
                // tumbar una publicación que ya está commiteada. La media queda
                // `pending` y la reconciliación la levanta, que es exactamente
                // para lo que existe.
                foreach ($added as $uuid) {
                    try {
                        PromoteFrontendMedia::dispatchSync($uuid);
                    } catch (Throwable $e) {
                        Log::warning('frontend.media_promotion_deferred', [
                            'media' => $uuid,
                            'entity' => "page:{$locked->key}",
                            'reason' => $e->getMessage(),
                        ]);
                    }
                }
            });
        });
    }

    /**
     * Inventory of the dynamic entities each enabled dynamic section referenced
     * at publish time (§16.1.1 generated_from_ids): the snapshot records WHAT was
     * live so a consumer has a stable record, not only the section parameters.
     *
     * @param  Collection<int, FrontendSection>  $sections
     * @return list<array{section_key: string, ids: list<int>}>
     */
    private function generatedFromIds(Collection $sections, string $pageKey): array
    {
        $inventory = [];
        foreach ($sections as $section) {
            // Registry boundary (M-E4): a non-canonical dynamic row must not
            // contaminate the inventory either — the same fail-closed check the
            // sections snapshot applies.
            if (! $section->is_enabled || ! $this->schema->isCanonicalSection($pageKey, $section->section_key, $section->type)) {
                continue;
            }

            $ids = match ($section->type) {
                'featured_properties' => Property::query()->published()->featured()->pluck('id')->all(),
                'opportunity_properties' => Property::query()->published()->opportunity()->pluck('id')->all(),
                'featured_projects' => Project::query()->where('is_featured', true)->pluck('id')->all(),
                default => null,
            };

            if ($ids !== null) {
                $inventory[] = ['section_key' => $section->section_key, 'ids' => array_map('intval', $ids)];
            }
        }

        return $inventory;
    }
}
