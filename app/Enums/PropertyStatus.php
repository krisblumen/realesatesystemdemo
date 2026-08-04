<?php

namespace App\Enums;

enum PropertyStatus: string
{
    case Borrador = 'borrador';
    case Publicado = 'publicado';
    case Pausado = 'pausado';
    case Vendido = 'vendido';
    case Rentado = 'rentado';

    public function label(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Publicado => 'Publicado',
            self::Pausado => 'Pausado',
            self::Vendido => 'Vendido',
            self::Rentado => 'Rentado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Borrador => 'gray',
            self::Publicado => 'success',
            self::Pausado => 'warning',
            self::Vendido, self::Rentado => 'info',
        };
    }

    public function isPublic(): bool
    {
        return $this === self::Publicado;
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Borrador => [self::Publicado],
            self::Publicado => [self::Pausado, self::Vendido, self::Rentado],
            self::Pausado => [self::Publicado, self::Vendido, self::Rentado],
            self::Vendido, self::Rentado => [self::Borrador],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
