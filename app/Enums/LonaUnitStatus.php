<?php

namespace App\Enums;

enum LonaUnitStatus: string
{
    case PendienteColocacion = 'pendiente_colocacion';
    case Colocada = 'colocada';

    public function label(): string
    {
        return match ($this) {
            self::PendienteColocacion => 'Pendiente de colocación',
            self::Colocada => 'Colocada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PendienteColocacion => 'warning',
            self::Colocada => 'success',
        };
    }
}
