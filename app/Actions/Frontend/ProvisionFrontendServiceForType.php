<?php

namespace App\Actions\Frontend;

use App\Models\FrontendService;
use App\Models\ServiceType;

/**
 * Da de alta el `FrontendService` que le corresponde a un `ServiceType` recién
 * creado desde el panel (RFC-074: 1:1, fail-closed — M-2 dice literalmente
 * «ausencia de FrontendService = inelegible»).
 *
 * Arranca APAGADO en las dos ubicaciones (`show_in_home`, `show_in_services`).
 * No es prudencia de más: el tipo recién creado no tiene descripción, íconos
 * ni foto propios —sólo hereda el título—, así que mostrarlo de entrada
 * publicaría una tarjeta a medio llenar en el sitio en vivo en el mismo
 * instante en que el owner terminó de crear el TIPO, antes de haber tenido
 * chance de escribir el resto. El owner lo enciende él mismo desde «Servicios
 * del sitio» cuando el contenido ya está listo — «Guardar cambios» ahí
 * publica al instante (Estrategia A, RFC-074), así que ese es el momento
 * correcto para hacerlo público, no éste.
 *
 * insert-if-missing (`firstOrCreate`) y NO destructivo, igual que
 * `SeedInversionService` y `FrontendServiceSeeder`: puede volver a correr sin
 * pisar un servicio que el owner ya haya personalizado.
 */
class ProvisionFrontendServiceForType
{
    public function run(ServiceType $type): FrontendService
    {
        return FrontendService::query()->firstOrCreate(
            ['service_type_code' => $type->code],
            [
                'title' => $type->label,
                'show_in_home' => false,
                'show_in_services' => false,
                'allow_leads' => true,
                'sort_order' => $type->sort_order ?? 0,
            ],
        );
    }
}
