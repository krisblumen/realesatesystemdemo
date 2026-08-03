<?php

namespace Tests\Feature\Zones;

use App\Models\Zone;
use Database\Seeders\GeoCatalogSeeder;
use Database\Seeders\ZoneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression: migration 2026_07_04_000000 changed zones.polygon from
 * geometry(Polygon,4326) to geometry(MultiPolygon,4326), but the seeder kept
 * emitting POLYGON WKT. Magellan's cast rejected it, so
 * `migrate:fresh --seed` — the documented way to provision an environment —
 * died on ZoneSeeder.
 *
 * The seeder must produce geometry the current column accepts.
 */
class ZoneSeederGeometryTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_seeder_runs_against_the_multipolygon_column(): void
    {
        $this->seed(GeoCatalogSeeder::class);
        $this->seed(ZoneSeeder::class);

        $this->assertGreaterThan(0, Zone::count(), 'ZoneSeeder must create its zones.');
    }

    public function test_every_seeded_zone_is_stored_as_a_valid_multipolygon(): void
    {
        $this->seed(GeoCatalogSeeder::class);
        $this->seed(ZoneSeeder::class);

        $rows = DB::select('select name, GeometryType(polygon) as type, ST_IsValid(polygon) as valid from zones');

        $this->assertNotEmpty($rows);

        foreach ($rows as $row) {
            $this->assertSame('MULTIPOLYGON', $row->type, "Zone {$row->name} must be a MultiPolygon.");
            $this->assertTrue((bool) $row->valid, "Zone {$row->name} must be a valid geometry.");
        }
    }

    public function test_the_seeder_is_idempotent(): void
    {
        $this->seed(GeoCatalogSeeder::class);
        $this->seed(ZoneSeeder::class);
        $count = Zone::count();

        $this->seed(ZoneSeeder::class);

        $this->assertSame($count, Zone::count(), 'Re-seeding must not duplicate zones.');
    }
}
