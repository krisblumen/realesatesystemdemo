<?php

namespace Tests\Feature\Frontend;

use App\Services\Frontend\Contracts\FrontendContent;
use App\Services\Frontend\FrontendMediaReference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * M-3 and M-4 (audit of batch A).
 *
 * M-3 — §16.1 mandates "hard validation on save PLUS defensive normalization
 * at the render boundary". The form is not the only writer: imports, manual
 * SQL, legacy rows or a future bug can persist garbage. Whatever the database
 * holds, the public site must publish either something valid or the exact
 * fallback — never a broken mailto or https://wa.me/1.
 *
 * M-4 — cache is an optimization with a TTL safety net, not a dependency. If
 * the store is down the site must still render from the database; a cache
 * outage turning public pages into 500s is a self-inflicted incident.
 */
class FrontendReadBoundaryTest extends TestCase
{
    use RefreshDatabase;

    /** Writes straight to SQL to bypass every application-level guard. */
    private function persistRaw(array $columns): void
    {
        DB::table('frontend_settings')->updateOrInsert(
            ['singleton_key' => 'default'],
            $columns + ['site_name' => 'Landra', 'created_at' => now(), 'updated_at' => now()],
        );
    }

    public function test_an_invalid_persisted_email_degrades_to_the_exact_fallback(): void
    {
        $this->persistRaw(['public_email' => 'not-an-email']);

        $this->assertSame(
            'hola@landracore.com',
            app(FrontendContent::class)->settings()['contact']['email'],
        );
    }

    public function test_an_unusable_persisted_whatsapp_degrades_to_the_exact_fallback(): void
    {
        // 'x1' used to yield https://wa.me/1 — a link that looks real and is not.
        $this->persistRaw(['whatsapp_phone' => 'x1']);

        $dto = app(FrontendContent::class)->settings()['contact'];

        $this->assertSame('524422722623', $dto['whatsapp']);
        $this->assertSame('https://wa.me/524422722623', $dto['whatsapp_href']);
    }

    public function test_a_valid_persisted_contact_is_respected(): void
    {
        $this->persistRaw([
            'public_email' => 'contacto@prueba.mx',
            'whatsapp_phone' => '+52 (155) 1234-5678',
        ]);

        $dto = app(FrontendContent::class)->settings()['contact'];

        $this->assertSame('contacto@prueba.mx', $dto['email']);
        $this->assertSame('5215512345678', $dto['whatsapp'], 'Formatting characters are stripped, digits kept.');
        $this->assertSame('https://wa.me/5215512345678', $dto['whatsapp_href']);
    }

    public function test_an_undefined_cache_store_degrades_to_a_direct_read_and_logs(): void
    {
        // The REAL Laravel failure, not a generic mock: CacheManager::resolve()
        // throws the global \InvalidArgumentException — which extends
        // LogicException, so it is neither a RuntimeException nor the PSR
        // interface. Enumerating exception classes missed it once already.
        $this->persistRaw(['public_email' => 'contacto@prueba.mx']);
        config(['cache.default' => 'audit-missing-store']);

        Log::shouldReceive('warning')->atLeast()->once();

        $dto = app(FrontendContent::class)->settings();

        $this->assertSame('contacto@prueba.mx', $dto['contact']['email'],
            'An invalid cache configuration must not take the public site down.');
    }

    public function test_an_undefined_cache_store_still_serves_the_exact_fallbacks(): void
    {
        // No configuration at all AND no cache: the public site must still
        // render the documented brand/contact fallbacks (§16.7).
        config(['cache.default' => 'audit-missing-store']);
        Log::shouldReceive('warning')->atLeast()->once();

        $dto = app(FrontendContent::class)->settings();

        $this->assertSame('hola@landracore.com', $dto['contact']['email']);
        $this->assertSame('https://wa.me/524422722623', $dto['contact']['whatsapp_href']);
        $this->assertStringContainsString('logo-on-light.svg', $dto['brand']['logo_light_url']);
    }

    public function test_a_store_that_throws_while_reading_degrades_to_a_direct_read(): void
    {
        $this->persistRaw(['public_email' => 'contacto@prueba.mx']);

        Cache::shouldReceive('get')->once()->andThrow(new \RuntimeException('store down'));
        Cache::shouldReceive('put')->andReturnTrue();
        Log::shouldReceive('warning')->atLeast()->once();

        $this->assertSame(
            'contacto@prueba.mx',
            app(FrontendContent::class)->settings()['contact']['email'],
        );
    }

    public function test_a_store_that_throws_while_writing_still_returns_the_value(): void
    {
        // A write failure is even less excusable as a 500: the data is already
        // in hand, only the optimization failed.
        $this->persistRaw(['public_email' => 'contacto@prueba.mx']);

        Cache::shouldReceive('get')->once()->andReturnNull();
        Cache::shouldReceive('put')->once()->andThrow(new \RuntimeException('store down'));
        Log::shouldReceive('warning')->atLeast()->once();

        $this->assertSame(
            'contacto@prueba.mx',
            app(FrontendContent::class)->settings()['contact']['email'],
        );
    }

    public function test_a_programming_error_inside_build_is_not_swallowed(): void
    {
        // Degrading on cache failure must not become a catch-all that hides
        // real bugs behind a silently wrong page. LogicException comes from
        // build(), not from the store, so it must surface.
        $this->persistRaw([]);

        $this->mock(FrontendMediaReference::class, function ($mock) {
            $mock->shouldReceive('resolve')->andThrow(new \LogicException('boom'));
        });

        $this->expectException(\LogicException::class);

        app(FrontendContent::class)->settings();
    }
}
