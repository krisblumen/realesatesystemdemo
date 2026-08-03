<?php

namespace App\Filament\Resources\LonaBatchResource\Pages;

use App\Filament\Resources\LonaBatchResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLonaBatches extends ListRecords
{
    protected static string $resource = LonaBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Asignar lonas'),
        ];
    }
}
