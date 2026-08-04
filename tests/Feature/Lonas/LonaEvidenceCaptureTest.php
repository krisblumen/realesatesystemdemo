<?php

namespace Tests\Feature\Lonas;

use App\Enums\LonaUnitStatus;
use App\Livewire\Lonas\CapturePlacementEvidence;
use App\Models\LonaUnit;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class LonaEvidenceCaptureTest extends TestCase
{
    use RefreshDatabase;

    /** A valid 1x1 transparent PNG as a data URI. */
    private const PHOTO = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    private function agentWithPendingUnit(): array
    {
        $agent = User::factory()->activeAgent()->create();
        $unit = LonaUnit::factory()->forAgent($agent)->create();

        return [$agent, $unit];
    }

    public function test_cannot_mark_unit_placed_without_photo_data(): void
    {
        [$agent, $unit] = $this->agentWithPendingUnit();

        Livewire::actingAs($agent)
            ->test(CapturePlacementEvidence::class, ['lonaUnit' => $unit])
            ->set('ubicacionReferencia', 'Av. Reforma 123')
            ->call('confirmPlacement', '')
            ->assertHasErrors('photoData');

        $this->assertSame(LonaUnitStatus::PendienteColocacion, $unit->refresh()->status);
    }

    public function test_marking_unit_placed_stores_media_and_updates_status(): void
    {
        [$agent, $unit] = $this->agentWithPendingUnit();
        $property = Property::factory()->published()->create(['agent_id' => $agent->id]);

        Livewire::actingAs($agent)
            ->test(CapturePlacementEvidence::class, ['lonaUnit' => $unit])
            ->set('propertyId', $property->id)
            ->call('confirmPlacement', self::PHOTO)
            ->assertHasNoErrors();

        $unit->refresh();
        $this->assertSame(LonaUnitStatus::Colocada, $unit->status);
        $this->assertNotNull($unit->placed_at);
        $this->assertSame($property->id, $unit->property_id);
        $this->assertNotNull($unit->getFirstMedia('evidencia'));
    }

    public function test_placement_with_ubicacion_referencia_only_succeeds(): void
    {
        [$agent, $unit] = $this->agentWithPendingUnit();

        Livewire::actingAs($agent)
            ->test(CapturePlacementEvidence::class, ['lonaUnit' => $unit])
            ->set('ubicacionReferencia', 'Esquina Juárez y Morelos')
            ->call('confirmPlacement', self::PHOTO)
            ->assertHasNoErrors();

        $unit->refresh();
        $this->assertSame(LonaUnitStatus::Colocada, $unit->status);
        $this->assertNull($unit->property_id);
        $this->assertSame('Esquina Juárez y Morelos', $unit->ubicacion_referencia);
    }

    public function test_placement_requires_property_or_ubicacion_referencia(): void
    {
        [$agent, $unit] = $this->agentWithPendingUnit();

        Livewire::actingAs($agent)
            ->test(CapturePlacementEvidence::class, ['lonaUnit' => $unit])
            ->call('confirmPlacement', self::PHOTO)
            ->assertHasErrors('ubicacionReferencia');
    }

    public function test_rejects_photo_data_with_invalid_mime_prefix(): void
    {
        [$agent, $unit] = $this->agentWithPendingUnit();

        Livewire::actingAs($agent)
            ->test(CapturePlacementEvidence::class, ['lonaUnit' => $unit])
            ->set('ubicacionReferencia', 'X')
            ->call('confirmPlacement', 'data:text/plain;base64,aGVsbG8=')
            ->assertHasErrors('photoData');
    }

    public function test_rejects_photo_data_larger_than_max_size(): void
    {
        [$agent, $unit] = $this->agentWithPendingUnit();

        $oversized = 'data:image/png;base64,'.str_repeat('A', 7_000_001);

        Livewire::actingAs($agent)
            ->test(CapturePlacementEvidence::class, ['lonaUnit' => $unit])
            ->set('ubicacionReferencia', 'X')
            ->call('confirmPlacement', $oversized)
            ->assertHasErrors('photoData');
    }

    public function test_cannot_assign_another_agents_property(): void
    {
        [$agent, $unit] = $this->agentWithPendingUnit();
        $otherAgent = User::factory()->activeAgent()->create();
        $foreignProperty = Property::factory()->published()->create(['agent_id' => $otherAgent->id]);

        Livewire::actingAs($agent)
            ->test(CapturePlacementEvidence::class, ['lonaUnit' => $unit])
            ->set('propertyId', $foreignProperty->id)
            ->call('confirmPlacement', self::PHOTO)
            ->assertHasErrors('propertyId');
    }

    /**
     * El componente protege la colocación con `authorize('place', $unit)` (en mount y
     * en confirmPlacement). Livewire, en su harness de test, no expone limpiamente la
     * AuthorizationException del mount (la convierte en una respuesta no-200), así que
     * se verifica el guard exacto que el componente consume: la LonaUnitPolicy::place.
     */
    public function test_place_policy_denies_a_non_owner_agent(): void
    {
        [$agentA, $unit] = $this->agentWithPendingUnit();
        $agentB = User::factory()->activeAgent()->create();

        $this->assertTrue(Gate::forUser($agentA)->allows('place', $unit));
        $this->assertFalse(Gate::forUser($agentB)->allows('place', $unit));
    }

    public function test_cannot_place_an_already_placed_unit(): void
    {
        $agent = User::factory()->activeAgent()->create();
        $unit = LonaUnit::factory()->forAgent($agent)->placed()->create();

        Livewire::actingAs($agent)
            ->test(CapturePlacementEvidence::class, ['lonaUnit' => $unit])
            ->set('ubicacionReferencia', 'X')
            ->call('confirmPlacement', self::PHOTO)
            ->assertHasErrors('lonaUnit');
    }
}
