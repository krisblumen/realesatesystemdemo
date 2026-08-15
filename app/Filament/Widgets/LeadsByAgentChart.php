<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\ChartWidget;

/**
 * Leads por agente, en barras HORIZONTALES.
 *
 * POR QUÉ HORIZONTALES Y NO VERTICALES, que es lo que había: los nombres de las
 * personas no entran abajo de una barra vertical. El navegador los rota, los
 * corta o directamente saltea uno de cada dos — y un tablero donde no se leen los
 * nombres de los agentes no sirve para lo único que existe, que es comparar
 * quién trae más.
 *
 * Acostada, cada nombre tiene una línea entera para él y la comparación se lee
 * de un vistazo. Es también como aparece en la maqueta de la landing.
 *
 * El ámbar es el de la marca. El azul que había acá (`#3b82f6`) era anterior a la
 * des-marcación y sobrevivió porque estaba escrito en PHP: la búsqueda de colores
 * viejos miró CSS.
 */
class LeadsByAgentChart extends ChartWidget
{
    private const ACENTO = '#f5a624';

    /**
     * Cuántos agentes se dibujan.
     *
     * Con veinte agentes el gráfico se vuelve una lista ilegible de barras de un
     * píxel. Los que más traen es lo que se mira; el resto está en el listado de
     * agentes, que es donde corresponde.
     */
    private const CUANTOS = 8;

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
        $agentes = User::role('agente')
            ->withCount('leads')
            ->has('leads')

            // SIN LOS QUE NO TRAJERON NADA. Una barra de largo cero no compara:
            // ocupa un renglón entero para decir «nada» y empuja hacia arriba a
            // los que sí trajeron. En un demo recién creado, donde casi ningún
            // agente tiene leads todavía, el gráfico era una lista de nombres
            // con líneas en blanco.
            //
            // Quién tiene cero es un dato real, pero se responde en el listado
            // de agentes. Acá la pregunta es quién trae más.
            ->orderByDesc('leads_count')
            ->limit(self::CUANTOS)
            ->get();

        return [
            'datasets' => [[
                'label' => 'Leads',
                'data' => $agentes->pluck('leads_count')->all(),
                'backgroundColor' => self::ACENTO,
                'borderRadius' => 4,

                // Sin contorno: el borde por defecto de Filament ensucia la
                // barra en vez de definirla. Ver la nota de `LeadsByMonthChart`.
                'borderWidth' => 0,
            ]],
            'labels' => $agentes->pluck('name')->all(),
        ];
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
            // Lo que acuesta las barras. Sin esto el gráfico es el de antes.
            'indexAxis' => 'y',
            'plugins' => ['legend' => ['display' => false]],
            'scales' => [
                'x' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]],
                'y' => ['grid' => ['display' => false]],
            ],
        ];
    }
}
