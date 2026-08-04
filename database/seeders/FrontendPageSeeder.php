<?php

namespace Database\Seeders;

use App\Actions\Frontend\SeedFrontendPages;
use Illuminate\Database\Seeder;

/**
 * Dev/test only (RFC-075): the production source is the seed migration. Delegates
 * to the same idempotent action so the canonical pages/sections always exist.
 */
class FrontendPageSeeder extends Seeder
{
    public function run(): void
    {
        app(SeedFrontendPages::class)->run();
    }
}
