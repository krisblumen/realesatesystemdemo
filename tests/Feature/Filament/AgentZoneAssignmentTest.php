<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\ZoneResource;
use App\Filament\Resources\ZoneResource\Pages\EditZone;
use App\Filament\Resources\ZoneResource\RelationManagers\AgentsRelationManager;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class AgentZoneAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_agent_zone_pivot_is_unique_and_exposes_bidirectional_relationships(): void
    {
        $zone = Zone::factory()->create();
        $agent = $this->userWithRole('agente');

        $zone->agents()->attach($agent);
        $zone->agents()->syncWithoutDetaching([$agent->id]);

        $this->assertTrue(Schema::hasTable('agent_zone'));
        $this->assertCount(1, $zone->refresh()->agents);
        $this->assertTrue($zone->agents->first()->is($agent));
        $this->assertCount(1, $agent->refresh()->zones);
        $this->assertTrue($agent->zones->first()->is($zone));
    }

    public function test_owner_and_admin_can_attach_and_detach_agents_but_agent_cannot(): void
    {
        $zone = Zone::factory()->create();
        $agent = $this->userWithRole('agente');

        foreach (['owner', 'admin'] as $role) {
            $manager = $this->actingAs($this->userWithRole($role))->relationManager($zone);

            $manager
                ->callTableAction('attach', data: ['recordId' => $agent->id])
                ->assertHasNoTableActionErrors();

            $this->assertTrue($zone->refresh()->agents()->whereKey($agent->id)->exists());

            $manager->assertCanSeeTableRecords([$agent]);

            $manager
                ->callTableAction('detach', $agent)
                ->assertHasNoTableActionErrors();

            $this->assertFalse($zone->refresh()->agents()->whereKey($agent->id)->exists());
        }

        $this->actingAs($agent)
            ->get(ZoneResource::getUrl('edit', ['record' => $zone]))
            ->assertForbidden();
    }

    public function test_owner_can_reassign_agent_between_zones_without_duplicate_assignments(): void
    {
        $origin = Zone::factory()->create();
        $target = Zone::factory()->create();
        $agent = $this->userWithRole('agente');

        $origin->agents()->syncWithoutDetaching([$agent->id]);
        $origin->agents()->syncWithoutDetaching([$agent->id]);

        $this->actingAs($this->userWithRole('owner'))
            ->relationManager($target)
            ->callTableAction('attach', data: ['recordId' => $agent->id])
            ->assertHasNoTableActionErrors()
            ->assertCanSeeTableRecords([$agent]);

        $origin->agents()->detach($agent);

        $this->assertDatabaseCount('agent_zone', 1);
        $this->assertFalse($origin->refresh()->agents()->whereKey($agent->id)->exists());
        $this->assertTrue($target->refresh()->agents()->whereKey($agent->id)->exists());
        $this->assertTrue($agent->refresh()->zones->first()->is($target));
    }

    public function test_only_active_agente_users_are_assignable_from_relation_manager_options(): void
    {
        $activeAgent = $this->userWithRole('agente');
        $suspendedAgent = User::factory()->suspended()->withRole('agente')->create();
        $owner = $this->userWithRole('owner');
        $admin = $this->userWithRole('admin');

        $assignableAgentIds = AgentsRelationManager::assignableAgentsQuery(User::query())
            ->pluck('id')
            ->all();

        $this->assertContains($activeAgent->id, $assignableAgentIds);
        $this->assertNotContains($suspendedAgent->id, $assignableAgentIds);
        $this->assertNotContains($owner->id, $assignableAgentIds);
        $this->assertNotContains($admin->id, $assignableAgentIds);
    }

    public function test_backend_rejects_attaching_owner_or_admin_as_zone_agents(): void
    {
        $zone = Zone::factory()->create();
        $owner = $this->userWithRole('owner');
        $admin = $this->userWithRole('admin');

        $this->actingAs($owner)
            ->relationManager($zone)
            ->callTableAction('attach', data: ['recordId' => $admin->id])
            ->assertHasTableActionErrors();

        $this->assertFalse($zone->refresh()->agents()->whereKey($admin->id)->exists());
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->withRole($role)->create();
    }

    private function relationManager(Zone $zone): Testable
    {
        return Livewire::test(AgentsRelationManager::class, [
            'ownerRecord' => $zone,
            'pageClass' => EditZone::class,
        ]);
    }
}
