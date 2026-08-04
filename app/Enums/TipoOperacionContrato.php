<?php

namespace App\Enums;

/**
 * Tipo de operación de un contrato de intermediación. Enum PROPIO (no reutiliza
 * App\Enums\OperationType, que solo tiene venta/renta) porque el contrato admite
 * además "renta con opción a compra" — decisión D-1 del diseño de la Épica 10.
 */
enum TipoOperacionContrato: string
{
    case Venta = 'venta';
    case Renta = 'renta';
    case RentaOpcionCompra = 'renta_opcion_compra';

    public function label(): string
    {
        return match ($this) {
            self::Venta => 'Venta',
            self::Renta => 'Renta',
            self::RentaOpcionCompra => 'Renta con opción a compra',
        };
    }
}
