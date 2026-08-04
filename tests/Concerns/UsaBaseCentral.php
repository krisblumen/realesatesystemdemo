<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Prepara la base central para un test, sin tocar la del inquilino.
 *
 * NO usa `RefreshDatabase`, y no es por gusto: `RefreshDatabase` envuelve la
 * conexión POR DEFECTO, que en esta épica cambia a mitad de petición para
 * apuntar al inquilino. Además el andamiaje de inquilinos necesita crear bases
 * de verdad, y `CREATE DATABASE` no puede correr dentro de una transacción.
 *
 * Acá la central se migra una vez por proceso y cada test se envuelve en su
 * propia transacción sobre esa conexión.
 */
trait UsaBaseCentral
{
    private static bool $centralMigrada = false;

    protected function setUpUsaBaseCentral(): void
    {
        if (! self::$centralMigrada) {
            $this->crearBaseCentralSiFalta();

            $this->artisan('migrate:fresh', [
                '--database' => 'central',
                '--path' => 'database/migrations/central',
                '--force' => true,
            ])->run();

            self::$centralMigrada = true;
        }

        DB::connection('central')->beginTransaction();
    }

    protected function tearDownUsaBaseCentral(): void
    {
        DB::connection('central')->rollBack();
    }

    /**
     * La suite se prepara sola.
     *
     * Un «database does not exist» al arrancar los tests manda a depurar el
     * lugar equivocado: parece un problema del código y es una base que nunca
     * se creó. Se usa la conexión de mantenimiento porque `CREATE DATABASE` no
     * puede correr sobre la base que se está por crear.
     */
    private function crearBaseCentralSiFalta(): void
    {
        $nombre = config('database.connections.central.database');

        $existe = DB::connection('maintenance')
            ->selectOne('SELECT 1 FROM pg_database WHERE datname = ?', [$nombre]);

        if ($existe === null) {
            DB::connection('maintenance')->statement('CREATE DATABASE '.$this->citarIdentificador($nombre));
        }
    }

    private function citarIdentificador(string $nombre): string
    {
        // El nombre viene de configuración, no de una petición, pero se cita
        // igual: es la misma regla que en el alta de un inquilino, y las reglas
        // que tienen excepciones se olvidan en la excepción.
        return '"'.str_replace('"', '""', $nombre).'"';
    }
}
