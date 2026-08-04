<?php

namespace Database\Factories;

use App\Enums\LonaRequestStatus;
use App\Enums\OperationType;
use App\Models\LonaRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LonaRequest> */
class LonaRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agent_id' => User::factory()->activeAgent(),
            'operation_type' => OperationType::Venta,
            'cantidad_solicitada' => fake()->numberBetween(1, 10),
            'estado' => LonaRequestStatus::Pendiente,
        ];
    }

    public function ofType(OperationType $type): static
    {
        return $this->state(['operation_type' => $type]);
    }

    public function approved(): static
    {
        return $this->state(['estado' => LonaRequestStatus::Aprobada]);
    }

    public function rejected(string $motivo = 'Sin cupo disponible'): static
    {
        return $this->state([
            'estado' => LonaRequestStatus::Rechazada,
            'motivo_rechazo' => $motivo,
        ]);
    }
}
