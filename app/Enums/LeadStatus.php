<?php

namespace App\Enums;

enum LeadStatus: string
{
    case Nuevo = 'nuevo';
    case Contactado = 'contactado';
    case EnSeguimiento = 'en_seguimiento';
    case CerradoGanado = 'cerrado_ganado';
    case CerradoPerdido = 'cerrado_perdido';

    public function label(): string
    {
        return match ($this) {
            self::Nuevo => 'Nuevo',
            self::Contactado => 'Contactado',
            self::EnSeguimiento => 'En seguimiento',
            self::CerradoGanado => 'Cerrado ganado',
            self::CerradoPerdido => 'Cerrado perdido',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Nuevo => 'brand-orange',
            self::Contactado => 'info',
            self::EnSeguimiento => 'warning',
            self::CerradoGanado => 'success',
            self::CerradoPerdido => 'danger',
        };
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Nuevo => [self::Contactado],
            self::Contactado => [self::EnSeguimiento, self::CerradoGanado, self::CerradoPerdido],
            self::EnSeguimiento => [self::CerradoGanado, self::CerradoPerdido],
            self::CerradoGanado, self::CerradoPerdido => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::CerradoGanado, self::CerradoPerdido], true);
    }
}
