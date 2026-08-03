<?php

namespace Database\Factories;

use App\Enums\OperationType;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\ZoneStatus;
use App\Models\Property;
use App\Models\PropertyOwner;
use App\Models\User;
use App\Models\Zone;
use App\Services\PropertyStatusService;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Property> */
class PropertyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->streetName().' '.fake()->randomNumber(3),
            'description' => fake()->paragraph(),
            'operation_type' => OperationType::Venta,
            'property_type' => PropertyType::Casa,
            'status' => PropertyStatus::Borrador,
            'price' => fake()->numberBetween(800_000, 12_000_000),
            'bedrooms' => fake()->numberBetween(1, 5),
            'bathrooms' => fake()->randomElement([1, 1.5, 2, 2.5, 3]),
            'parking_spaces' => fake()->numberBetween(0, 3),
            'land_area' => fake()->randomFloat(2, 40, 1000),
            'construction_area' => fake()->randomFloat(2, 40, 700),
            'zone_id' => null,
            'agent_id' => null,
        ];
    }

    public function published(): static
    {
        return $this
            ->state([
                'street' => fake()->streetName(),
                'exterior_number' => (string) fake()->numberBetween(1, 999),
                'colonia' => 'Centro',
                'postal_code' => (string) fake()->numerify('76###'),
            ])
            ->for(Zone::factory()->withPolygon()->state(['status' => ZoneStatus::Active]))
            ->for(User::factory()->withRole('agente')->active(), 'agent')
            ->afterCreating(function (Property $property): void {
                $property->addMediaFromString(self::coverBytes())
                    ->usingFileName('cover.png')
                    ->toMediaCollection('cover');

                $owner = PropertyOwner::factory()->create(['agent_id' => $property->agent_id]);

                $property->forceFill([
                    'owner_id' => $owner->id,
                    'commission_percentage' => 5.0,
                ])->saveQuietly();

                app(PropertyStatusService::class)->transition($property, PropertyStatus::Publicado);
            });
    }

    public function draftWithoutCover(): static
    {
        return $this->state(['status' => PropertyStatus::Borrador]);
    }

    public function forAgent(User $agent): static
    {
        return $this->state(['agent_id' => $agent->id]);
    }

    private static function coverBytes(): string
    {
        return (string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAIAAAACUFjqAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADUlEQVQYlWNgGAWkAwABNgABxYufBwAAAABJRU5ErkJggg==',
            true,
        );
    }
}
