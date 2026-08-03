<?php

namespace Tests\Feature\Lonas;

use App\Enums\OperationType;
use App\Models\LonaBatch;
use App\Models\LonaRequest;
use App\Models\LonaUnit;
use App\Models\Property;
use App\Models\User;
use App\Notifications\LonaRequestSubmittedNotification;
use App\Services\Lonas\LonaEligibilityService;
use App\Services\Lonas\LonaRequestService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LonaEligibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    private function eligibility(): LonaEligibilityService
    {
        return app(LonaEligibilityService::class);
    }

    private function requests(): LonaRequestService
    {
        return app(LonaRequestService::class);
    }

    /** Crea $count unidades sin colocar del tipo dado para el agente. */
    private function makeUncolocated(User $agent, OperationType $type, int $count): void
    {
        $batch = LonaBatch::factory()->for($agent, 'agent')->ofType($type)->create();
        LonaUnit::factory()->count($count)->for($batch, 'batch')->forAgent($agent)->ofType($type)->create();
    }

    /** Crea $count unidades ya colocadas (justificadas) del tipo dado para el agente. */
    private function makePlaced(User $agent, OperationType $type, int $count): void
    {
        $batch = LonaBatch::factory()->for($agent, 'agent')->ofType($type)->create();
        LonaUnit::factory()->count($count)->for($batch, 'batch')->forAgent($agent)->ofType($type)->placed()->create();
    }

    public function test_a_fresh_agent_can_request_up_to_the_cap(): void
    {
        $agent = User::factory()->activeAgent()->create();

        $this->assertSame(5, $this->eligibility()->availableToRequest($agent, OperationType::Venta));
        $this->assertTrue($this->eligibility()->canRequestMore($agent, OperationType::Venta));
    }

    public function test_agent_at_the_cap_cannot_request_more(): void
    {
        $agent = User::factory()->activeAgent()->create();
        $this->makeUncolocated($agent, OperationType::Venta, 5);

        $this->assertSame(0, $this->eligibility()->availableToRequest($agent, OperationType::Venta));
        $this->assertFalse($this->eligibility()->canRequestMore($agent, OperationType::Venta));
    }

    public function test_placing_lonas_with_evidence_replenishes_available_cupo(): void
    {
        // Escenario del negocio: si ya justifico 4 con evidencia, puede pedir 4.
        $agent = User::factory()->activeAgent()->create();
        $this->makeUncolocated($agent, OperationType::Venta, 1); // 1 sin colocar
        $this->makePlaced($agent, OperationType::Venta, 4);      // 4 ya colocadas

        // Sólo 1 cuenta contra el tope → puede pedir 4.
        $this->assertSame(4, $this->eligibility()->availableToRequest($agent, OperationType::Venta));
    }

    public function test_placed_lonas_do_not_count_against_the_cap(): void
    {
        $agent = User::factory()->activeAgent()->create();
        $this->makePlaced($agent, OperationType::Venta, 5);

        $this->assertSame(5, $this->eligibility()->availableToRequest($agent, OperationType::Venta));
    }

    public function test_caps_for_venta_and_renta_are_independent(): void
    {
        $agent = User::factory()->activeAgent()->create();
        $this->makeUncolocated($agent, OperationType::Venta, 5);

        $this->assertFalse($this->eligibility()->canRequestMore($agent, OperationType::Venta));
        $this->assertSame(5, $this->eligibility()->availableToRequest($agent, OperationType::Renta));
    }

    public function test_a_pending_request_blocks_further_requests_of_that_type(): void
    {
        $agent = User::factory()->activeAgent()->create();
        LonaRequest::factory()->for($agent, 'agent')->ofType(OperationType::Venta)->create();

        $this->assertSame(0, $this->eligibility()->availableToRequest($agent, OperationType::Venta));
    }

    public function test_submit_creates_pending_request_and_notifies_owner_and_admin(): void
    {
        Notification::fake();
        $owner = User::factory()->withRole('owner')->create();
        $admin = User::factory()->withRole('admin')->create();
        $agent = User::factory()->activeAgent()->create();

        $request = $this->requests()->submit($agent, OperationType::Venta, 5);

        $this->assertTrue($request->isPending());
        $this->assertSame(5, $request->cantidad_solicitada);
        Notification::assertSentTo([$owner, $admin], LonaRequestSubmittedNotification::class);
    }

    public function test_submit_rejects_a_quantity_over_the_available_cupo(): void
    {
        Notification::fake();
        $agent = User::factory()->activeAgent()->create();
        $this->makeUncolocated($agent, OperationType::Venta, 3); // cupo disponible = 2

        $this->expectException(ValidationException::class);
        $this->requests()->submit($agent, OperationType::Venta, 3);
    }

    public function test_submit_accepts_a_quantity_within_the_available_cupo(): void
    {
        Notification::fake();
        $agent = User::factory()->activeAgent()->create();
        $this->makeUncolocated($agent, OperationType::Venta, 3); // cupo disponible = 2

        $request = $this->requests()->submit($agent, OperationType::Venta, 2);

        $this->assertSame(2, $request->cantidad_solicitada);
    }

    public function test_agent_at_the_cap_cannot_submit_anything(): void
    {
        Notification::fake();
        $agent = User::factory()->activeAgent()->create();
        $this->makeUncolocated($agent, OperationType::Venta, 5);

        $this->expectException(ValidationException::class);
        $this->requests()->submit($agent, OperationType::Venta, 1);
    }

    public function test_agent_cannot_submit_second_pending_request_of_same_type(): void
    {
        Notification::fake();
        $agent = User::factory()->activeAgent()->create();

        $this->requests()->submit($agent, OperationType::Venta, 3);

        $this->expectException(ValidationException::class);
        $this->requests()->submit($agent, OperationType::Venta, 1);
    }

    public function test_a_new_request_is_allowed_after_the_previous_is_resolved(): void
    {
        Notification::fake();
        $agent = User::factory()->activeAgent()->create();

        LonaRequest::factory()->for($agent, 'agent')->ofType(OperationType::Venta)->approved()->create();

        $second = $this->requests()->submit($agent, OperationType::Venta, 4);

        $this->assertTrue($second->isPending());
    }

    public function test_submit_rejects_a_property_belonging_to_another_agent(): void
    {
        Notification::fake();
        $agent = User::factory()->activeAgent()->create();
        $otherAgent = User::factory()->activeAgent()->create();
        $foreign = Property::factory()->published()->create(['agent_id' => $otherAgent->id]);

        $this->expectException(ValidationException::class);
        $this->requests()->submit($agent, OperationType::Venta, 3, $foreign);
    }

    public function test_submit_rejects_an_unpublished_property(): void
    {
        Notification::fake();
        $agent = User::factory()->activeAgent()->create();
        $draft = Property::factory()->create(['agent_id' => $agent->id]); // borrador por defecto

        $this->expectException(ValidationException::class);
        $this->requests()->submit($agent, OperationType::Venta, 3, $draft);
    }

    public function test_submit_accepts_own_published_property(): void
    {
        Notification::fake();
        $agent = User::factory()->activeAgent()->create();
        $own = Property::factory()->published()->create(['agent_id' => $agent->id]);

        $request = $this->requests()->submit($agent, OperationType::Venta, 3, $own);

        $this->assertSame($own->id, $request->property_id);
    }
}
