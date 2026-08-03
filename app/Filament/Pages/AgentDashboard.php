<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AgentZonesWidget;
use App\Filament\Widgets\MyPerformanceWidget;
use Filament\Pages\Page;

class AgentDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationLabel = 'Mi Zona';

    protected static ?string $title = 'Mi Zona';

    protected static ?string $slug = 'mi-zona';

    protected static string $view = 'filament.pages.agent-dashboard';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('agente') ?? false;
    }

    /**
     * @return array<class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            AgentZonesWidget::class,
            MyPerformanceWidget::class,
        ];
    }
}
