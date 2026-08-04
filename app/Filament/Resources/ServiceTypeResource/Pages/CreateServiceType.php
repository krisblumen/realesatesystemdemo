<?php

namespace App\Filament\Resources\ServiceTypeResource\Pages;

use App\Actions\Frontend\ProvisionFrontendServiceForType;
use App\Filament\Resources\ServiceTypeResource;
use App\Models\ServiceType;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateServiceType extends CreateRecord
{
    protected static string $resource = ServiceTypeResource::class;

    /**
     * `FrontendService` existe 1:1 con `ServiceType` (RFC-074) y
     * `FrontendServiceResource` NUNCA lo crea a mano —el owner sólo edita
     * contenido, `canCreate()` ahí es `false` a propósito—. Sin este paso, un
     * tipo nuevo quedaría inelegible para siempre y sin ningún error visible
     * que lo explique: simplemente no aparecería en «Servicios del sitio»
     * para poder cargarle contenido.
     */
    protected function afterCreate(): void
    {
        /** @var ServiceType $type */
        $type = $this->record;

        app(ProvisionFrontendServiceForType::class)->run($type);
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Tipo de servicio creado')
            ->body('Andá a «Servicios del sitio» para escribirle el contenido y activarlo — por ahora no se muestra en el sitio.');
    }
}
