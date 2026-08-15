<?php

namespace App\Filament\Resources\PropertyResource\Pages;

use App\Enums\PropertyStatus;
use App\Filament\Resources\PropertyResource;
use App\Models\Property;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListProperties extends ListRecords
{
    protected static string $resource = PropertyResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }

    /**
     * Divide el listado por estado para que no crezca en una sola lista
     * interminable. La primera pestaña (Publicados) es la activa por defecto.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'publicados' => $this->statusTab('Publicados', [PropertyStatus::Publicado]),
            'pausados' => $this->statusTab('Pausados', [PropertyStatus::Pausado]),
            'borradores' => $this->statusTab('Borradores', [PropertyStatus::Borrador]),
            'cerrados' => $this->statusTab('Vendidos / Rentados', [
                PropertyStatus::Vendido,
                PropertyStatus::Rentado,
            ]),
        ];
    }

    /**
     * En qué pestaña vive un estado.
     *
     * VIVE ACÁ, al lado de `getTabs()`, y no copiado en quien arma el enlace: si
     * alguien renombra una pestaña y la clave queda escrita en otro archivo, el
     * enlace sigue funcionando y abre la pestaña equivocada. Sin error, sin
     * aviso — el usuario hace clic en «Borradores» y ve los publicados.
     *
     * Que sea un `match` sin rama por defecto es a propósito: agregar un estado
     * al enum y olvidarse de esto revienta acá y no en la pantalla de alguien.
     */
    public static function pestanaDe(PropertyStatus $estado): string
    {
        return match ($estado) {
            PropertyStatus::Publicado => 'publicados',
            PropertyStatus::Pausado => 'pausados',
            PropertyStatus::Borrador => 'borradores',
            PropertyStatus::Vendido, PropertyStatus::Rentado => 'cerrados',
        };
    }

    /**
     * @param  array<int, PropertyStatus>  $statuses
     */
    private function statusTab(string $label, array $statuses): Tab
    {
        $values = array_map(fn (PropertyStatus $status): string => $status->value, $statuses);

        $user = auth()->user();
        $count = Property::query()
            ->when($user instanceof User, fn (Builder $query): Builder => $query->visibleTo($user))
            ->whereIn('status', $values)
            ->count();

        return Tab::make($label)
            ->badge($count)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', $values));
    }
}
