<?php

namespace Tests\Feature\Properties;

use App\Enums\OperationType;
use App\Enums\PropertyStatus;
use App\Models\Property;
use App\Models\PropertyOwner;
use App\Models\Zone;
use App\Services\PropertyStatusService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PropertyStatusServiceTest extends TestCase
{
    use RefreshDatabase;

    private PropertyStatusService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->service = app(PropertyStatusService::class);
    }

    public function test_publish_requires_cover_image(): void
    {
        $property = Property::factory()->create(['zone_id' => Zone::factory()]);

        $this->expectException(ValidationException::class);
        $this->service->transition($property, PropertyStatus::Publicado);
    }

    public function test_publish_requires_a_current_active_zone_with_polygon(): void
    {
        $property = Property::factory()->create();
        $this->addCover($property);

        try {
            $this->service->transition($property, PropertyStatus::Publicado);
            $this->fail('Publishing without a zone must fail.');
        } catch (ValidationException) {
            $this->assertSame(PropertyStatus::Borrador, $property->refresh()->status);
        }

        $property->update(['zone_id' => Zone::factory()->inactive()->create()->id]);

        $this->expectException(ValidationException::class);
        $this->service->transition($property, PropertyStatus::Publicado);
    }

    public function test_valid_property_can_publish_pause_and_republish(): void
    {
        $property = Property::factory()->create([
            'zone_id' => Zone::factory(),
            'owner_id' => PropertyOwner::factory(),
            'commission_percentage' => 5,
            'street' => 'Av. Constituyentes',
            'colonia' => 'Centro',
        ]);
        $this->addCover($property);

        $this->service->transition($property, PropertyStatus::Publicado);
        $this->assertSame(PropertyStatus::Publicado, $property->status);

        $this->service->transition($property, PropertyStatus::Pausado);
        $this->assertSame(PropertyStatus::Pausado, $property->status);

        $this->service->transition($property, PropertyStatus::Publicado);
        $this->assertSame(PropertyStatus::Publicado, $property->status);
    }

    public function test_sold_and_rented_states_require_matching_operation(): void
    {
        $sale = Property::factory()->published()->create(['operation_type' => OperationType::Venta]);
        $rent = Property::factory()->published()->create(['operation_type' => OperationType::Renta]);

        $this->service->transition($sale, PropertyStatus::Vendido);
        $this->service->transition($rent, PropertyStatus::Rentado);

        $this->assertSame(PropertyStatus::Vendido, $sale->status);
        $this->assertSame(PropertyStatus::Rentado, $rent->status);

        $invalid = Property::factory()->published()->create(['operation_type' => OperationType::Renta]);

        $this->expectException(ValidationException::class);
        $this->service->transition($invalid, PropertyStatus::Vendido);
    }

    public function test_terminal_states_can_reopen_only_to_draft(): void
    {
        $property = Property::factory()->published()->create(['operation_type' => OperationType::Venta]);
        $this->service->transition($property, PropertyStatus::Vendido);
        $this->service->transition($property, PropertyStatus::Borrador);

        $this->assertSame(PropertyStatus::Borrador, $property->status);

        $this->expectException(ValidationException::class);
        $this->service->transition($property, PropertyStatus::Pausado);
    }

    private function addCover(Property $property): void
    {
        $property->addMediaFromString((string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADUlEQVQYlWNgGAWkAwABNgABxYufBwAAAABJRU5ErkJggg==',
            true,
        ))
            ->usingFileName('cover.png')
            ->toMediaCollection('cover');
    }
}
