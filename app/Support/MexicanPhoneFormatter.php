<?php

namespace App\Support;

class MexicanPhoneFormatter
{
    /**
     * Formatea un teléfono mexicano de 10 dígitos para impresión (lona, PDFs).
     *
     * Números de Ciudad de México (LADA 55) van en grupos de 2: 55-10-10-10-10.
     * El resto del país va en grupos 3-3-2-2: 442-119-09-59.
     *
     * Si el valor no tiene 10 dígitos reconocibles (una vez quitado el +52/52
     * de país, si viene con él), se devuelve tal cual: mejor mostrar el dato
     * crudo que arriesgar a cortar mal un número que no sigue el patrón esperado.
     */
    public static function format(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($digits) === 12 && str_starts_with($digits, '52')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) !== 10) {
            return $phone;
        }

        if (str_starts_with($digits, '55')) {
            return implode('-', str_split($digits, 2));
        }

        return substr($digits, 0, 3).'-'.substr($digits, 3, 3).'-'.substr($digits, 6, 2).'-'.substr($digits, 8, 2);
    }
}
