<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Carga los polígonos de áreas por código postal (Querétaro).
 * Fuente: exportado con pg_dump desde la base del proyecto de origen, tras
 * correr geo:import-postal-codes. El nombre de aquella base no aplica en este
 * repositorio; los datos ya viajan en el archivo .sql de este directorio.
 *
 * Uso: php artisan db:seed --class=PostalCodeAreaSeeder
 */
class PostalCodeAreaSeeder extends Seeder
{
    public function run(): void
    {
        $sql = base_path('database/seeders/data/postal_code_areas.sql');

        if (! file_exists($sql)) {
            $this->command?->error('Archivo no encontrado: database/seeders/data/postal_code_areas.sql');

            return;
        }

        // Truncate primero para que sea idempotente.
        DB::statement('TRUNCATE TABLE postal_code_areas RESTART IDENTITY CASCADE');

        // Filtra: comandos psql (\restrict, etc.) y set_config(search_path)
        // que rompe el schema path para las queries siguientes de Laravel.
        $content = implode("\n", array_filter(
            explode("\n", (string) file_get_contents($sql)),
            fn (string $line): bool => ! str_starts_with(ltrim($line), '\\')
                && ! str_contains($line, 'set_config(\'search_path\'')
        ));

        DB::unprepared($content);

        // Restaura el search_path para las queries subsiguientes de Laravel.
        DB::statement('SET search_path TO public');

        $count = DB::table('postal_code_areas')->count();
        $this->command?->info("PostalCodeAreaSeeder: {$count} áreas cargadas.");
    }
}
