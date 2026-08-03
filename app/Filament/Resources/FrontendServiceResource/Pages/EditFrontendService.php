<?php

namespace App\Filament\Resources\FrontendServiceResource\Pages;

use App\Filament\Resources\FrontendServiceResource;
use App\Services\Frontend\FrontendCacheGeneration;
use App\Services\Frontend\SyncFrontendServiceImage;
use Filament\Resources\Pages\EditRecord;

/**
 * "Save is publishing" (Strategy A): editing content or toggles takes effect at
 * once. The image is synced through the validated uuid boundary (§16.4) and a
 * generation bump invalidates every services cache key afterwards (RFC-074).
 */
class EditFrontendService extends EditRecord
{
    protected static string $resource = FrontendServiceResource::class;

    protected function getHeaderActions(): array
    {
        return []; // No delete: a FrontendService is 1:1 with its ServiceType.
    }

    protected function afterSave(): void
    {
        // La transición de qué imagen queda vigente vive en el dominio, no acá
        // (Épica 12.3 §4): lock del servicio, validación de frontera, columna,
        // marca de `pending` y despacho del job DESPUÉS del commit. Antes esta
        // pantalla sólo apuntaba la columna, y alcanzaba mientras todo estaba en
        // el disco público; con el disco privado, una imagen que nadie promueve
        // no se ve nunca.
        app(SyncFrontendServiceImage::class)($this->record);

        // Any change that alters the public render bumps the durable generation
        // so every reader moves to a fresh services cache key (§16.8).
        app(FrontendCacheGeneration::class)->bump();
    }
}
