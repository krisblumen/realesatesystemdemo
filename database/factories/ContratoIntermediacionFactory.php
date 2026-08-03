<?php

namespace Database\Factories;

use App\Enums\EstadoContrato;
use App\Enums\TipoOperacionContrato;
use App\Models\ContratoIntermediacion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContratoIntermediacion> */
class ContratoIntermediacionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'folio' => fake()->unique()->regexify('[A-Z0-9]{8}'),
            'estado' => EstadoContrato::Generado,
            'cliente_nombre' => fake()->name(),
            'cliente_telefono' => fake()->numerify('55########'),
            'cliente_email' => fake()->safeEmail(),
            'cliente_direccion' => fake()->address(),
            'inmueble_tipo' => fake()->randomElement(['casa', 'departamento', 'terreno', 'local']),
            'tipo_operacion' => TipoOperacionContrato::Venta,
            'inmueble_direccion' => fake()->address(),
            'precio_autorizado' => fake()->numberBetween(500, 20000) * 1000,
            'comision_porcentaje' => fake()->randomFloat(2, 1, 10),
            'vigencia_inicio' => now()->toDateString(),
            'vigencia_fin' => now()->addMonths(3)->toDateString(),
            'exclusividad' => false,
            'plantilla_version' => 'v1',
            'agente_id' => User::factory()->activeAgent(),
        ];
    }

    public function ofType(TipoOperacionContrato $tipo): static
    {
        return $this->state(['tipo_operacion' => $tipo]);
    }

    public function enEstado(EstadoContrato $estado): static
    {
        return $this->state(['estado' => $estado]);
    }
}
