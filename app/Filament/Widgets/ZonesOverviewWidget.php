<?php

namespace App\Filament\Widgets;

use App\Enums\ZoneStatus;
use App\Models\User;
use App\Models\Zone;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ZonesOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 15;

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['owner', 'admin']) ?? false;
    }

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        return [
            Stat::make('Zonas totales', Zone::count()),
            Stat::make('Zonas activas', Zone::where('status', ZoneStatus::Active->value)->count()),
            Stat::make('Agentes asignados', User::role('agente')->count()),
        ];
    }
}
