<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ContratoIntermediacionResource;
use App\Filament\Resources\LeadResource;
use App\Filament\Resources\PropertyResource;
use Filament\Widgets\Widget;

/**
 * Los tres botones que arrancan el trabajo del día.
 *
 * POR QUÉ EXISTE, más allá de que esté en la maqueta. Quien entra a un demo tiene
 * tres minutos de curiosidad y un menú lateral que no conoce. Sin un punto de
 * partida evidente, la sesión termina en «miré el tablero y me fui»; con él,
 * termina en «cargué un inmueble y vi cómo aparece en mi sitio», que es la única
 * forma de que el producto se entienda.
 *
 * CADA BOTÓN PREGUNTA SI SE PUEDE, y no se dibuja si no. Ofrecerle a un agente un
 * botón que lo lleva a un 403 es peor que no ofrecerle nada: la primera lectura
 * no es «no tengo permiso», es «esto está roto».
 */
class AccionesRapidasWidget extends Widget
{
    protected static string $view = 'filament.widgets.acciones-rapidas';

    protected static ?int $sort = 10;

    /**
     * @return array<int, array{etiqueta: string, icono: string, url: string}>
     */
    public function getAcciones(): array
    {
        $acciones = [];

        if (PropertyResource::canCreate()) {
            $acciones[] = [
                'etiqueta' => 'Nueva propiedad',
                'icono' => 'heroicon-m-plus',
                'url' => PropertyResource::getUrl('create'),
            ];
        }

        if (LeadResource::canCreate()) {
            $acciones[] = [
                'etiqueta' => 'Registrar lead',
                'icono' => 'heroicon-m-user-plus',
                'url' => LeadResource::getUrl('create'),
            ];
        }

        if (ContratoIntermediacionResource::canCreate()) {
            $acciones[] = [
                'etiqueta' => 'Generar contrato',
                'icono' => 'heroicon-m-document-text',
                'url' => ContratoIntermediacionResource::getUrl('create'),
            ];
        }

        return $acciones;
    }

    /**
     * Sin ninguna acción disponible, el widget no existe.
     *
     * Una tarjeta titulada «Acciones rápidas» y vacía por debajo no es neutral:
     * ocupa lugar y deja la sensación de que algo no cargó.
     *
     * VA EN `canView()` Y NO EN UN `@if` DE LA VISTA. Con el `@if`, una vista que
     * no dibuja nada deja a Livewire sin elemento raíz y el componente revienta
     * — se cambiaba una tarjeta vacía por un error. `canView()` es además donde
     * Filament espera esta decisión, así que el widget desaparece del tablero en
     * vez de dibujarse hueco.
     */
    public static function canView(): bool
    {
        return PropertyResource::canCreate()
            || LeadResource::canCreate()
            || ContratoIntermediacionResource::canCreate();
    }
}
