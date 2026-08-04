<?php

namespace Tests\Feature\Leads;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\PropertyStatus;
use App\Events\LeadCaptured;
use App\Livewire\Leads\LeadCaptureForm;
use App\Models\Lead;
use App\Models\Property;
use App\Models\User;
use App\Notifications\LeadConfirmationNotification;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

class PublicLeadCaptureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);

        RateLimiter::clear('lead-capture:127.0.0.1');
    }

    public function test_public_form_creates_a_new_lead_and_dispatches_event(): void
    {
        Event::fake([LeadCaptured::class]);
        $property = Property::factory()->published()->create();

        Livewire::test(LeadCaptureForm::class, [
            'propertyId' => $property->id,
            'source' => LeadSource::Inmueble->value,
        ])
            ->set('name', 'María López')
            ->set('email', 'maria@example.test')
            ->set('phone', '4421234567')
            ->set('message', 'Quiero más información.')
            ->set('service_type', 'comercializacion')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);

        $lead = Lead::where('email', 'maria@example.test')->firstOrFail();

        $this->assertSame(LeadStatus::Nuevo, $lead->status);
        $this->assertSame(LeadSource::Inmueble, $lead->source);
        $this->assertSame('comercializacion', $lead->service_type);
        $this->assertSame($property->id, $lead->property_id);
        $this->assertNull($lead->agent_id);

        Event::assertDispatched(LeadCaptured::class, fn (LeadCaptured $event): bool => $event->lead->is($lead));
    }

    public function test_general_contact_form_sends_generic_confirmation_to_the_client(): void
    {
        Notification::fake();

        Livewire::test(LeadCaptureForm::class)
            ->set('name', 'María López')
            ->set('email', 'maria-generico@example.test')
            ->set('service_type', 'comercializacion')
            ->call('submit')
            ->assertHasNoErrors();

        $lead = Lead::where('email', 'maria-generico@example.test')->firstOrFail();

        Notification::assertSentTo(
            $lead,
            LeadConfirmationNotification::class,
            function (LeadConfirmationNotification $notification) use ($lead): bool {
                $mail = $notification->toMail($lead);

                return str_contains(implode(' ', $mail->introLines), 'Un asesor de New Hauz');
            },
        );
    }

    public function test_property_page_form_sends_personalized_confirmation_with_the_agent(): void
    {
        Notification::fake();
        $agent = User::factory()->activeAgent()->create(['name' => 'Carlos Ruiz', 'phone' => '4421112233']);
        $property = Property::factory()->forAgent($agent)->published()->create();

        Livewire::test(LeadCaptureForm::class, [
            'propertyId' => $property->id,
            'source' => LeadSource::Inmueble->value,
        ])
            ->set('name', 'María López')
            ->set('email', 'maria-inmueble@example.test')
            ->set('service_type', 'comercializacion')
            ->call('submit')
            ->assertHasNoErrors();

        $lead = Lead::where('email', 'maria-inmueble@example.test')->firstOrFail();
        $this->assertSame($agent->id, $lead->agent_id);

        Notification::assertSentTo(
            $lead,
            LeadConfirmationNotification::class,
            function (LeadConfirmationNotification $notification) use ($lead): bool {
                $mail = $notification->toMail($lead);
                $lines = implode(' ', $mail->introLines);

                return str_contains($lines, 'Carlos Ruiz') && str_contains($lines, '4421112233');
            },
        );
    }

    public function test_property_lead_rejects_missing_unpublished_or_unassigned_property(): void
    {
        $agent = User::factory()->activeAgent()->create();
        $draft = Property::factory()->forAgent($agent)->create(['status' => PropertyStatus::Borrador]);
        $unassignedPublished = Property::factory()->create();
        $unassignedPublished->forceFill(['status' => PropertyStatus::Publicado])->saveQuietly();

        foreach ([999999, $draft->id, $unassignedPublished->id] as $propertyId) {
            Livewire::test(LeadCaptureForm::class, [
                'propertyId' => $propertyId,
                'source' => LeadSource::Inmueble->value,
            ])
                ->set('name', 'María López')
                ->set('email', 'maria'.$propertyId.'@example.test')
                ->call('submit')
                ->assertHasErrors(['property_id']);
        }

        $this->assertSame(0, Lead::count());
    }

    public function test_locked_public_form_forces_service_type_before_validation(): void
    {
        Livewire::test(LeadCaptureForm::class, [
            'source' => LeadSource::Web->value,
            'serviceType' => 'comercializacion',
            'locked' => true,
        ])
            ->set('service_type', 'arquitectura')
            ->set('name', 'Payload Manipulado')
            ->set('email', 'payload@example.test')
            ->call('submit')
            ->assertHasNoErrors();

        $lead = Lead::where('email', 'payload@example.test')->firstOrFail();

        $this->assertSame('comercializacion', $lead->service_type);
    }

    public function test_non_commercial_public_submission_prohibits_property_id(): void
    {
        $property = Property::factory()->published()->create();

        Livewire::test(LeadCaptureForm::class, [
            'propertyId' => $property->id,
            'serviceType' => 'arquitectura',
        ])
            ->set('name', 'Arquitectura Pública')
            ->set('email', 'arquitectura-publica@example.test')
            ->call('submit')
            ->assertHasErrors(['property_id' => 'prohibited']);

        $this->assertSame(0, Lead::where('email', 'arquitectura-publica@example.test')->count());
    }

    public function test_public_form_sanitizes_name_and_message_before_persisting(): void
    {
        Livewire::test(LeadCaptureForm::class)
            ->set('name', '<script>alert(1)</script> María')
            ->set('email', 'safe@example.test')
            ->set('message', '<b>Hola</b><script>alert(1)</script>')
            ->set('service_type', 'comercializacion')
            ->call('submit')
            ->assertHasNoErrors();

        $lead = Lead::where('email', 'safe@example.test')->firstOrFail();

        $this->assertSame('alert(1) María', $lead->name);
        $this->assertSame('Holaalert(1)', $lead->message);
    }

    public function test_public_form_validates_required_fields_and_email(): void
    {
        Livewire::test(LeadCaptureForm::class)
            ->set('name', '')
            ->set('email', 'not-an-email')
            ->call('submit')
            ->assertHasErrors([
                'name' => 'required',
                'email' => 'email',
            ]);

        $this->assertSame(0, Lead::count());
    }

    public function test_honeypot_discards_spam_silently(): void
    {
        Livewire::test(LeadCaptureForm::class)
            ->set('name', 'Bot')
            ->set('email', 'bot@example.test')
            ->set('company_website', 'https://spam.example')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);

        $this->assertSame(0, Lead::count());
        $this->assertSame(1, RateLimiter::attempts('lead-capture:127.0.0.1'));
    }

    public function test_rate_limiter_counts_invalid_submissions(): void
    {
        Livewire::test(LeadCaptureForm::class)
            ->set('name', 'María López')
            ->set('email', 'not-an-email')
            ->call('submit')
            ->assertHasErrors(['email']);

        $this->assertSame(1, RateLimiter::attempts('lead-capture:127.0.0.1'));
        $this->assertSame(0, Lead::count());
    }

    public function test_rate_limiter_blocks_repeated_submissions_from_same_ip(): void
    {
        RateLimiter::hit('lead-capture:127.0.0.1', 60);
        RateLimiter::hit('lead-capture:127.0.0.1', 60);
        RateLimiter::hit('lead-capture:127.0.0.1', 60);

        Livewire::test(LeadCaptureForm::class)
            ->set('name', 'María López')
            ->set('email', 'maria@example.test')
            ->call('submit')
            ->assertHasErrors(['rate_limit']);

        $this->assertSame(0, Lead::count());
    }
}
