<?php

use App\Models\FrontendService;
use App\Services\Frontend\Media\MediaPromotionState;
use App\Services\Frontend\Media\ServiceMediaReference;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Reconoce como `promoted` las imágenes de servicios que YA se sirven en público
 * (Épica 12.3 §7).
 *
 * **No mueve ni un archivo.** Una imagen que hoy está en el disco público y es
 * el `image_media_id` de un servicio vivo cumple exactamente la definición de
 * `promoted`: copiada, verificada y sirviéndose. Marcarla reconoce el estado en
 * el que ya está; moverla sería inventar trabajo con riesgo de romper URLs vivas
 * para llegar al mismo lugar.
 *
 * Tres caminos, y el del medio es el que la primera versión del diseño no tenía:
 *
 * 1. Es la columna vigente **y el archivo existe con tamaño > 0** → `promoted`.
 * 2. Es la columna vigente **pero el archivo falta o mide 0** → NO se marca y se
 *    registra. Fail-closed: el servicio cae al fallback antes que servir una URL
 *    rota. Verificar antes de marcar es la garantía que el job ya tiene y que la
 *    migración no heredaba.
 * 3. No es la columna vigente → se deja TAL CUAL, sin flags. Es una versión
 *    superada; marcarla `promoted` la volvería intocable para siempre.
 *
 * Forward-only e idempotente: una segunda corrida no cambia nada, porque
 * `markPromoted` sobre una fila ya promovida escribe el mismo valor.
 */
return new class extends Migration
{
    public function up(): void
    {
        $state = new MediaPromotionState;
        $publico = Storage::disk('public');
        $marcadas = 0;
        $omitidas = [];

        $candidatas = Media::query()
            ->where('model_type', (new FrontendService)->getMorphClass())
            ->where('collection_name', ServiceMediaReference::COLLECTION)
            ->where('disk', 'public')
            ->cursor();

        foreach ($candidatas as $media) {
            $vigente = FrontendService::query()
                ->where('image_media_id', (string) $media->uuid)
                ->exists();

            if (! $vigente) {
                continue;
            }

            $ruta = $media->getPathRelativeToRoot();

            if (! $publico->exists($ruta) || $publico->size($ruta) === 0) {
                $omitidas[] = (string) $media->uuid;

                continue;
            }

            // La media va suelta a propósito: es un cambio de metadatos sobre una
            // fila que ya sirve, no una promoción real, y no hay bytes que copiar.
            DB::transaction(fn () => $state->markPromoted($media));
            $marcadas++;
        }

        Log::info('frontend.service_media_backfill', [
            'promoted' => $marcadas,
            'skipped_missing_file' => count($omitidas),
            'skipped_uuids' => $omitidas,
        ]);
    }

    public function down(): void
    {
        // Sin reversa: quitar el flag dejaría de servirse una imagen que hoy es
        // pública y está vigente, que es peor que el estado previo. El disco de
        // esas filas nunca se tocó, así que revertir la migración de esquema no
        // requiere revertir esta.
    }
};
