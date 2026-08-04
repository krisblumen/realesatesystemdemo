<?php

namespace Tests\Feature\Leads;

use App\Enums\LeadStatus;
use App\Events\LeadAssigned;
use App\Events\LeadCaptured;
use App\Models\Lead;
use App\Models\Property;
use App\Models\User;
use App\Models\Zone;
use App\Services\LeadAssignmentService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class LeadAssignmentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_property_agent_has_assignment_priority(): void
    {
        Event::fake([LeadAssigned::class]);
        $agent = $this->activeAgent();
        $fallback = $this->activeAgent();
        $property = Property::factory()->forAgent($agent)->create();
        $lead = Lead::factory()->create(['property_id' => $property->id]);

        app(LeadAssignmentService::class)->assign($lead);

        $this->assertSame($agent->id, $lead->refresh()->agent_id);
        $this->assertNotNull($lead->assigned_at);
        Event::assertDispatched(LeadAssigned::class, fn (LeadAssigned $event): bool => $event->lead->is($lead) && $event->agent->is($agent));
        $this->assertNotSame($fallback->id, $lead->agent_id);
    }

    public function test_zone_agent_is_used_when_property_has_no_agent(): void
    {
        $agent = $this->activeAgent();
        $zone = Zone::factory()->create();
        $zone->agents()->attach($agent);
        $lead = Lead::factory()->create(['zone_id' => $zone->id]);

        app(LeadAssignmentService::class)->assign($lead);

        $this->assertSame($agent->id, $lead->refresh()->agent_id);
    }

    public function test_zone_agent_with_fewer_recent_leads_is_chosen_when_zone_has_multiple_agents(): void
    {
        $busy = $this->activeAgent();
        $available = $this->activeAgent();
        $zone = Zone::factory()->create();
        $zone->agents()->attach([$busy->id, $available->id]);
        Lead::factory()->count(2)->create([
            'agent_id' => $busy->id,
            'assigned_at' => now()->subHour(),
        ]);
        Lead::factory()->create([
            'agent_id' => $available->id,
            'assigned_at' => now()->subHour(),
        ]);
        $lead = Lead::factory()->create(['zone_id' => $zone->id, 'agent_id' => null]);

        app(LeadAssignmentService::class)->assign($lead);

        $this->assertSame($available->id, $lead->refresh()->agent_id);
    }

    public function test_lead_without_property_or_zone_context_stays_unassigned_even_with_active_agents(): void
    {
        $this->activeAgent();
        $lead = Lead::factory()->create(['property_id' => null, 'zone_id' => null, 'agent_id' => null]);

        app(LeadAssignmentService::class)->assign($lead);

        $this->assertNull($lead->refresh()->agent_id);
        $this->assertNull($lead->assigned_at);
    }

    public function test_assignment_is_idempotent_when_lead_already_has_agent(): void
    {
        $assigned = $this->activeAgent();
        $other = $this->activeAgent();
        $assignedAt = now()->subDay()->startOfSecond();
        $lead = Lead::factory()->create([
            'agent_id' => $assigned->id,
            'assigned_at' => $assignedAt,
        ]);

        app(LeadAssignmentService::class)->assign($lead);

        $lead->refresh();
        $this->assertSame($assigned->id, $lead->agent_id);
        $this->assertNotSame($other->id, $lead->agent_id);
        $this->assertSame($assignedAt->toDateTimeString(), $lead->assigned_at->toDateTimeString());
    }

    public function test_without_active_agents_lead_remains_unassigned_without_error(): void
    {
        $lead = Lead::factory()->create(['status' => LeadStatus::Nuevo, 'agent_id' => null]);

        app(LeadAssignmentService::class)->assign($lead);

        $this->assertNull($lead->refresh()->agent_id);
        $this->assertNull($lead->assigned_at);
    }

    public function test_reconcile_command_assigns_pending_leads_once_their_zone_gains_an_agent(): void
    {
        $zone = Zone::factory()->create();
        $lead = Lead::factory()->create([
            'agent_id' => null,
            'zone_id' => $zone->id,
            'status' => LeadStatus::Nuevo,
            'created_at' => now()->subMinutes(15),
        ]);
        $agent = $this->activeAgent();
        $zone->agents()->attach($agent);

        $this->artisan('leads:reconcile', ['--minutes' => 10])
            ->expectsOutput('1 pending leads assigned.')
            ->assertSuccessful();

        $this->assertSame($agent->id, $lead->refresh()->agent_id);
    }

    public function test_lead_captured_listener_assigns_when_enabled(): void
    {
        config()->set('leads.auto_assignment.enabled', true);
        $agent = $this->activeAgent();
        $property = Property::factory()->forAgent($agent)->create();
        $lead = Lead::factory()->create(['agent_id' => null, 'property_id' => $property->id]);

        LeadCaptured::dispatch($lead);

        $this->assertSame($agent->id, $lead->refresh()->agent_id);
    }

    public function test_lead_captured_listener_does_not_assign_when_disabled(): void
    {
        config()->set('leads.auto_assignment.enabled', false);
        $this->activeAgent();
        $lead = Lead::factory()->create(['agent_id' => null]);

        LeadCaptured::dispatch($lead);

        $this->assertNull($lead->refresh()->agent_id);
    }

    public function test_reassign_open_leads_moves_only_open_leads_of_agent(): void
    {
        $from = $this->activeAgent();
        $to = $this->activeAgent();
        $owner = User::factory()->withRole('owner')->create();

        $open1 = Lead::factory()->create(['agent_id' => $from->id, 'status' => LeadStatus::Nuevo]);
        $open2 = Lead::factory()->create(['agent_id' => $from->id, 'status' => LeadStatus::EnSeguimiento]);
        $closed = Lead::factory()->create(['agent_id' => $from->id, 'status' => LeadStatus::CerradoGanado]);

        $count = app(LeadAssignmentService::class)->reassignOpenLeads($from, $to, $owner, 'Renuncia del agente.');

        $this->assertSame(2, $count);
        $this->assertSame($to->id, $open1->refresh()->agent_id);
        $this->assertSame($to->id, $open2->refresh()->agent_id);
        $this->assertSame($from->id, $closed->refresh()->agent_id);
        $this->assertDatabaseHas('lead_assignment_logs', [
            'lead_id' => $open1->id,
            'from_agent_id' => $from->id,
            'to_agent_id' => $to->id,
            'assigned_by_id' => $owner->id,
            'source' => 'manual',
            'reason' => 'Renuncia del agente.',
        ]);
    }

    private function activeAgent(): User
    {
        return User::factory()->active()->withRole('agente')->create();
    }
}
