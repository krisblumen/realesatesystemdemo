<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ScopesToAgent;
use App\Models\Property;
use App\Models\PropertyOwner;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class OwnersCommissionStatsWidget extends BaseWidget
{
    use ScopesToAgent;

    protected static ?int $sort = 12;

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $avgCommission = $this->propertiesQuery()
            ->published()
            ->whereNotNull('commission_percentage')
            ->avg('commission_percentage');

        return [
            Stat::make('Propietarios', $this->ownersQuery()->count())
                ->description('Registrados')
                ->descriptionIcon('heroicon-m-user-group'),
            Stat::make('Comisión promedio', $avgCommission !== null
                ? number_format((float) $avgCommission, 2).'%'
                : '—')
                ->description('De inmuebles publicados')
                ->descriptionIcon('heroicon-m-receipt-percent')
                ->color('success'),
            Stat::make('Inmuebles con propietario', $this->propertiesQuery()->whereNotNull('owner_id')->count())
                ->description('Vinculados a un propietario')
                ->descriptionIcon('heroicon-m-link')
                ->color('info'),
        ];
    }

    private function ownersQuery(): Builder
    {
        $query = PropertyOwner::query();

        if ($this->isAgentScope()) {
            $query->where('agent_id', $this->agentId());
        }

        return $query;
    }

    private function propertiesQuery(): Builder
    {
        $query = Property::query();

        if ($this->isAgentScope()) {
            $query->where('agent_id', $this->agentId());
        }

        return $query;
    }
}
