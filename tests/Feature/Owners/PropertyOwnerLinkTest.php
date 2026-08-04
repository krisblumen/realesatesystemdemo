<?php

namespace Tests\Feature\Owners;

use App\Models\Property;
use App\Models\PropertyOwner;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyOwnerLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_property_belongs_to_owner_and_owner_has_many_properties(): void
    {
        $owner = PropertyOwner::factory()->create();
        $first = Property::factory()->create(['owner_id' => $owner->id]);
        $second = Property::factory()->create(['owner_id' => $owner->id]);

        $this->assertTrue($first->owner->is($owner));
        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            $owner->properties->pluck('id')->all(),
        );
    }

    public function test_commission_percentage_persists_with_two_decimals(): void
    {
        $property = Property::factory()->create(['commission_percentage' => 5.5]);

        $this->assertSame('5.50', (string) $property->fresh()->commission_percentage);
    }

    public function test_commission_percentage_rejects_values_out_of_range(): void
    {
        $this->expectException(QueryException::class);

        Property::factory()->create(['commission_percentage' => 150]);
    }
}
