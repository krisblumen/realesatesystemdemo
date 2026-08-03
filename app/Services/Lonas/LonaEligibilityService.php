<?php

namespace App\Services\Lonas;

use App\Enums\LonaRequestStatus;
use App\Enums\LonaUnitStatus;
use App\Enums\OperationType;
use App\Models\LonaRequest;
use App\Models\LonaUnit;
use App\Models\User;

class LonaEligibilityService
{
    /**
     * Tope de lonas SIN COLOCAR por agente y por tipo. Es un modelo de reposición:
     * las lonas ya justificadas con evidencia (colocadas) no cuentan, así que colocar
     * una libera un cupo para pedir otra. Venta y renta se cuentan por separado.
     */
    public const CAP_PER_TYPE = 5;

    /**
     * Unidades de este tipo que el agente aún tiene sin colocar. Son las que cuentan
     * contra el tope; las colocadas ya no.
     */
    public function uncolocatedCount(User $agent, OperationType $type): int
    {
        return LonaUnit::query()
            ->where('agent_id', $agent->id)
            ->where('operation_type', $type->value)
            ->where('status', LonaUnitStatus::PendienteColocacion->value)
            ->count();
    }

    /**
     * Cuántas lonas de este tipo puede SOLICITAR el agente ahora mismo:
     * el cupo libre (tope − sin colocar), o 0 si ya tiene una solicitud pendiente
     * (sólo se permite una pendiente por tipo a la vez — ver índice único parcial).
     */
    public function availableToRequest(User $agent, OperationType $type): int
    {
        $tieneSolicitudPendiente = LonaRequest::query()
            ->where('agent_id', $agent->id)
            ->where('operation_type', $type->value)
            ->where('estado', LonaRequestStatus::Pendiente->value)
            ->exists();

        if ($tieneSolicitudPendiente) {
            return 0;
        }

        return max(0, self::CAP_PER_TYPE - $this->uncolocatedCount($agent, $type));
    }

    public function canRequestMore(User $agent, OperationType $type): bool
    {
        return $this->availableToRequest($agent, $type) > 0;
    }
}
