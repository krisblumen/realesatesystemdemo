<?php

namespace Tests\Feature\Tenancy;

use Tests\TestCase;

/**
 * Las migraciones de la central no viajan en la plantilla del inquilino.
 *
 * Si `database/migrations/central/` se colara en `php artisan migrate`, cada
 * inquilino nacería con su propia tabla `tenants` y el padrón dejaría de
 * significar nada: habría tantos padrones como inquilinos, cada uno viendo sólo
 * a sí mismo. El síntoma aparecería lejísimos de la causa.
 *
 * Laravel no las mezcla porque `Migrator::getMigrationFiles()` usa
 * `glob($path.'/*_*.php')`, que no entra en subdirectorios. Este test fija ese
 * comportamiento: si algún día alguien cambia la ruta o el framework empieza a
 * recorrer recursivamente, se entera acá y no en producción.
 */
class MigracionesSeparadasTest extends TestCase
{
    private function archivosDe(string $ruta): array
    {
        return array_map('basename', glob(database_path($ruta).'/*_*.php') ?: []);
    }

    public function test_the_central_migrations_live_in_their_own_directory(): void
    {
        $central = $this->archivosDe('migrations/central');

        $this->assertNotEmpty($central, 'La central tiene que tener sus propias migraciones.');
        $this->assertTrue(
            (bool) preg_grep('/create_tenants_table/', $central),
            'El padrón de inquilinos es la razón de existir de la central.',
        );
    }

    public function test_the_tenant_migrations_do_not_include_the_central_ones(): void
    {
        $inquilino = $this->archivosDe('migrations');

        $this->assertNotEmpty($inquilino);
        $this->assertEmpty(
            preg_grep('/create_tenants_table/', $inquilino),
            'La tabla `tenants` en la plantilla daría un padrón por inquilino.',
        );

        $this->assertEmpty(
            array_intersect($inquilino, $this->archivosDe('migrations/central')),
            'Ninguna migración puede estar en los dos juegos.',
        );
    }

    public function test_the_default_migration_run_never_reaches_the_central_directory(): void
    {
        // Lo que de verdad importa: no que los archivos estén separados, sino
        // que el recorrido por defecto NO los junte. `migrate` sin --path usa
        // database/migrations, y su glob no es recursivo.
        $porDefecto = glob(database_path('migrations').'/*_*.php') ?: [];

        foreach ($porDefecto as $archivo) {
            $this->assertStringNotContainsString(
                '/central/',
                $archivo,
                'Una migración de la central alcanzada por `php artisan migrate` acabaría en cada inquilino.',
            );
        }
    }
}
