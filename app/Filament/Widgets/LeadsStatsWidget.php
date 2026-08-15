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

    protected static ?int $sort = 7;

    /**
     * Al lado del gráfico de leads por mes, y en UNA columna.
     *
     * Las tres tarjetas dicen lo mismo que el gráfico —cuántos leads hay y
     * cuándo llegaron— así que separadas por media pantalla obligan a mirar dos
     * veces para leer una sola idea. Juntas, el número y la curva se comparan de
     * un vistazo.
     *
     * En una columna y no en fila: comparten el ancho con el gráfico, y tres
     * tarjetas apretadas en medio ancho no entran sin partir los números.
     */
    protected int|string|array $columnSpan = 1;

    protected function getColumns(): int
    {
        return 1;
    }

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
