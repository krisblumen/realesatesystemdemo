<?php

namespace Tests\Feature\Properties;

use App\Enums\OperationType;
use App\Enums\PropertyStatus;
use App\Models\Property;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyScopesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_filter_scopes_return_only_matching_properties(): void
    {
        $zone = Zone::factory()->create();
        $otherZone = Zone::factory()->create();
        $sale = Property::factory()->create(['zone_id' => $zone->id]);
        Property::factory()->create([
            'zone_id' => $otherZone->id,
            'operation_type' => OperationType::Renta,
        ]);

        $sale->forceFill(['status' => PropertyStatus::Publicado])->saveQuietly();

        $this->assertTrue(Property::published()->sole()->is($sale));
        $this->assertTrue(Property::byZone($zone->id)->sole()->is($sale));
        $this->assertTrue(Property::byOperation(OperationType::Venta)->sole()->is($sale));
    }

    public function test_visible_to_honors_agent_precedence_and_unassigned_zone_access(): void
    {
        $zone = Zone::factory()->create();
        $agentA = User::factory()->withRole('agente')->create();
        $agentB = User::factory()->withRole('agente')->create();
        $owner = User::factory()->withRole('owner')->create();
        $zone->agents()->attach([$agentA->id, $agentB->id]);

        $owned = Property::factory()->create(['zone_id' => $zone->id, 'agent_id' => $agentA->id]);
        $assignedToOther = Property::factory()->create(['zone_id' => $zone->id, 'agent_id' => $agentB->id]);
        $unassigned = Property::factory()->create(['zone_id' => $zone->id, 'agent_id' => null]);

        $visibleToA = Property::visibleTo($agentA)->pluck('id');

        $this->assertTrue($visibleToA->contains($owned->id));
        $this->assertTrue($visibleToA->contains($unassigned->id));
        $this->assertFalse($visibleToA->contains($assignedToOther->id));
        $this->assertCount(3, Property::visibleTo($owner)->get());
    }
}
