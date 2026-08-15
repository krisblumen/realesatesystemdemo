<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ScopesToAgent;
use App\Models\Lead;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Leads captados mes a mes, como en la maqueta de la landing.
 *
 * TRES CAMBIOS, Y NINGUNO ES DE GUSTO.
 *
 * 1. DE LÍNEA A BARRAS. Quien entra al demo vio antes el tablero de la landing;
 *    si acá se encuentra otra cosa, la primera impresión es que le mostraron
 *    algo que no existe.
 *
 * 2. DOCE MESES Y NO SEIS. El año entero es lo que da la sensación de historia,
 *    que es justamente lo que un demo tiene que transmitir.
 *
 * 3. LOS COLORES DE LA MARCA. El azul `#3b82f6` que había acá era anterior a la
 *    des-marcación: quedó vivo porque estaba escrito en PHP y la búsqueda de
 *    colores viejos miraba CSS.
 *
 * Los DOS ÚLTIMOS meses van en ámbar y el resto en gris. No es decoración: en un
 * año de barras, lo que importa es dónde está parado uno hoy, y el ojo va al
 * color antes que a la posición.
 */
class LeadsByMonthChart extends ChartWidget
{
    use ScopesToAgent;

    private const ACENTO = '#f5a624';

    private const APAGADO = '#cbd2d9';

    /**
     * Cuántos meses del final se resaltan.
     */
    private const RECIENTES = 2;

    protected static ?int $sort = 6;

    public function getHeading(): string
    {
        return $this->isAgentScope() ? 'Mis leads por mes' : 'Leads por mes';
    }

    public function getDescription(): ?string
    {
        return (string) now()->year;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $meses = $this->doceMeses();
        $conteos = $this->conteoPorMes(array_key_first($meses));

        $datos = [];
        $colores = [];
        $etiquetas = [];

        $ultimos = array_slice(array_keys($meses), -self::RECIENTES);

        foreach ($meses as $clave => $fecha) {
            $datos[] = $conteos[$clave] ?? 0;
            $colores[] = in_array($clave, $ultimos, true) ? self::ACENTO : self::APAGADO;

            // Sólo la inicial: doce nombres de mes no entran en el ancho de un
            // widget, y el navegador los rota o los saltea de a uno.
            $etiquetas[] = mb_strtoupper(mb_substr($fecha->translatedFormat('M'), 0, 1));
        }

        return [
            'datasets' => [[
                'label' => 'Leads captados',
                'data' => $datos,
                'backgroundColor' => $colores,
                'borderRadius' => 4,
            ]],
            'labels' => $etiquetas,
        ];
    }

    /**
     * @return array<string, Carbon>
     */
    private function doceMeses(): array
    {
        $meses = [];

        for ($i = 11; $i >= 0; $i--) {
            $mes = now()->subMonths($i)->startOfMonth();
            $meses[$mes->format('Y-m')] = $mes;
        }

        return $meses;
    }

    /**
     * UNA consulta agrupada y no doce.
     *
     * La versión anterior contaba mes por mes dentro de un bucle: con seis meses
     * eran seis viajes a la base, y pasar a doce los duplicaba. En un tablero que
     * se dibuja en cada carga del panel, eso se paga siempre.
     *
     * @return array<string, int>
     */
    private function conteoPorMes(string $desde): array
    {
        return $this->baseQuery()
            ->where('created_at', '>=', Carbon::createFromFormat('Y-m', $desde)->startOfMonth())
            ->selectRaw("to_char(date_trunc('month', created_at), 'YYYY-MM') as mes, count(*) as total")
            ->groupBy('mes')
            ->pluck('total', 'mes')
            ->map(fn ($total): int => (int) $total)
            ->all();
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => [
                'y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]],
                'x' => ['grid' => ['display' => false]],
            ],
        ];
    }

    private function baseQuery(): Builder
    {
        $query = Lead::query();

        if ($this->isAgentScope()) {
            $query->where('agent_id', $this->agentId());
        }

        return $query;
    }
}
