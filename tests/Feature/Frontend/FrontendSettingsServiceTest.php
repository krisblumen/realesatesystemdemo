<?php

namespace Tests\Feature\Frontend;

use App\Models\FrontendSetting;
use App\Services\Frontend\FrontendCacheGeneration;
use App\Services\Frontend\FrontendSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Kernel read contract for settings() (§16.3) with the exact fallbacks of
 * §16.7. Brand media resolves ONLY through the explicit *_media_id columns:
 * a file sitting in the collection without being referenced must not leak
 * into the render.
 */
class FrontendSettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_without_configuration_it_returns_the_exact_hardcoded_fallbacks(): void
    {
        $dto = app(FrontendSettingsService::class)->settings();

        $this->assertSame('Landra', $dto['site_name']);
        $this->assertSame('hola@landracore.com', $dto['contact']['email']);
        $this->assertSame('524422722623', $dto['contact']['whatsapp']);
        $this->assertSame('https://wa.me/524422722623', $dto['contact']['whatsapp_href']);
        $this->assertStringContainsString('images/brand/logo-on-light.svg', $dto['brand']['logo_light_url']);
        $this->assertStringContainsString('images/brand/logo-on-dark.svg', $dto['brand']['logo_dark_url']);
        $this->assertStringContainsString('images/brand/landra-core.ico', $dto['brand']['favicon_url']);
        $this->assertStringContainsString('images/metaimage/meta_image_landra.jpg', $dto['brand']['og_image_url']);
    }

    public function test_saved_values_override_fallbacks(): void
    {
        $setting = FrontendSetting::current();
        $setting->update([
            'site_name' => 'Inmobiliaria Prueba',
            'public_email' => 'contacto@prueba.mx',
            'whatsapp_phone' => '5215512345678',
        ]);

        $dto = app(FrontendSettingsService::class)->settings();

        $this->assertSame('Inmobiliaria Prueba', $dto['site_name']);
        $this->assertSame('contacto@prueba.mx', $dto['contact']['email']);
        $this->assertSame('https://wa.me/5215512345678', $dto['contact']['whatsapp_href']);
    }

    public function test_brand_resolves_by_explicit_media_id_never_by_collection_membership(): void
    {
        $setting = FrontendSetting::current();

        // Two files in the same collection: only the referenced one may win.
        $old = $setting->addMedia(UploadedFile::fake()->image('logo-old.png'))
            ->preservingOriginal()->toMediaCollection('logo-light');
        $new = $setting->addMedia(UploadedFile::fake()->image('logo-new.png'))
            ->preservingOriginal()->toMediaCollection('logo-light');

        // Unreferenced: collection has files, the column is null -> fallback.
        $dto = app(FrontendSettingsService::class)->settings();
        $this->assertStringContainsString('logo-on-light.svg', $dto['brand']['logo_light_url']);

        // Referenced: the OLD one on purpose — proves it is the column deciding,
        // not "latest in collection".
        $setting->update(['logo_light_media_id' => $old->uuid]);

        $dto = app(FrontendSettingsService::class)->settings();
        $this->assertSame($old->getUrl(), $dto['brand']['logo_light_url']);
        $this->assertNotSame($new->getUrl(), $dto['brand']['logo_light_url']);
    }

    public function test_reads_are_cached_under_the_generation_key_and_saving_invalidates(): void
    {
        config(['cache.default' => 'database']);
        Cache::store('database')->flush();

        $service = app(FrontendSettingsService::class);
        $generation = app(FrontendCacheGeneration::class);

        $before = $generation->current();
        $service->settings();
        $this->assertTrue(
            Cache::store('database')->has("frontend:g{$before}:settings"),
            'The read must populate the generation-scoped key.'
        );

        // Saving the singleton must bump the generation (afterCommit observer):
        // the next read lands on a NEW key, so a stale refill can never be read back.
        FrontendSetting::current()->update(['site_name' => 'Cambiada']);

        $after = $generation->current();
        $this->assertGreaterThan($before, $after, 'Saving must bump the cache generation.');
        $this->assertSame('Cambiada', $service->settings()['site_name']);
    }
}
