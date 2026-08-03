<?php

namespace App\Services\Contratos;

use App\Models\ContratoIntermediacion;

/**
 * Genera el folio único global de 8 caracteres de un contrato (RFC-064). Usa un
 * alfabeto sin caracteres ambiguos (sin 0/O/1/I/L) y reintenta ante colisión; el
 * índice UNIQUE de la tabla es la red de seguridad real (ver ContratoCreacionService).
 */
class FolioGenerator
{
    private const ALFABETO = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    private const LONGITUD = 8;

    private const MAX_INTENTOS = 10;

    public function generar(): string
    {
        for ($i = 0; $i < self::MAX_INTENTOS; $i++) {
            $folio = $this->aleatorio();

            if (! ContratoIntermediacion::withTrashed()->where('folio', $folio)->exists()) {
                return $folio;
            }
        }

        throw new \RuntimeException('No se pudo generar un folio único tras '.self::MAX_INTENTOS.' intentos.');
    }

    private function aleatorio(): string
    {
        $out = '';
        $max = strlen(self::ALFABETO) - 1;

        for ($i = 0; $i < self::LONGITUD; $i++) {
            $out .= self::ALFABETO[random_int(0, $max)];
        }

        return $out;
    }
}
