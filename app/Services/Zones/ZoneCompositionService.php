<?php

namespace App\Services\Zones;

use Illuminate\Support\Facades\DB;

/**
 * Compone una zona a partir de un conjunto de códigos postales: agrega sus
 * polígonos (catálogo postal_code_areas) en un MultiPolygon y arma la descripción
 * con todas las colonias de esos CP (catálogo postal_codes).
 */
class ZoneCompositionService
{
    /**
     * MultiPolygon (EWKT, SRID 4326) que agrega los polígonos de los CP dados.
     * Devuelve null si ninguno de los CP tiene área en el catálogo.
     *
     * @param  list<string>  $postalCodes
     */
    public function geometryEwktFor(array $postalCodes): ?string
    {
        $codes = $this->normalize($postalCodes);

        if ($codes === []) {
            return null;
        }

        $placeholders = implode(',', array_fill(0, count($codes), '?'));

        $row = DB::selectOne(
            "SELECT ST_AsEWKT(ST_Multi(ST_Union(polygon))) AS ewkt
             FROM postal_code_areas
             WHERE postal_code IN ({$placeholders})",
            $codes,
        );

        return $row?->ewkt;
    }

    /**
     * Descripción automática: todas las colonias (distintas, ordenadas) de todos
     * los CP dados, separadas por coma.
     *
     * @param  list<string>  $postalCodes
     */
    public function descriptionFor(array $postalCodes): string
    {
        $codes = $this->normalize($postalCodes);

        if ($codes === []) {
            return '';
        }

        return DB::table('postal_codes')
            ->whereIn('postal_code', $codes)
            ->orderBy('colonia')
            ->distinct()
            ->pluck('colonia')
            ->implode(', ');
    }

    /**
     * CP válidos (5 dígitos), sin duplicados.
     *
     * @param  list<string>  $codes
     * @return list<string>
     */
    private function normalize(array $codes): array
    {
        return collect($codes)
            ->map(fn (mixed $code): string => trim((string) $code))
            ->filter(fn (string $code): bool => preg_match('/^\d{5}$/', $code) === 1)
            ->unique()
            ->values()
            ->all();
    }
}
