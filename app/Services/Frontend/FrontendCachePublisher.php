<?php

namespace App\Services\Frontend;

use App\Services\Frontend\Contracts\FrontendPublisher;

/**
 * Invalidation = durable generation bump (§16.8). Callers that mutate inside
 * a transaction must defer through DB::afterCommit so readers never see a new
 * generation pointing at not-yet-committed data.
 */
class FrontendCachePublisher implements FrontendPublisher
{
    public function __construct(private readonly FrontendCacheGeneration $generation) {}

    public function invalidate(): int
    {
        return $this->generation->bump();
    }
}
