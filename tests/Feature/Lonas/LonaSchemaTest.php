<?php

namespace Tests\Feature\Lonas;

use App\Enums\LonaRequestStatus;
use App\Enums\LonaUnitStatus;
use App\Enums\OperationType;
use App\Models\LonaBatch;
use App\Models\LonaRequest;
use App\Models\LonaUnit;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class LonaSchemaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_lona_permissions_are_seeded_per_role(): void
    {
        $owner = User::factory()->withRole('owner')->create();
        $admin = User::factory()->withRole('admin')->create();
        $agente = User::factory()->withRole('agente')->create();

        $this->assertTrue($owner->can('lonas.manage'));
        $this->assertTrue($owner->can('lonas.place'));

        $this->assertTrue($admin->can('lonas.manage'));
        $this->assertFalse($admin->can('lonas.place'));

        $this->assertTrue($agente->can('lonas.place'));
        $this->assertFalse($agente->can('lonas.manage'));
    }

    public function test_partial_unique_index_blocks_second_pending_request_of_same_type(): void
    {
        $agent = User::factory()->activeAgent()->create();

        LonaRequest::factory()->for($agent, 'agent')->ofType(OperationType::Venta)->create();

        $this->expectException(QueryException::class);

        LonaRequest::factory()->for($agent, 'agent')->ofType(OperationType::Venta)->create();
    }

    public function test_pending_requests_of_different_types_coexist(): void
    {
        $agent = User::factory()->activeAgent()->create();

        LonaRequest::factory()->for($agent, 'agent')->ofType(OperationType::Venta)->create();
        LonaRequest::factory()->for($agent, 'agent')->ofType(OperationType::Renta)->create();

        $this->assertSame(2, LonaRequest::where('agent_id', $agent->id)->count());
    }

    public function test_a_new_pending_request_is_allowed_after_the_previous_is_resolved(): void
    {
        $agent = User::factory()->activeAgent()->create();

        LonaRequest::factory()->for($agent, 'agent')->ofType(OperationType::Venta)->approved()->create();

        $second = LonaRequest::factory()->for($agent, 'agent')->ofType(OperationType::Venta)->create();

        $this->assertTrue($second->isPending());
    }

    public function test_soft_deleted_pending_request_frees_the_unique_slot(): void
    {
        $agent = User::factory()->activeAgent()->create();

        $first = LonaRequest::factory()->for($agent, 'agent')->ofType(OperationType::Venta)->create();
        $first->delete();

        $second = LonaRequest::factory()->for($agent, 'agent')->ofType(OperationType::Venta)->create();

        $this->assertTrue($second->exists);
    }

    public function test_batch_units_and_relations_resolve(): void
    {
        $agent = User::factory()->activeAgent()->create();
        $batch = LonaBatch::factory()->for($agent, 'agent')->ofType(OperationType::Venta)->create(['cantidad' => 3]);

        LonaUnit::factory()->count(3)->for($batch, 'batch')->forAgent($agent)->create();

        $this->assertCount(3, $batch->units);
        $this->assertInstanceOf(OperationType::class, $batch->operation_type);
        $this->assertSame($agent->id, $batch->agent->id);
        $this->assertSame(LonaUnitStatus::PendienteColocacion, $batch->units->first()->status);
    }

    public function test_lona_batch_policy_gates_on_manage_permission(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $agente = User::factory()->withRole('agente')->create();
        $batch = LonaBatch::factory()->create();

        $this->assertTrue(Gate::forUser($admin)->allows('view', $batch));
        $this->assertFalse(Gate::forUser($agente)->allows('view', $batch));
        $this->assertFalse(Gate::forUser($admin)->allows('forceDelete', $batch));
    }

    public function test_lona_unit_place_policy_restricts_to_owning_agent(): void
    {
        $agentA = User::factory()->withRole('agente')->create();
        $agentB = User::factory()->withRole('agente')->create();
        $unit = LonaUnit::factory()->forAgent($agentA)->create();

        $this->assertTrue(Gate::forUser($agentA)->allows('place', $unit));
        $this->assertFalse(Gate::forUser($agentB)->allows('place', $unit));
    }

    public function test_request_status_defaults_to_pending(): void
    {
        $request = LonaRequest::factory()->create();

        $this->assertSame(LonaRequestStatus::Pendiente, $request->estado);
    }
}
