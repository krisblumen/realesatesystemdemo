<?php

namespace App\Enums;

enum LonaRequestStatus: string
{
    case Pendiente = 'pendiente';
    case Aprobada = 'aprobada';
    case Rechazada = 'rechazada';

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Aprobada => 'Aprobada',
            self::Rechazada => 'Rechazada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pendiente => 'warning',
            self::Aprobada => 'success',
            self::Rechazada => 'danger',
        };
    }
}
