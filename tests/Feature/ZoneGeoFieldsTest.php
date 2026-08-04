<?php

namespace Tests\Feature;

use App\Filament\Resources\ZoneResource\Pages\CreateZone;
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

class ZoneGeoFieldsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(GeoCatalogSeeder::class);
    }

    public function test_zone_belongs_to_state_and_municipality(): void
    {
        [$state, $municipality] = $this->geoPair('Querétaro');

        $zone = Zone::factory()->create([
            'state_id' => $state->id,
            'municipality_id' => $municipality->id,
            'postal_code' => '76000',
        ]);

        $this->assertTrue($zone->state->is($state));
        $this->assertTrue($zone->municipality->is($municipality));
        $this->assertSame('76000', $zone->postal_code);
    }

    public function test_municipality_options_are_scoped_to_selected_state(): void
    {
        [$queretaro, $queretaroMunicipality] = $this->geoPair('Querétaro');
        [$aguascalientes, $aguascalientesMunicipality] = $this->geoPair('Aguascalientes');

        $options = Municipality::query()
            ->where('state_id', $queretaro->id)
            ->orderBy('name')
            ->pluck('name', 'id');

        $this->assertArrayHasKey($queretaroMunicipality->id, $options->all());
        $this->assertArrayNotHasKey($aguascalientesMunicipality->id, $options->all());
        $this->assertNotSame($aguascalientes->id, $queretaro->id);
    }

    public function test_zone_can_be_created_from_a_postal_code(): void
    {
        $this->actingAs(User::factory()->withRole('owner')->create());
        [$state, $municipality] = $this->geoPair('Querétaro');
        $this->seedPostalCodeArea('76000');

        Livewire::test(CreateZone::class)
            ->fillForm($this->zoneFormData($state, $municipality, ['76000']))
            ->call('create')
            ->assertHasNoFormErrors();

        $zone = Zone::query()->where('slug', 'codigo-valido')->firstOrFail();

        $this->assertSame(['76000'], $zone->postalCodeList());
        $this->assertSame('76000', $zone->postal_code);
        $this->assertNotNull($zone->polygon);
    }

    public function test_zone_form_requires_at_least_one_postal_code(): void
    {
        $this->actingAs(User::factory()->withRole('owner')->create());
        [$state, $municipality] = $this->geoPair('Querétaro');

        Livewire::test(CreateZone::class)
            ->fillForm($this->zoneFormData($state, $municipality, []))
            ->call('create')
            ->assertHasFormErrors(['postal_codes' => 'required']);
    }

    /**
     * @return array{State, Municipality}
     */
    private function geoPair(string $stateName): array
    {
        $state = State::query()->where('name', $stateName)->firstOrFail();
        $municipality = Municipality::query()->where('state_id', $state->id)->firstOrFail();

        return [$state, $municipality];
    }

    /**
     * @param  list<string>  $postalCodes
     * @return array<string, mixed>
     */
    private function zoneFormData(State $state, Municipality $municipality, array $postalCodes, string $slug = 'codigo-valido'): array
    {
        return [
            'name' => str($slug)->headline()->toString(),
            'slug' => $slug,
            'state_id' => $state->id,
            'municipality_id' => $municipality->id,
            'status' => 'activa',
            'postal_codes' => $postalCodes,
        ];
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
