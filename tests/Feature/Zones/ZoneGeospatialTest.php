<?php

namespace Tests\Feature\Zones;

use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ZoneGeospatialTest extends TestCase
{
    use RefreshDatabase;

    public function test_zone_persists_polygon_with_srid_4326_and_reads_it_as_geojson_without_corruption(): void
    {
        $zone = Zone::factory()->make();

        $zone->setPolygonFromWkt('POLYGON((-99.1400 19.4300, -99.1300 19.4300, -99.1300 19.4400, -99.1400 19.4400, -99.1400 19.4300))');
        $zone->save();

        $stored = DB::table('zones')
            ->where('id', $zone->id)
            ->selectRaw('ST_SRID(polygon) as srid, ST_AsText(polygon) as wkt, ST_AsGeoJSON(polygon)::json as geojson')
            ->first();

        $this->assertSame(4326, $stored->srid);
        $this->assertSame('MULTIPOLYGON(((-99.14 19.43,-99.13 19.43,-99.13 19.44,-99.14 19.44,-99.14 19.43)))', $stored->wkt);
        $this->assertSame($stored->geojson, $zone->refresh()->polygonAsGeoJson());
    }

    public function test_zone_rejects_invalid_polygon_using_postgis_validation(): void
    {
        $zone = Zone::factory()->make();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Zone polygon must be a valid PostGIS polygon with SRID 4326 and a closed exterior ring.');

        $zone->setPolygonFromWkt('POLYGON((0 0, 1 1, 1 0, 0 1, 0 0))');
        $zone->save();
    }

    public function test_zone_rejects_polygon_with_non_4326_srid(): void
    {
        $zone = Zone::factory()->make();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Zone polygon must be a valid PostGIS polygon with SRID 4326 and a closed exterior ring.');

        $zone->setPolygonFromWkt('SRID=3857;POLYGON((0 0, 1 0, 1 1, 0 1, 0 0))');
        $zone->save();
    }

    public function test_zone_rejects_polygon_with_unclosed_ring(): void
    {
        $zone = Zone::factory()->make();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Zone polygon must be a valid PostGIS polygon with SRID 4326 and a closed exterior ring.');

        $zone->setPolygonFromWkt('POLYGON((0 0, 1 0, 1 1, 0 1))');
        $zone->save();
    }

    public function test_zone_rejects_geojson_polygon_with_non_4326_crs_when_postgis_recognizes_legacy_crs(): void
    {
        $geoJson = [
            'type' => 'Polygon',
            'crs' => [
                'type' => 'name',
                'properties' => [
                    'name' => 'EPSG:3857',
                ],
            ],
            'coordinates' => [[
                [0, 0],
                [1, 0],
                [1, 1],
                [0, 1],
                [0, 0],
            ]],
        ];

        $encoded = json_encode($geoJson, JSON_THROW_ON_ERROR);
        $srid = DB::selectOne(
            'SELECT ST_SRID(ST_GeomFromGeoJSON(?)) AS srid',
            [$encoded],
        )->srid;

        if ((int) $srid !== 3857) {
            $this->markTestSkipped('This PostGIS version ignores legacy GeoJSON CRS metadata, so EPSG:3857 cannot exercise SRID rejection through ST_GeomFromGeoJSON.');
        }

        $zone = Zone::factory()->make();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Zone polygon must be a valid PostGIS polygon with SRID 4326 and a closed exterior ring.');

        $zone->setPolygonFromGeoJson($geoJson);
        $zone->save();
    }

    public function test_zone_calculates_center_point_from_polygon_on_create_and_update(): void
    {
        $zone = Zone::factory()->make();
        $zone->setPolygonFromGeoJson([
            'type' => 'Polygon',
            'coordinates' => [[
                [-99.14, 19.43],
                [-99.12, 19.43],
                [-99.12, 19.45],
                [-99.14, 19.45],
                [-99.14, 19.43],
            ]],
        ]);
        $zone->save();

        $this->assertCenterPointEquals($zone, -99.13, 19.44);

        $zone->setPolygonFromWkt('POLYGON((-99.1600 19.4600, -99.1400 19.4600, -99.1400 19.4800, -99.1600 19.4800, -99.1600 19.4600))');
        $zone->save();

        $this->assertCenterPointEquals($zone->refresh(), -99.15, 19.47);
    }

    public function test_scope_finds_zones_containing_a_point_with_index_friendly_bounding_box(): void
    {
        $inside = Zone::factory()->make(['name' => 'Inside Zone']);
        $inside->setPolygonFromWkt('POLYGON((-99.1400 19.4300, -99.1200 19.4300, -99.1200 19.4500, -99.1400 19.4500, -99.1400 19.4300))');
        $inside->save();

        $outside = Zone::factory()->make(['name' => 'Outside Zone']);
        $outside->setPolygonFromWkt('POLYGON((-100.1400 20.4300, -100.1200 20.4300, -100.1200 20.4500, -100.1400 20.4500, -100.1400 20.4300))');
        $outside->save();

        $query = Zone::query()->containingPoint(-99.13, 19.44);

        $this->assertStringContainsString('&&', $query->toSql());
        $this->assertTrue(str_contains($query->toSql(), 'ST_Contains') || str_contains($query->toSql(), 'ST_Within'));
        $this->assertTrue($query->pluck('id')->contains($inside->id));
        $this->assertFalse($query->pluck('id')->contains($outside->id));
    }

    public function test_property_point_scope_keeps_qualified_signature_after_contract_activation(): void
    {
        $query = Zone::query()->containingPropertyPoint('properties.location');

        $this->assertStringContainsString('"properties"."location"', $query->toSql());
        $this->assertTrue(Schema::hasTable('properties'));
    }

    private function assertCenterPointEquals(Zone $zone, float $longitude, float $latitude): void
    {
        $center = DB::table('zones')
            ->where('id', $zone->id)
            ->selectRaw('ST_X(center_point) as longitude, ST_Y(center_point) as latitude')
            ->first();

        $this->assertNotNull($center);
        $this->assertEqualsWithDelta($longitude, (float) $center->longitude, 0.000001);
        $this->assertEqualsWithDelta($latitude, (float) $center->latitude, 0.000001);
    }
}
