<?php

namespace Tests\Feature\Lonas;

use App\Enums\LonaRequestStatus;
use App\Enums\LonaUnitStatus;
use App\Enums\OperationType;
use App\Models\LonaBatch;
use App\Models\LonaRequest;
use App\Models\LonaUnit;
use App\Models\Property;
use App\Models\User;
use App\Notifications\LonaRequestApprovedNotification;
use App\Notifications\LonaRequestRejectedNotification;
use App\Services\Lonas\LonaBatchApprovalService;
use App\Services\Lonas\LonaEligibilityService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LonaRequestApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        Notification::fake();
    }

    private function service(): LonaBatchApprovalService
    {
        return app(LonaBatchApprovalService::class);
    }

    public function test_grant_creates_batch_and_pending_units(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $agent = User::factory()->activeAgent()->create();

        $batch = $this->service()->grant($agent, OperationType::Venta, 4, $admin);

        $this->assertSame(4, $batch->cantidad);
        $this->assertCount(4, $batch->units);
        $this->assertTrue($batch->units->every(fn ($u) => $u->status === LonaUnitStatus::PendienteColocacion));
        $this->assertNotNull($batch->getFirstMedia('diseno-pdf'));
    }

    public function test_approving_a_request_marks_it_approved_and_notifies_agent(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $agent = User::factory()->activeAgent()->create();
        $request = LonaRequest::factory()->for($agent, 'agent')->ofType(OperationType::Venta)->create();

        $batch = $this->service()->grant($agent, OperationType::Venta, 3, $admin, null, $request);

        $this->assertSame(LonaRequestStatus::Aprobada, $request->refresh()->estado);
        $this->assertSame($admin->id, $request->reviewed_by);
        $this->assertSame($request->id, $batch->lona_request_id);
        Notification::assertSentTo($agent, LonaRequestApprovedNotification::class);
    }

    public function test_grant_with_published_property_attaches_pdf(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $agent = User::factory()->activeAgent()->create();
        $property = Property::factory()->published()->create(['agent_id' => $agent->id]);

        $batch = $this->service()->grant($agent, OperationType::Venta, 2, $admin, $property);

        $this->assertNotNull($batch->getFirstMedia('diseno-pdf'));
    }

    public function test_grant_without_property_still_generates_pdf(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $agent = User::factory()->activeAgent()->create();

        $batch = $this->service()->grant($agent, OperationType::Renta, 2, $admin);

        $this->assertNotNull($batch->getFirstMedia('diseno-pdf'));
    }

    public function test_grant_does_not_copy_qr_property_into_unit_property(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $agent = User::factory()->activeAgent()->create();
        $property = Property::factory()->published()->create(['agent_id' => $agent->id]);

        $batch = $this->service()->grant($agent, OperationType::Venta, 3, $admin, $property);

        $this->assertTrue($batch->units->every(fn ($u) => $u->property_id === null));
    }

    public function test_grant_rejects_a_user_without_agente_role(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $notAnAgent = User::factory()->withRole('admin')->create();

        $this->expectException(ValidationException::class);
        $this->service()->grant($notAnAgent, OperationType::Venta, 2, $admin);
    }

    public function test_grant_rejects_a_suspended_agent(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $suspended = User::factory()->suspended()->withRole('agente')->create();

        $this->expectException(ValidationException::class);
        $this->service()->grant($suspended, OperationType::Venta, 2, $admin);
    }

    public function test_grant_rejects_an_unpublished_qr_property(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $agent = User::factory()->activeAgent()->create();
        $draft = Property::factory()->create(['agent_id' => $agent->id]); // borrador por defecto

        $this->expectException(ValidationException::class);
        $this->service()->grant($agent, OperationType::Venta, 2, $admin, $draft);
    }

    public function test_grant_rejects_cantidad_over_the_cap(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $agent = User::factory()->activeAgent()->create();

        // Tope de 5 sin colocar por tipo: 6 de una excede.
        $this->expectException(ValidationException::class);
        $this->service()->grant($agent, OperationType::Venta, 6, $admin);
    }

    public function test_grant_rejects_when_it_would_push_the_agent_over_the_cap(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $agent = User::factory()->activeAgent()->create();

        // El agente ya tiene 3 sin colocar; darle 3 más lo llevaría a 6 (> 5).
        $batch = LonaBatch::factory()->for($agent, 'agent')->ofType(OperationType::Venta)->create();
        LonaUnit::factory()->count(3)->for($batch, 'batch')->forAgent($agent)->ofType(OperationType::Venta)->create();

        $this->expectException(ValidationException::class);
        $this->service()->grant($agent, OperationType::Venta, 3, $admin);
    }

    public function test_grant_up_to_the_remaining_cupo_succeeds(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $agent = User::factory()->activeAgent()->create();

        // Ya tiene 3 sin colocar; puede recibir 2 más para llegar justo al tope de 5.
        $batch = LonaBatch::factory()->for($agent, 'agent')->ofType(OperationType::Venta)->create();
        LonaUnit::factory()->count(3)->for($batch, 'batch')->forAgent($agent)->ofType(OperationType::Venta)->create();

        $newBatch = $this->service()->grant($agent, OperationType::Venta, 2, $admin);

        $this->assertSame(2, $newBatch->cantidad);
        $this->assertSame(5, app(LonaEligibilityService::class)->uncolocatedCount($agent, OperationType::Venta));
    }

    public function test_rejecting_a_request_sets_status_reason_and_notifies_agent(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $agent = User::factory()->activeAgent()->create();
        $request = LonaRequest::factory()->for($agent, 'agent')->ofType(OperationType::Venta)->create();

        $this->service()->reject($request, $admin, 'Sin cupo disponible este mes.');

        $request->refresh();
        $this->assertSame(LonaRequestStatus::Rechazada, $request->estado);
        $this->assertSame('Sin cupo disponible este mes.', $request->motivo_rechazo);
        Notification::assertSentTo($agent, LonaRequestRejectedNotification::class);
    }
}
