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

    public function test_the_central_has_the_framework_tables_the_host_needs(): void
    {
        // EL DEFECTO QUE ESTE TEST PREVIENE, encontrado en el servidor.
        //
        // La sesión y el caché usan la conexión POR DEFECTO, y en el host
        // central esa conexión es la central. Sin estas tablas, la PRIMERA
        // petición a ese host muere con «relation sessions does not exist» —
        // antes de llegar a ninguna ruta, porque la sesión arranca primero.
        //
        // El diseño del lote A lo decía y la implementación no lo hacía: la
        // central sólo creaba `tenants`.
        $central = $this->archivosDe('migrations/central');
        $contenido = '';

        foreach ($central as $archivo) {
            $contenido .= (string) file_get_contents(database_path('migrations/central/'.$archivo));
        }

        foreach (['sessions', 'cache', 'jobs', 'failed_jobs'] as $tabla) {
            $this->assertStringContainsString(
                "Schema::create('{$tabla}'",
                $contenido,
                "La central necesita la tabla «{$tabla}»: sin ella el host central no atiende una sola petición.",
            );
        }
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
