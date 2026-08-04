<?php

namespace Tests\Feature\Owners;

use App\Models\PropertyOwner;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class PropertyOwnerPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_agent_only_manages_own_clients(): void
    {
        $agentA = User::factory()->withRole('agente')->create();
        $agentB = User::factory()->withRole('agente')->create();
        $client = PropertyOwner::factory()->create(['agent_id' => $agentA->id]);

        $this->assertTrue(Gate::forUser($agentA)->allows('view', $client));
        $this->assertTrue(Gate::forUser($agentA)->allows('update', $client));

        $this->assertFalse(Gate::forUser($agentB)->allows('view', $client));
        $this->assertFalse(Gate::forUser($agentB)->allows('update', $client));

        $this->assertFalse(PropertyOwner::visibleTo($agentB)->whereKey($client)->exists());
        $this->assertTrue(PropertyOwner::visibleTo($agentA)->whereKey($client)->exists());
    }

    public function test_owner_and_admin_manage_all_clients(): void
    {
        $agent = User::factory()->withRole('agente')->create();
        $client = PropertyOwner::factory()->create(['agent_id' => $agent->id]);

        foreach (['owner', 'admin'] as $role) {
            $actor = User::factory()->withRole($role)->create();

            $this->assertTrue(Gate::forUser($actor)->allows('view', $client));
            $this->assertTrue(Gate::forUser($actor)->allows('update', $client));
            $this->assertTrue(PropertyOwner::visibleTo($actor)->whereKey($client)->exists());
        }
    }

    public function test_only_owner_and_admin_can_delete_clients(): void
    {
        $agent = User::factory()->withRole('agente')->create();
        $admin = User::factory()->withRole('admin')->create();
        $client = PropertyOwner::factory()->create(['agent_id' => $agent->id]);

        $this->assertFalse(Gate::forUser($agent)->allows('delete', $client));
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $client));
    }
}
