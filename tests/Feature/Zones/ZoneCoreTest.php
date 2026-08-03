<?php

namespace Tests\Feature\Zones;

use App\Enums\ZoneStatus;
use App\Models\Property;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ZoneCoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_zones_table_exposes_core_postgis_contract(): void
    {
        $this->assertTrue(Schema::hasTable('zones'));

        foreach ([
            'id',
            'name',
            'slug',
            'description',
            'state_id',
            'municipality_id',
            'postal_code',
            'status',
            'polygon',
            'center_point',
            'deleted_at',
            'created_at',
            'updated_at',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('zones', $column), "Missing zones.{$column} column.");
        }

        $geometryColumns = DB::table('geometry_columns')
            ->where('f_table_name', 'zones')
            ->whereIn('f_geometry_column', ['polygon', 'center_point'])
            ->pluck('type', 'f_geometry_column')
            ->all();

        $this->assertSame('MULTIPOLYGON', $geometryColumns['polygon'] ?? null);
        $this->assertSame('POINT', $geometryColumns['center_point'] ?? null);
    }

    public function test_zone_generates_unique_slugs_including_soft_deleted_rows(): void
    {
        $deleted = Zone::factory()->create([
            'name' => 'Centro Histórico',
            'slug' => 'centro-historico',
        ]);
        $deleted->delete();

        $zone = Zone::factory()->create([
            'name' => 'Centro Histórico',
            'slug' => null,
        ]);

        $this->assertSame('centro-historico-2', $zone->slug);
    }

    public function test_zone_status_casts_helpers_and_soft_deletes(): void
    {
        $zone = Zone::factory()->create(['status' => ZoneStatus::Active]);

        $this->assertSame(ZoneStatus::Active, $zone->status);
        $this->assertTrue($zone->isActive());
        $this->assertFalse($zone->isInactive());

        $zone->forceFill(['status' => ZoneStatus::Inactive])->save();

        $this->assertSame(ZoneStatus::Inactive, $zone->refresh()->status);
        $this->assertFalse($zone->isActive());
        $this->assertTrue($zone->isInactive());

        $zone->delete();

        $this->assertSoftDeleted('zones', ['id' => $zone->id]);
    }

    public function test_zone_factory_can_create_valid_polygon_fixture(): void
    {
        $zone = Zone::factory()->withPolygon()->create();

        $geometry = DB::table('zones')
            ->where('id', $zone->id)
            ->selectRaw('ST_SRID(polygon) as polygon_srid, ST_SRID(center_point) as center_srid, ST_AsGeoJSON(polygon) as polygon_geojson')
            ->first();

        $this->assertSame(4326, $geometry->polygon_srid);
        $this->assertSame(4326, $geometry->center_srid);
        $this->assertStringContainsString('"type":"MultiPolygon"', $geometry->polygon_geojson);
        $this->assertNotNull($zone->refresh()->center_point);
    }

    public function test_zone_exposes_real_property_relationship_contract(): void
    {
        $zone = Zone::factory()->create();

        $this->assertTrue(Schema::hasTable('agent_zone'));
        $this->assertInstanceOf(BelongsToMany::class, $zone->agents());
        $this->assertSame(User::class, $zone->agents()->getRelated()::class);
        $this->assertInstanceOf(HasMany::class, $zone->properties());
        $this->assertSame(Property::class, $zone->properties()->getRelated()::class);
        $this->assertCount(0, $zone->properties()->get());
        $this->assertTrue(Schema::hasTable('properties'));
    }
}
