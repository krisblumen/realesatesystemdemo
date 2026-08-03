<?php

namespace Tests\Feature\Filament;

use App\Enums\LeadStatus;
use App\Filament\Resources\LeadResource\Pages\EditLead;
use App\Filament\Resources\LeadResource\RelationManagers\LeadNotesRelationManager;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LeadNotesRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_agent_can_add_note_and_author_is_set_automatically(): void
    {
        $agent = $this->userWithRole('agente');
        $lead = Lead::factory()->create(['agent_id' => $agent->id]);
        $this->actingAs($agent);

        Livewire::test(LeadNotesRelationManager::class, [
            'ownerRecord' => $lead,
            'pageClass' => EditLead::class,
        ])
            ->callTableAction('create', data: ['body' => 'El cliente acordó una visita.'])
            ->assertHasNoTableActionErrors();

        $note = LeadNote::firstOrFail();
        $this->assertSame($lead->id, $note->lead_id);
        $this->assertSame($agent->id, $note->user_id);
        $this->assertSame('El cliente acordó una visita.', $note->body);
    }

    public function test_notes_are_not_editable(): void
    {
        $owner = $this->userWithRole('owner');
        $lead = Lead::factory()->create();
        LeadNote::factory()->create(['lead_id' => $lead->id]);
        $this->actingAs($owner);

        Livewire::test(LeadNotesRelationManager::class, [
            'ownerRecord' => $lead,
            'pageClass' => EditLead::class,
        ])->assertTableActionDoesNotExist('edit');
    }

    public function test_agent_cannot_delete_notes_but_owner_can(): void
    {
        $agent = $this->userWithRole('agente');
        $lead = Lead::factory()->create(['agent_id' => $agent->id]);
        $note = LeadNote::factory()->create(['lead_id' => $lead->id, 'user_id' => $agent->id]);

        $this->actingAs($agent);
        Livewire::test(LeadNotesRelationManager::class, [
            'ownerRecord' => $lead,
            'pageClass' => EditLead::class,
        ])->assertTableActionHidden('delete', $note);

        $this->actingAs($this->userWithRole('owner'));
        Livewire::test(LeadNotesRelationManager::class, [
            'ownerRecord' => $lead,
            'pageClass' => EditLead::class,
        ])->assertTableActionVisible('delete', $note);
    }

    public function test_comments_can_be_added_to_open_leads_but_not_to_closed_ones(): void
    {
        $this->actingAs($this->userWithRole('owner'));

        $open = Lead::factory()->create(['status' => LeadStatus::EnSeguimiento]);
        Livewire::test(LeadNotesRelationManager::class, [
            'ownerRecord' => $open,
            'pageClass' => EditLead::class,
        ])->assertTableActionVisible('create');

        foreach ([LeadStatus::CerradoGanado, LeadStatus::CerradoPerdido] as $closedStatus) {
            $closed = Lead::factory()->create(['status' => $closedStatus]);
            Livewire::test(LeadNotesRelationManager::class, [
                'ownerRecord' => $closed,
                'pageClass' => EditLead::class,
            ])->assertTableActionHidden('create');
        }
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->withRole($role)->create();
    }
}
