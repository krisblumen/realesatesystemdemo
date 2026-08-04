<?php

namespace App\Filament\Widgets;

use App\Enums\ZoneStatus;
use App\Models\User;
use App\Models\Zone;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Collection;

/**
 * Versión liviana (sólo nombres) de las zonas del agente para el escritorio.
 * El detalle con mapa y polígono vive en la página "Mi Zona".
 */
class AgentZonesListWidget extends Widget
{
    protected static string $view = 'filament.widgets.agent-zones-list';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 0;

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('agente') ?? false;
    }

    /**
     * @return Collection<int, Zone>
     */
    public function getZones(): Collection
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return new Collection;
        }

        return $user
            ->zones()
            ->with('municipality')
            ->where('status', ZoneStatus::Active->value)
            ->orderBy('name')
            ->get();
    }
}
