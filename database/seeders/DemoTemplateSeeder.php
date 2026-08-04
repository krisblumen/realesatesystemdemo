<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Lo que lleva adentro la plantilla desde la que nace cada inquilino.
 *
 * NO se usa `DatabaseSeeder`. Termina llamando a `OwnerSeeder` y `AgentSeeder`,
 * que crean usuarios con correo fijo y contraseña conocida; una plantilla con
 * ese `owner` adentro hace que cada inquilino nazca con una cuenta que no es de
 * nadie. El `owner` del inquilino se crea en el alta, con contraseña generada.
 *
 * LA LISTA ES ENUMERADA, NO POR DESCARTE. Un sembrador que alguien agregue a
 * `DatabaseSeeder` el año que viene no debe entrar solo en cada inquilino: para
 * que entre acá hay que decidirlo y escribirlo.
 */
class DemoTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // Los roles y permisos tienen que existir ANTES de que el alta cree
            // al `owner` del inquilino.
            PermissionSeeder::class,

            // Catálogo compartido.
            ServiceTypeSeeder::class,
            ProjectTypeSeeder::class,
            FeatureSeeder::class,

            // El CMS: las seis páginas canónicas y los servicios del frontend.
            FrontendServiceSeeder::class,
            FrontendPageSeeder::class,

            // Geografía. Es lo más pesado de la plantilla y por eso conviene que
            // viaje copiada y no se resiembre por inquilino.
            GeoCatalogSeeder::class,
            PostalCodeAreaSeeder::class,

            // Sin zonas no se puede cargar un inmueble.
            ZoneSeeder::class,

            // Contenido de muestra. NO es opcional: un inquilino que abre el
            // panel y ve listas en cero tiene que cargar datos para empezar a
            // mirar, y no lo va a hacer. El demo se juega en el primer minuto.
            DemoDataSeeder::class,
        ]);
    }
}
