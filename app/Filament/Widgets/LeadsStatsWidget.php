<?php

namespace App\Filament\Widgets;

use App\Enums\LeadStatus;
use App\Filament\Widgets\Concerns\ScopesToAgent;
use App\Models\Lead;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class LeadsStatsWidget extends BaseWidget
{
    use ScopesToAgent;

    protected static ?int $sort = 5;

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        return [
            Stat::make('Leads nuevos', $this->baseQuery()->where('status', LeadStatus::Nuevo->value)->count())
                ->description('Sin contactar todavía')
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('warning'),
            Stat::make('Sin asignar', $this->isAgentScope() ? 0 : Lead::query()->unassigned()->count())
                ->description($this->isAgentScope() ? 'No aplica' : 'Esperan un agente')
                ->descriptionIcon('heroicon-m-user-minus')
                ->color('danger'),
            Stat::make('Leads del mes', $this->baseQuery()->whereBetween('created_at', [
                now()->startOfMonth(),
                now()->endOfMonth(),
            ])->count())
                ->description('Captados este mes')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('success'),
        ];
    }

    private function baseQuery(): Builder
    {
        $query = Lead::query();

        if ($this->isAgentScope()) {
            $query->where('agent_id', $this->agentId());
        }

        return $query;
    }
}
