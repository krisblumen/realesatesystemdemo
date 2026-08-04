<?php

namespace App\Filament\Resources\FrontendPageResource\Pages;

use App\Filament\Resources\FrontendPageResource;
use Filament\Resources\Pages\ListRecords;

class ListFrontendPages extends ListRecords
{
    protected static string $resource = FrontendPageResource::class;

    protected function getHeaderActions(): array
    {
        return []; // Pages are the five canonical rows; none are created here.
    }
}
