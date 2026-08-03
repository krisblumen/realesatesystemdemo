<?php

namespace App\Services;

use App\Enums\OperationType;
use App\Enums\PropertyStatus;
use App\Enums\ZoneStatus;
use App\Models\Property;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PropertyStatusService
{
    public function transition(Property $property, PropertyStatus $target): void
    {
        DB::transaction(function () use ($property, $target): void {
            $fresh = Property::query()
                ->whereKey($property->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $current = $fresh->status;

            if (! $current->canTransitionTo($target)) {
                throw ValidationException::withMessages([
                    'status' => "Transición no permitida: {$current->label()} → {$target->label()}.",
                ]);
            }

            match ($target) {
                PropertyStatus::Publicado => $this->guardPublish($fresh),
                PropertyStatus::Vendido => $this->guardOperation($fresh, OperationType::Venta, 'vendido'),
                PropertyStatus::Rentado => $this->guardOperation($fresh, OperationType::Renta, 'rentado'),
                default => null,
            };

            $fresh->status = $target;
            $fresh->save();

            $property->setRawAttributes($fresh->getAttributes(), true);
        });
    }

    private function guardPublish(Property $property): void
    {
        $zone = $property->zone()->first();

        if ($zone === null) {
            throw ValidationException::withMessages([
                'status' => 'No se puede publicar: el inmueble no tiene una zona vigente asignada.',
            ]);
        }

        if ($zone->status !== ZoneStatus::Active) {
            throw ValidationException::withMessages([
                'status' => 'No se puede publicar en una zona inactiva.',
            ]);
        }

        if ($zone->polygonAsGeoJson() === null) {
            throw ValidationException::withMessages([
                'status' => 'No se puede publicar: la zona no tiene polígono definido.',
            ]);
        }

        if (! $property->hasCoverImage()) {
            throw ValidationException::withMessages([
                'status' => 'No se puede publicar un inmueble sin imagen principal.',
            ]);
        }

        if ($property->owner_id === null) {
            throw ValidationException::withMessages([
                'status' => 'No se puede publicar: el inmueble no tiene propietario asignado.',
            ]);
        }

        if ($property->commission_percentage === null) {
            throw ValidationException::withMessages([
                'status' => 'No se puede publicar: falta el porcentaje de comisión pactada.',
            ]);
        }
    }

    private function guardOperation(Property $property, OperationType $required, string $label): void
    {
        if ($property->operation_type !== $required) {
            throw ValidationException::withMessages([
                'status' => "Solo un inmueble en {$required->label()} puede marcarse como {$label}.",
            ]);
        }
    }
}
