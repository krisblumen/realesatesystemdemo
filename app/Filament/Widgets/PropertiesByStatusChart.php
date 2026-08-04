<?php

namespace App\Filament\Widgets;

use App\Enums\PropertyStatus;
use App\Filament\Widgets\Concerns\ScopesToAgent;
use App\Models\Property;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;

class PropertiesByStatusChart extends ChartWidget
{
    use ScopesToAgent;

    protected static ?int $sort = 3;

    public function getHeading(): string
    {
        return $this->isAgentScope() ? 'Mis inmuebles por estado' : 'Inmuebles por estado';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $labels = [];
        $data = [];

        foreach (PropertyStatus::cases() as $status) {
            $labels[] = $status->label();
            $data[] = $this->baseQuery()->where('status', $status->value)->count();
        }

        return [
            'datasets' => [[
                'label' => 'Inmuebles',
                'data' => $data,
                'backgroundColor' => ['#9ca3af', '#22c55e', '#f59e0b', '#3b82f6', '#6366f1'],
            ]],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
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
