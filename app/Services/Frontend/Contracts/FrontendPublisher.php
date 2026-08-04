<?php

namespace App\Services\Frontend\Contracts;

/**
 * Write-side contract of the frontend kernel (RFC-076, §16.8).
 *
 * Invalidation is a GENERATION BUMP, never a targeted forget: readers move to
 * the new frontend:g{N}:* keys and a concurrent refill that wrote under the
 * old generation is simply never read again.
 */
interface FrontendPublisher
{
    /** Returns the new generation. */
    public function invalidate(): int;
}
