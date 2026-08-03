<?php

namespace App\Services\Frontend;

use Illuminate\Support\Facades\DB;

/**
 * Durable generation counter behind the frontend cache keys (frontend:g{N}:...).
 *
 * The bump is ONE atomic SQL statement — no read-modify-write in PHP, no
 * Cache::increment() (which returns false on a missing key with the database
 * store). Two concurrent bumps always produce two distinct increments.
 *
 * The current value is memoized per request and refreshed after every bump.
 */
class FrontendCacheGeneration
{
    private ?int $current = null;

    public function current(): int
    {
        return $this->current ??= (int) DB::selectOne(
            'select generation from frontend_cache_generation where id = 1'
        )->generation;
    }

    public function bump(): int
    {
        $generation = (int) DB::selectOne(
            'update frontend_cache_generation set generation = generation + 1 where id = 1 returning generation'
        )->generation;

        return $this->current = $generation;
    }
}
