<?php

namespace App\Support;

/**
 * Convierte un monto a su representación en letras en pesos mexicanos, con el formato usado
 * en instrumentos legales: "un millón de pesos 00/100 M.N.". Cubre 0 hasta 999,999,999.99.
 */
class NumeroALetras
{
    private const UNIDADES = [
        '', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve',
        'diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete',
        'dieciocho', 'diecinueve', 'veinte',
    ];

    private const VEINTI = [
        'veintiuno', 'veintidós', 'veintitrés', 'veinticuatro', 'veinticinco',
        'veintiséis', 'veintisiete', 'veintiocho', 'veintinueve',
    ];

    private const DECENAS = ['', '', '', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'];

    private const CENTENAS = [
        '', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos',
        'seiscientos', 'setecientos', 'ochocientos', 'novecientos',
    ];

    public static function pesos(float $monto): string
    {
        $entero = (int) floor(abs($monto));
        $centavos = (int) round((abs($monto) - $entero) * 100);

        $letras = self::entero($entero);
        $exactoMillones = $entero >= 1000000 && $entero % 1000000 === 0;

        // Apócope de "uno" → "un" antes del sustantivo ("un peso", "veintiún pesos").
        $letras = preg_replace('/veintiuno$/', 'veintiún', $letras);
        $letras = preg_replace('/(^|\s)uno$/', '$1un', $letras);

        $sustantivo = ($exactoMillones ? 'de ' : '').($entero === 1 ? 'peso' : 'pesos');

        return trim($letras).' '.$sustantivo.' '.str_pad((string) $centavos, 2, '0', STR_PAD_LEFT).'/100 M.N.';
    }

    private static function entero(int $n): string
    {
        if ($n === 0) {
            return 'cero';
        }

        $millones = intdiv($n, 1000000);
        $miles = intdiv($n % 1000000, 1000);
        $resto = $n % 1000;
        $out = '';

        if ($millones > 0) {
            $out .= $millones === 1 ? 'un millón' : self::tresCifras($millones).' millones';
        }
        if ($miles > 0) {
            $out .= ' '.($miles === 1 ? 'mil' : self::tresCifras($miles).' mil');
        }
        if ($resto > 0) {
            $out .= ' '.self::tresCifras($resto);
        }

        return trim((string) preg_replace('/\s+/', ' ', $out));
    }

    /** 1..999 */
    private static function tresCifras(int $n): string
    {
        if ($n === 100) {
            return 'cien';
        }

        $out = self::CENTENAS[intdiv($n, 100)];
        $resto = $n % 100;

        if ($resto > 0) {
            $out = trim($out.' '.self::dosCifras($resto));
        }

        return trim($out);
    }

    /** 1..99 */
    private static function dosCifras(int $n): string
    {
        if ($n <= 20) {
            return self::UNIDADES[$n];
        }
        if ($n < 30) {
            return self::VEINTI[$n - 21];
        }

        $out = self::DECENAS[intdiv($n, 10)];
        $u = $n % 10;

        if ($u > 0) {
            $out .= ' y '.self::UNIDADES[$u];
        }

        return $out;
    }
}
