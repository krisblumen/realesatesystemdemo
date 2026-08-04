<?php

namespace App\Models;

use Clickbar\Magellan\Data\Geometries\MultiPolygon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'postal_code',
    'municipality_id',
    'state_id',
    'polygon',
])]
class PostalCodeArea extends Model
{
    /**
     * @return BelongsTo<Municipality, $this>
     */
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    /**
     * @return BelongsTo<State, $this>
     */
    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    /**
     * Full MultiPolygon as GeoJSON. Mirror of Zone::polygonAsGeoJson().
     */
    public function polygonAsGeoJson(): ?string
    {
        if (! $this->exists) {
            return null;
        }

        return DB::table($this->getTable())
            ->where($this->getKeyName(), $this->getKey())
            ->selectRaw('ST_AsGeoJSON(polygon) as geojson')
            ->value('geojson');
    }

    /**
     * Largest single polygon (by area) of the MultiPolygon for a postal code,
     * returned as a GeoJSON Polygon string, or null if the CP is not catalogued.
     *
     * Server-side conversion is MANDATORY: the blade renderExisting() only
     * handles GeoJSON type 'Polygon', never 'MultiPolygon'.
     */
    public static function largestRingGeoJson(string $postalCode): ?string
    {
        $row = DB::selectOne(
            <<<'SQL'
            SELECT ST_AsGeoJSON(geom) AS geojson
            FROM (
                SELECT (ST_Dump(polygon)).geom AS geom
                FROM postal_code_areas
                WHERE postal_code = ?
            ) parts
            ORDER BY ST_Area(geom) DESC
            LIMIT 1
            SQL,
            [$postalCode],
        );

        return $row?->geojson;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'polygon' => MultiPolygon::class,
        ];
    }
}
