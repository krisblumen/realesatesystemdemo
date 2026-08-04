<?php

namespace App\Jobs;

use App\Services\Frontend\Media\MediaPromotionState;
use App\Services\Frontend\Media\PromotableMediaOwners;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Copies ONE published image from the private draft disk to the public one and
 * flips the row that decides its URL (Épica 12.1 §7.8).
 *
 * Why the row and not just the bytes: `Media::getUrl()` resolves against the disk
 * stored ON THE ROW (`DefaultUrlGenerator:12` → `$this->getDisk()->url(...)`).
 * Copying bytes to the public disk and setting a flag would NOT make the URL
 * public. So the promotion copies, verifies, and only then updates `disk` and
 * `conversions_disk` — one single representation of the media, no parallel
 * resolver. (Safe: `moves_media_on_update` is false in this project, and the
 * default path generator ignores the disk, so Spatie moves nothing on save.)
 *
 * Why it locks the PAGE and not only the media row: with only the media lock, the
 * job could read a `published_revision` that still names the file, start copying,
 * and have a concurrent publish drop that reference — leaving public bytes nobody
 * references and breaking invariant 2. Holding the page lock makes the predicate
 * check and the state write atomic against publishing. The chain itself lives in
 * PublishedMediaReference::lockChainFor() — one implementation of the global
 * order `page → section → media` (§7.9), so publisher and job cannot diverge.
 *
 * Idempotent by construction: two runs take the same row lock, the second finds
 * `promoted` and returns. Retries never duplicate a file or a state.
 *
 * The private copy is NEVER deleted: v1 has no physical media deletion (§18.13).
 * It stops being referenced and shows up in `frontend:media:report-unreferenced`.
 *
 * Sirve a CUALQUIER dueño (Épica 12.3 §3.2). Lo que varía por dueño —la cadena
 * de locks y qué significa «vigente»— se resuelve por estrategia; este cuerpo no
 * tiene una sola rama por tipo, y un guard estructural lo verifica. Un tipo sin
 * estrategia registrada no promueve nada: fail-closed, sin default.
 */
class PromoteFrontendMedia implements ShouldQueue
{
    use Queueable;

    private const PRIVATE_DISK = 'frontend-private';

    private const PUBLIC_DISK = 'public';

    /** The uuid, not the model: a serialized model would be stale by the time it runs. */
    public function __construct(public readonly string $uuid) {}

    public function handle(PromotableMediaOwners $owners, MediaPromotionState $state): void
    {
        // La sintaxis es parte de la frontera (§7.10): `media.uuid` es una
        // columna uuid NATIVA, y consultarla con basura lanza SQLSTATE 22P02 en
        // vez de devolver «no encontrado». El guard va acá, que es la puerta de
        // entrada común a todas las estrategias.
        if (! Str::isUuid($this->uuid)) {
            return;
        }

        // Qué estrategia atiende esta media se resuelve ANTES de abrir la
        // transacción, sin lock: el tipo y la colección de una fila son
        // inmutables, así que la respuesta no puede cambiar después.
        $media = Media::query()->where('uuid', $this->uuid)->first();
        $owner = $media === null ? null : $owners->for($media);

        if ($owner === null) {
            // Media desconocida, o de un tipo sin estrategia declarada. Fail-
            // closed: no se toca la fila. Es lo que mantiene fuera de alcance a
            // la media de marca de FrontendSetting, que comparte tabla y flags.
            return;
        }

        DB::transaction(function () use ($owner, $state): void {
            DB::statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');

            // La cadena de locks vive en la estrategia, en UN solo lugar (§7.9):
            // `page → section → media` o `service → media` según el dueño.
            // Armarla acá a mano es exactamente cómo la auditoría del lote A
            // encontró este job invirtiendo los dos últimos.
            $chain = $owner->acquireLockChain($this->uuid);

            if (! $chain->isComplete()) {
                return;
            }

            $lockedOwner = $chain->owner;
            $lockedMedia = $chain->media;

            // Step 2 — already public. Terminal state: clean any residual pending
            // flag (invariant 1) and stop without copying.
            if ($state->isPromoted($lockedMedia)) {
                $state->clearPending($lockedMedia);

                return;
            }

            // Step 2-bis — la referencia pudo soltarse después de encolar el job.
            // Bajo el lock del dueño esta respuesta no puede cambiar mientras
            // actuamos sobre ella.
            if (! $owner->isReferencedByLiveContent($this->uuid, $lockedOwner)) {
                $state->clearPending($lockedMedia);

                Log::info('frontend.media_promotion_cancelled', [
                    'media' => $this->uuid,
                ] + $owner->logContext($lockedOwner));

                return;
            }

            $this->copyToPublicDisk($lockedMedia);

            $lockedMedia->disk = self::PUBLIC_DISK;
            $lockedMedia->conversions_disk = self::PUBLIC_DISK;
            $state->markPromoted($lockedMedia);

            Log::info('frontend.media_promoted', [
                'media' => $this->uuid,
                'authorizing_revision' => $lockedMedia->getCustomProperty(MediaPromotionState::AUTHORIZING_REVISION),
            ] + $owner->logContext($lockedOwner));
        });
    }

    /**
     * Copy the ORIGINAL preserving Spatie's relative path (`{id}/{file_name}`),
     * so the path stays valid after the disk flip, then VERIFY it landed.
     *
     * No conversions or responsive images: the `images` collection declares none
     * (§7.6). If any are ever added, this job must promote the whole family
     * before marking `promoted` — otherwise a derivative stays private and the
     * public URL half-breaks.
     */
    private function copyToPublicDisk(Media $media): void
    {
        $relative = $media->getPathRelativeToRoot();
        $from = Storage::disk(self::PRIVATE_DISK);
        $to = Storage::disk(self::PUBLIC_DISK);

        if (! $from->exists($relative)) {
            // Nothing to promote. Do NOT mark promoted: leave it pending so the
            // reconciliation can retry once the file is there.
            throw new RuntimeException("Missing source file for media {$this->uuid} at {$relative}.");
        }

        $stream = $from->readStream($relative);
        $to->writeStream($relative, $stream);

        if (is_resource($stream)) {
            fclose($stream);
        }

        // Verification is part of the contract: a silent failed write must not
        // become a `promoted` row pointing at nothing. Both disks are configured
        // with throw=false, so the return values alone cannot be trusted.
        if (! $to->exists($relative) || $to->size($relative) !== $from->size($relative)) {
            throw new RuntimeException("Verification failed promoting media {$this->uuid} to the public disk.");
        }
    }
}
