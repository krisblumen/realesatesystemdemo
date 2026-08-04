<?php

namespace Tests\Feature\Properties;

use App\Models\Feature;
use App\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_property_can_attach_and_detach_catalog_features(): void
    {
        $property = Property::factory()->create();
        $pool = Feature::factory()->create(['name' => 'Alberca', 'slug' => 'alberca']);
        $garden = Feature::factory()->create(['name' => 'Jardín', 'slug' => 'jardin']);

        $property->features()->attach([$pool->id, $garden->id]);

        $this->assertCount(2, $property->features);
        $this->assertDatabaseHas('property_feature', [
            'property_id' => $property->id,
            'feature_id' => $pool->id,
        ]);

        $property->features()->detach($pool);

        $this->assertDatabaseMissing('property_feature', [
            'property_id' => $property->id,
            'feature_id' => $pool->id,
        ]);
        $this->assertDatabaseHas('features', ['id' => $pool->id]);
    }

    public function test_feature_exposes_inverse_property_relation(): void
    {
        $feature = Feature::factory()->create();
        $property = Property::factory()->create();
        $feature->properties()->attach($property);

        $this->assertTrue($feature->properties()->sole()->is($property));
    }
}
