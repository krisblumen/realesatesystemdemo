<?php

namespace App\Support\Frontend;

/**
 * Cómo se REPARTEN a lo ancho las tarjetas de una sección.
 *
 * La regla es una sola, para cualquier cantidad: hasta cuatro por fila, y la
 * ÚLTIMA fila —la que quedó incompleta— se reparte el ancho entre las que
 * tenga. Así nunca queda un hueco al final de la grilla:
 *
 *   1 tarjeta   → una a todo el ancho
 *   2           → mitad y mitad
 *   3           → tres tercios
 *   4           → cuatro cuartos
 *   5           → cuatro arriba, la quinta a todo el ancho
 *   6           → cuatro arriba, dos mitades abajo
 *   7           → cuatro arriba, tres tercios abajo
 *   8           → cuatro y cuatro
 *
 * La misma regla vale con OTRO ancho de fila. De a dos —lo que usa `values`,
 * donde cada tarjeta lleva un párrafo largo— queda:
 *
 *   2 tarjetas  → mitad y mitad
 *   3           → dos arriba, la tercera a todo el ancho
 *   4           → dos y dos
 *   5           → dos, dos, y la quinta a todo el ancho
 *
 * Vive acá y no dentro de una vista porque la comparten `capability_cards` y
 * `featured_projects`: copiada en cada blade, la regla se desincroniza en el
 * primer ajuste que alguien haga en una sola.
 *
 * Las clases se devuelven ENTERAS y literales: son las que Tailwind compila
 * leyendo este archivo, y nada del payload entra en el nombre de una clase
 * (§6.1).
 */
class SectionCardGrid
{
    /** 12 / n, sólo los repartos que la grilla de 12 admite sin sobrantes. */
    private const SPAN = [
        1 => 'sm:col-span-12',
        2 => 'sm:col-span-6',
        3 => 'sm:col-span-4',
        4 => 'sm:col-span-3',
    ];

    /**
     * Las clases del contenedor de la grilla.
     *
     * `$separacion` sale de un mapa fijo y no de una interpolación: Tailwind
     * compila leyendo este archivo, y una clase armada con un número no
     * existiría en el CSS final.
     */
    public static function container(string $separacion = 'gap-6'): string
    {
        $gap = match ($separacion) {
            'gap-8' => 'gap-8',
            'gap-10' => 'gap-10',
            default => 'gap-6',
        };

        return "grid grid-cols-1 {$gap} sm:grid-cols-12";
    }

    /**
     * El ancho de la tarjeta número `$index` (base 0) de un total de `$total`.
     *
     * `$porFila` es cuántas entran en una fila COMPLETA. Cuatro es el caso
     * habitual; de a dos lo usa `values`, donde cada tarjeta lleva un párrafo
     * largo y cuatro por fila las dejaría como columnas de diario.
     *
     * El reparto se calcula con el RESTO de dividir, no restando: con más
     * tarjetas que una fila y media —`featured_projects` admite hasta 24—
     * restar daría un divisor fuera del mapa, y todas las de las filas del
     * medio saldrían con el ancho de reserva.
     */
    public static function span(int $index, int $total, int $porFila = 4): string
    {
        // Sólo los repartos que la grilla de 12 admite sin sobrantes; cualquier
        // otro pedido cae en cuatro, que es el de siempre.
        $porFila = isset(self::SPAN[$porFila]) ? $porFila : 4;

        $ultimaFila = $total % $porFila;
        // Dónde arranca la fila incompleta. Si `$total` es múltiplo exacto no
        // hay ninguna, y este corte cae después de la última tarjeta.
        $completas = $total - $ultimaFila;

        return $index < $completas
            ? self::SPAN[$porFila]
            : (self::SPAN[$ultimaFila] ?? self::SPAN[$porFila]);
    }
}
