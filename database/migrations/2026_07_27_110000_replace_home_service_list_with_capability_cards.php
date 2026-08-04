<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La sección de la home deja de ser el listado de servicios y pasa a ser «Qué
 * hacemos», con encabezado y tarjetas propias.
 *
 * **Por qué no alcanzaba con cambiar el registro.** `SeedFrontendPages` crea las
 * secciones con `firstOrCreate` por `(page, section_key)`. Si sólo cambiara la
 * clave en config, la fila vieja `services_home` quedaría huérfana —sin tipo
 * canónico, invisible en el panel y presente en la base— y se crearía otra al
 * lado. Esta migración la renombra en su lugar.
 *
 * **Por qué el payload viejo se descarta y se siembra otro.** El tipo cambia de
 * `service_list` —cuyo payload sólo llevaba parámetros de presentación, porque
 * los ítems los resolvía el kernel— a `capability_cards`, que necesita sus
 * propias tarjetas. Ningún campo del viejo significa lo mismo en el nuevo, así
 * que traducirlo sería inventar contenido.
 *
 * En su lugar se siembra el contenido que el sitio publicado ya muestra. Dejarlo
 * en null habría dejado la home sin esa sección hasta que alguien la escribiera
 * de cero; sembrarlo la deja igual que hoy y convierte la tarea del owner en
 * editar, que es mucho más barato que redactar.
 */
return new class extends Migration
{
    public function up(): void
    {
        $home = DB::table('frontend_pages')->where('key', 'home')->value('id');

        if ($home === null) {
            return;
        }

        DB::table('frontend_sections')
            ->where('frontend_page_id', $home)
            ->where('section_key', 'services_home')
            ->update([
                'section_key' => 'what_we_do',
                'type' => 'capability_cards',
                'payload' => json_encode([
                    'eyebrow' => 'QUÉ HACEMOS',
                    'title' => 'Cuatro disciplinas, un solo equipo',
                    'body' => 'Del terreno a la entrega de llaves: arquitectura, construcción, comercialización e inversión bajo un mismo estándar.',
                    'items' => [
                        ['title' => 'Arquitectura', 'description' => 'Diseño a la medida que equilibra estética, función y valor a largo plazo.'],
                        ['title' => 'Construcción', 'description' => 'Ejecución de obra con control de calidad, tiempos y presupuesto.'],
                        ['title' => 'Comercialización', 'description' => 'Vendemos y rentamos tu propiedad con estrategia, foto profesional y leads calificados.'],
                        ['title' => 'Inversión', 'description' => 'Oportunidades opcionadas con potencial de plusvalía en zonas de alto crecimiento.'],
                    ],
                ], JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        $home = DB::table('frontend_pages')->where('key', 'home')->value('id');

        if ($home === null) {
            return;
        }

        DB::table('frontend_sections')
            ->where('frontend_page_id', $home)
            ->where('section_key', 'what_we_do')
            ->update([
                'section_key' => 'services_home',
                'type' => 'service_list',
                'payload' => null,
                'updated_at' => now(),
            ]);
    }
};
