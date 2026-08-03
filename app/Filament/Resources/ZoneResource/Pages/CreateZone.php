<?php

namespace App\Filament\Resources\ZoneResource\Pages;

use App\Filament\Resources\ZoneResource;
use App\Filament\Resources\ZoneResource\Concerns\ResolvesZonePostalCodePolygon;
use App\Models\Zone;
use App\Services\Zones\ZoneCompositionService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateZone extends CreateRecord
{
    use ResolvesZonePostalCodePolygon;

    protected static string $resource = ZoneResource::class;

    /** @var list<string> */
    protected array $postalCodes = [];

    protected ?string $polygonEwkt = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->assertStateBelongsToMexico($data['state_id'] ?? null);

        $this->postalCodes = $data['postal_codes'] ?? [];
        unset($data['postal_codes']);

        $service = app(ZoneCompositionService::class);
        $this->polygonEwkt = $service->geometryEwktFor($this->postalCodes);
        $data['description'] = $service->descriptionFor($this->postalCodes);
        // Se conserva postal_code (primer CP) por compatibilidad; el pivote es la fuente de verdad.
        $data['postal_code'] = $this->postalCodes[0] ?? null;

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $record = new Zone($data);

        if (filled($this->polygonEwkt)) {
            $record->polygon = $this->polygonEwkt;
        }

        $record->save();
        $record->syncPostalCodes($this->postalCodes);

        return $record;
    }
}
