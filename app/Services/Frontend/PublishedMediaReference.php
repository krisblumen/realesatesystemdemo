<?php

namespace App\Services\Frontend;

use App\Models\FrontendPage;
use App\Models\FrontendSection;
use App\Services\Frontend\Media\MediaLockChain;
use App\Services\Frontend\Media\MediaPromotionState;
use App\Services\Frontend\Media\PromotableMediaOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Single frontier for everything that asks «what does the PUBLISHED revision
 * reference, and which file may it show?» (Épica 12.1 §7.8, §7.11).
 *
 * Two problems live here, and both are identity problems:
 *
 * 1. **Resolution.** `section_key` is a REUSABLE editorial key, not a historical
 *    identity: the unique index is partial (`WHERE deleted_at IS NULL`), so a
 *    soft-deleted section and a live one can share a key. Resolving a published
 *    snapshot by key would bind it to the wrong owner (verified: soft-delete
 *    `hero` then recreate it, and `keyBy('section_key')` returns the NEW row).
 *    So a published media resolves through ITS OWN ROW: `Media.model_id` IS the
 *    owner. That identity survives soft-delete and key recreation, and needs no
 *    change to the snapshot format — already published revisions are fixed too.
 *
 * 2. **The promotion predicate.** Publisher, job and reconciliation must all
 *    answer «is this uuid still referenced by the live published revision?» with
 *    ONE definition, or they drift and leave `pending_promotion` stale forever.
 *    {@see isReferencedByPublishedRevision()} is that definition; the three of
 *    them call it, never their own query.
 *
 * Scope: FrontendSection/`images` only. This class stays the strategy for PAGE
 * sections and nothing else; `FrontendService.image` has its own implementation
 * of {@see PromotableMediaOwner} (Épica 12.3 §3), because «still referenced»
 * means something different there — a service has no published revision, only a
 * column. What the two share is the pipeline, not the predicate.
 */
class PublishedMediaReference implements PromotableMediaOwner
{
    /** Referenced by a published revision, not yet copied to the public disk. */
    public const PENDING = MediaPromotionState::PENDING;

    /** Copied, verified and flipped to the public disk. TERMINAL state. */
    public const PROMOTED = MediaPromotionState::PROMOTED;

    /** The page `revision` that authorized the promotion (observability only). */
    public const AUTHORIZING_REVISION = MediaPromotionState::AUTHORIZING_REVISION;

    /** The only collection Épica 12.1 promotes. */
    public const COLLECTION = 'images';

    public function __construct(
        private readonly FrontendPageContentService $content,
        private readonly MediaPromotionState $state = new MediaPromotionState,
    ) {}

    // ------------------------------------------- PromotableMediaOwner ------
    //
    // Adaptadores de la Épica 12.3 §3.1. NINGÚN cuerpo de los métodos de dominio
    // de abajo se reescribe: esto traduce firmas, nada más.

    public function modelType(): string
    {
        return (new FrontendSection)->getMorphClass();
    }

    public function collection(): string
    {
        return self::COLLECTION;
    }

    /** La cadena `page → section → media` que ya vive en lockChainFor(), tipada. */
    public function acquireLockChain(string $uuid): MediaLockChain
    {
        [$page, $media] = $this->lockChainFor($uuid);

        return new MediaLockChain($page, $media);
    }

    public function isReferencedByLiveContent(string $uuid, Model $lockedOwner): bool
    {
        return $lockedOwner instanceof FrontendPage
            && $this->isReferencedByPublishedRevision($uuid, $lockedOwner);
    }

    /** @return array<string, scalar|null> */
    public function logContext(Model $owner): array
    {
        return $owner instanceof FrontendPage
            ? ['entity' => "page:{$owner->key}", 'observed_revision' => $owner->revision]
            : ['entity' => 'page:?'];
    }

    /**
     * Every media uuid the page's PUBLISHED snapshot references.
     *
     * Reuses the existing walker instead of re-implementing it: one definition of
     * "a media_id anywhere in a payload" for draft and published alike.
     *
     * @return list<string>
     */
    public function mediaIdsOf(?FrontendPage $page): array
    {
        $snapshot = $page?->published_revision;

        return is_array($snapshot) ? $this->content->mediaIds($snapshot) : [];
    }

    /**
     * THE predicate. `false` for a malformed uuid, a page without a snapshot, or
     * a uuid the snapshot no longer names.
     */
    public function isReferencedByPublishedRevision(string $uuid, ?FrontendPage $page): bool
    {
        if (! Str::isUuid($uuid) || $page === null) {
            return false;
        }

        return in_array($uuid, $this->mediaIdsOf($page), true);
    }

