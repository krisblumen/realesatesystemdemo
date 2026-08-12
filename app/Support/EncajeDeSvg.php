<?php

namespace App\Support;

/**
 * Mete un SVG adentro de una caja sin deformarlo, y lo centra.
 *
 * EXISTE POR UN DEFECTO QUE SALIÓ DOS VECES. El CSS fijaba sólo el ancho del
 * logo y dejaba el alto al azar de la proporción del archivo. Mientras el logo
 * fue horizontal, funcionó; cuando la des-marcación lo cambió por uno casi
 * cuadrado, el mismo ancho de 950pt pasó a ocupar 998pt de alto y el logo bajó
 * hasta taparle la palabra VENTA a la lona.
 *
 * El mismo error, con otra cara, se había arreglado antes en el logo de los
 * correos. Dos veces es un patrón: **una caja con una sola dimensión no acota
 * nada**.
 *
 * ES `contain` A MANO porque dompdf no entiende `object-fit`, que es lo que uno
 * usaría en un navegador. Se calcula en PHP y se emite como medidas explícitas.
 *
 * Nota sobre la proporción: cuando no se puede leer del archivo se asume
 * cuadrada, y eso NO es un riesgo. La caja acota igual sea cual sea la
 * proporción; una proporción equivocada afecta cómo se ve, nunca si se sale.
 */
class EncajeDeSvg
{
    /**
     * @return array{ancho: float, alto: float, izquierda: float, arriba: float}
     */
    public static function contener(
        string $ruta,
        float $anchoDeLaCaja,
        float $altoDeLaCaja,
        float $izquierdaDeLaCaja = 0.0,
        float $arribaDeLaCaja = 0.0,
    ): array {
        $proporcion = self::proporcionDe($ruta);

        $ancho = $anchoDeLaCaja;
        $alto = $anchoDeLaCaja / $proporcion;

        // Si al llenar el ancho se pasa de alto, manda el alto. Es la única
        // rama que hacía falta y la que no existía.
        if ($alto > $altoDeLaCaja) {
            $alto = $altoDeLaCaja;
            $ancho = $altoDeLaCaja * $proporcion;
        }

        return [
            'ancho' => $ancho,
            'alto' => $alto,
            'izquierda' => $izquierdaDeLaCaja + ($anchoDeLaCaja - $ancho) / 2,
            'arriba' => $arribaDeLaCaja + ($altoDeLaCaja - $alto) / 2,
        ];
    }

    /**
     * Ancho dividido alto, leído del archivo.
     */
    public static function proporcionDe(string $ruta): float
    {
        if (! is_readable($ruta)) {
            return 1.0;
        }

        // Sólo la cabecera: un SVG de marca puede pesar cientos de kilobytes de
        // trazados que no aportan nada a esta cuenta.
        $cabecera = (string) file_get_contents($ruta, false, null, 0, 4096);

        if (preg_match('/viewBox\s*=\s*"\s*[\d.\-]+\s+[\d.\-]+\s+([\d.]+)\s+([\d.]+)/i', $cabecera, $m) === 1) {
            [$ancho, $alto] = [(float) $m[1], (float) $m[2]];

            if ($ancho > 0 && $alto > 0) {
                return $ancho / $alto;
            }
        }

        // Sin `viewBox`, los atributos sueltos. Se toman los del elemento `svg`
        // y no los primeros que aparezcan: adentro hay `width`/`height` de las
        // formas, y tomar esos daría la proporción de un pedazo del dibujo.
        if (preg_match('/<svg\b[^>]*>/i', $cabecera, $etiqueta) === 1
            && preg_match('/\bwidth\s*=\s*"([\d.]+)/i', $etiqueta[0], $w) === 1
            && preg_match('/\bheight\s*=\s*"([\d.]+)/i', $etiqueta[0], $h) === 1
            && (float) $h[1] > 0
        ) {
            return (float) $w[1] / (float) $h[1];
        }

        return 1.0;
    }
}
