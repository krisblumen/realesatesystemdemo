<?php

namespace Database\Seeders;

use App\Models\FrontendService;
use App\Models\ServiceType;
use Illuminate\Database\Seeder;

/**
 * Ensures every ServiceType has a FrontendService (RFC-074): fail-closed
 * eligibility means a type without a frontend row is silently unavailable, so
 * dev/test data (and any type added after the create migration) gets a row.
 *
 * Idempotent and non-destructive — firstOrCreate never overwrites a row an
 * owner or a test customised. The production source stays the migration backfill
 * plus SeedInversionService.
 */
class FrontendServiceSeeder extends Seeder
{
    public function run(): void
    {
        foreach (ServiceType::query()->get() as $type) {
            FrontendService::query()->firstOrCreate(
                ['service_type_code' => $type->code],
                [
                    'title' => $type->label,
                    'show_in_home' => true,
                    'show_in_services' => true,
                    'allow_leads' => $type->code !== 'inversion',
                    'sort_order' => $type->sort_order ?? 0,
                ],
            );
        }
    }
}
