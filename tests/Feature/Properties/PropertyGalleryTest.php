<?php

namespace Tests\Feature\Properties;

use App\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

class PropertyGalleryTest extends TestCase
{
    use RefreshDatabase;

    public function test_cover_collection_keeps_one_file_and_generates_conversions(): void
    {
        $property = Property::factory()->create();

        $first = $this->addImage($property, 'cover', 'first.png');
        $second = $this->addImage($property, 'cover', 'second.png');

        $this->assertDatabaseMissing('media', ['id' => $first->id]);
        $this->assertSame(1, $property->fresh()->getMedia('cover')->count());
        $this->assertTrue($second->fresh()->hasGeneratedConversion('thumb'));
        $this->assertTrue($second->fresh()->hasGeneratedConversion('web'));
    }

    public function test_gallery_accepts_multiple_images_and_persists_order(): void
    {
        $property = Property::factory()->create();
        $first = $this->addImage($property, 'gallery', 'first.png');
        $second = $this->addImage($property, 'gallery', 'second.png');

        Media::setNewOrder([$second->id, $first->id]);

        $this->assertSame(
            [$second->id, $first->id],
            $property->fresh()->getMedia('gallery')->pluck('id')->all(),
        );

        $second->delete();

        $this->assertSame([$first->id], $property->fresh()->getMedia('gallery')->pluck('id')->all());
    }

    private function addImage(Property $property, string $collection, string $name): Media
    {
        return $property->addMediaFromString($this->imageBytes())
            ->usingFileName($name)
            ->toMediaCollection($collection);
    }

    private function imageBytes(): string
    {
        return (string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADUlEQVQYlWNgGAWkAwABNgABxYufBwAAAABJRU5ErkJggg==',
            true,
        );
    }
}
