<?php

namespace App\Filament\Widgets;

use App\Enums\PropertyStatus;
use App\Filament\Widgets\Concerns\ScopesToAgent;
use App\Models\Property;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class PropertiesStatsWidget extends BaseWidget
{
    use ScopesToAgent;

    protected static ?int $sort = 2;

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        return [
            Stat::make('Inmuebles totales', $this->baseQuery()->count())
                ->description('Todos los inmuebles')
                ->descriptionIcon('heroicon-m-home'),
            Stat::make('Publicados', $this->baseQuery()->where('status', PropertyStatus::Publicado->value)->count())
                ->description('Visibles al público')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),
            Stat::make('Vendidos / Rentados', $this->baseQuery()->whereIn('status', [
                PropertyStatus::Vendido->value,
                PropertyStatus::Rentado->value,
            ])->count())
                ->description('Operaciones cerradas')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('info'),
            Stat::make('Borradores', $this->baseQuery()->where('status', PropertyStatus::Borrador->value)->count())
                ->description('Pendientes de publicar')
                ->descriptionIcon('heroicon-m-pencil-square')
                ->color('gray'),
        ];
    }

    private function baseQuery(): Builder
    {
        $query = Property::query();

        if ($this->isAgentScope()) {
            $query->where('agent_id', $this->agentId());
        }

        return $query;
    }
}
