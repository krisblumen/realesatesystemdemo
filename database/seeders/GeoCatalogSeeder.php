<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

/*
 * Geographic catalog source: db_estados/{states,municipalities,cp_queretaro}.xlsx (official
 * INEGI codes), read via phpoffice/phpspreadsheet. Replaces the retired geo:import command,
 * which parsed paises.sql/estados.sql/municipios.sql dumps whose `clave` column was not the
 * real INEGI code (see StateSeeder/MunicipalitySeeder docblocks).
 */
class GeoCatalogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Country::query()->updateOrCreate(
            ['name' => 'México'],
            ['iso2' => 'MX', 'clave' => 'MEX'],
        );

        $this->call([
            StateSeeder::class,
            MunicipalitySeeder::class,
            PostalCodeSeeder::class,
        ]);
    }
}
