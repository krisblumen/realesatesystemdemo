<?php

namespace App\Services\Zones;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * La geometría de una zona se almacena como MultiPolygon (SRID 4326). Estos
 * helpers aceptan un Polygon o un MultiPolygon válido y siempre devuelven el
 * EWKT como MultiPolygon (envolviendo con ST_Multi cuando hace falta).
 */
class ZoneGeometry
{
    private const INVALID_POLYGON_MESSAGE = 'Zone polygon must be a valid PostGIS polygon with SRID 4326 and a closed exterior ring.';

    /**
     * @param  array<string, mixed>|string  $geoJson
     */
    public static function polygonEwktFromGeoJson(array|string $geoJson): string
    {
        $json = is_string($geoJson) ? $geoJson : json_encode($geoJson, JSON_THROW_ON_ERROR);

        return self::validatedMultiPolygonEwkt('ST_GeomFromGeoJSON(?)', [$json]);
    }

    public static function polygonEwktFromWkt(string $wkt): string
    {
        $ewkt = str_starts_with(strtoupper(trim($wkt)), 'SRID=')
            ? $wkt
            : "SRID=4326;{$wkt}";

        return self::validatedMultiPolygonEwkt('ST_GeomFromEWKT(?)', [$ewkt]);
    }

    public static function validatePolygonEwkt(string $ewkt): string
    {
        return self::validatedMultiPolygonEwkt('ST_GeomFromEWKT(?)', [$ewkt]);
    }

    /**
     * Valida que la geometría sea un Polygon o MultiPolygon válido en SRID 4326
     * y devuelve su EWKT normalizado como MultiPolygon.
     *
     * @param  array<int, mixed>  $bindings
     */
    private static function validatedMultiPolygonEwkt(string $geometryExpression, array $bindings): string
    {
        try {
            $geometry = DB::selectOne(
                <<<SQL
                WITH candidate AS (
                    SELECT {$geometryExpression} AS geom
                )
                SELECT
                    ST_AsEWKT(ST_Multi(geom)) AS ewkt,
                    ST_SRID(geom) AS srid,
                    GeometryType(geom) AS geometry_type,
                    ST_IsValid(geom) AS is_valid
                FROM candidate
                SQL,
                $bindings,
            );
        } catch (\Throwable $exception) {
            throw new InvalidArgumentException(self::INVALID_POLYGON_MESSAGE, previous: $exception);
        }

        if (
            ! $geometry
            || (int) $geometry->srid !== 4326
            || ! in_array($geometry->geometry_type, ['POLYGON', 'MULTIPOLYGON'], true)
            || ! (bool) $geometry->is_valid
        ) {
            throw new InvalidArgumentException(self::INVALID_POLYGON_MESSAGE);
        }

        return (string) $geometry->ewkt;
    }
}
