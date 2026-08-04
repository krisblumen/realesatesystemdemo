<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Orden requerido:
     *   1. Roles y permisos (base para asignar roles a usuarios)
     *   2. Características de inmuebles
     *   3. Catálogo geográfico (estados → municipios → códigos postales)
     *   4. Zonas reales de operación (depende de municipios)
     *   5. Usuario owner
     *   6. Agentes de prueba con credenciales conocidas
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            ServiceTypeSeeder::class,
            FrontendServiceSeeder::class,
            FrontendPageSeeder::class,
            ProjectTypeSeeder::class,
            FeatureSeeder::class,
            GeoCatalogSeeder::class,
            PostalCodeAreaSeeder::class,
            ZoneSeeder::class,
            OwnerSeeder::class,
            AgentSeeder::class,
        ]);
    }
}
