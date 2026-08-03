<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\RelationManagers\ZonesRelationManager;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserZonesRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_lists_agent_zones_and_owner_can_detach_them(): void
    {
        $agent = $this->userWithRole('agente');
        $zoneA = Zone::factory()->create(['name' => 'Zona A']);
        $zoneB = Zone::factory()->create(['name' => 'Zona B']);
        $agent->zones()->attach([$zoneA->id, $zoneB->id]);
        $this->actingAs($this->userWithRole('owner'));

        Livewire::test(ZonesRelationManager::class, [
            'ownerRecord' => $agent,
            'pageClass' => EditUser::class,
        ])
            ->assertCanSeeTableRecords([$zoneA, $zoneB])
            ->callTableAction('detach', $zoneA)
            ->assertHasNoTableActionErrors();

        $this->assertFalse($agent->zones()->where('zones.id', $zoneA->id)->exists());
        $this->assertTrue($agent->zones()->where('zones.id', $zoneB->id)->exists());
    }

    public function test_zones_cannot_be_attached_from_the_user_screen(): void
    {
        $agent = $this->userWithRole('agente');
        $this->actingAs($this->userWithRole('owner'));

        Livewire::test(ZonesRelationManager::class, [
            'ownerRecord' => $agent,
            'pageClass' => EditUser::class,
        ])->assertTableActionDoesNotExist('attach');
    }

    public function test_zones_relation_manager_is_only_visible_for_agents(): void
    {
        $this->assertTrue(ZonesRelationManager::canViewForRecord($this->userWithRole('agente'), EditUser::class));
        $this->assertFalse(ZonesRelationManager::canViewForRecord($this->userWithRole('owner'), EditUser::class));
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->withRole($role)->create();
    }
}
