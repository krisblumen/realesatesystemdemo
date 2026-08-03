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
