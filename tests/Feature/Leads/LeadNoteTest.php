<?php

namespace Tests\Feature\Leads;

use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadNoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_note_belongs_to_lead_and_author(): void
    {
        $lead = Lead::factory()->create();
        $author = User::factory()->create();

        $note = LeadNote::factory()->create([
            'lead_id' => $lead->id,
            'user_id' => $author->id,
            'body' => 'Llamada: el cliente pide visita el sábado.',
        ]);

        $this->assertTrue($note->lead->is($lead));
        $this->assertTrue($note->author->is($author));
        $this->assertSame('Llamada: el cliente pide visita el sábado.', $note->body);
    }

    public function test_lead_notes_are_ordered_most_recent_first(): void
    {
        $lead = Lead::factory()->create();
        $old = LeadNote::factory()->create(['lead_id' => $lead->id, 'created_at' => now()->subDay()]);
        $new = LeadNote::factory()->create(['lead_id' => $lead->id, 'created_at' => now()]);

        $this->assertSame([$new->id, $old->id], $lead->notes->pluck('id')->all());
    }
}
