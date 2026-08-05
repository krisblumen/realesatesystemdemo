<?php

namespace App\Services\Frontend;

use App\Models\FrontendSetting;
use App\Services\Frontend\Contracts\FrontendContent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * settings() read model (§16.3) with the exact fallbacks of §16.7.
 *
 * Cache keys carry the durable generation (frontend:g{N}:settings): a bump
 * moves every reader to a fresh key, so stale refills die unread. The short
 * TTL is only a safety net on top of that.
 *
 * Brand media resolves EXCLUSIVELY through the *_media_id columns. Files in a
 * collection that are not referenced by the column do not exist for the
 * render — getFirstMedia() is banned here (§16.4).
 */
class FrontendSettingsService implements FrontendContent
{
    private const TTL_SECONDS = 300;

    /** Exact fallbacks of §16.7, verified against the public layout. */
    private const FALLBACK_EMAIL = 'hola@landracore.com';

    private const FALLBACK_WHATSAPP = '524422722623';

    /** Shortest plausible international number; below this a wa.me link is a dead end. */
    private const MIN_PHONE_DIGITS = 10;

    public function __construct(
        private readonly FrontendCacheGeneration $generation,
        private readonly FrontendMediaReference $references,
    ) {}

    /**
     * Cache is an optimization with a TTL safety net, never a hard dependency:
     * a store outage must degrade to a direct read, not turn public pages into
     * 500s.
     *
     * The isolation here is STRUCTURAL, not a list of exception classes.
     * build() runs outside every cache try/catch, so its errors can never be
     * swallowed — while cache calls can be guarded broadly, which is what a
     * store failure actually needs. Enumerating classes already failed once:
     * CacheManager::resolve() throws the global \InvalidArgumentException
     * (CacheManager.php:120), a LogicException that is neither a
     * RuntimeException nor the PSR interface, so it slipped straight through.
     */
    public function settings(): array
    {
        $key = sprintf('frontend:g%d:settings', $this->generation->current());

        try {
            $cached = Cache::get($key);
        } catch (Throwable $e) {
            $this->reportCacheFailure('read', $key, $e);
            $cached = null;
        }

        if (is_array($cached)) {
            return $cached;
        }

        // Outside any cache handling on purpose: a domain or programming error
        // in build() must surface as itself, never as a silent stale page.
        $settings = $this->build();

        try {
            Cache::put($key, $settings, self::TTL_SECONDS);
        } catch (Throwable $e) {
            // The data is already in hand; only the optimization failed.
            $this->reportCacheFailure('write', $key, $e);
        }

        return $settings;
    }

    private function reportCacheFailure(string $operation, string $key, Throwable $e): void
    {
        Log::warning('Frontend settings cache unavailable, serving straight from the database.', [
            'operation' => $operation,
            'key' => $key,
            'exception' => $e::class,
            'message' => $e->getMessage(),
        ]);
    }

    private function build(): array
    {
        $setting = FrontendSetting::query()->where('singleton_key', 'default')->first();

        $whatsapp = $this->normalizedWhatsapp($setting?->whatsapp_phone);
        $email = $this->normalizedEmail($setting?->public_email);

        return [
            'site_name' => $setting?->site_name ?: 'Landra',
            'tagline' => $setting?->tagline,
            'contact' => [
                'phone' => $setting?->public_phone,
                'whatsapp' => $whatsapp,
                'whatsapp_href' => "https://wa.me/{$whatsapp}",
                'email' => $email,
                'address' => $setting?->public_address,
                'hours' => $setting?->business_hours,
            ],
            'seo' => [
                'meta_title' => $setting?->default_meta_title,
                'meta_description' => $setting?->default_meta_description,
                'og_title' => $setting?->default_og_title,
                'og_description' => $setting?->default_og_description,
            ],
            'brand' => [
                'logo_light_url' => $this->brandUrl($setting, 'logo_light_media_id', 'logo-light', asset('images/brand/logo-on-light.svg')),
                'logo_dark_url' => $this->brandUrl($setting, 'logo_dark_media_id', 'logo-dark', asset('images/brand/logo-on-dark.svg')),
                'favicon_url' => $this->brandUrl($setting, 'favicon_media_id', 'favicon', asset('images/brand/landra-core.ico')),
                'og_image_url' => $this->brandUrl($setting, 'og_image_media_id', 'default-og-image', asset('images/metaimage/meta_image_landra.jpg')),
            ],
        ];
    }

    /**
     * §16.1: hard validation on save is not enough — the form is not the only
     * writer. Imports, manual SQL or legacy rows can hold garbage, and the
     * public site must publish something valid or the exact fallback, never a
     * broken mailto.
     */
    private function normalizedEmail(?string $email): string
    {
        $email = trim((string) $email);

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : self::FALLBACK_EMAIL;
    }

    /**
     * A phone with too few digits used to produce https://wa.me/1 — a link
     * that looks real and goes nowhere. Below a plausible international length
     * the number is unusable, so we publish the fallback instead.
     */
    private function normalizedWhatsapp(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        return strlen($digits) >= self::MIN_PHONE_DIGITS ? $digits : self::FALLBACK_WHATSAPP;
    }

    private function brandUrl(?FrontendSetting $setting, string $column, string $collection, string $fallback): string
    {
        if ($setting === null) {
            return $fallback;
        }

        // Defensive boundary: the uuid must still belong to this singleton and
        // this collection; anything else falls back instead of leaking media.
        // Same rule as the save path — one implementation, no drift.
        return $this->references
            ->resolve($setting->{$column}, $setting, $collection)
            ?->getUrl() ?? $fallback;
    }
}
