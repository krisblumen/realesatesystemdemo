<?php

namespace App\Actions\Frontend;

use App\Models\FrontendService;
use App\Models\ServiceType;

/**
 * Reconciles "Inversión inmobiliaria" with the operational catalog (RFC-074,
 * B-5/M-5): the frontend has always shown it, but it was never a ServiceType.
 *
 * Insert-if-missing and NON-DESTRUCTIVE: it uses firstOrCreate, never
 * updateOrInsert, so running it again — from the migration, a seeder or a test —
 * cannot overwrite a row an owner already customised. Production seeds through
 * this action; the migration invokes it.
 */
class SeedInversionService
{
    private const CODE = 'inversion';

    public function run(): void
    {
        ServiceType::query()->firstOrCreate(
            ['code' => self::CODE],
            [
                'label' => 'Inversión inmobiliaria',
                'color' => 'warning',
                'sort_order' => 4,
                'active' => true,
            ],
        );

        FrontendService::query()->firstOrCreate(
            ['service_type_code' => self::CODE],
            [
                'title' => 'Inversión inmobiliaria',
                'short_description' => 'Oportunidades opcionadas con potencial de plusvalía en zonas de alto crecimiento.',
                'long_description' => 'Asesoría para inversionistas con visión de futuro: te ayudamos a trazar la ruta de una inversión inmobiliaria sólida.',
                'bullets' => ['Análisis de plusvalía', 'Zonas de alto crecimiento', 'Acompañamiento integral'],
                'icon' => 'trending-up',
                // Shown as institutional content, but NOT lead-eligible: the
                // current form does not offer inversion (RFC-074 decision).
                'show_in_home' => true,
                'show_in_services' => true,
                'allow_leads' => false,
                'sort_order' => 4,
            ],
        );
    }
}
