<?php

namespace Database\Factories;

use App\Enums\LonaUnitStatus;
use App\Enums\OperationType;
use App\Models\LonaBatch;
use App\Models\LonaUnit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LonaUnit> */
class LonaUnitFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $agent = User::factory()->activeAgent();

        return [
            'lona_batch_id' => LonaBatch::factory(),
            'agent_id' => $agent,
            'operation_type' => OperationType::Venta,
            'status' => LonaUnitStatus::PendienteColocacion,
        ];
    }

    public function ofType(OperationType $type): static
    {
        return $this->state(['operation_type' => $type]);
    }

    public function placed(): static
    {
        return $this->state([
            'status' => LonaUnitStatus::Colocada,
            'placed_at' => now(),
        ]);
    }

    /**
     * Alinea el agente de la unidad con el del lote (útil para pruebas realistas).
     */
    public function forAgent(User $agent): static
    {
        return $this->state(['agent_id' => $agent->id]);
    }
}
