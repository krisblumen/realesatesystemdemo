<?php

namespace App\Filament\Widgets;

use App\Enums\PropertyStatus;
use App\Filament\Widgets\Concerns\ScopesToAgent;
use App\Models\Property;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Inmuebles por estado.
 *
 * EL COLOR SE ELIGE POR ESTADO, no por posición. Antes era un arreglo de cinco
 * colores en fila, apareado con `PropertyStatus::cases()` por orden de llegada:
 * el día que alguien agregue un estado en el medio o reordene el enum, cada
 * color pasa a significar otra cosa y nada falla — el gráfico simplemente miente.
 *
 * UN SOLO ACENTO: el ámbar de la marca es lo publicado —lo único que le importa
 * a quien mira— y todo lo demás queda en segundo plano. Los otros cuatro se
 * separan por luminosidad, de lo más claro a lo más oscuro, y cada uno tiene que
 * distinguirse del vecino EN EL DIBUJO y no sólo en la leyenda: una dona con dos
 * porciones del mismo color deja de ser un gráfico. Ya pasó una vez, con
 * borrador y vendido en el mismo gris.
 */
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
        // UNA consulta agrupada y no una por estado.
        $porEstado = $this->baseQuery()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $labels = [];
        $data = [];
        $colores = [];

        foreach (PropertyStatus::cases() as $status) {
            $labels[] = $status->label();
            $data[] = (int) ($porEstado[$status->value] ?? 0);
            $colores[] = self::colorDe($status);
        }

        return [
            'datasets' => [[
                'label' => 'Inmuebles',
                'data' => $data,
                'backgroundColor' => $colores,
                'borderWidth' => 0,
            ]],
            'labels' => $labels,
        ];
    }

    private static function colorDe(PropertyStatus $estado): string
    {
        return match ($estado) {
            PropertyStatus::Borrador => '#e2e8f0',   // slate-200: el más claro, todavía no existe para nadie
            PropertyStatus::Publicado => '#f5a624',  // ámbar de la marca: lo que está a la venta
            PropertyStatus::Pausado => '#121923',    // casi negro: existe, pero no se muestra
            PropertyStatus::Vendido => '#333c4d',    // azul pizarra oscuro
            PropertyStatus::Rentado => '#97add2',    // azul claro: cerrada y todavía viva
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            // SIN EJES. Una dona no tiene ejes, y Filament los dibuja igual: el
            // gráfico salía con dos escalas numéricas alrededor que no miden
            // nada y le sacan lugar al dibujo.
            'scales' => ['x' => ['display' => false], 'y' => ['display' => false]],
            'plugins' => ['legend' => ['position' => 'bottom']],
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
