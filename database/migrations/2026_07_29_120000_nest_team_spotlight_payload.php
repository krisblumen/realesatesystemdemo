<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El destacado del equipo pasa de tres claves sueltas a un objeto anidado.
 *
 * `spotlight_title` / `spotlight_body` → `spotlight: {title, body}`.
 *
 * NO ES COSMÉTICO. El destacado ahora puede llevar su propio logo —una división
 * con imagen comercial propia, como el despacho de arquitectura— y todo el
 * pipeline de imágenes encuentra las fotos recorriendo el payload en busca de la
 * clave `media_id`: validación de elegibilidad, promoción al publicar y el
 * reporte de huérfanas. Un `spotlight_media_id` plano habría sido invisible para
 * los tres — nunca se validaba, nunca llegaba al disco público, y el reporte lo
 * habría dado por borrable.
 *
 * Se reescriben los BORRADORES y también los SNAPSHOTS publicados. Los snapshots
 * son lo que el sitio muestra: dejarlos con la forma vieja habría hecho
 * desaparecer el destacado de una página ya publicada hasta la próxima
 * publicación, sin que nadie lo tocara.
 *
 * La vuelta atrás deshace exactamente lo mismo.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->reescribirSecciones(fn (array $payload): array => self::anidar($payload));
        $this->reescribirSnapshots(fn (array $payload): array => self::anidar($payload));
    }

    public function down(): void
    {
        $this->reescribirSecciones(fn (array $payload): array => self::aplanar($payload));
        $this->reescribirSnapshots(fn (array $payload): array => self::aplanar($payload));
    }

    /** @param  callable(array<string, mixed>): array<string, mixed>  $transformar */
    private function reescribirSecciones(callable $transformar): void
    {
        DB::table('frontend_sections')->where('type', 'team')->orderBy('id')
            ->each(function (object $row) use ($transformar): void {
                $payload = json_decode((string) $row->payload, true);

                if (! is_array($payload)) {
                    return;
                }

                DB::table('frontend_sections')->where('id', $row->id)
                    ->update(['payload' => json_encode($transformar($payload))]);
            });
    }

    /**
     * Los snapshots viven dentro del JSON de la página, así que hay que entrar a
     * buscar las secciones de tipo `team` una por una.
     *
     * @param  callable(array<string, mixed>): array<string, mixed>  $transformar
     */
    private function reescribirSnapshots(callable $transformar): void
    {
        DB::table('frontend_pages')->orderBy('id')->each(function (object $row) use ($transformar): void {
            $snapshot = json_decode((string) $row->published_revision, true);

            if (! is_array($snapshot) || ! is_array($snapshot['sections'] ?? null)) {
                return;
            }

            $cambio = false;

            foreach ($snapshot['sections'] as $i => $section) {
                if (($section['type'] ?? null) !== 'team' || ! is_array($section['payload'] ?? null)) {
                    continue;
                }

                $snapshot['sections'][$i]['payload'] = $transformar($section['payload']);
                $cambio = true;
            }

            if ($cambio) {
                DB::table('frontend_pages')->where('id', $row->id)
                    ->update(['published_revision' => json_encode($snapshot)]);
            }
        });
    }

    /** @param  array<string, mixed>  $payload */
    private static function anidar(array $payload): array
    {
        $spotlight = is_array($payload['spotlight'] ?? null) ? $payload['spotlight'] : [];

        foreach (['title', 'body'] as $campo) {
            $plano = "spotlight_{$campo}";

            // El que ya estuviera anidado gana: si se corriera dos veces, no
            // pisa lo nuevo con lo viejo.
            if (isset($payload[$plano]) && ! isset($spotlight[$campo])) {
                $spotlight[$campo] = $payload[$plano];
            }

            unset($payload[$plano]);
        }

        // Un destacado vacío se OMITE: el payload canónico no lleva objetos
        // vacíos, y el render los trataría como contenido.
        if ($spotlight !== []) {
            $payload['spotlight'] = $spotlight;
        }

        return $payload;
    }

    /** @param  array<string, mixed>  $payload */
    private static function aplanar(array $payload): array
    {
        $spotlight = is_array($payload['spotlight'] ?? null) ? $payload['spotlight'] : [];

        foreach (['title', 'body'] as $campo) {
            if (isset($spotlight[$campo])) {
                $payload["spotlight_{$campo}"] = $spotlight[$campo];
            }
        }

        // El logo y su descripción no tienen lugar en la forma vieja: se pierden
        // al volver atrás, y es la única pérdida posible de esta reversión.
        unset($payload['spotlight']);

        return $payload;
    }
};
