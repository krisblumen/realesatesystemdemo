<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AgentLonaRequestsWidget;
use App\Filament\Widgets\AgentLonaUnitsWidget;
use Filament\Pages\Page;

class AgentLonas extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationLabel = 'Mis Lonas';

    protected static ?string $title = 'Mis Lonas';

    protected static ?string $slug = 'mis-lonas';

    protected static string $view = 'filament.pages.agent-lonas';

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
            AgentLonaRequestsWidget::class,
            AgentLonaUnitsWidget::class,
        ];
    }
}
