<?php

namespace App\Filament\Resources\ContratoIntermediacionResource\Pages;

use App\Filament\Resources\ContratoIntermediacionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListContratos extends ListRecords
{
    protected static string $resource = ContratoIntermediacionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Nuevo contrato'),
        ];
    }
}
