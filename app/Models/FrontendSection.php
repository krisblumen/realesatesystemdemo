<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A draft editable section of a page (RFC-075). SoftDeletes is mandatory: it
 * stops Spatie from cascading a delete onto media the published snapshot still
 * references (InteractsWithMedia); forceDelete is barred by the policy.
 *
 * The `images` collection has NO singleFile()/onlyKeepLatest() — those delete
 * files on replace, which v1 forbids (§18.13, learned in C-D1). The payload
 * references the current media by validated media_id (§16.4); superseded files
 * simply stop being referenced.
 *
 * It lives on the PRIVATE disk (§16.4, Épica 12.1 §7.6). Media-library defaults
 * to `public` (`config/media-library.php:36`), which would have made every draft
 * image publicly reachable before anyone published it. Draft images are read
 * only through the owner-only route (FrontendSectionMediaController) and are
 * copied to the public disk by PromoteFrontendMedia when a revision that
 * references them is published.
 *
 * No conversions and no responsive images on purpose: promotion moves the
 * ORIGINAL only. Adding a derivative here without extending the job would leave
 * it behind on the private disk (Épica 12.1 §7.6, M-2).
 */
class FrontendSection extends Model implements HasMedia
{
    use InteractsWithMedia;
    use SoftDeletes;

    protected $fillable = [
        'frontend_page_id',
        'section_key',
        'type',
        'payload',
        'is_enabled',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'is_enabled' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(FrontendPage::class, 'frontend_page_id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images')->useDisk('frontend-private');
    }
}
