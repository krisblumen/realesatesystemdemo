<?php

namespace App\Filament\Widgets;

use App\Enums\PropertyStatus;
use App\Filament\Widgets\Concerns\ScopesToAgent;
use App\Models\Property;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

/**
 * Las cuatro tarjetas de arriba del tablero, como en la maqueta de la landing.
 *
 * DOS CAMBIOS CONTRA LO QUE HABÍA:
 *
 * 1. VENDIDOS Y RENTADOS VAN SEPARADOS. Estaban sumados en una sola tarjeta, y
 *    son dos negocios distintos: una inmobiliaria que vende mucho y renta poco
 *    no se parece en nada a una que hace al revés. Sumarlos esconde justo el
 *    dato por el que alguien mira este tablero.
 *
 * 2. FUERA «INMUEBLES TOTALES». Es la suma de las otras tres, así que no
 *    agregaba información y sí ocupaba el lugar de una tarjeta. Cuatro tarjetas
 *    entran en una fila; cinco parten la fila y el tablero pierde su forma.
 *
 * UNA SOLA CONSULTA para las cuatro. Antes eran cuatro `count()` separados
 * —cinco, contando el total— sobre la misma tabla y con el mismo filtro. En un
 * tablero que se dibuja en cada carga del panel, eso se paga siempre.
 */
class PropertiesStatsWidget extends BaseWidget
{
    use ScopesToAgent;

    protected static ?int $sort = 2;

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $porEstado = $this->baseQuery()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $contar = fn (PropertyStatus $estado): int => (int) ($porEstado[$estado->value] ?? 0);

        return [
            Stat::make('Publicados', $contar(PropertyStatus::Publicado))
                ->description('inmuebles en el sitio')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),
            Stat::make('Borradores', $contar(PropertyStatus::Borrador))
                ->description('por completar')
                ->descriptionIcon('heroicon-m-pencil-square')
                ->color('gray'),
            Stat::make('Vendidos', $contar(PropertyStatus::Vendido))
                ->description('operaciones cerradas')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
            Stat::make('Rentados', $contar(PropertyStatus::Rentado))
                ->description('contratos vigentes')
                ->descriptionIcon('heroicon-m-key')
                ->color('warning'),
        ];
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
