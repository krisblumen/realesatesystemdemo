<?php

namespace Tests\Feature\Properties;

use App\Enums\OperationType;
use App\Enums\PropertyType;
use App\Models\Property;
use App\Models\Zone;
use App\Support\PropertySlugGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertySlugTest extends TestCase
{
    use RefreshDatabase;

    public function test_slug_is_generated_from_zone_type_and_title(): void
    {
        $zone = Zone::factory()->create(['name' => 'Juriquilla', 'slug' => 'juriquilla']);

        $property = Property::factory()->create([
            'title' => 'Con alberca',
            'property_type' => PropertyType::Casa,
            'zone_id' => $zone->id,
        ]);

        $this->assertSame('juriquilla-casa-con-alberca', $property->slug);
    }

    public function test_slug_collision_includes_soft_deleted_properties(): void
    {
        $deleted = Property::factory()->create(['title' => 'Casa Centro']);
        $deleted->delete();

        $property = Property::factory()->create(['title' => 'Casa Centro']);

        $this->assertSame($deleted->slug.'-2', $property->slug);
    }

    public function test_editing_title_keeps_the_existing_slug(): void
    {
        $property = Property::factory()->create(['title' => 'Original']);
        $slug = $property->slug;

        $property->update(['title' => 'Updated']);

        $this->assertSame($slug, $property->refresh()->slug);
    }

    public function test_generator_excludes_the_current_property_when_regenerating(): void
    {
        $property = Property::factory()->create();

        $generated = app(PropertySlugGenerator::class)->generate($property, $property->id);

        $this->assertSame($property->slug, $generated);
    }

    public function test_generator_persists_a_unique_slug_through_the_retry_entrypoint(): void
    {
        Property::factory()->create(['title' => 'Repeated']);
        $property = new Property([
            'title' => 'Repeated',
            'operation_type' => OperationType::Venta,
            'property_type' => PropertyType::Casa,
            'price' => 1_000_000,
        ]);

        app(PropertySlugGenerator::class)->persist($property);

        $this->assertTrue($property->exists);
        $this->assertSame('casa-repeated-2', $property->slug);
    }
}
