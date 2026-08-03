<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ScopesToAgent;
use App\Models\Lead;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;

class LeadsByMonthChart extends ChartWidget
{
    use ScopesToAgent;

    protected static ?int $sort = 6;

    public function getHeading(): string
    {
        return $this->isAgentScope() ? 'Mis leads por mes' : 'Leads por mes';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $labels = [];
        $data = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $labels[] = ucfirst($month->translatedFormat('M Y'));
            $data[] = $this->baseQuery()->whereBetween('created_at', [
                $month->copy()->startOfMonth(),
                $month->copy()->endOfMonth(),
            ])->count();
        }

        return [
            'datasets' => [[
                'label' => 'Leads captados',
                'data' => $data,
                'fill' => true,
                'borderColor' => '#3b82f6',
                'backgroundColor' => 'rgba(59, 130, 246, 0.15)',
            ]],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
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
