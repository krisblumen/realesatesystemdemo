<?php

namespace App\Filament\Widgets;

use App\Enums\ZoneStatus;
use App\Models\User;
use App\Models\Zone;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Collection;

class AgentZonesWidget extends Widget
{
    protected static string $view = 'filament.widgets.agent-zones';

    protected int|string|array $columnSpan = 'full';

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

    /**
     * Zonas del agente con su polígono en GeoJSON para dibujarlas en el mapa.
     *
     * @return array<int, array{name: string, municipality: ?string, geojson: ?string}>
     */
    public function getZoneMaps(): array
    {
        return $this->getZones()
            ->map(fn (Zone $zone): array => [
                'name' => $zone->name,
                'municipality' => $zone->municipality?->name,
                'geojson' => $zone->polygonAsGeoJson(),
            ])
            ->all();
    }
}
