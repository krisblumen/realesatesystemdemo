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

        // Filtra tres cosas del prólogo de `pg_dump`:
        //
        // 1. Comandos de psql (`\restrict`, etc.), que no son SQL.
        // 2. `set_config('search_path')`, que rompe el schema path de las
        //    consultas siguientes de Laravel.
        // 3. LOS `SET` DE TIEMPO DE ESPERA QUE DEPENDEN DE LA VERSIÓN. Este
        //    archivo se generó con un pg_dump moderno y trae
        //    `SET transaction_timeout`, que existe desde PostgreSQL 17. Contra
        //    un servidor 16 —el de producción— la carga muere con
        //    «unrecognized configuration parameter». No son datos ni afectan al
        //    resultado: son preferencias de sesión del volcado.
        $ignorables = [
            'SET transaction_timeout',
            'SET idle_in_transaction_session_timeout',
            'SET statement_timeout',
            'SET lock_timeout',
        ];

        $content = implode("\n", array_filter(
            explode("\n", (string) file_get_contents($sql)),
            function (string $line) use ($ignorables): bool {
                $limpia = ltrim($line);

                foreach ($ignorables as $prefijo) {
                    if (str_starts_with($limpia, $prefijo)) {
                        return false;
                    }
                }

                return ! str_starts_with($limpia, '\\')
                    && ! str_contains($line, 'set_config(\'search_path\'');
            },
        ));

        DB::unprepared($content);

        // Restaura el search_path para las queries subsiguientes de Laravel.
        DB::statement('SET search_path TO public');

        $count = DB::table('postal_code_areas')->count();
        $this->command?->info("PostalCodeAreaSeeder: {$count} áreas cargadas.");
    }
}
