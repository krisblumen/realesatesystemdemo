<?php

namespace App\Enums;

enum LeadSource: string
{
    case Web = 'web';
    case Landing = 'landing';
    case Inmueble = 'inmueble';
    case Manual = 'manual';
    case Telefono = 'telefono';

    public function label(): string
    {
        return match ($this) {
            self::Web => 'Web',
            self::Landing => 'Landing',
            self::Inmueble => 'Inmueble',
            self::Manual => 'Manual',
            self::Telefono => 'Teléfono',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Web => 'info',
            self::Landing => 'success',
            self::Inmueble => 'warning',
            self::Manual => 'gray',
            self::Telefono => 'primary',
        };
    }
}
