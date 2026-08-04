<?php

namespace App\Support\Frontend;

use App\Services\Frontend\BrandPalette;

/**
 * El filete que separa una sección con fondo propio del resto de la página.
 *
 * Cuando una sección elige un color de fondo distinto al general del sitio, se
 * lee como una BANDA: un bloque que interrumpe la página. Sin un borde arriba y
 * abajo, esa banda queda flotando y el corte se ve sucio contra la sección
 * vecina. Es el mismo filete que las páginas estáticas ya usan a mano
 * (`border-y border-cloud` en Nosotros e Inversionistas).
 *
 * SÓLO GRIS, y siempre el mismo. No es una decisión que se le ofrezca al owner:
 * el filete existe para marcar un borde, no para sumar otro color a la mezcla,
 * y un contorno que compita con el fondo elegido haría exactamente lo
 * contrario.
 *
 * SÓLO SI EL FONDO ES DISTINTO al del sitio. Sobre el mismo color no hay
 * ninguna banda que delimitar, y el filete se vería como una línea suelta
 * cruzando la página.
 *
 * La comparación es por COLOR RESUELTO y no por la clave elegida: el owner
 * puede pintar la sección de blanco teniendo el sitio en blanco, y esas dos
 * claves distintas son el mismo color en pantalla. Comparar nombres dibujaría
 * un filete donde no hay nada que separar.
 */
class SectionBand
{
    /**
     * Las clases del filete, o vacío si esta sección no lo lleva.
     *
     * `$clave` es el `background_color` del payload. Ausente significa «el
     * fondo del sitio», que es el default de todas las secciones que lo
     * ofrecen, y por lo tanto tampoco lleva filete.
     */
    public static function edges(?string $clave): string
    {
        if ($clave === null || $clave === '' || $clave === 'site') {
            return '';
        }

        $muestras = app(BrandPalette::class)->swatches();

        $delSitio = $muestras['site']['hex'] ?? null;
        $deLaSeccion = $muestras[$clave]['hex'] ?? null;

        // Un color que no está en la paleta no debería llegar —es cerrada y el
        // schema la valida—, pero si llegara se trata como distinto: el filete
        // de más es menos malo que una banda sin borde.
        if ($delSitio !== null && $deLaSeccion !== null
            && strcasecmp($delSitio, $deLaSeccion) === 0) {
            return '';
        }

        return 'border-y border-cloud';
    }
}
