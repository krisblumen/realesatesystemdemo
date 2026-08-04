<?php

namespace Database\Factories;

use App\Enums\ZoneStatus;
use App\Models\Country;
use App\Models\Municipality;
use App\Models\State;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<Zone>
 */
class ZoneFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $geo = $this->geoDefaults();

        return [
            'name' => fake()->unique()->city(),
            'slug' => null,
            'description' => fake()->optional()->sentence(),
            'state_id' => $geo['state_id'],
            'municipality_id' => $geo['municipality_id'],
            'postal_code' => fake()->optional()->numerify('#####'),
            'status' => ZoneStatus::Active,
            'polygon' => 'SRID=4326;MULTIPOLYGON(((-100.40 20.60, -100.30 20.60, -100.30 20.70, -100.40 20.70, -100.40 20.60)))',
            'center_point' => null,
        ];
    }

    /**
     * Toda zona nace con su CP en el PIVOTE, no sólo en la columna.
     *
     * En la aplicación no existe una zona sin pivote: la migración
     * `2026_07_04_000000_zones_support_multiple_postal_codes` lo pobló para las
     * que ya había, y crear o editar una zona pasa por `syncPostalCodes()`. La
     * factory era el único lugar que producía zonas a medias, y eso hacía que
     * las pruebas mintieran en los dos sentidos: dejaban pasar código que sólo
     * mira la columna, y hacían fallar al que consulta el pivote —que es la
     * fuente de verdad—.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Zone $zone): void {
            if (blank($zone->postal_code)) {
                return;
            }

            DB::table('zone_postal_code')->insertOrIgnore([
                'zone_id' => $zone->getKey(),
                'postal_code' => $zone->postal_code,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ZoneStatus::Inactive,
        ]);
    }

    public function withPolygon(): static
    {
        return $this->state(fn (array $attributes) => [
            'polygon' => 'SRID=4326;MULTIPOLYGON(((-100.40 20.60, -100.30 20.60, -100.30 20.70, -100.40 20.70, -100.40 20.60)))',
        ]);
    }

    /**
     * @return array{state_id: int, municipality_id: int}
     */
    private function geoDefaults(): array
    {
        $state = State::query()
            ->whereHas('municipalities')
            ->with('municipalities')
            ->orderBy('id')
            ->first();

        if ($state === null) {
            $country = Country::query()->firstOrCreate(
                ['name' => 'México'],
                ['iso2' => 'MX', 'clave' => 'MEX'],
            );

            $state = State::query()->firstOrCreate(
                ['country_id' => $country->id, 'name' => 'Querétaro'],
                ['clave' => 'QUE', 'source_id' => 40],
            );
        }

        $municipality = $state->municipalities->first()
            ?? Municipality::query()->firstOrCreate(
                ['state_id' => $state->id, 'name' => $state->name],
                ['source_id' => null],
            );

        return [
            'state_id' => $state->id,
            'municipality_id' => $municipality->id,
        ];
    }
}
