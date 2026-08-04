<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\ChartWidget;

class LeadsByAgentChart extends ChartWidget
{
    protected static ?string $heading = 'Leads por agente';

    protected static ?int $sort = 8;

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['owner', 'admin']) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $agents = User::role('agente')
            ->withCount('leads')
            ->orderByDesc('leads_count')
            ->get();

        return [
            'datasets' => [[
                'label' => 'Leads',
                'data' => $agents->pluck('leads_count')->all(),
                'backgroundColor' => '#3b82f6',
            ]],
            'labels' => $agents->pluck('name')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
