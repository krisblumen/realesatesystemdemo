<?php

namespace App\Filament\Resources\FrontendServiceResource\Pages;

use App\Filament\Resources\FrontendServiceResource;
use Filament\Resources\Pages\ListRecords;

class ListFrontendServices extends ListRecords
{
    protected static string $resource = FrontendServiceResource::class;

    // No header create action: services exist 1:1 for a ServiceType (RFC-074).
    protected function getHeaderActions(): array
    {
        return [];
    }
}
