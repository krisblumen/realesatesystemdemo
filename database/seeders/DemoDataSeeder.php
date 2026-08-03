<?php

namespace Database\Seeders;

use App\Enums\PropertyStatus;
use App\Enums\ZoneStatus;
use App\Models\Property;
use App\Models\PropertyOwner;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Database\Seeder;

/**
 * Datos ficticios para pruebas en el panel (inmo_db).
 * Crea agentes, zonas, propietarios e inmuebles de demostración.
 *
 * Uso: php artisan db:seed --class=DemoDataSeeder
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // 5 agentes activos con rol "agente".
        $agents = User::factory()
            ->count(5)
            ->withRole('agente')
            ->active()
            ->create();

        // 4 zonas con polígono válido (sin la columna eliminada "municipality").
        $polygons = [
            'SRID=4326;POLYGON((-100.40 20.60, -100.30 20.60, -100.30 20.70, -100.40 20.70, -100.40 20.60))',
            'SRID=4326;POLYGON((-100.45 20.55, -100.38 20.55, -100.38 20.62, -100.45 20.62, -100.45 20.55))',
            'SRID=4326;POLYGON((-100.32 20.58, -100.25 20.58, -100.25 20.66, -100.32 20.66, -100.32 20.58))',
            'SRID=4326;POLYGON((-100.50 20.50, -100.42 20.50, -100.42 20.58, -100.50 20.58, -100.50 20.50))',
        ];

        $zones = collect($polygons)->map(fn (string $ewkt, int $i): Zone => Zone::create([
            'name' => 'Zona Demo '.($i + 1),
            'description' => 'Zona de prueba '.($i + 1),
            'status' => ZoneStatus::Active,
            'polygon' => $ewkt,
        ]));

        // 10 propietarios repartidos entre los agentes (2 por agente).
        $ownersByAgent = [];

        $agents->each(function (User $agent) use (&$ownersByAgent): void {
            $ownersByAgent[$agent->id] = PropertyOwner::factory()
                ->count(2)
                ->create(['agent_id' => $agent->id]);
        });

        // 20 inmuebles en borrador, cada uno con agente, zona y propietario del mismo agente.
        for ($i = 0; $i < 20; $i++) {
            $agent = $agents->random();
            $owner = $ownersByAgent[$agent->id]->random();

            Property::factory()->create([
                'agent_id' => $agent->id,
                'zone_id' => $zones->random()->id,
                'owner_id' => $owner->id,
                'commission_percentage' => 5.0,
                'status' => PropertyStatus::Borrador,
            ]);
        }

        $this->command?->info('Demo: 5 agentes, 4 zonas, 10 propietarios, 20 inmuebles.');
    }
}
