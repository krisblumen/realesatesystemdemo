<?php

namespace App\Models;

use App\Services\Frontend\Contracts\FrontendPublisher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Site-wide singleton for the public frontend (Épica 12).
 *
 * Access it through current(): the row is created lazily and the UNIQUE +
 * CHECK constraints guarantee it can never have siblings. Publication is
 * strategy A (immediate): saving is publishing, there are no draft columns.
 *
 * Brand media resolution: ALWAYS through the explicit *_media_id columns.
 * Collections accumulate versions (no physical deletion in v1), so
 * getFirstMedia() is not deterministic and must not be used for rendering.
 */
class FrontendSetting extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $guarded = [];

    protected static function booted(): void
    {
        // Strategy A: saving IS publishing. The generation bump is deferred to
        // afterCommit so a reader never lands on a new generation whose data
        // has not been committed yet (§16.8).
        static::saved(function (): void {
            DB::afterCommit(
                fn () => app(FrontendPublisher::class)->invalidate()
            );
        });
    }

    protected function casts(): array
    {
        return [
            'business_hours' => 'array',
            'primary_cta' => 'array',
            'secondary_cta' => 'array',
            'navigation' => 'array',
            'footer' => 'array',
            'theme' => 'array',
            'social_links' => 'array',
        ];
    }

    public static function current(): self
    {
        return self::firstOrCreate(
            ['singleton_key' => 'default'],
            ['site_name' => 'New Hauz'],
        );
    }

    /**
     * Épica 12 rule (§16.4): NO singleFile() / onlyKeepLatest() on these
     * collections — both trigger clearMediaCollectionExcept() and physically
     * delete the previous file, which v1 forbids. Collections are storage;
     * the *_media_id columns decide which file is active.
     */
    public function registerMediaCollections(): void
    {
        $raster = ['image/png', 'image/jpeg', 'image/webp'];

        $this->addMediaCollection('logo-light')->acceptsMimeTypes($raster);
        $this->addMediaCollection('logo-dark')->acceptsMimeTypes($raster);
        $this->addMediaCollection('favicon')->acceptsMimeTypes(['image/png', 'image/x-icon', 'image/vnd.microsoft.icon']);
        $this->addMediaCollection('default-og-image')->acceptsMimeTypes($raster);
    }
}
