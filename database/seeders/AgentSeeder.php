<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Agentes de prueba con credenciales conocidas.
 * Útil para que todo el equipo tenga el mismo acceso en desarrollo.
 *
 * Credenciales (modificables vía .env):
 *   AGENT_PASSWORD (por defecto: "password")
 *
 * Uso: php artisan db:seed --class=AgentSeeder
 */
class AgentSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('AGENT_PASSWORD', 'password');

        $agents = [
            ['name' => 'Agente Uno',   'email' => 'agente1@landra.test'],
            ['name' => 'Agente Dos',   'email' => 'agente2@landra.test'],
        ];

        $zones = Zone::where('status', 'activa')->pluck('id');

        foreach ($agents as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make($password),
                    'status' => UserStatus::Active,
                ],
            );

            if (! $user->hasRole('agente')) {
                $user->assignRole('agente');
            }

            // Asignar todas las zonas activas al agente.
            if ($zones->isNotEmpty()) {
                $user->zones()->syncWithoutDetaching($zones->toArray());
            }

            $label = $user->wasRecentlyCreated ? 'creado' : 'ya existía';
            $this->command?->info("Agente {$label}: {$data['email']} | password: {$password}");
        }
    }
}
