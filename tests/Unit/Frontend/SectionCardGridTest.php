<?php

namespace Tests\Unit\Frontend;

use App\Support\Frontend\SectionCardGrid;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * La regla del reparto: hasta cuatro por fila, y la última fila incompleta se
 * reparte el ancho entre las que tenga — nunca queda un hueco al final.
 */
class SectionCardGridTest extends TestCase
{
    /**
     * @param  list<string>  $esperado  el ancho de cada tarjeta, en orden
     */
    #[DataProvider('repartos')]
    public function test_the_last_row_shares_the_full_width(int $total, array $esperado): void
    {
        $anchos = array_map(
            fn (int $i): string => SectionCardGrid::span($i, $total),
            range(0, $total - 1),
        );

        $this->assertSame($esperado, $anchos);
    }

    public static function repartos(): array
    {
        $completo = 'sm:col-span-12';
        $mitad = 'sm:col-span-6';
        $tercio = 'sm:col-span-4';
        $cuarto = 'sm:col-span-3';

        return [
            'una a todo el ancho' => [1, [$completo]],
            'dos mitades' => [2, [$mitad, $mitad]],
            'tres tercios' => [3, [$tercio, $tercio, $tercio]],
            'cuatro cuartos' => [4, [$cuarto, $cuarto, $cuarto, $cuarto]],
            'cinco: la última a todo el ancho' => [5, [$cuarto, $cuarto, $cuarto, $cuarto, $completo]],
            'seis: dos mitades abajo' => [6, [$cuarto, $cuarto, $cuarto, $cuarto, $mitad, $mitad]],
            'siete: tres tercios abajo' => [7, [$cuarto, $cuarto, $cuarto, $cuarto, $tercio, $tercio, $tercio]],
            'ocho: cuatro y cuatro' => [8, array_fill(0, 8, $cuarto)],

            // Arriba de ocho es donde la versión que RESTABA cuatro se rompía:
            // daba un divisor fuera del mapa y todas caían al ancho de reserva.
            'nueve: la última a todo el ancho' => [9, [...array_fill(0, 8, $cuarto), $completo]],
            'diez: dos mitades abajo' => [10, [...array_fill(0, 8, $cuarto), $mitad, $mitad]],
            'doce: tres filas completas' => [12, array_fill(0, 12, $cuarto)],
            'trece: la última a todo el ancho' => [13, [...array_fill(0, 12, $cuarto), $completo]],
        ];
    }

    /**
     * La MISMA regla con filas de dos, que es lo que usa `values`: cada tarjeta
     * lleva un párrafo largo y cuatro por fila las dejarían como columnas de
     * diario.
     *
     * @param  list<string>  $esperado
     */
    #[DataProvider('repartosDeADos')]
    public function test_the_rule_holds_with_rows_of_two(int $total, array $esperado): void
    {
        $anchos = array_map(
            fn (int $i): string => SectionCardGrid::span($i, $total, porFila: 2),
            range(0, $total - 1),
        );

        $this->assertSame($esperado, $anchos);
    }

    public static function repartosDeADos(): array
    {
        $completo = 'sm:col-span-12';
        $mitad = 'sm:col-span-6';

        return [
            'una a todo el ancho' => [1, [$completo]],
            'dos mitades' => [2, [$mitad, $mitad]],
            'tres: la última a todo el ancho' => [3, [$mitad, $mitad, $completo]],
            'cuatro: dos y dos' => [4, array_fill(0, 4, $mitad)],
            'cinco: la última a todo el ancho' => [5, [...array_fill(0, 4, $mitad), $completo]],
            'seis: tres filas parejas' => [6, array_fill(0, 6, $mitad)],
        ];
    }

    public function test_an_unsupported_row_width_falls_back_to_four(): void
    {
        // Sólo se reparten los anchos que la grilla de 12 admite sin sobrantes.
        // Un pedido de cinco por fila no puede dar quintos, así que en vez de
        // inventar una clase que Tailwind no compiló, vuelve al de siempre.
        $this->assertSame('sm:col-span-3', SectionCardGrid::span(0, 8, porFila: 5));
    }

    public function test_the_container_never_interpolates_payload(): void
    {
        // La grilla es una constante: si alguna vez sale de un valor del payload,
        // ese valor termina dentro de un nombre de clase (§6.1).
        $this->assertSame('grid grid-cols-1 gap-6 sm:grid-cols-12', SectionCardGrid::container());
        $this->assertSame('grid grid-cols-1 gap-8 sm:grid-cols-12', SectionCardGrid::container('gap-8'));
        // Una separación que no está en el mapa cae en la de siempre, en vez de
        // producir una clase inexistente.
        $this->assertSame('grid grid-cols-1 gap-6 sm:grid-cols-12', SectionCardGrid::container('gap-99'));
    }
}
