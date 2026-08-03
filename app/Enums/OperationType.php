<?php

namespace App\Enums;

enum OperationType: string
{
    case Venta = 'venta';
    case Renta = 'renta';

    public function label(): string
    {
        return match ($this) {
            self::Venta => 'Venta',
            self::Renta => 'Renta',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Venta => 'success',
            self::Renta => 'info',
        };
    }
}
