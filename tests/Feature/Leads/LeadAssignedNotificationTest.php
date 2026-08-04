<?php

namespace Tests\Feature\Leads;

use App\Events\LeadAssigned;
use App\Filament\Resources\LeadResource;
use App\Models\Lead;
use App\Models\Property;
use App\Models\User;
use App\Notifications\LeadAssignedNotification;
use Database\Seeders\PermissionSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LeadAssignedNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_notification_uses_database_and_mail_channels_with_lead_context(): void
    {
        $agent = $this->activeAgent();
        $property = Property::factory()->create(['title' => 'Casa Roble']);
        $lead = Lead::factory()->create([
            'name' => 'Laura Prospecto',
            'email' => 'laura@example.test',
            'phone' => '4421234567',
            'property_id' => $property->id,
            'agent_id' => $agent->id,
        ]);
        $notification = new LeadAssignedNotification($lead);

        $this->assertSame(['database', 'mail'], $notification->via($agent));

        $database = $notification->toDatabase($agent);
        $this->assertSame('Nuevo lead asignado', $database['title']);
        $this->assertSame('filament', $database['format']);
        $this->assertStringContainsString('Laura Prospecto', $database['body']);
        $this->assertStringContainsString('Casa Roble', $database['body']);

        $mail = $notification->toMail($agent);
        $this->assertInstanceOf(MailMessage::class, $mail);
    }

    public function test_lead_assigned_event_notifies_the_assigned_agent(): void
    {
        Notification::fake();
        $agent = $this->activeAgent();
        $lead = Lead::factory()->create(['agent_id' => $agent->id]);

        LeadAssigned::dispatch($lead, $agent, 'automatic');

        Notification::assertSentTo($agent, LeadAssignedNotification::class, function (LeadAssignedNotification $notification) use ($lead): bool {
            return $notification->lead->is($lead);
        });
    }

    public function test_unassigned_leads_do_not_create_orphan_notifications(): void
    {
        Notification::fake();
        $lead = Lead::factory()->create(['agent_id' => null]);

        $this->assertNull($lead->agent);

        Notification::assertNothingSent();
    }

    public function test_notification_is_queueable_and_creates_database_payload_for_filament_bell(): void
    {
        $agent = $this->activeAgent();
        $lead = Lead::factory()->create(['agent_id' => $agent->id]);
        $notification = new LeadAssignedNotification($lead);

        $this->assertInstanceOf(ShouldQueue::class, $notification);

        $agent->notify($notification);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $agent->id,
            'type' => LeadAssignedNotification::class,
        ]);
        $this->assertSame(1, $agent->unreadNotifications()->count());

        $database = $notification->toDatabase($agent);
        $this->assertSame('filament', $database['format']);
        $this->assertSame('Nuevo lead asignado', $database['title']);
    }

    public function test_lead_resource_url_is_available_for_notification_action(): void
    {
        $agent = $this->activeAgent();
        $lead = Lead::factory()->create(['agent_id' => $agent->id]);

        $this->actingAs($agent);

        $database = (new LeadAssignedNotification($lead))->toDatabase($agent);

        $this->assertStringContainsString(
            LeadResource::getUrl('edit', ['record' => $lead]),
            json_encode($database, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        );
    }

    private function activeAgent(): User
    {
        return User::factory()->active()->withRole('agente')->create();
    }
}
