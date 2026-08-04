<?php

namespace App\Rules;

use App\Models\Zone;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidZonePolygonGeoJson implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        try {
            (new Zone)->setPolygonFromGeoJson((string) $value);
        } catch (\Throwable) {
            $fail('El polígono debe ser un GeoJSON válido de tipo Polygon.');
        }
    }
}
