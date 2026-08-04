<?php

namespace Tests\Feature\Leads;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\Property;
use App\Models\ServiceType;
use App\Models\User;
use App\Models\Zone;
use App\Services\LeadStatusService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LeadCoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_lead_defaults_and_enum_casts_are_available(): void
    {
        $lead = Lead::create([
            'name' => 'María Prospecto',
            'email' => 'maria@example.test',
        ]);

        $this->assertSame('web', $lead->getRawOriginal('source'));
        $this->assertSame('nuevo', $lead->getRawOriginal('status'));
        $this->assertSame('comercializacion', $lead->service_type);
        $this->assertSame(LeadSource::Web, $lead->source);
        $this->assertSame(LeadStatus::Nuevo, $lead->status);
        $this->assertTrue($lead->isNew());
        $this->assertFalse($lead->isAssigned());
        $this->assertFalse($lead->isClosed());
    }

    public function test_lead_relationships_resolve_property_agent_and_deferred_zone_contract(): void
    {
        $agent = User::factory()->create();
        $zone = Zone::factory()->create();
        $property = Property::factory()->for($zone)->for($agent, 'agent')->create();

        $lead = Lead::create([
            'name' => 'Carlos Prospecto',
            'email' => 'carlos@example.test',
            'phone' => '4421234567',
            'message' => 'Estoy interesado en este inmueble.',
            'source' => LeadSource::Inmueble,
            'status' => LeadStatus::Contactado,
            'property_id' => $property->id,
            'agent_id' => $agent->id,
            'zone_id' => $zone->id,
            'assigned_at' => now(),
        ]);

        $this->assertTrue($lead->property->is($property));
        $this->assertTrue($lead->agent->is($agent));
        $this->assertTrue($lead->zone->is($zone));
        $this->assertTrue($lead->isAssigned());
        $this->assertFalse($lead->isClosed());
    }

    public function test_helpers_and_scopes_express_lead_state(): void
    {
        $agent = User::factory()->create();
        $newLead = Lead::factory()->create();
        $assignedLead = Lead::factory()->create(['agent_id' => $agent->id]);
        $closedLead = Lead::factory()->create(['status' => LeadStatus::CerradoGanado]);

        $this->assertTrue($closedLead->isClosed());

        $this->assertTrue(Lead::unassigned()->whereKey($newLead)->exists());
        $this->assertFalse(Lead::unassigned()->whereKey($assignedLead)->exists());
        $this->assertTrue(Lead::byAgent($agent)->whereKey($assignedLead)->exists());
        $this->assertTrue(Lead::byStatus(LeadStatus::CerradoGanado)->whereKey($closedLead)->exists());
    }

    public function test_factory_states_and_origins_are_expressive(): void
    {
        $agent = User::factory()->activeAgent()->create();
        $lead = Lead::factory()
            ->source(LeadSource::Telefono)
            ->status(LeadStatus::EnSeguimiento)
            ->assignedTo($agent)
            ->create();

        $this->assertSame(LeadSource::Telefono, $lead->source);
        $this->assertSame(LeadStatus::EnSeguimiento, $lead->status);
        $this->assertSame($agent->id, $lead->agent_id);
        $this->assertNotNull($lead->assigned_at);
    }

    public function test_status_machine_blocks_invalid_shortcuts_and_terminal_reopens(): void
    {
        $service = app(LeadStatusService::class);
        $newLead = Lead::factory()->create(['status' => LeadStatus::Nuevo]);

        $this->expectException(ValidationException::class);
        $service->transition($newLead, LeadStatus::CerradoGanado);
    }

    public function test_status_machine_allows_expected_progression_and_terminal_states_are_closed(): void
    {
        $service = app(LeadStatusService::class);
        $lead = Lead::factory()->create(['status' => LeadStatus::Nuevo]);

        $service->transition($lead, LeadStatus::Contactado);
        $this->assertSame(LeadStatus::Contactado, $lead->refresh()->status);

        $service->transition($lead, LeadStatus::EnSeguimiento);
        $service->transition($lead, LeadStatus::CerradoGanado);

        $this->assertTrue($lead->refresh()->isClosed());
        $this->assertFalse($lead->status->canTransitionTo(LeadStatus::EnSeguimiento));
    }

    public function test_lead_uses_soft_deletes(): void
    {
        $lead = Lead::factory()->create();

        $lead->delete();

        $this->assertSoftDeleted('leads', ['id' => $lead->id]);
    }

    public function test_lead_inherits_zone_from_property_when_zone_is_blank(): void
    {
        $zone = Zone::factory()->create();
        $property = Property::factory()->for($zone)->create();

        $lead = Lead::create([
            'name' => 'Sin Zona Explicita',
            'email' => 'sinzona@example.test',
            'source' => LeadSource::Inmueble,
            'property_id' => $property->id,
        ]);

        $this->assertSame($zone->id, $lead->zone_id);
    }

    public function test_lead_keeps_explicit_zone_over_property_zone(): void
    {
        $propertyZone = Zone::factory()->create();
        $explicitZone = Zone::factory()->create();
        $property = Property::factory()->for($propertyZone)->create();

        $lead = Lead::create([
            'name' => 'Zona Explicita',
            'email' => 'explicita@example.test',
            'source' => LeadSource::Inmueble,
            'property_id' => $property->id,
            'zone_id' => $explicitZone->id,
        ]);

        $this->assertSame($explicitZone->id, $lead->zone_id);
    }

    public function test_lead_without_property_has_null_zone(): void
    {
        $lead = Lead::create([
            'name' => 'Sin Inmueble',
            'email' => 'sininmueble@example.test',
        ]);

        $this->assertNull($lead->zone_id);
    }

    public function test_initial_service_type_catalog_is_seeded(): void
    {
        // `inversion` is reconciled into the catalog by the frontend_services
        // migration (RFC-074, B-5): it is now a real ServiceType (sort_order 4).
        $this->assertSame(
            ['comercializacion', 'arquitectura', 'construccion', 'inversion'],
            ServiceType::query()->orderBy('sort_order')->pluck('code')->all()
        );
    }

    public function test_non_commercial_service_type_clears_property_and_zone(): void
    {
        $zone = Zone::factory()->create();
        $property = Property::factory()->for($zone)->create();

        $lead = Lead::create([
            'name' => 'Proyecto Arquitectura',
            'email' => 'arquitectura@example.test',
            'service_type' => 'arquitectura',
            'property_id' => $property->id,
            'zone_id' => $zone->id,
        ]);

        $this->assertSame('arquitectura', $lead->service_type);
        $this->assertNull($lead->property_id);
        $this->assertNull($lead->zone_id);
    }
}
