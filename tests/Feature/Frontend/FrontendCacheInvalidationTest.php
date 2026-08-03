<?php

namespace Tests\Feature\Frontend;

use App\Services\Frontend\FrontendCacheGeneration;
use App\Services\Frontend\FrontendSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\UsesRealPostgresConnections;
use Tests\TestCase;

/**
 * The generation bump is the ONLY invalidation (RFC-076): a refill written under
 * the old generation lives at `frontend:g{N-1}:*` and is never read again after
 * the bump, because every reader now composes a `g{N}` key. Verified with the
 * real database cache store and a second PostgreSQL connection driving the bump.
 */
class FrontendCacheInvalidationTest extends TestCase
{
    use RefreshDatabase, UsesRealPostgresConnections;

    public function test_old_refill_is_not_read_after_generation_bump(): void
    {
        config(['cache.default' => 'database']);

        $generation = app(FrontendCacheGeneration::class)->current();

        // Simulate a slow reader that missed the cache at generation N and wrote
        // its (now doomed) refill just after another connection bumped.
        Cache::put("frontend:g{$generation}:settings", ['site_name' => 'STALE-REFILL'], 300);

        // A second real connection commits the bump to N+1 (as the owner's save
        // would, in another request).
        $b = $this->realConnection('pgsql_cache_bump');
        try {
            $b->statement('update frontend_cache_generation set generation = generation + 1 where id = 1');

            // A NEW public request: fresh services read the bumped generation,
            // compose a g{N+1} key, miss the stale g{N} entry and rebuild from
            // the database. (The per-request memo is why we drop the instances.)
            $this->app->forgetInstance(FrontendCacheGeneration::class);
            $this->app->forgetInstance(FrontendSettingsService::class);

            $settings = app(FrontendSettingsService::class)->settings();

            $this->assertNotSame('STALE-REFILL', $settings['site_name'], 'The old-generation refill must never be read again.');
            $this->assertSame($generation + 1, app(FrontendCacheGeneration::class)->current());

            // The doomed entry still physically exists at the old key (only TTL
            // collects it) — proving invalidation is by namespace, not deletion.
            $this->assertTrue(Cache::has("frontend:g{$generation}:settings"));
        } finally {
            $this->releaseRealConnections(['update frontend_cache_generation set generation = 1 where id = 1']);
            DB::table('cache')->delete();
        }
    }
}
