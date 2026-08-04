<?php

namespace Tests\Feature\Filament;

use App\Enums\OperationType;
use App\Filament\Resources\LonaEvidenceResource;
use App\Filament\Resources\LonaEvidenceResource\Pages\ListLonaEvidence;
use App\Models\LonaBatch;
use App\Models\LonaUnit;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LonaEvidenceResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->active()->withRole($role)->create();
    }

    private function placedUnit(User $agent): LonaUnit
    {
        $batch = LonaBatch::factory()->for($agent, 'agent')->ofType(OperationType::Venta)->create();

        return LonaUnit::factory()->for($batch, 'batch')->forAgent($agent)->ofType(OperationType::Venta)->placed()->create();
    }

    public function test_owner_and_admin_can_access_evidence_but_agent_cannot(): void
    {
        foreach (['owner', 'admin'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get(LonaEvidenceResource::getUrl('index'))->assertOk();
        }

        $this->actingAs($this->userWithRole('agente'))
            ->get(LonaEvidenceResource::getUrl('index'))->assertForbidden();
    }

    public function test_only_placed_units_are_listed(): void
    {
        $agent = $this->userWithRole('agente');
        $placed = $this->placedUnit($agent);

        $batch = LonaBatch::factory()->for($agent, 'agent')->ofType(OperationType::Venta)->create();
        $pending = LonaUnit::factory()->for($batch, 'batch')->forAgent($agent)->ofType(OperationType::Venta)->create();

        $this->actingAs($this->userWithRole('admin'));

        Livewire::test(ListLonaEvidence::class)
            ->assertCanSeeTableRecords([$placed])
            ->assertCanNotSeeTableRecords([$pending]);
    }

    public function test_evidence_can_be_filtered_by_agent(): void
    {
        $agentA = $this->userWithRole('agente');
        $agentB = $this->userWithRole('agente');
        $unitA = $this->placedUnit($agentA);
        $unitB = $this->placedUnit($agentB);

        $this->actingAs($this->userWithRole('owner'));

        Livewire::test(ListLonaEvidence::class)
            ->filterTable('agent_id', $agentA->id)
            ->assertCanSeeTableRecords([$unitA])
            ->assertCanNotSeeTableRecords([$unitB]);
    }
}
