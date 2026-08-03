<?php

namespace Tests\Feature\Filament;

use App\Enums\LonaRequestStatus;
use App\Enums\OperationType;
use App\Filament\Pages\AgentLonas;
use App\Filament\Resources\LonaBatchResource;
use App\Filament\Resources\LonaBatchResource\Pages\CreateLonaBatch;
use App\Filament\Resources\LonaRequestResource;
use App\Filament\Resources\LonaRequestResource\Pages\ListLonaRequests;
use App\Filament\Widgets\AgentLonaUnitsWidget;
use App\Models\LonaBatch;
use App\Models\LonaRequest;
use App\Models\Property;
use App\Models\User;
use App\Notifications\LonaRequestApprovedNotification;
use App\Notifications\LonaRequestRejectedNotification;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class LonaResourcesTest extends TestCase
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

    public function test_owner_and_admin_can_access_lona_resources_but_agent_cannot(): void
    {
        foreach (['owner', 'admin'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get(LonaBatchResource::getUrl('index'))->assertOk();
            $this->actingAs($this->userWithRole($role))
                ->get(LonaRequestResource::getUrl('index'))->assertOk();
        }

        $agent = $this->userWithRole('agente');
        $this->actingAs($agent)->get(LonaBatchResource::getUrl('index'))->assertForbidden();
        $this->actingAs($agent)->get(LonaRequestResource::getUrl('index'))->assertForbidden();
    }

    public function test_admin_can_assign_a_batch_which_creates_units_and_pdf(): void
    {
        Notification::fake();
        $admin = $this->userWithRole('admin');
        $agent = $this->userWithRole('agente');

        $this->actingAs($admin);

        Livewire::test(CreateLonaBatch::class)
            ->fillForm([
                'agent_id' => $agent->id,
                'operation_type' => OperationType::Venta->value,
                'cantidad' => 3,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $batch = LonaBatch::where('agent_id', $agent->id)->firstOrFail();
        $this->assertSame(3, $batch->cantidad);
        $this->assertCount(3, $batch->units);
        $this->assertNotNull($batch->getFirstMedia('diseno-pdf'));
    }

    public function test_admin_can_approve_a_request_from_the_inbox(): void
    {
        Notification::fake();
        $admin = $this->userWithRole('admin');
        $agent = $this->userWithRole('agente');
        $request = LonaRequest::factory()->for($agent, 'agent')->ofType(OperationType::Venta)->create();

        $this->actingAs($admin);

        Livewire::test(ListLonaRequests::class)
            ->callTableAction('approve', $request, ['cantidad' => 4]);

        $this->assertSame(LonaRequestStatus::Aprobada, $request->refresh()->estado);
        $this->assertSame(4, LonaBatch::where('lona_request_id', $request->id)->firstOrFail()->cantidad);
        Notification::assertSentTo($agent, LonaRequestApprovedNotification::class);
    }

    public function test_admin_can_reject_a_request_with_a_reason(): void
    {
        Notification::fake();
        $admin = $this->userWithRole('admin');
        $agent = $this->userWithRole('agente');
        $request = LonaRequest::factory()->for($agent, 'agent')->ofType(OperationType::Venta)->create();

        $this->actingAs($admin);

        Livewire::test(ListLonaRequests::class)
            ->callTableAction('reject', $request, ['motivo_rechazo' => 'Sin cupo este mes.']);

        $request->refresh();
        $this->assertSame(LonaRequestStatus::Rechazada, $request->estado);
        $this->assertSame('Sin cupo este mes.', $request->motivo_rechazo);
        Notification::assertSentTo($agent, LonaRequestRejectedNotification::class);
    }

    public function test_agent_can_access_their_lonas_page_but_non_agent_cannot(): void
    {
        $this->actingAs($this->userWithRole('agente'))
            ->get(AgentLonas::getUrl())->assertOk();

        $this->actingAs($this->userWithRole('admin'))
            ->get(AgentLonas::getUrl())->assertForbidden();
    }

    public function test_agent_can_submit_a_request_from_their_page(): void
    {
        Notification::fake();
        $agent = $this->userWithRole('agente');

        $this->actingAs($agent);

        Livewire::test(AgentLonaUnitsWidget::class)
            ->callTableAction('requestMoreVenta', data: ['cantidad' => 5]);

        $request = LonaRequest::where('agent_id', $agent->id)->firstOrFail();
        $this->assertSame(LonaRequestStatus::Pendiente, $request->estado);
        $this->assertSame(OperationType::Venta, $request->operation_type);
    }

    public function test_agent_cannot_smuggle_another_agents_property_via_the_widget(): void
    {
        Notification::fake();
        $agent = $this->userWithRole('agente');
        $otherAgent = $this->userWithRole('agente');
        $foreign = Property::factory()->published()->create(['agent_id' => $otherAgent->id]);

        $this->actingAs($agent);

        // Payload manipulado con el inmueble de otro agente: el servicio de dominio
        // lo rechaza y no se crea ninguna solicitud (auditoría de implementación M-IMP-1).
        Livewire::test(AgentLonaUnitsWidget::class)
            ->callTableAction('requestMoreVenta', data: [
                'cantidad' => 3,
                'property_id' => $foreign->id,
            ]);

        $this->assertSame(0, LonaRequest::where('agent_id', $agent->id)->count());
    }
}
