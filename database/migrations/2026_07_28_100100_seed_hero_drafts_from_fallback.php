<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Siembra el borrador de cada hero con el texto que su página YA muestra.
 *
 * El problema que cierra: el hero de las cinco páginas tenía `payload` en null,
 * así que el sitio servía el fallback de configuración —con su antetítulo, su
 * título y su subtítulo— mientras el editor abría **vacío**. Quien entraba veía
 * una página con texto y un formulario en blanco, y escribía lo que veía en el
 * orden en que lo veía: el antetítulo terminaba en «Título» y el título en
 * «Subtítulo», corridos un lugar. No es distracción, es que el formulario no
 * daba de dónde partir.
 *
 * **Las imágenes NO se siembran.** Las del fallback son URLs externas y el
 * payload sólo admite `media_id` de media propia: copiarlas sería guardar una
 * referencia que el schema rechaza. El hero sigue mostrando sus fotos por
 * fallback hasta que el owner suba las suyas, que es exactamente el
 * comportamiento actual.
 *
 * Sólo toca los heroes con `payload` NULL: uno ya editado no se pisa.
 */
return new class extends Migration
{
    public function up(): void
    {
        $fallbacks = (array) config('frontend-sections.hero_fallback');

        foreach ($fallbacks as $pageKey => $fallback) {
            $pageId = DB::table('frontend_pages')->where('key', $pageKey)->value('id');

            if ($pageId === null) {
                continue;
            }

            $payload = collect($fallback)
                ->only(['eyebrow', 'title', 'subtitle', 'logo_enabled', 'logo_size', 'text_align', 'primary_cta', 'secondary_cta'])
                ->filter(fn ($value): bool => $value !== null && $value !== '')
                ->all();

            if (($payload['title'] ?? '') === '') {
                continue;
            }

            // Lo que el schema exige sí o sí, con el mismo valor que usa el render.
            $payload['text_align'] ??= 'left';
            $payload['logo_enabled'] ??= false;
            $payload['logo_size'] ??= 'md';
            $payload['slides'] = [];

            DB::table('frontend_sections')
                ->where('frontend_page_id', $pageId)
                ->where('section_key', 'hero')
                ->whereNull('payload')
                ->update([
                    'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Sin reversa: vaciar el borrador borraría el trabajo que el owner haya
        // hecho encima desde entonces, y el estado previo —un formulario en
        // blanco sobre una página con texto— era justamente el defecto.
    }
};
