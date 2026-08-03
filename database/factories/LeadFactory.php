<?php

namespace Database\Factories;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Lead> */
class LeadFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->optional()->numerify('##########'),
            'message' => fake()->optional()->sentence(),
            'source' => LeadSource::Web,
            'status' => LeadStatus::Nuevo,
            'property_id' => null,
            'agent_id' => null,
            'zone_id' => null,
            'assigned_at' => null,
        ];
    }

    public function source(LeadSource $source): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => $source,
        ]);
    }

    public function status(LeadStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
        ]);
    }

    public function assignedTo(User $agent): static
    {
        return $this->state(fn (array $attributes) => [
            'agent_id' => $agent->id,
            'assigned_at' => now(),
        ]);
    }

    public function forProperty(Property $property): static
    {
        return $this->state(fn (array $attributes) => [
            'property_id' => $property->id,
        ]);
    }
}
