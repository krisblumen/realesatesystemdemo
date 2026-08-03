<?php

namespace App\Filament\Resources\ZoneResource\Pages;

use App\Filament\Resources\ZoneResource;
use App\Filament\Resources\ZoneResource\Concerns\ResolvesZonePostalCodePolygon;
use App\Models\Zone;
use App\Services\Zones\ZoneCompositionService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditZone extends EditRecord
{
    use ResolvesZonePostalCodePolygon;

    protected static string $resource = ZoneResource::class;

    /** @var list<string> */
    protected array $postalCodes = [];

    protected ?string $polygonEwkt = null;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Las geometrías (objetos Magellan) no se pueden serializar en Livewire
        // y el form ya no las edita directamente.
        unset($data['polygon'], $data['center_point']);

        $codes = $this->zoneRecord()->postalCodeList();
        $data['postal_codes'] = $codes;
        $data['map_zones'] = ZoneResource::mapZonesFor($codes);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->assertStateBelongsToMexico($data['state_id'] ?? null);

        $this->postalCodes = $data['postal_codes'] ?? [];
        unset($data['postal_codes']);

        $service = app(ZoneCompositionService::class);
        $this->polygonEwkt = $service->geometryEwktFor($this->postalCodes);
        $data['description'] = $service->descriptionFor($this->postalCodes);
        $data['postal_code'] = $this->postalCodes[0] ?? null;

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof Zone) {
            throw new \LogicException('ZoneResource edit page must be mounted with a Zone record.');
        }

        try {
            $record->fill($data);
            $record->polygon = $this->polygonEwkt;
            $record->save();
            $record->syncPostalCodes($this->postalCodes);
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title('No se pudo guardar la zona')
                ->body(collect($exception->errors())->flatten()->first())
                ->send();

            throw (new Halt)->rollBackDatabaseTransaction();
        }

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn (): bool => auth()->user()?->can('delete', $this->zoneRecord()) ?? false)
                ->before(function (Actions\DeleteAction $action): void {
                    try {
                        $this->zoneRecord()->assertDeletable();
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->danger()
                            ->title('No se puede eliminar la zona "'.$this->zoneRecord()->name.'"')
                            ->body(collect($exception->errors())->flatten()->first())
                            ->send();

                        $action->cancel();
                    }
                }),
            Actions\RestoreAction::make()
                ->visible(fn (): bool => auth()->user()?->can('restore', $this->zoneRecord()) ?? false),
        ];
    }

    private function zoneRecord(): Zone
    {
        $record = $this->record;

        if (! $record instanceof Zone) {
            throw new \LogicException('ZoneResource edit page must be mounted with a Zone record.');
        }

        return $record;
    }
}
