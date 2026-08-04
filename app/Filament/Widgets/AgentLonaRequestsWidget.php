<?php

namespace App\Filament\Widgets;

use App\Enums\LonaRequestStatus;
use App\Models\LonaRequest;
use App\Models\User;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Collection;

/**
 * Indicador de solicitudes de lonas pendientes de aprobación en "Mis Lonas".
 * Sin esto, el agente sólo ve el botón "Solicitar más" deshabilitado (tooltip),
 * sin ninguna señal persistente de que ya tiene una solicitud en curso.
 * Sólo se muestra si hay al menos una solicitud pendiente — no genera ruido
 * cuando el agente no tiene ninguna.
 */
class AgentLonaRequestsWidget extends Widget
{
    protected static bool $isDiscovered = false;

    protected static string $view = 'filament.widgets.agent-lona-requests';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -1; // arriba de AgentLonaUnitsWidget

    public static function canView(): bool
    {
        $user = auth()->user();

        if (! $user instanceof User || ! $user->hasRole('agente')) {
            return false;
        }

        return LonaRequest::query()
            ->where('agent_id', $user->id)
            ->where('estado', LonaRequestStatus::Pendiente->value)
            ->exists();
    }

    /**
     * @return Collection<int, LonaRequest>
     */
    public function getPendingRequests(): Collection
    {
        return LonaRequest::query()
            ->where('agent_id', auth()->id())
            ->where('estado', LonaRequestStatus::Pendiente->value)
            ->orderByDesc('created_at')
            ->get();
    }
}