    /**
     * Resolve a media for a PUBLISHED revision without knowing its owner in
     * advance — the whole point of §7.11. Returns null on any failure: malformed
     * uuid, unknown uuid, wrong morph type, wrong collection, or an owner that
     * belongs to another page.
     *
     * `withTrashed()`: a soft-deleted section is still the rightful owner of the
     * media its published revision references. The owner only SCOPES the lookup;
     * editorial currency is the snapshot's business, not the draft row's.
     *
     * Promotion is NOT checked here — that is a render policy (§7.8), applied by
     * the presenter, so this method stays a pure identity/authorization boundary.
     */
    public function resolvePublished(string $uuid, ?FrontendPage $page, string $collection = self::COLLECTION): ?Media
    {
        if ($page === null || ! Str::isUuid($uuid)) {
            return null;
        }

        $media = Media::query()
            ->where('uuid', $uuid)
            ->where('model_type', (new FrontendSection)->getMorphClass())
            ->where('collection_name', $collection)
            ->first();

        if ($media === null) {
            return null;
        }

        $owner = $this->owningSection($media);

        return $owner !== null && (int) $owner->frontend_page_id === (int) $page->getKey()
            ? $media
            : null;
    }

    /**
     * The section that owns a media, soft-deleted or not. Null when the media
     * belongs to another model entirely.
     */
    public function owningSection(Media $media): ?FrontendSection
    {
        if ($media->model_type !== (new FrontendSection)->getMorphClass()) {
            return null;
        }

        return FrontendSection::withTrashed()->whereKey($media->model_id)->first();
    }

    /**
     * The page a media ultimately belongs to — what the promotion job needs to
     * take the page lock first (§7.9 lock order). Resolved through the owning
     * section WITH trashed, so a soft-deleted section never orphans the job.
     */
    public function owningPage(Media $media): ?FrontendPage
    {
        $section = $this->owningSection($media);

        return $section === null
            ? null
            : FrontendPage::query()->whereKey($section->frontend_page_id)->first();
    }

    /**
     * Media flagged `pending_promotion` that NO live published revision
     * references any more — the state M-2 leaves behind when a publish drops a
     * reference before the job runs. The reconciliation clears these.
     *
     * Scoped to FrontendSection/`images`: FrontendService and FrontendSetting are
     * outside Épica 12.1 and their flags must never be touched (M-6).
     *
     * @return iterable<Media>
     */
    public function danglingPending(): iterable
    {
        $candidates = Media::query()
            ->where('model_type', (new FrontendSection)->getMorphClass())
            ->where('collection_name', self::COLLECTION)
            ->where('custom_properties->'.self::PENDING, true)
            ->cursor();

        foreach ($candidates as $media) {
            if (! $this->isReferencedByPublishedRevision((string) $media->uuid, $this->owningPage($media))) {
                yield $media;
            }
        }
    }

    /**
     * Acquire the GLOBAL lock chain for ONE media — `page → section → media` —
     * and return the locked page and media.
     *
     * ONE implementation on purpose. The Lote A audit found the job taking
     * `page → media → section` while its own comment claimed the documented
     * order: every actor was assembling the chain by hand, so one of them
     * silently inverted it. Single-media actors (the promotion job and the
     * reconciliation) now share this routine and cannot diverge again.
     *
     * The publisher is structurally different — it holds the page and ALL its
     * sections and then locks a SET of media by uuid ASC — so it keeps its own
     * sequence; that tail is the same order this chain ends with.
     *
     * Callers must already be inside a transaction.
     *
     * @return array{0: ?FrontendPage, 1: ?Media}
     */
    public function lockChainFor(string $uuid): array
    {
        // Unlocked discovery of WHICH rows to lock. A media's section and page
        // are immutable, so this cannot pick the wrong targets — and doing it
        // first is what allows the locks below to follow the order exactly.
        $media = Media::query()->where('uuid', $uuid)->first();

        if ($media === null) {
            return [null, null];
        }

        $section = $this->owningSection($media);
        $page = $this->owningPage($media);

        if ($page === null) {
            return [null, null];
        }

        // 1. page
        $lockedPage = FrontendPage::query()->whereKey($page->getKey())->lockForUpdate()->first();

        if ($lockedPage === null) {
            return [null, null];
        }

        // 2. section (id ASC — a single row here)
        if ($section !== null) {
            FrontendSection::withTrashed()->whereKey($section->getKey())->lockForUpdate()->first();
        }

        // 3. media (uuid ASC — a single row here)
        $lockedMedia = Media::query()->where('uuid', $uuid)->lockForUpdate()->first();

        return [$lockedPage, $lockedMedia];
    }

    // ---------------------------------------------------------------- state --
    //
    // Delegación pura a MediaPromotionState (Épica 12.3 §3.1b): la máquina de
    // estados es idéntica para cualquier dueño, así que vive en UN lugar. Las
    // firmas no cambian y las pruebas de 12.1-A/B pasan sin tocarse — ese es el
    // criterio que hace verificable la extracción.

    public function isPromoted(Media $media): bool
    {
        return $this->state->isPromoted($media);
    }

    public function isPending(Media $media): bool
    {
        return $this->state->isPending($media);
    }

    public function markPending(Media $lockedMedia, int $authorizingRevision): void
    {
        $this->state->markPending($lockedMedia, $authorizingRevision);
    }

    public function clearPending(Media $lockedMedia): void
    {
        $this->state->clearPending($lockedMedia);
    }

    public function markPromoted(Media $lockedMedia): void
    {
        $this->state->markPromoted($lockedMedia);
    }
}
