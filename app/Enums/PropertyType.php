<?php

namespace App\Enums;

enum PropertyType: string
{
    case Casa = 'casa';
    case Departamento = 'departamento';
    case Terreno = 'terreno';
    case Local = 'local';
    case Oficina = 'oficina';
    case Bodega = 'bodega';

    public function label(): string
    {
        return match ($this) {
            self::Casa => 'Casa',
            self::Departamento => 'Departamento',
            self::Terreno => 'Terreno',
            self::Local => 'Local comercial',
            self::Oficina => 'Oficina',
            self::Bodega => 'Bodega',
        };
    }
}
