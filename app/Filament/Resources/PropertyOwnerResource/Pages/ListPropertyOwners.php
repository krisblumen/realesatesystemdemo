<?php

namespace App\Filament\Resources\PropertyOwnerResource\Pages;

use App\Filament\Resources\PropertyOwnerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPropertyOwners extends ListRecords
{
    protected static string $resource = PropertyOwnerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Crear propietario'),
        ];
    }
}
