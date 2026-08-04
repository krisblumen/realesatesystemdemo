<?php

namespace Database\Factories;

use App\Models\PropertyOwner;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PropertyOwner> */
class PropertyOwnerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => (string) fake()->numerify('##########'),
            'email' => fake()->optional()->safeEmail(),
            'agent_id' => null,
        ];
    }
}
