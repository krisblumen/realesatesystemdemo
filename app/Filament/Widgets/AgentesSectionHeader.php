<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ScopesToAgent;

class AgentesSectionHeader extends SectionHeaderWidget
{
    use ScopesToAgent;

    protected static ?int $sort = 8;

    public function getSectionTitle(): ?string
    {
        return $this->isAgentScope() ? 'Mi rendimiento' : 'Agentes';
    }

    public function getSectionDescription(): ?string
    {
        return $this->isAgentScope() ? 'Tu conversión de leads.' : 'Rendimiento del equipo.';
    }
}
