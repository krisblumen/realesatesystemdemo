<?php

namespace Tests\Feature\Regression;

use App\Filament\Resources\UserResource;
use App\Filament\Resources\ZoneResource;
use App\Models\Property;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Epica123RegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_admin_panel_and_previous_resources_remain_operational(): void
    {
        $owner = User::factory()->withRole('owner')->create();

        $this->actingAs($owner)->get('/admin')->assertOk();
        $this->actingAs($owner)->get(UserResource::getUrl('index'))->assertOk();
        $this->actingAs($owner)->get(ZoneResource::getUrl('index'))->assertOk();
    }

    public function test_roles_and_properties_permission_remain_seeded(): void
    {
        $this->assertSame(
            ['admin', 'agente', 'arquitectura', 'owner', 'proyectos'],
            Role::query()->orderBy('name')->pluck('name')->all(),
        );
        $this->assertTrue(Permission::query()->where('name', 'properties.manage')->exists());
    }

    public function test_user_and_zone_property_contracts_resolve_real_records(): void
    {
        $agent = User::factory()->create();
        $zone = Zone::factory()->create();
        $property = Property::factory()->create([
            'agent_id' => $agent->id,
            'zone_id' => $zone->id,
        ]);

        $this->assertTrue($agent->properties->contains($property));
        $this->assertTrue($zone->properties->contains($property));
    }
}
