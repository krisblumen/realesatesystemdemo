<?php

namespace App\Filament\Resources\ZoneResource\Concerns;

use App\Models\PostalCode;
use App\Models\PostalCodeArea;
use App\Models\State;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

trait ResolvesZonePostalCodePolygon
{
    /**
     * Livewire-callable. Returns a GeoJSON Polygon string (largest ring of the
     * catalogued MultiPolygon) for the given postal code, or null if not found.
     * Called from the blade "Obtener" button via $wire.call(...).
     */
    public function fetchPostalCodePolygon(?string $postalCode): ?string
    {
        $postalCode = trim((string) $postalCode);

        if (! preg_match('/^\d{5}$/', $postalCode)) {
            return null;
        }

        $geoJson = PostalCodeArea::largestRingGeoJson($postalCode);

        if ($geoJson === null) {
            Notification::make()
                ->title('Sin polígono para ese código postal')
                ->body('No hay un polígono catalogado para el C.P. '.$postalCode.'. Puedes dibujarlo manualmente.')
                ->warning()
                ->send();

            return null;
        }

        return $geoJson;
    }

    /**
     * Livewire-callable. Devuelve una frase con las colonias del código postal
     * para autocompletar la descripción de la zona al pulsar "Obtener Zona",
     * o null si no hay colonias catalogadas.
     */
    public function coloniasDescriptionForPostalCode(?string $postalCode): ?string
    {
        $postalCode = trim((string) $postalCode);

        if (! preg_match('/^\d{5}$/', $postalCode)) {
            return null;
        }

        $colonias = PostalCode::query()
            ->where('postal_code', $postalCode)
            ->orderBy('colonia')
            ->pluck('colonia')
            ->all();

        if ($colonias === []) {
            return null;
        }

        return 'Esta zona abarca las colonias: '.implode(', ', $colonias).'.';
    }

    protected function assertStateBelongsToMexico(mixed $stateId): void
    {
        if (blank($stateId)) {
            return;
        }

        $belongs = State::query()
            ->whereKey($stateId)
            ->whereHas('country', fn (Builder $q) => $q->where('iso2', 'MX'))
            ->exists();

        if (! $belongs) {
            throw new \LogicException('Zone state must belong to Mexico.');
        }
    }
}
