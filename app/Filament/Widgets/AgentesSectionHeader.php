<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ScopesToAgent;

class AgentesSectionHeader extends SectionHeaderWidget
{
    use ScopesToAgent;

    /**
     * Justo ARRIBA de «Leads por mes» (sort 6), y no antes de «Leads por
     * agente».
     *
     * Encabeza la fila entera donde viven el gráfico del mes y las tres
     * tarjetas: los tres bloques hablan de cómo viene el equipo, y el
     * encabezado suelto a la derecha no encabezaba nada.
     */
    protected static ?int $sort = 5;

    public function getSectionTitle(): ?string
    {
        return $this->isAgentScope() ? 'Mi rendimiento' : 'Agentes';
    }

    public function getSectionDescription(): ?string
    {
        return $this->isAgentScope() ? 'Tu conversión de leads.' : 'Rendimiento del equipo.';
    }
}
