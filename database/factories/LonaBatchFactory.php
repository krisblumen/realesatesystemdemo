<?php

namespace Database\Factories;

use App\Enums\OperationType;
use App\Models\LonaBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LonaBatch> */
class LonaBatchFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agent_id' => User::factory()->activeAgent(),
            'lona_request_id' => null,
            'operation_type' => OperationType::Venta,
            'cantidad' => fake()->numberBetween(1, 10),
            'created_by' => User::factory()->active()->withRole('admin'),
        ];
    }

    public function ofType(OperationType $type): static
    {
        return $this->state(['operation_type' => $type]);
    }
}
