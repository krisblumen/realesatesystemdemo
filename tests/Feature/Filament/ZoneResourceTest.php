<?php

namespace Tests\Feature\Filament;

use App\Enums\ZoneStatus;
use App\Filament\Resources\ZoneResource;
use App\Filament\Resources\ZoneResource\Pages\CreateZone;
use App\Filament\Resources\ZoneResource\Pages\EditZone;
use App\Filament\Resources\ZoneResource\Pages\ListZones;
use App\Models\Municipality;
use App\Models\PostalCode;
use App\Models\Property;
use App\Models\State;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\GeoCatalogSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ZoneResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(GeoCatalogSeeder::class);
    }

    public function test_owner_and_admin_can_access_zone_resource_pages_while_agente_cannot(): void
    {
        $owner = $this->userWithRole('owner');
        $admin = $this->userWithRole('admin');
        $agente = $this->userWithRole('agente');
        $zone = Zone::factory()->create();

        $this->actingAs($owner)->get(ZoneResource::getUrl('index'))->assertOk();
        $this->actingAs($owner)->get(ZoneResource::getUrl('create'))->assertOk();
        $this->actingAs($owner)->get(ZoneResource::getUrl('edit', ['record' => $zone]))->assertOk();

        $this->actingAs($admin)->get(ZoneResource::getUrl('index'))->assertOk();
        $this->actingAs($admin)->get(ZoneResource::getUrl('create'))->assertOk();
        $this->actingAs($admin)->get(ZoneResource::getUrl('edit', ['record' => $zone]))->assertOk();

        $this->actingAs($agente)->get(ZoneResource::getUrl('index'))->assertForbidden();
    }

    public function test_owner_can_create_zone_from_multiple_postal_codes(): void
    {
        $this->actingAs($this->userWithRole('owner'));
        [$state, $municipality] = $this->geoPair();

        $this->seedPostalCatalog('76090', 'Centro');
        $this->seedPostalCatalog('76093', 'La Cañada');

        Livewire::test(CreateZone::class)
            ->fillForm([
                'name' => 'Centro Sur',
                'slug' => 'centro-sur',
                'state_id' => $state->id,
                'municipality_id' => $municipality->id,
                'status' => ZoneStatus::Active->value,
                'postal_codes' => ['76090', '76093'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $zone = Zone::where('slug', 'centro-sur')->firstOrFail();

        $this->assertSame('Centro Sur', $zone->name);
        $this->assertTrue($zone->state->is($state));
        $this->assertSame(['76090', '76093'], $zone->postalCodeList());
        $this->assertSame('76090', $zone->postal_code);
        $this->assertNotNull($zone->polygon);
        $this->assertStringContainsString('"type":"MultiPolygon"', (string) $zone->polygonAsGeoJson());
        // La descripción se arma automáticamente con las colonias de todos los CP.
        $this->assertStringContainsString('Centro', (string) $zone->description);
        $this->assertStringContainsString('La Cañada', (string) $zone->description);
    }

    public function test_colonia_finder_adds_its_postal_code_to_the_zone_selection(): void
    {
        $this->actingAs($this->userWithRole('owner'));
        [$state, $municipality] = $this->geoPair();

        $this->seedPostalCatalog('76090', 'Centro');
        $this->seedPostalCatalog('76093', 'La Cañada');
        $coloniaId = PostalCode::where('postal_code', '76093')->where('colonia', 'La Cañada')->value('id');

        Livewire::test(CreateZone::class)
            ->fillForm([
                'state_id' => $state->id,
                'municipality_id' => $municipality->id,
                'postal_codes' => ['76090'],
            ])
            ->set('data.colonia_finder', [$coloniaId])
            ->assertSet('data.postal_codes', ['76090', '76093'])
            ->assertSet('data.colonia_finder', []);
    }

    public function test_zone_form_validates_required_unique_slug_and_postal_codes(): void
    {
        $this->actingAs($this->userWithRole('owner'));
        Zone::factory()->create(['slug' => 'centro-sur']);

        Livewire::test(CreateZone::class)
            ->fillForm([
                'name' => '',
                'slug' => 'centro-sur',
                'state_id' => null,
                'municipality_id' => null,
                'status' => null,
                'postal_codes' => [],
            ])
            ->call('create')
            ->assertHasFormErrors([
                'name' => 'required',
                'slug' => 'unique',
                'state_id' => 'required',
                'municipality_id' => 'required',
                'status' => 'required',
                'postal_codes' => 'required',
            ]);
    }

    public function test_admin_can_edit_zone_postal_codes_but_cannot_delete_or_restore(): void
    {
        $admin = $this->userWithRole('admin');
        [$state, $municipality] = $this->geoPair();
        $zone = Zone::factory()->create([
            'name' => 'Juriquilla',
            'slug' => 'juriquilla',
            'state_id' => $state->id,
            'municipality_id' => $municipality->id,
            'status' => ZoneStatus::Active,
        ]);

        $this->seedPostalCatalog('76230', 'Juriquilla');

        $this->actingAs($admin);

        Livewire::test(EditZone::class, ['record' => $zone->getRouteKey()])
            ->fillForm([
                'name' => 'Juriquilla',
                'slug' => 'juriquilla',
                'state_id' => $state->id,
                'municipality_id' => $municipality->id,
                'status' => ZoneStatus::Inactive->value,
                'postal_codes' => ['76230'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $zone->refresh();

        $this->assertSame(ZoneStatus::Inactive, $zone->status);
        $this->assertSame(['76230'], $zone->postalCodeList());
        $this->assertSame('76230', $zone->postal_code);
        $this->assertStringContainsString('Juriquilla', (string) $zone->description);

        Livewire::test(ListZones::class)
            ->assertTableActionHidden('delete', $zone)
            ->assertTableActionHidden('restore', $zone);
    }

    public function test_editing_zone_cannot_remove_postal_code_with_assigned_properties(): void
    {
        $owner = $this->userWithRole('owner');
        [$state, $municipality] = $this->geoPair();
        $zone = Zone::factory()->create([
            'state_id' => $state->id,
            'municipality_id' => $municipality->id,
        ]);

        $this->seedPostalCatalog('76090', 'Centro');
        $this->seedPostalCatalog('76093', 'La Cañada');
        $zone->syncPostalCodes(['76090', '76093']);

        Property::factory()->create(['zone_id' => $zone->id, 'postal_code' => '76090']);

        $this->actingAs($owner);

        Livewire::test(EditZone::class, ['record' => $zone->getRouteKey()])
            ->fillForm([
                'postal_codes' => ['76093'],
            ])
            ->call('save')
            ->assertNotified();

        $this->assertSame(['76090', '76093'], $zone->refresh()->postalCodeList());
    }

    public function test_owner_cannot_delete_zone_from_edit_page_with_assigned_properties(): void
    {
        $owner = $this->userWithRole('owner');
        $zone = Zone::factory()->create();
        Property::factory()->create(['zone_id' => $zone->id]);

        $this->actingAs($owner);

        Livewire::test(EditZone::class, ['record' => $zone->getRouteKey()])
            ->callAction('delete')
            ->assertNotified();

        $this->assertFalse($zone->fresh()->trashed());
    }

    public function test_owner_can_soft_delete_and_restore_zone_from_resource_table(): void
    {
        $owner = $this->userWithRole('owner');
        $zone = Zone::factory()->create();

        $this->actingAs($owner);

        Livewire::test(ListZones::class)
            ->callTableAction('delete', $zone);

        $this->assertSoftDeleted('zones', ['id' => $zone->id]);

        $trashedZone = Zone::withTrashed()->findOrFail($zone->id);

        Livewire::test(ListZones::class)
            ->filterTable('trashed', false)
            ->callTableAction('restore', $trashedZone);

        $this->assertFalse($zone->fresh()->trashed());
    }

    public function test_owner_can_search_and_filter_zones_by_state_and_status(): void
    {
        $owner = $this->userWithRole('owner');
        [$state, $municipality] = $this->geoPair('Querétaro');
        [$otherState, $otherMunicipality] = $this->geoPair('Aguascalientes');
        $activeZone = Zone::factory()->create([
            'name' => 'Centro Histórico',
            'state_id' => $state->id,
            'municipality_id' => $municipality->id,
            'status' => ZoneStatus::Active,
        ]);
        $inactiveZone = Zone::factory()->inactive()->create([
            'name' => 'Aguascalientes Norte',
            'state_id' => $otherState->id,
            'municipality_id' => $otherMunicipality->id,
        ]);

        $this->actingAs($owner);

        Livewire::test(ListZones::class)
            ->searchTable('Centro')
            ->assertCanSeeTableRecords([$activeZone])
            ->assertCanNotSeeTableRecords([$inactiveZone]);

        Livewire::test(ListZones::class)
            ->filterTable('state_id', $otherState->id)
            ->assertCanSeeTableRecords([$inactiveZone])
            ->assertCanNotSeeTableRecords([$activeZone]);

        Livewire::test(ListZones::class)
            ->filterTable('status', ZoneStatus::Inactive->value)
            ->assertCanSeeTableRecords([$inactiveZone])
            ->assertCanNotSeeTableRecords([$activeZone]);
    }

    public function test_zone_form_requires_at_least_one_postal_code(): void
    {
        $this->actingAs($this->userWithRole('owner'));
        [$state, $municipality] = $this->geoPair();

        Livewire::test(CreateZone::class)
            ->fillForm([
                'name' => 'Sin CP',
                'slug' => 'sin-cp',
                'state_id' => $state->id,
                'municipality_id' => $municipality->id,
                'status' => ZoneStatus::Active->value,
                'postal_codes' => [],
            ])
            ->call('create')
            ->assertHasFormErrors(['postal_codes' => 'required']);
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->withRole($role)->create();
    }

    /**
     * @return array{State, Municipality}
     */
    private function geoPair(string $stateName = 'Querétaro'): array
    {
        $state = State::query()->where('name', $stateName)->firstOrFail();
        $municipality = Municipality::query()->where('state_id', $state->id)->firstOrFail();

        return [$state, $municipality];
    }

    /**
     * Siembra un CP con su geometría (postal_code_areas) y una colonia (postal_codes)
     * para poder componer zonas reales en los tests.
     */
    private function seedPostalCatalog(string $code, string $colonia): void
    {
        DB::table('postal_codes')->updateOrInsert(
            ['postal_code' => $code, 'colonia' => $colonia],
            ['created_at' => now(), 'updated_at' => now()],
        );

        DB::statement(
            "INSERT INTO postal_code_areas (postal_code, polygon, created_at, updated_at)
             VALUES (?, ST_Multi(ST_GeomFromText('POLYGON((-100.40 20.60,-100.30 20.60,-100.30 20.70,-100.40 20.70,-100.40 20.60))', 4326)), now(), now())
             ON CONFLICT (postal_code) DO NOTHING",
            [$code],
        );
    }
}
