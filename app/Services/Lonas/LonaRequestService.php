<?php

namespace App\Services\Lonas;

use App\Enums\LonaRequestStatus;
use App\Enums\OperationType;
use App\Enums\PropertyStatus;
use App\Models\LonaRequest;
use App\Models\Property;
use App\Models\User;
use App\Notifications\LonaRequestSubmittedNotification;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Alta de solicitudes de reposición del lado del agente (RFC-062, decisión I-5).
 * La aprobación/rechazo viven en LonaBatchApprovalService (lado admin/owner).
 */
class LonaRequestService
{
    public function __construct(private readonly LonaEligibilityService $eligibility) {}

    public function submit(User $agent, OperationType $type, int $cantidad, ?Property $property = null): LonaRequest
    {
        // Tope de reposición: sólo puede pedir el cupo libre (5 − sin colocar), y 0 si
        // ya tiene una solicitud pendiente de ese tipo.
        $disponible = $this->eligibility->availableToRequest($agent, $type);

        if ($disponible < 1) {
            throw ValidationException::withMessages([
                'operation_type' => 'No puedes solicitar más lonas de este tipo por ahora: llegaste al máximo de '.LonaEligibilityService::CAP_PER_TYPE.' sin colocar, o ya tienes una solicitud pendiente. Coloca lonas con evidencia para liberar cupo.',
            ]);
        }

        if ($cantidad < 1 || $cantidad > $disponible) {
            throw ValidationException::withMessages([
                'cantidad' => 'Sólo puedes solicitar entre 1 y '.$disponible.' lonas de '.$type->label().' ahora mismo.',
            ]);
        }

        // Invariantes de dominio en el servicio, no sólo en el select de Filament
        // (auditoría de implementación M-IMP-1): el inmueble sugerido debe ser publicado
        // Y del propio agente, aunque el payload Livewire venga manipulado.
        if ($property !== null
            && ($property->status !== PropertyStatus::Publicado || $property->agent_id !== $agent->id)) {
            throw ValidationException::withMessages([
                'property_id' => 'El inmueble sugerido debe ser uno de tus inmuebles publicados.',
            ]);
        }

        try {
            $request = LonaRequest::create([
                'agent_id' => $agent->id,
                'operation_type' => $type->value,
                'cantidad_solicitada' => $cantidad,
                'property_id' => $property?->id,
                'estado' => LonaRequestStatus::Pendiente->value,
            ]);
        } catch (QueryException) {
            // Índice único parcial (RFC-062 5.2): otra solicitud pendiente del mismo
            // tipo ganó la carrera. Se traduce a un error de validación limpio.
            throw ValidationException::withMessages([
                'operation_type' => 'Ya existe una solicitud pendiente de este tipo.',
            ]);
        }

        Notification::send(
            User::role(['owner', 'admin'])->get(),
            new LonaRequestSubmittedNotification($request),
        );

        return $request;
    }
}
