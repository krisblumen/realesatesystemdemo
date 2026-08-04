<?php

namespace Tests\Feature\Filament;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Filament\Resources\LeadResource;
use App\Filament\Resources\LeadResource\Pages\CreateLead;
use App\Filament\Resources\LeadResource\Pages\EditLead;
use App\Filament\Resources\LeadResource\Pages\ListLeads;
use App\Models\Lead;
use App\Models\LeadAssignmentLog;
use App\Models\Property;
use App\Models\User;
use App\Notifications\LeadAssignedNotification;
use Database\Seeders\PermissionSeeder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class LeadResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_roles_with_permission_can_access_leads_index(): void
    {
        $this->actingAs($this->userWithRole('owner'))->get(LeadResource::getUrl('index'))->assertOk();
        $this->actingAs($this->userWithRole('admin'))->get(LeadResource::getUrl('index'))->assertOk();
        $this->actingAs($this->userWithRole('agente'))->get(LeadResource::getUrl('index'))->assertOk();
    }

    public function test_agent_only_sees_own_leads_while_owner_sees_all(): void
    {
        $agentA = $this->userWithRole('agente');
        $agentB = $this->userWithRole('agente');
        $mine = Lead::factory()->create(['agent_id' => $agentA->id]);
        $other = Lead::factory()->create(['agent_id' => $agentB->id]);
        $unassigned = Lead::factory()->create(['agent_id' => null]);

        $this->actingAs($agentA);

        Livewire::test(ListLeads::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$other, $unassigned]);

        $this->actingAs($this->userWithRole('owner'));

        Livewire::test(ListLeads::class)
            ->assertCanSeeTableRecords([$mine, $other, $unassigned]);
    }

    public function test_agent_cannot_access_another_agents_lead_edit_page(): void
    {
        $agent = $this->userWithRole('agente');
        $other = $this->userWithRole('agente');
        $lead = Lead::factory()->create(['agent_id' => $other->id]);

        $this->actingAs($agent)
            ->get(LeadResource::getUrl('edit', ['record' => $lead]))
            ->assertNotFound();
    }

    public function test_owner_can_filter_search_and_change_status_of_leads(): void
    {
        $owner = $this->userWithRole('owner');
        $agent = $this->userWithRole('agente');
        $property = Property::factory()->create();
        $lead = Lead::factory()->create([
            'name' => 'Laura Prospecto',
            'email' => 'laura@example.test',
            'phone' => '4429990000',
            'source' => LeadSource::Web,
            'status' => LeadStatus::Nuevo,
            'property_id' => $property->id,
            'agent_id' => $agent->id,
        ]);
        $other = Lead::factory()->create(['status' => LeadStatus::CerradoPerdido]);

        $this->actingAs($owner);

        Livewire::test(ListLeads::class)
            ->searchTable('laura@example.test')
            ->assertCanSeeTableRecords([$lead])
            ->assertCanNotSeeTableRecords([$other])
            ->filterTable('status', LeadStatus::Nuevo->value)
            ->filterTable('source', LeadSource::Web->value)
            ->filterTable('service_type', 'comercializacion')
            ->filterTable('agent_id', $agent->id)
            ->assertCanSeeTableRecords([$lead]);

        Livewire::test(ListLeads::class)
            ->callTableAction('changeStatus', $lead, ['status' => LeadStatus::Contactado->value]);

        $this->assertSame(LeadStatus::Contactado, $lead->refresh()->status);
    }

    public function test_owner_can_manually_reassign_a_lead_and_audit_the_change(): void
    {
        $owner = $this->userWithRole('owner');
        $fromAgent = $this->userWithRole('agente');
        $toAgent = $this->userWithRole('agente');
        $lead = Lead::factory()->create([
            'agent_id' => $fromAgent->id,
            'assigned_at' => now()->subDay(),
        ]);

        $this->actingAs($owner);

        Livewire::test(ListLeads::class)
            ->callTableAction('reassign', $lead, [
                'agent_id' => $toAgent->id,
                'reason' => 'Balance manual de carga.',
            ]);

        $lead->refresh();
        $this->assertSame($toAgent->id, $lead->agent_id);
        $this->assertNotNull($lead->assigned_at);
        $this->assertDatabaseHas('lead_assignment_logs', [
            'lead_id' => $lead->id,
            'from_agent_id' => $fromAgent->id,
            'to_agent_id' => $toAgent->id,
            'assigned_by_id' => $owner->id,
            'reason' => 'Balance manual de carga.',
        ]);
        $this->assertSame(1, LeadAssignmentLog::count());
    }

    public function test_changing_agent_from_the_edit_form_also_audits_and_notifies(): void
    {
        Notification::fake();
        $owner = $this->userWithRole('owner');
        $fromAgent = $this->userWithRole('agente');
        $toAgent = $this->userWithRole('agente');
        $lead = Lead::factory()->create(['agent_id' => $fromAgent->id]);

        $this->actingAs($owner);

        Livewire::test(EditLead::class, ['record' => $lead->getRouteKey()])
            ->fillForm(['agent_id' => $toAgent->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $lead->refresh();
        $this->assertSame($toAgent->id, $lead->agent_id);
        $this->assertNotNull($lead->assigned_at);
        $this->assertDatabaseHas('lead_assignment_logs', [
            'lead_id' => $lead->id,
            'from_agent_id' => $fromAgent->id,
            'to_agent_id' => $toAgent->id,
            'assigned_by_id' => $owner->id,
        ]);

        Notification::assertSentTo($toAgent, LeadAssignedNotification::class);
    }

    public function test_saving_the_edit_form_without_changing_agent_does_not_duplicate_audit_log(): void
    {
        Notification::fake();
        $owner = $this->userWithRole('owner');
        $agent = $this->userWithRole('agente');
        $lead = Lead::factory()->create(['agent_id' => $agent->id]);

        $this->actingAs($owner);

        Livewire::test(EditLead::class, ['record' => $lead->getRouteKey()])
            ->fillForm(['phone' => '+52 442 000 1111'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(0, LeadAssignmentLog::count());
        Notification::assertNothingSent();
    }

    public function test_agent_creating_manual_lead_is_assigned_to_self_and_can_see_it(): void
    {
        $agent = $this->userWithRole('agente');
        $this->actingAs($agent);

        Livewire::test(CreateLead::class)
            ->fillForm([
                'name' => 'Lead Manual Agente',
                'email' => 'manual-agent@example.test',
                'phone' => '4425551111',
                'message' => 'Capturado desde panel.',
                'source' => LeadSource::Manual->value,
                'service_type' => 'comercializacion',
                'status' => LeadStatus::Nuevo->value,
                'property_id' => null,
                'agent_id' => null,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $lead = Lead::where('email', 'manual-agent@example.test')->firstOrFail();

        $this->assertSame($agent->id, $lead->agent_id);
        $this->assertNotNull($lead->assigned_at);

        Livewire::test(ListLeads::class)
            ->assertCanSeeTableRecords([$lead]);
    }

    public function test_agent_cannot_see_manual_reassign_action(): void
    {
        $agent = $this->userWithRole('agente');
        $lead = Lead::factory()->create(['agent_id' => $agent->id]);

        $this->actingAs($agent);

        Livewire::test(ListLeads::class)
            ->assertTableActionHidden('reassign', $lead);
    }

    public function test_owner_can_bulk_reassign_selected_leads(): void
    {
        $owner = $this->userWithRole('owner');
        $from = User::factory()->active()->withRole('agente')->create();
        $to = User::factory()->active()->withRole('agente')->create();
        $leads = Lead::factory()->count(3)->create([
            'agent_id' => $from->id,
            'status' => LeadStatus::Nuevo,
        ]);

        $this->actingAs($owner);

        Livewire::test(ListLeads::class)
            ->callTableBulkAction('reassignSelected', $leads, [
                'agent_id' => $to->id,
                'reason' => 'Rebalanceo de carga.',
            ]);

        foreach ($leads as $lead) {
            $this->assertSame($to->id, $lead->refresh()->agent_id);
        }

        $this->assertSame(3, LeadAssignmentLog::where('to_agent_id', $to->id)->count());
    }

    public function test_owner_can_reassign_all_open_leads_of_an_agent(): void
    {
        $owner = $this->userWithRole('owner');
        $from = User::factory()->active()->withRole('agente')->create();
        $to = User::factory()->active()->withRole('agente')->create();
        $open = Lead::factory()->create(['agent_id' => $from->id, 'status' => LeadStatus::Nuevo]);
        $closed = Lead::factory()->create(['agent_id' => $from->id, 'status' => LeadStatus::CerradoGanado]);

        $this->actingAs($owner);

        Livewire::test(ListLeads::class)
            ->callTableAction('reassignAgentLeads', data: [
                'from_agent_id' => $from->id,
                'to_agent_id' => $to->id,
                'reason' => 'Renuncia del agente.',
            ]);

        $this->assertSame($to->id, $open->refresh()->agent_id);
        $this->assertSame($from->id, $closed->refresh()->agent_id);
    }

    public function test_agent_cannot_bulk_reassign_leads(): void
    {
        $agent = $this->userWithRole('agente');
        Lead::factory()->create(['agent_id' => $agent->id]);

        $this->actingAs($agent);

        Livewire::test(ListLeads::class)
            ->assertTableBulkActionHidden('reassignSelected');
    }

    public function test_agent_only_sees_own_properties_in_the_lead_form(): void
    {
        $agent = $this->userWithRole('agente');
        $mine = Property::factory()->forAgent($agent)->create();
        $other = Property::factory()->forAgent($this->userWithRole('agente'))->create();
        $this->actingAs($agent);

        Livewire::test(CreateLead::class)
            ->assertFormFieldExists('property_id', fn (Select $field): bool => array_key_exists($mine->id, $field->getOptions())
                && ! array_key_exists($other->id, $field->getOptions()));
    }

    public function test_lead_form_service_type_options_come_from_active_catalog(): void
    {
        $owner = $this->userWithRole('owner');
        $this->actingAs($owner);

        Livewire::test(CreateLead::class)
            ->assertFormFieldExists('service_type', fn (Radio $field): bool => $field->getOptions() === [
                // The internal lead form lists the ACTIVE catalog; inversion is
                // now a real active ServiceType (RFC-074), so an admin can log a
                // lead for it even though the public form does not offer it.
                'comercializacion' => 'Comercialización',
                'arquitectura' => 'Arquitectura',
                'construccion' => 'Construcción',
                'inversion' => 'Inversión inmobiliaria',
            ]);
    }

    public function test_owner_sees_all_properties_in_the_lead_form(): void
    {
        $owner = $this->userWithRole('owner');
        $a = Property::factory()->forAgent($this->userWithRole('agente'))->create();
        $b = Property::factory()->forAgent($this->userWithRole('agente'))->create();
        $this->actingAs($owner);

        Livewire::test(CreateLead::class)
            ->assertFormFieldExists('property_id', fn (Select $field): bool => array_key_exists($a->id, $field->getOptions())
                && array_key_exists($b->id, $field->getOptions()));
    }

    public function test_leads_cannot_be_deleted_by_anyone(): void
    {
        $owner = $this->userWithRole('owner');
        $lead = Lead::factory()->create();
        $this->actingAs($owner);

        $this->assertFalse(LeadResource::canDelete($lead));

        Livewire::test(ListLeads::class)
            ->assertTableActionDoesNotExist('delete');
    }

    public function test_agent_cannot_edit_contact_data_but_owner_can(): void
    {
        $agent = $this->userWithRole('agente');
        $lead = Lead::factory()->create(['agent_id' => $agent->id]);

        $this->actingAs($agent);
        Livewire::test(EditLead::class, ['record' => $lead->getRouteKey()])
            ->assertFormFieldIsDisabled('name')
            ->assertFormFieldIsDisabled('email')
            ->assertFormFieldIsDisabled('phone');

        $this->actingAs($this->userWithRole('owner'));
        Livewire::test(EditLead::class, ['record' => $lead->getRouteKey()])
            ->assertFormFieldIsEnabled('name');
    }

    public function test_navigation_badge_counts_open_unassigned_leads_for_owner_and_admin(): void
    {
        Lead::factory()->count(2)->create(['agent_id' => null, 'status' => LeadStatus::Nuevo]);
        Lead::factory()->create(['agent_id' => $this->userWithRole('agente')->id, 'status' => LeadStatus::Nuevo]);
        Lead::factory()->create(['agent_id' => null, 'status' => LeadStatus::CerradoGanado]);

        $this->actingAs($this->userWithRole('owner'));
        $this->assertSame('2', LeadResource::getNavigationBadge());

        $this->actingAs($this->userWithRole('admin'));
        $this->assertSame('2', LeadResource::getNavigationBadge());
    }

    public function test_navigation_badge_counts_only_the_agents_own_open_leads(): void
    {
        $agent = $this->userWithRole('agente');
        $other = $this->userWithRole('agente');
        Lead::factory()->count(2)->create(['agent_id' => $agent->id, 'status' => LeadStatus::Nuevo]);
        Lead::factory()->create(['agent_id' => $agent->id, 'status' => LeadStatus::CerradoGanado]);
        Lead::factory()->create(['agent_id' => $other->id, 'status' => LeadStatus::Nuevo]);
        Lead::factory()->create(['agent_id' => null, 'status' => LeadStatus::Nuevo]);

        $this->actingAs($agent);

        $this->assertSame('2', LeadResource::getNavigationBadge());
    }

    public function test_navigation_badge_is_null_when_there_are_no_matching_leads(): void
    {
        $agent = $this->userWithRole('agente');
        Lead::factory()->create(['agent_id' => null, 'status' => LeadStatus::Nuevo]);
        Lead::factory()->create(['agent_id' => $agent->id, 'status' => LeadStatus::CerradoGanado]);
        $this->actingAs($agent);
        $this->assertNull(LeadResource::getNavigationBadge());

        $owner = $this->userWithRole('owner');
        Lead::query()->update(['agent_id' => $owner->id]);
        $this->actingAs($owner);
        $this->assertNull(LeadResource::getNavigationBadge());
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->withRole($role)->create();
    }
}
