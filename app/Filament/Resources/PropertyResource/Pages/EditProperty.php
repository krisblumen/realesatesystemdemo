<?php

namespace App\Filament\Resources\PropertyResource\Pages;

use App\Enums\PropertyStatus;
use App\Filament\Resources\PropertyResource;
use App\Filament\Resources\PropertyResource\Pages\Concerns\EnforcesAgentPropertyOwnership;
use App\Models\Property;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditProperty extends EditRecord
{
    use EnforcesAgentPropertyOwnership;

    protected static string $resource = PropertyResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->enforceAgentOwnership($data);
    }

    /**
     * Un inmueble publicado no se queda sin imagen principal.
     *
     * La regla vivía en un hook sobre el borrado de la media, y ahí era
     * imposible de aplicar bien: cuando el agente REEMPLAZA la foto, Filament
     * borra la vieja antes de guardar la nueva, así que el hook veía una base
     * sin fotos y cortaba un guardado perfectamente válido. Encima el error
     * viajaba con una clave que no era la de ningún campo, así que no se
     * mostraba en ningún lado y el botón parecía muerto.
     *
     * Acá se sabe lo que el modelo no puede saber: si el agente eligió una foto
     * nueva. Vaciarla sigue prohibido; cambiarla, no. Y el mensaje cae sobre el
     * campo de la foto, que es donde hay que mirar.
     */
    /**
     * El guardado corre con el candado del modelo apartado, porque la regla ya
     * se aplicó acá arriba sobre lo que el agente eligió — y el candado, que
     * mira la base, no puede ver un archivo que todavía no es media.
     */
    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        Property::deferCoverGuard(fn () => parent::save($shouldRedirect, $shouldSendSavedNotification));
    }

    protected function beforeSave(): void
    {
        if ($this->propertyRecord()->status !== PropertyStatus::Publicado) {
            return;
        }

        if (filled($this->data['cover'] ?? null)) {
            return;
        }

        throw ValidationException::withMessages([
            'data.cover' => 'Un inmueble publicado no puede quedarse sin imagen principal. Pausa el inmueble primero.',
        ]);
    }

    protected function afterSave(): void
    {
        // Señal para que el front confirme visualmente el guardado (botón en verde).
        $this->dispatch('nh-record-saved');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn (): bool => auth()->user()?->can('delete', $this->propertyRecord()) ?? false),
            Actions\RestoreAction::make()
                ->visible(fn (): bool => auth()->user()?->can('restore', $this->propertyRecord()) ?? false)
                ->before(function (): void {
                    $property = $this->propertyRecord();

                    if ($property->status === PropertyStatus::Publicado) {
                        $property->status = PropertyStatus::Borrador;
                        $property->save();
                    }
                }),
        ];
    }

    private function propertyRecord(): Property
    {
        $record = $this->record;

        if (! $record instanceof Property) {
            throw new \LogicException('PropertyResource edit page requires a Property record.');
        }

        return $record;
    }
}
