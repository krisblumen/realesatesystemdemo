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
 * Datos ficticios para pruebas en el panel (demo_db).
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

        // Las zonas NO se fabrican acá: se usan las que ya sembró ZoneSeeder.
        //
        // Antes este sembrador creaba cuatro zonas propias y MORÍA en la primera
        // —`Zone.polygon` se castea a MultiPolygon y acá se pasaba un Polygon—
        // dejando 5 usuarios y cero inmuebles. Quedó viejo cuando cambió el
        // modelo geográfico.
        //
        // El arreglo no es envolver los polígonos. Es dejar de fabricar zonas
        // que la aplicación nunca fabricaría: las de ZoneSeeder llevan municipio
        // y código postal, como las que salen del panel. Una zona de muestra que
        // no se parece a una real hace que el demo enseñe algo que no existe —y
        // ya nos pasó que una factory así dejara pasar un bug a producción con
        // la suite en verde.
        $zones = Zone::query()->where('status', ZoneStatus::Active)->get();

        if ($zones->isEmpty()) {
            $this->command?->error('No hay zonas activas. Corré ZoneSeeder antes que este sembrador.');

            return;
        }

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

        $this->command?->info('Demo: 5 agentes, '.$zones->count().' zona(s) existentes, 10 propietarios, 20 inmuebles.');
    }
}
