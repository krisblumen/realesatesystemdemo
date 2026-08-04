<?php

namespace Tests\Feature\Leads;

use App\Events\LeadCaptured;
use App\Models\Lead;
use App\Models\Property;
use App\Models\User;
use App\Notifications\LeadConfirmationNotification;
use Database\Seeders\PermissionSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LeadConfirmationNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_notification_uses_mail_channel_and_is_queueable(): void
    {
        $lead = Lead::factory()->create(['name' => 'María López']);
        $notification = new LeadConfirmationNotification($lead);

        $this->assertSame(['mail'], $notification->via($lead));
        $this->assertInstanceOf(ShouldQueue::class, $notification);
    }

    public function test_generic_message_when_lead_has_no_property(): void
    {
        $lead = Lead::factory()->create(['name' => 'María López', 'property_id' => null]);

        $mail = (new LeadConfirmationNotification($lead))->toMail($lead);

        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertStringContainsString('Hola María López', $mail->greeting);
        $this->assertStringContainsString('Un asesor de New Hauz se pondrá en contacto', implode(' ', $mail->introLines));
    }

    public function test_generic_message_when_property_lead_has_no_agent_resolved_yet(): void
    {
        $property = Property::factory()->create(['agent_id' => null]);
        $lead = Lead::factory()->create([
            'name' => 'María López',
            'property_id' => $property->id,
            'agent_id' => null,
        ]);

        $mail = (new LeadConfirmationNotification($lead))->toMail($lead);

        $this->assertStringContainsString('Un asesor de New Hauz se pondrá en contacto', implode(' ', $mail->introLines));
    }

    public function test_personalized_message_when_property_lead_has_an_agent(): void
    {
        $agent = User::factory()->activeAgent()->create(['name' => 'Carlos Ruiz', 'phone' => '4421112233']);
        $property = Property::factory()->forAgent($agent)->create();
        $lead = Lead::factory()->create([
            'name' => 'María López',
            'property_id' => $property->id,
            'agent_id' => $agent->id,
        ]);

        $mail = (new LeadConfirmationNotification($lead))->toMail($lead);
        $lines = implode(' ', $mail->introLines);

        $this->assertStringContainsString('El asesor Carlos Ruiz se pondrá en contacto', $lines);
        $this->assertStringContainsString('4421112233', $lines);
    }

    public function test_personalized_message_omits_phone_line_when_agent_has_none(): void
    {
        $agent = User::factory()->activeAgent()->create(['name' => 'Carlos Ruiz', 'phone' => null]);
        $property = Property::factory()->forAgent($agent)->create();
        $lead = Lead::factory()->create([
            'property_id' => $property->id,
            'agent_id' => $agent->id,
        ]);

        $mail = (new LeadConfirmationNotification($lead))->toMail($lead);
        $lines = implode(' ', $mail->introLines);

        $this->assertStringContainsString('El asesor Carlos Ruiz', $lines);
        $this->assertStringNotContainsString('escribirle directo', $lines);
    }

    public function test_lead_captured_event_notifies_the_client_after_agent_assignment_resolves(): void
    {
        Notification::fake();
        $agent = User::factory()->activeAgent()->create();
        $property = Property::factory()->forAgent($agent)->create();
        $lead = Lead::factory()->create(['property_id' => $property->id, 'agent_id' => null]);

        LeadCaptured::dispatch($lead);

        Notification::assertSentTo($lead->fresh(), LeadConfirmationNotification::class);
    }
}
