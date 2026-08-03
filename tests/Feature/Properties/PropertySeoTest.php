<?php

namespace Tests\Feature\Properties;

use App\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertySeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_seo_helpers_use_property_fallbacks(): void
    {
        $description = '<p>'.str_repeat('Descripción amplia ', 20).'</p>';
        $property = Property::factory()->create([
            'title' => 'Casa Juriquilla',
            'description' => $description,
            'meta_title' => null,
            'meta_description' => null,
            'canonical_url' => null,
        ]);

        $this->assertSame('Casa Juriquilla', $property->seoTitle());
        $this->assertStringNotContainsString('<p>', $property->seoDescription());
        $this->assertLessThanOrEqual(163, mb_strlen($property->seoDescription()));
        $this->assertSame(url("/inmuebles/{$property->slug}"), $property->canonical());
    }

    public function test_seo_helpers_prefer_custom_metadata(): void
    {
        $property = Property::factory()->create([
            'meta_title' => 'Meta personalizada',
            'meta_description' => 'Descripción personalizada',
            'canonical_url' => 'https://example.test/inmueble-canonico',
        ]);

        $this->assertSame('Meta personalizada', $property->seoTitle());
        $this->assertSame('Descripción personalizada', $property->seoDescription());
        $this->assertSame('https://example.test/inmueble-canonico', $property->canonical());
    }

    public function test_open_graph_image_contract_prefers_cover_then_gallery(): void
    {
        $property = Property::factory()->create();
        $property->addMediaFromString($this->imageBytes())
            ->usingFileName('gallery.png')
            ->toMediaCollection('gallery');

        $galleryUrl = $property->getFirstMediaUrl('cover', 'web')
            ?: $property->getFirstMediaUrl('cover')
            ?: $property->getFirstMediaUrl('gallery', 'web')
            ?: $property->getFirstMediaUrl('gallery');

        $this->assertStringContainsString('gallery', $galleryUrl);

        $property->addMediaFromString($this->imageBytes())
            ->usingFileName('cover.png')
            ->toMediaCollection('cover');
        $property = $property->fresh();

        $coverUrl = $property->getFirstMediaUrl('cover', 'web')
            ?: $property->getFirstMediaUrl('cover')
            ?: $property->getFirstMediaUrl('gallery', 'web')
            ?: $property->getFirstMediaUrl('gallery');

        $this->assertStringContainsString('cover', $coverUrl);
    }

    private function imageBytes(): string
    {
        return (string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADUlEQVQYlWNgGAWkAwABNgABxYufBwAAAABJRU5ErkJggg==',
            true,
        );
    }
}
