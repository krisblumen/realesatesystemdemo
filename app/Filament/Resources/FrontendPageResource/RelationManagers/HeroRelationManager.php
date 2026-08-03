<?php

namespace App\Filament\Resources\FrontendPageResource\RelationManagers;

use Illuminate\Database\Eloquent\Builder;

/**
 * La portada, en su propio bloque y arriba de todo.
 *
 * Estaba dentro del listado de secciones, como una fila más con las dos flechas
 * apagadas. Funcionaba, pero decía lo contrario de lo que se quería: una fila en
 * una lista ordenable se lee como algo que se puede acomodar, y la portada no lo
 * es. Sacada de la lista, la regla no hay que explicarla — no hay nada que
 * apagar ni contra qué chocar.
 *
 * Hereda TODO de `SectionsRelationManager`: el formulario del hero, sus imágenes
 * y el guardado son los mismos. Acá sólo cambia a qué fila mira y que no muestra
 * flechas.
 */
class HeroRelationManager extends SectionsRelationManager
{
    protected static ?string $title = 'Portada';

    protected function tableQueryFilter(Builder $query): Builder
    {
        return $query->where('type', 'hero');
    }

    /** Sin flechas: la portada no se mueve. */
    protected function rowActions(): array
    {
        return [];
    }
}
