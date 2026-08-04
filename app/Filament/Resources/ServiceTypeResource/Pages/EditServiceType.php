<?php

namespace App\Filament\Resources\ServiceTypeResource\Pages;

use App\Filament\Resources\ServiceTypeResource;
use App\Services\Frontend\FrontendCacheGeneration;
use Filament\Resources\Pages\EditRecord;

class EditServiceType extends EditRecord
{
    protected static string $resource = ServiceTypeResource::class;

    protected function afterSave(): void
    {
        // `active` gates whether a service shows and accepts leads (§16.6), so
        // toggling it must invalidate the services render cache (RFC-074).
        app(FrontendCacheGeneration::class)->bump();
    }
}
