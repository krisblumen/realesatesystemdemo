<?php

namespace App\Console\Commands;

use App\Jobs\PromoteFrontendMedia;
use App\Models\FrontendPage;
use App\Models\FrontendService;
use App\Services\Frontend\Media\MediaPromotionState;
use App\Services\Frontend\Media\PromotableMediaOwner;
use App\Services\Frontend\Media\PromotableMediaOwners;
use App\Services\Frontend\Media\ServiceMediaReference;
use App\Services\Frontend\PublishedMediaReference;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Recovery net for the promotion pipeline (Épica 12.1 §7.8). The dispatch in
 * `DB::afterCommit` is an in-memory callback: a process that dies between commit
 * and callback, or a queue that loses the job, would leave a published image
 * private forever. This command makes that recoverable without a sixth table.
 *
 * TWO sweeps, both idempotent:
 *
 *  1. **Redispatch** — media a live published revision references that is not
 *     `promoted` yet. Re-queues the same idempotent job.
 *  2. **Cleanup** — media still flagged `pending_promotion` that NO live
 *     published revision references any more (M-2): a publish dropped the
 *     reference before the job ran, so the flag would stay stale forever
 *     (sweep 1 never sees it — it is not referenced). Returns it to `draft`.
 *
 * Scope: FrontendSection/`images` only. FrontendService and FrontendSetting are
 * outside Épica 12.1 (§0.8) and their flags must never be touched (M-6).
 *
 * It NEVER deletes a file: v1 has no physical media deletion (§18.13).
 */
class ReconcileFrontendMediaPromotions extends Command
{
    protected $signature = 'frontend:media:reconcile {--dry-run : Report what would change without writing}';

    protected $description = 'Re-queue missed frontend media promotions and clear pending flags that lost their reference.';

    public function handle(
        PublishedMediaReference $published,
        PromotableMediaOwners $owners,
        MediaPromotionState $state,
        ServiceMediaReference $services,
    ): int {
        $dryRun = (bool) $this->option('dry-run');

        $redispatched = $this->redispatchMissing($published, $dryRun)
            + $this->redispatchMissingServices($services, $state, $dryRun);

        // El barrido 2 recorre TODAS las estrategias declaradas: secciones y
        // servicios desde 12.3. `FrontendSetting` sigue fuera porque no tiene
        // estrategia registrada, no por un filtro escrito acá.
        $cleared = 0;

        foreach ($owners->all() as $owner) {
            $cleared += $this->clearDangling($owner, $state, $dryRun);
        }

        $this->info(sprintf(
            '%s%d promotion(s) re-queued, %d stale pending flag(s) cleared.',
            $dryRun ? '[dry-run] ' : '',
            $redispatched,
            $cleared,
        ));

        return self::SUCCESS;
    }

    /** Sweep 1: referenced by a published revision, still not promoted. */
    private function redispatchMissing(PublishedMediaReference $published, bool $dryRun): int
    {
        $count = 0;

        FrontendPage::query()
            ->whereNotNull('published_revision')
            ->orderBy('id')
            ->cursor()
            ->each(function (FrontendPage $page) use ($published, $dryRun, &$count): void {
                foreach ($published->mediaIdsOf($page) as $uuid) {
                    // resolvePublished() applies the whole guard chain: uuid
                    // format, morph type, collection and page ownership. An
                    // out-of-scope or bogus reference simply is not our business.
                    $media = $published->resolvePublished($uuid, $page);

                    if ($media === null || $published->isPromoted($media)) {
                        continue;
                    }

                    $count++;

                    if (! $dryRun) {
                        // SÍNCRONA, igual que el mecanismo primario: si el
                        // rescate también dependiera de un worker, no rescataría
                        // nada justamente en el escenario que lo hace falta.
                        $this->promoteNow($uuid);
                    }
                }
            });

        return $count;
    }

    /**
     * Barrido 1-bis: la imagen VIGENTE de un servicio que todavía no se promovió
     * (Épica 12.3). Es la red de seguridad de §4: si el despacho `afterCommit`
     * no llegó a correr, acá se recupera.
     */
    private function redispatchMissingServices(ServiceMediaReference $services, MediaPromotionState $state, bool $dryRun): int
    {
        $count = 0;

        FrontendService::query()
            ->whereNotNull('image_media_id')
            ->orderBy('id')
            ->cursor()
            ->each(function (FrontendService $service) use ($services, $state, $dryRun, &$count): void {
                $media = Media::query()
                    ->where('uuid', $service->image_media_id)
                    ->where('model_type', $services->modelType())
                    ->where('model_id', $service->getKey())
                    ->where('collection_name', $services->collection())
                    ->first();

                if ($media === null || $state->isPromoted($media)) {
                    return;
                }

                $count++;

                if (! $dryRun) {
                    $this->promoteNow((string) $media->uuid);
                }
            });

        return $count;
    }

    /**
     * Promueve en el acto y absorbe el fallo: un archivo que todavía no está
     * disponible no debe cortar el barrido de los demás.
     */
    private function promoteNow(string $uuid): void
    {
        try {
            PromoteFrontendMedia::dispatchSync($uuid);
        } catch (\Throwable $e) {
            Log::warning('frontend.media_promotion_deferred', [
                'media' => $uuid,
                'reason' => $e->getMessage(),
            ]);
        }
    }

    /** Sweep 2: flagged pending, referenced by nobody (M-2). */
    private function clearDangling(PromotableMediaOwner $owner, MediaPromotionState $state, bool $dryRun): int
    {
        $count = 0;

        foreach ($owner->danglingPending() as $media) {
            if ($dryRun) {
                $count++;

                continue;
            }

            // Counted only when the flag was ACTUALLY cleared: the scan is
            // unlocked, so a publish can re-reference the media between the sweep
            // and the transaction. Counting candidates would report a cleanup
            // that never happened.
            $cleared = DB::transaction(function () use ($media, $owner, $state): bool {
                DB::statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');

                // La MISMA cadena que usa el job (§7.9), la que declare este
                // dueño. Y el predicado se revalida BAJO el lock, porque entre el
                // barrido y la transacción alguien pudo volver a referenciarla.
                $chain = $owner->acquireLockChain((string) $media->uuid);

                if (! $chain->isComplete()) {
                    return false;
                }

                if ($owner->isReferencedByLiveContent((string) $chain->media->uuid, $chain->owner)) {
                    return false;
                }

                $state->clearPending($chain->media);

                return true;
            });

            if ($cleared) {
                $count++;
            }
        }

        return $count;
    }
}
