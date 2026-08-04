<?php

namespace Tests\Feature\Properties;

use App\Models\Property;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class PropertyPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_agent_cannot_manage_another_agents_property_even_in_a_shared_zone(): void
    {
        $zone = Zone::factory()->create();
        $agentA = User::factory()->withRole('agente')->create();
        $agentB = User::factory()->withRole('agente')->create();
        $zone->agents()->attach([$agentA->id, $agentB->id]);
        $property = Property::factory()->create([
            'zone_id' => $zone->id,
            'agent_id' => $agentA->id,
        ]);

        $this->assertFalse(Gate::forUser($agentB)->allows('view', $property));
        $this->assertFalse(Gate::forUser($agentB)->allows('update', $property));
        $this->assertFalse(Property::visibleTo($agentB)->whereKey($property)->exists());
    }

    public function test_agent_can_manage_owned_and_unassigned_properties_in_assigned_zones(): void
    {
        $zone = Zone::factory()->create();
        $agent = User::factory()->withRole('agente')->create();
        $zone->agents()->attach($agent);
        $owned = Property::factory()->create(['agent_id' => $agent->id]);
        $unassigned = Property::factory()->create(['zone_id' => $zone->id, 'agent_id' => null]);

        $this->assertTrue(Gate::forUser($agent)->allows('update', $owned));
        $this->assertTrue(Gate::forUser($agent)->allows('update', $unassigned));
        $this->assertFalse(Gate::forUser($agent)->allows('delete', $owned));
    }

    public function test_owner_and_admin_can_manage_and_soft_delete_properties(): void
    {
        $owner = User::factory()->withRole('owner')->create();
        $admin = User::factory()->withRole('admin')->create();
        $property = Property::factory()->create();

        foreach ([$owner, $admin] as $user) {
            $this->assertTrue(Gate::forUser($user)->allows('viewAny', Property::class));
            $this->assertTrue(Gate::forUser($user)->allows('update', $property));
            $this->assertTrue(Gate::forUser($user)->allows('delete', $property));
            $this->assertTrue(Gate::forUser($user)->allows('restore', $property));
            $this->assertFalse(Gate::forUser($user)->allows('forceDelete', $property));
        }
    }
}
