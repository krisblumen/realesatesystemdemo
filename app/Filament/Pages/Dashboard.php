<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AgentZonesWidget;
use App\Filament\Widgets\MyPerformanceWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    /**
     * Widgets personales del agente (zonas y rendimiento propio) viven sólo en
     * la página "Mi Zona". El Panel general muestra métricas globales, así que
     * aquí se excluyen para no duplicarlos ni mostrar datos propios en el global.
     *
     * @return array<class-string|object>
     */
    public function getWidgets(): array
    {
        $personalWidgets = [
            AgentZonesWidget::class,
            MyPerformanceWidget::class,
        ];

        return array_values(array_filter(
            parent::getWidgets(),
            fn (string|object $widget): bool => ! in_array($widget, $personalWidgets, true),
        ));
    }
}
