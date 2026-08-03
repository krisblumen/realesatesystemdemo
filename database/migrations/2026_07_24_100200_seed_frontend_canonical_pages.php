<?php

use App\Actions\Frontend\SeedFrontendPages;
use Illuminate\Database\Migrations\Migration;

/**
 * Production seeds the five canonical pages through the idempotent action
 * (RFC-075): production deploys with `migrate --force` and no seeders, so the
 * source of truth is this migration; FrontendPageSeeder is dev/test only.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(SeedFrontendPages::class)->run();
    }

    public function down(): void
    {
        // The pages/sections tables are dropped by their own down() migrations.
    }
};
