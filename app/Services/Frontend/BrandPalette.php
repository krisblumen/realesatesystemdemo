<?php

namespace App\Services\Frontend;

/**
 * Los colores REALES de la paleta de marca, resueltos a hexadecimal.
 *
 * Sirve para dos cosas que necesitan el color de verdad y no su clase:
 *
 *  1. Pintar las muestras en el panel. El sitio resuelve estas variantes con
 *     `color-mix()` sobre las variables de marca, pero el panel no tiene esas
 *     variables —son del layout del frontend—, así que las muestras se
 *     calcularían mal o no se verían.
 *  2. Decidir si sobre un color va texto claro u oscuro. Esa decisión no puede
 *     ser una lista escrita a mano: el owner cambia su acento y las diez
 *     variantes se recalculan, así que una tabla fija quedaría mintiendo.
 *
 * `color-mix(in srgb, X 55%, #000)` es lineal por canal, así que el hexadecimal
 * que sale de este cálculo es exactamente el que el navegador va a pintar. Si
 * dejara de coincidir, el owner elegiría un color en el panel y vería otro en su
 * sitio — hay una prueba que fija los porcentajes contra los del CSS.
 *
 * Antes se llamaba `CardBorderPalette` y vivía bajo `Media\`: nació sirviendo
 * sólo al borde de las tarjetas. Hoy la misma paleta también pinta el fondo del
 * bloque de cierre, y el nombre viejo mandaba a buscarla al lugar equivocado.
 */
class BrandPalette
{
    /** Mezcla con negro (oscurecer) o blanco (aclarar), en el mismo orden que el CSS. */
    private const VARIANTS = [
        '-d2' => [0.55, 0x00],
        '-d1' => [0.78, 0x00],
        '' => [1.0, 0x00],
        '-l1' => [0.55, 0xFF],
        '-l2' => [0.25, 0xFF],
    ];

    /**
     * Debajo de esta relación de contraste, el texto blanco sobre ese fondo deja
     * de ser legible. Es el mínimo AA de WCAG para texto normal.
     */
    private const MIN_CONTRAST = 4.5;

    public function __construct(private readonly FrontendThemeService $theme) {}

    /**
     * Cada opción de la paleta con su etiqueta y su color resuelto.
     *
     * @return array<string, array{label: string, hex: string}>
     */
    public function swatches(): array
    {
        $theme = $this->theme->theme();

        $bases = [
            'accent' => $theme['accent'] ?? '#f5a624',
            'primary' => $theme['primary'] ?? '#2e3842',
            // El fondo del sitio no tiene variantes: la muestra es el color tal
            // cual lo configuró el cliente.
            'site' => $theme['background'] ?? '#f2f4f6',
        ];

        $out = [];

        foreach ((array) config('frontend-sections.brand_palette') as $clave => $color) {
            // Un color con hexadecimal PROPIO no se deriva: son los neutros, que
            // no salen de la marca del cliente sino de la escala de grises del
            // sitio. Derivarlos daría negro, porque su base no existe.
            if (isset($color['hex'])) {
                $out[$clave] = ['label' => $color['label'], 'hex' => strtolower($color['hex'])];

                continue;
            }

            // La clave es `base` o `base-variante`; la base es lo anterior al guion.
            [$base, $variante] = str_contains($clave, '-')
                ? [strstr($clave, '-', true), strstr($clave, '-')]
                : [$clave, ''];

            $out[$clave] = [
                'label' => $color['label'],
                'hex' => $this->mix($bases[$base] ?? '#000000', ...(self::VARIANTS[$variante] ?? [1.0, 0x00])),
            ];
        }

        return $out;
    }

    /**
     * ¿Sobre este color hay que escribir en oscuro?
     *
     * Se calcula, no se lista: el acento del sitio es configurable, así que una
     * tabla de «estos son claros» quedaría desactualizada en cuanto el owner
     * cambie su marca. El caso que evita es concreto — el acento por defecto es
     * un ámbar con contraste 2.1:1 contra blanco, así que un título blanco
     * encima queda prácticamente ilegible.
     */
    public function needsDarkText(string $key): bool
    {
        $hex = $this->swatches()[$key]['hex'] ?? null;

        // Un color desconocido no debería llegar —la paleta es cerrada y el
        // schema la valida—, pero si llegara, el fondo por defecto es oscuro.
        if ($hex === null) {
            return false;
        }

        return $this->contrastWithWhite($hex) < self::MIN_CONTRAST;
    }

    /** Relación de contraste WCAG entre este color y el blanco. */
    private function contrastWithWhite(string $hex): float
    {
        return 1.05 / ($this->relativeLuminance($hex) + 0.05);
    }

    /** Luminancia relativa WCAG: los canales se linealizan antes de pesarse. */
    private function relativeLuminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        $pesos = [0.2126, 0.7152, 0.0722];
        $luz = 0.0;

        foreach ([0, 2, 4] as $i => $offset) {
            $canal = hexdec(substr($hex, $offset, 2)) / 255;
            $lineal = $canal <= 0.03928 ? $canal / 12.92 : (($canal + 0.055) / 1.055) ** 2.4;
            $luz += $pesos[$i] * $lineal;
        }

        return $luz;
    }

    /** El mismo cálculo que `color-mix(in srgb, $hex $ratio, $with)`. */
    private function mix(string $hex, float $ratio, int $with): string
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            return '#000000';
        }

        $canales = [];

        foreach ([0, 2, 4] as $i) {
            $valor = hexdec(substr($hex, $i, 2));
            $canales[] = (int) round($valor * $ratio + $with * (1 - $ratio));
        }

        return sprintf('#%02x%02x%02x', ...$canales);
    }
}
