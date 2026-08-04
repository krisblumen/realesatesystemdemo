<?php

namespace Database\Seeders;

use App\Actions\Frontend\SeedInversionService;
use App\Models\ServiceType;
use Illuminate\Database\Seeder;

class ServiceTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'comercializacion', 'label' => 'Comercialización', 'color' => 'info', 'sort_order' => 1],
            ['code' => 'arquitectura', 'label' => 'Arquitectura', 'color' => 'warning', 'sort_order' => 2],
            ['code' => 'construccion', 'label' => 'Construcción', 'color' => 'success', 'sort_order' => 3],
        ];

        foreach ($types as $type) {
            ServiceType::query()->firstOrCreate(['code' => $type['code']], $type);
        }

        // Inversión is reconciled through the same idempotent, non-destructive
        // action production uses (RFC-074); dev/test get it too.
        app(SeedInversionService::class)->run();
    }
}
