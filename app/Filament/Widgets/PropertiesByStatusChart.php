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
 * La escala es de grises con UN solo acento: el ámbar de la marca marca lo que
 * está publicado —que es lo único que le importa a quien mira— y todo lo demás
 * queda en segundo plano. Los cerrados usan los dos extremos de la escala
 * (`slate-300` y `slate-700`) para distinguirse entre sí.
 *
 * Y BORRADOR VA UN TONO MÁS CLARO que vendido, no por gusto: con los dos en
 * `slate-300` quedaban del mismo color exacto en la dona. En la leyenda se
 * distinguían, en el dibujo no — y una dona donde dos porciones son del mismo
 * color deja de ser un gráfico.
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
            PropertyStatus::Pausado => '#55636f',    // gris oscuro: existe, pero no se muestra
            PropertyStatus::Vendido => '#cbd5e1',    // slate-300
            PropertyStatus::Rentado => '#334155',    // slate-700
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
