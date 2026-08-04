<?php

namespace Tests\Feature;

use App\Filament\Resources\ZoneResource\Pages\CreateZone;
use App\Filament\Resources\ZoneResource\Pages\EditZone;
use App\Models\Municipality;
use App\Models\State;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\GeoCatalogSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ZonePolygonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(GeoCatalogSeeder::class);
        $this->actingAs(User::factory()->withRole('owner')->create());
    }

    public function test_saving_a_zone_from_postal_codes_stores_a_valid_postgis_multipolygon(): void
    {
        $zone = $this->createZoneFromForm('poligono-valido');

        $geometry = DB::selectOne(
            'SELECT ST_IsValid(polygon) AS valid, ST_SRID(polygon) AS srid, GeometryType(polygon) AS type FROM zones WHERE id = ?',
            [$zone->id],
        );

        $this->assertTrue((bool) $geometry->valid);
        $this->assertSame(4326, (int) $geometry->srid);
        $this->assertSame('MULTIPOLYGON', $geometry->type);
    }

    public function test_center_point_equals_st_centroid_of_polygon(): void
    {
        $zone = $this->createZoneFromForm('centroide-valido');

        $comparison = DB::selectOne(
            'SELECT ST_Equals(center_point, ST_Centroid(polygon)) AS same_centroid FROM zones WHERE id = ?',
            [$zone->id],
        );

        $this->assertTrue((bool) $comparison->same_centroid);
    }

    public function test_edit_form_prefills_the_zone_postal_codes(): void
    {
        $zone = $this->createZoneFromForm('edicion-cps');

        Livewire::test(EditZone::class, ['record' => $zone->getRouteKey()])
            ->assertFormSet([
                'postal_codes' => ['76000'],
            ]);
    }

    private function createZoneFromForm(string $slug): Zone
    {
        [$state, $municipality] = $this->geoPair();
        $this->seedPostalCodeArea('76000');

        Livewire::test(CreateZone::class)
            ->fillForm([
                'name' => str($slug)->headline()->toString(),
                'slug' => $slug,
                'state_id' => $state->id,
                'municipality_id' => $municipality->id,
                'status' => 'activa',
                'postal_codes' => ['76000'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        return Zone::query()->where('slug', $slug)->firstOrFail();
    }

    /**
     * @return array{State, Municipality}
     */
    private function geoPair(): array
    {
        $state = State::query()->where('name', 'Querétaro')->firstOrFail();
        $municipality = Municipality::query()->where('state_id', $state->id)->firstOrFail();

        return [$state, $municipality];
    }

    private function seedPostalCodeArea(string $code): void
    {
        DB::statement(
            "INSERT INTO postal_code_areas (postal_code, polygon, created_at, updated_at)
             VALUES (?, ST_Multi(ST_GeomFromText('POLYGON((-100.40 20.60,-100.30 20.60,-100.30 20.70,-100.40 20.70,-100.40 20.60))', 4326)), now(), now())
             ON CONFLICT (postal_code) DO NOTHING",
            [$code],
        );
    }
}
