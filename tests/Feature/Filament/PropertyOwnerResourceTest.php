<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\PropertyOwnerResource\Pages\CreatePropertyOwner;
use App\Filament\Resources\PropertyOwnerResource\Pages\ListPropertyOwners;
use App\Models\PropertyOwner;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PropertyOwnerResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_agent_creating_a_client_is_forced_as_its_agent(): void
    {
        $agent = $this->userWithRole('agente');
        $this->actingAs($agent);

        Livewire::test(CreatePropertyOwner::class)
            ->fillForm([
                'first_name' => 'Laura',
                'last_name' => 'Gómez',
                'phone' => '4426667777',
                'email' => 'laura@example.com',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $client = PropertyOwner::where('phone', '4426667777')->firstOrFail();
        $this->assertSame($agent->id, $client->agent_id);
    }

    public function test_agent_cannot_register_a_client_already_owned_by_another_agent(): void
    {
        $agentA = $this->userWithRole('agente');
        $agentB = $this->userWithRole('agente');
        PropertyOwner::factory()->create([
            'first_name' => 'Pedro', 'last_name' => 'Ruiz', 'phone' => '442 111 2233', 'agent_id' => $agentA->id,
        ]);

        $this->actingAs($agentB);

        Livewire::test(CreatePropertyOwner::class)
            ->fillForm([
                'first_name' => 'Pedro',
                'last_name' => 'Ruiz',
                'phone' => '4421112233',
                'email' => null,
            ])
            ->call('create');

        // El duplicado de otro agente queda bloqueado: solo existe el original.
        $this->assertSame(1, PropertyOwner::where('last_name', 'Ruiz')->count());
        $this->assertSame($agentA->id, PropertyOwner::where('last_name', 'Ruiz')->sole()->agent_id);
    }

    public function test_agent_only_sees_own_clients(): void
    {
        $agentA = $this->userWithRole('agente');
        $agentB = $this->userWithRole('agente');
        $mine = PropertyOwner::factory()->create(['agent_id' => $agentA->id]);
        $other = PropertyOwner::factory()->create(['agent_id' => $agentB->id]);

        $this->actingAs($agentA);

        Livewire::test(ListPropertyOwners::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_owner_sees_all_clients(): void
    {
        $agentA = $this->userWithRole('agente');
        $agentB = $this->userWithRole('agente');
        $a = PropertyOwner::factory()->create(['agent_id' => $agentA->id]);
        $b = PropertyOwner::factory()->create(['agent_id' => $agentB->id]);

        $this->actingAs($this->userWithRole('owner'));

        Livewire::test(ListPropertyOwners::class)
            ->assertCanSeeTableRecords([$a, $b]);
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->withRole($role)->create();
    }
}
