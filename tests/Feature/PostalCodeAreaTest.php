<?php

namespace Tests\Feature;

use App\Models\PostalCodeArea;
use App\Services\Zones\ZoneGeometry;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PostalCodeAreaTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_creates_table_with_correct_schema(): void
    {
        $this->assertTrue(Schema::hasTable('postal_code_areas'));

        $columns = DB::selectOne(
            <<<'SQL'
            SELECT
                (SELECT data_type FROM information_schema.columns WHERE table_name = 'postal_code_areas' AND column_name = 'postal_code') AS postal_code_type,
                (SELECT character_maximum_length FROM information_schema.columns WHERE table_name = 'postal_code_areas' AND column_name = 'postal_code') AS postal_code_length,
                (SELECT is_nullable FROM information_schema.columns WHERE table_name = 'postal_code_areas' AND column_name = 'postal_code') AS postal_code_nullable,
                (SELECT is_nullable FROM information_schema.columns WHERE table_name = 'postal_code_areas' AND column_name = 'municipality_id') AS municipality_id_nullable
            SQL
        );

        $this->assertSame('character varying', $columns->postal_code_type);
        $this->assertSame(5, (int) $columns->postal_code_length);
        $this->assertSame('NO', $columns->postal_code_nullable);
        $this->assertSame('YES', $columns->municipality_id_nullable);

        $geometryType = DB::selectOne(
            "SELECT type FROM geometry_columns WHERE f_table_name = 'postal_code_areas' AND f_geometry_column = 'polygon'"
        );
        $this->assertSame('MULTIPOLYGON', $geometryType->type);

        $gistIndexExists = DB::selectOne(
            <<<'SQL'
            SELECT 1 AS found
            FROM pg_indexes
            WHERE tablename = 'postal_code_areas'
              AND indexdef ILIKE '%USING gist%'
              AND indexdef ILIKE '%polygon%'
            SQL
        );
        $this->assertNotNull($gistIndexExists);

        $fkExists = DB::selectOne(
            <<<'SQL'
            SELECT 1 AS found
            FROM information_schema.table_constraints tc
            JOIN information_schema.key_column_usage kcu
                ON tc.constraint_name = kcu.constraint_name
            WHERE tc.table_name = 'postal_code_areas'
              AND tc.constraint_type = 'FOREIGN KEY'
              AND kcu.column_name = 'municipality_id'
            SQL
        );
        $this->assertNotNull($fkExists);
    }

    public function test_unique_constraint_on_postal_code(): void
    {
        $this->insertPostalCodeArea('06600', $this->singlePolygonMultiPolygonWkt());

        $this->expectException(QueryException::class);

        DB::table('postal_code_areas')->insert([
            'postal_code' => '06600',
            'created_at' => now(),
            'updated_at' => now(),
            'polygon' => DB::raw("ST_GeomFromEWKT('{$this->singlePolygonMultiPolygonWkt()}')"),
        ]);
    }

    public function test_polygon_as_geo_json_returns_multipolygon_type(): void
    {
        $this->insertPostalCodeArea('11000', $this->singlePolygonMultiPolygonWkt());

        $area = PostalCodeArea::query()->where('postal_code', '11000')->firstOrFail();

        $geoJson = $area->polygonAsGeoJson();

        $this->assertNotNull($geoJson);
        $decoded = json_decode((string) $geoJson, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('MultiPolygon', $decoded['type']);
    }

    public function test_unknown_postal_code_returns_null(): void
    {
        $area = PostalCodeArea::query()->where('postal_code', '99999')->first();

        $this->assertNull($area);
    }

    public function test_largest_ring_geo_json_returns_biggest_polygon(): void
    {
        $this->insertPostalCodeArea('76000', $this->twoComponentsMultiPolygonWkt());

        $geoJson = PostalCodeArea::largestRingGeoJson('76000');

        $this->assertNotNull($geoJson);
        $decoded = json_decode((string) $geoJson, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('Polygon', $decoded['type']);

        $area = DB::selectOne(
            'SELECT ST_Area(ST_GeomFromGeoJSON(?)) AS area',
            [$geoJson],
        );

        // The bigger component spans roughly a 0.1deg square; the smaller one a 0.01deg square.
        $this->assertGreaterThan(0.0001, (float) $area->area);
    }

    public function test_largest_ring_geo_json_returns_null_for_unknown_postal_code(): void
    {
        $geoJson = PostalCodeArea::largestRingGeoJson('00000');

        $this->assertNull($geoJson);
    }

    public function test_largest_ring_geo_json_output_passes_zone_geometry_validation(): void
    {
        $this->insertPostalCodeArea('76001', $this->twoComponentsMultiPolygonWkt());

        $geoJson = PostalCodeArea::largestRingGeoJson('76001');

        $this->assertNotNull($geoJson);

        $ewkt = ZoneGeometry::polygonEwktFromGeoJson((string) $geoJson);

        $this->assertStringContainsString('SRID=4326', $ewkt);
        $this->assertStringContainsString('POLYGON', $ewkt);
    }

    private function insertPostalCodeArea(string $postalCode, string $multiPolygonEwkt, ?int $municipalityId = null, ?int $stateId = null): void
    {
        DB::statement(
            <<<'SQL'
            INSERT INTO postal_code_areas (postal_code, municipality_id, state_id, polygon, created_at, updated_at)
            VALUES (?, ?, ?, ST_GeomFromEWKT(?), NOW(), NOW())
            SQL,
            [$postalCode, $municipalityId, $stateId, $multiPolygonEwkt],
        );
    }

    private function singlePolygonMultiPolygonWkt(): string
    {
        return 'SRID=4326;MULTIPOLYGON(((-100.40 20.60, -100.30 20.60, -100.30 20.70, -100.40 20.70, -100.40 20.60)))';
    }

    private function twoComponentsMultiPolygonWkt(): string
    {
        // Big component: ~0.1deg square. Small component: ~0.01deg square (10x smaller per side).
        return 'SRID=4326;MULTIPOLYGON('
            .'((-100.40 20.60, -100.30 20.60, -100.30 20.70, -100.40 20.70, -100.40 20.60)),'
            .'((-99.40 21.60, -99.39 21.60, -99.39 21.61, -99.40 21.61, -99.40 21.60))'
            .')';
    }
}
