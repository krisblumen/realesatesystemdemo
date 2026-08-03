<?php

namespace Tests\Feature\Properties;

use App\Enums\PropertyStatus;
use App\Enums\ZoneStatus;
use App\Models\Property;
use App\Models\Zone;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

class PropertyPublicationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_published_property_cannot_be_reassigned_to_invalid_zone(): void
    {
        $property = Property::factory()->published()->create();
        $inactiveZone = Zone::factory()->inactive()->create();

        $this->expectException(ValidationException::class);
        $property->update(['zone_id' => $inactiveZone->id]);
    }

    public function test_published_property_cannot_delete_its_last_cover(): void
    {
        $property = Property::factory()->published()->create();

        try {
            $property->clearMediaCollection('cover');
            $this->fail('Deleting the last cover of a published property must fail.');
        } catch (ValidationException) {
            $this->assertTrue($property->fresh()->hasCoverImage());
        }
    }

    public function test_published_property_can_replace_cover(): void
    {
        $property = Property::factory()->published()->create();
        $original = $property->getFirstMedia('cover');
        $this->assertInstanceOf(Media::class, $original);

        $replacement = $property->addMediaFromString((string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADUlEQVQYlWNgGAWkAwABNgABxYufBwAAAABJRU5ErkJggg==',
            true,
        ))
            ->usingFileName('replacement.png')
            ->toMediaCollection('cover');
        $original->delete();

        $this->assertDatabaseHas('media', ['id' => $replacement->id]);
        $this->assertSame(1, $property->fresh()->getMedia('cover')->count());
    }

    public function test_inactivating_zone_pauses_published_properties(): void
    {
        $property = Property::factory()->published()->create();
        $zone = $property->zone;

        $zone->forceFill(['status' => ZoneStatus::Inactive])->save();

        $this->assertSame(PropertyStatus::Pausado, $property->refresh()->status);
        $this->assertFalse(Property::published()->whereKey($property)->exists());
    }

    public function test_zone_with_published_property_cannot_be_deleted(): void
    {
        $property = Property::factory()->published()->create();

        $this->expectException(ValidationException::class);

        $property->zone->delete();
    }
}
