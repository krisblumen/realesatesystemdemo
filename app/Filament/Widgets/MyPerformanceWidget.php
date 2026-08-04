<?php

namespace App\Filament\Widgets;

use App\Enums\LeadStatus;
use App\Models\Lead;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MyPerformanceWidget extends BaseWidget
{
    protected static ?int $sort = 8;

    public static function canView(): bool
    {
        // Métricas propias del agente. Vive en "Mi Zona", su panel personal;
        // se muestra a cualquiera con rol agente, incluido el admin que también
        // es agente (que en el Panel general ve las métricas globales).
        return auth()->user()?->hasRole('agente') ?? false;
    }

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $agentId = auth()->id();

        $won = Lead::query()->where('agent_id', $agentId)
            ->where('status', LeadStatus::CerradoGanado->value)->count();
        $lost = Lead::query()->where('agent_id', $agentId)
            ->where('status', LeadStatus::CerradoPerdido->value)->count();
        $closed = $won + $lost;
        $rate = $closed > 0 ? (int) round($won / $closed * 100) : 0;

        return [
            Stat::make('Leads ganados', $won)
                ->description('Cerrados con éxito')
                ->descriptionIcon('heroicon-m-trophy')
                ->color('success'),
            Stat::make('Leads perdidos', $lost)
                ->description('Cerrados sin venta')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),
            Stat::make('Tasa de cierre', $rate.'%')
                ->description('Ganados sobre el total cerrado')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('info'),
        ];
    }
}
