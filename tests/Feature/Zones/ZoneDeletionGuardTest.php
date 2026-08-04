<?php

namespace Tests\Feature\Zones;

use App\Models\Property;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ZoneDeletionGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_zone_without_assignments_can_be_deleted(): void
    {
        $zone = Zone::factory()->create();

        $zone->delete();

        $this->assertSoftDeleted('zones', ['id' => $zone->id]);
    }

    public function test_zone_with_properties_cannot_be_deleted(): void
    {
        $zone = Zone::factory()->create();
        Property::factory()->create(['zone_id' => $zone->id]);

        $this->expectException(ValidationException::class);

        $zone->delete();
    }

    public function test_zone_with_agents_cannot_be_deleted(): void
    {
        $zone = Zone::factory()->create();
        $agent = User::factory()->create();
        $zone->agents()->attach($agent);

        $this->expectException(ValidationException::class);

        $zone->delete();
    }

    public function test_postal_code_still_in_use_by_a_property_cannot_be_removed_from_zone(): void
    {
        $zone = Zone::factory()->create();
        $zone->syncPostalCodes(['64000', '64010']);

        Property::factory()->create([
            'zone_id' => $zone->id,
            'postal_code' => '64000',
        ]);

        $this->expectException(ValidationException::class);

        $zone->syncPostalCodes(['64010']);
    }

    public function test_postal_code_without_properties_can_be_removed_from_zone(): void
    {
        $zone = Zone::factory()->create();
        $zone->syncPostalCodes(['64000', '64010']);

        $zone->syncPostalCodes(['64010']);

        $this->assertSame(['64010'], $zone->postalCodeList());
    }
}
