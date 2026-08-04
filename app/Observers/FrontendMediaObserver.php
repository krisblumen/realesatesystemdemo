<?php

namespace App\Observers;

use App\Models\FrontendSection;
use App\Models\FrontendService;
use App\Models\FrontendSetting;
use App\Services\Frontend\Contracts\FrontendPublisher;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * §16.8: every mutation that changes what the public frontend renders must bump
 * the durable cache generation — and Media is explicitly on that list. Adding
 * or removing a brand image without a bump leaves the site serving the old one
 * until the TTL expires.
 *
 * Scope is deliberately narrow: only media owned by frontend entities whose
 * collections feed the public render — the brand images of the singleton
 * (FrontendSetting), the service images (FrontendService) and the section
 * images (FrontendSection). A property cover has nothing to do with this kernel,
 * and bumping on it would invalidate the whole public cache on every listing
 * edit. The list mirrors every frontend `HasMedia` model, not just the first —
 * a media op on a service or section image must invalidate exactly like a brand
 * image does, whether it happens through Filament or straight through Media
 * Library.
 *
 * The bump is deferred to DB::afterCommit and is the ONLY invalidation
 * protocol — no targeted forget (§16.8). A rolled back write never bumps.
 */
class FrontendMediaObserver
{
    /** Morph classes whose media feeds the public frontend. */
    private const FRONTEND_MODELS = [
        FrontendSetting::class,
        FrontendService::class,
        FrontendSection::class,
    ];

    public function created(Media $media): void
    {
        $this->invalidate($media);
    }

    public function updated(Media $media): void
    {
        $this->invalidate($media);
    }

    public function deleted(Media $media): void
    {
        $this->invalidate($media);
    }

    private function invalidate(Media $media): void
    {
        if (! in_array($media->model_type, self::FRONTEND_MODELS, true)) {
            return;
        }

        DB::afterCommit(fn () => app(FrontendPublisher::class)->invalidate());
    }
}
