<?php

namespace App\Services\Frontend\Contracts;

/**
 * Read-side contract of the frontend kernel (RFC-076, §16.8): Blade views
 * never query models directly — they consume these normalized structs, and
 * every domain carries its own hardcoded fallback (§16.7).
 *
 * The contract grows incrementally with the batches: batch A ships settings();
 * theme/navigation/footer/services/page join in batches B..F.
 */
interface FrontendContent
{
    /**
     * @return array{
     *     site_name: string,
     *     tagline: ?string,
     *     contact: array{phone: ?string, whatsapp: ?string, whatsapp_href: ?string, email: string, address: ?string, hours: ?array},
     *     seo: array{meta_title: ?string, meta_description: ?string, og_title: ?string, og_description: ?string},
     *     brand: array{logo_light_url: string, logo_dark_url: string, favicon_url: string, og_image_url: string}
     * }
     */
    public function settings(): array;
}
