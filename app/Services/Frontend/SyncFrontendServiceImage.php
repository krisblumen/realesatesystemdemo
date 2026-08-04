<?php

namespace App\Services\Frontend;

use App\Jobs\PromoteFrontendMedia;
use App\Models\FrontendService;
use App\Services\Frontend\Media\MediaPromotionState;
use App\Services\Frontend\Media\ServiceMediaReference;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * Apunta `image_media_id` a la imagen recién subida y arranca su promoción
 * (Épica 12.3 §4).
 *
 * Vive fuera de la pantalla de Filament por dos razones. La primera es que esta
 * secuencia es el equivalente, para servicios, de lo que el publisher hace para
 * páginas: no es «lo que pasa después de guardar un formulario», es la
 * transición de dominio que decide qué imagen está vigente. La segunda es que
 * así se puede probar sin montar la pantalla.
 *
 * Lo que hacía antes `afterSave()` era apuntar la columna y bumpear la caché.
 * Alcanzaba mientras todo vivía en el disco público; con el disco privado, una
 * imagen que nadie marca y nadie promueve **nunca se vería**.
 *
 * La reconciliación programada es una RED DE SEGURIDAD, no el mecanismo de
 * publicación: si esta secuencia no corre, el sitio queda hasta 15 minutos sin
 * la foto nueva.
 */
class SyncFrontendServiceImage
{
    public function __construct(
        private readonly FrontendMediaReference $references,
        private readonly MediaPromotionState $state,
        private readonly ServiceMediaReference $owner,
    ) {}

    public function __invoke(FrontendService $service): void
    {
        $uuid = null;

        DB::transaction(function () use ($service, &$uuid): void {
            // 1. Lock del servicio. `withTrashed()` porque el lock es de
            //    propiedad: un servicio dado de baja sigue siendo dueño de su
            //    media aunque su contenido no esté vigente.
            $locked = FrontendService::withTrashed()
                ->whereKey($service->getKey())
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                return;
            }

            $anterior = $locked->image_media_id;

            // 2. El candidato que dejó el uploader no destructivo. La colección
            //    acumula versiones —nunca borra—, así que la actual es la última.
            $candidato = $locked->getMedia(ServiceMediaReference::COLLECTION)->last()?->uuid;
            $candidato = $candidato === null ? null : (string) $candidato;

            // 3. Validación de frontera: uuid bien formado, del morph correcto,
            //    de ESTE servicio y de la colección `image`. Inelegible ⇒ null y
            //    el servicio cae al fallback, sin excepción.
            $vigente = $candidato !== null
                && $this->references->isEligible($candidato, $locked, ServiceMediaReference::COLLECTION)
                    ? $candidato
                    : null;

            // 4. La columna, bajo el mismo lock.
            $locked->forceFill(['image_media_id' => $vigente])->saveQuietly();
            $service->setAttribute('image_media_id', $vigente);

            // 5. La nueva pasa a `pending`. `markPending` respeta la invariante 1:
            //    una media ya `promoted` no vuelve atrás.
            if ($vigente !== null && $vigente !== $anterior) {
                $media = Media::query()->where('uuid', $vigente)->lockForUpdate()->first();

                if ($media !== null) {
                    // Servicios no tiene revisiones que citar (guardar es
                    // publicar), así que la revisión autorizante es 0: el campo
                    // es sólo observabilidad y no participa de ninguna decisión.
                    $this->state->markPending($media, 0);
                    $uuid = $vigente;
                }
            }

            // 6. La saliente deja de estar referenciada: si quedó `pending` y no
            //    llegó a promoverse, se le limpia el flag. Es el análogo exacto
            //    de lo que hace el publisher de páginas al soltar una referencia.
            if ($anterior !== null && $anterior !== $vigente) {
                $previa = Media::query()->where('uuid', $anterior)->lockForUpdate()->first();

                if ($previa !== null && ! $this->state->isPromoted($previa)) {
                    $this->state->clearPending($previa);
                }
            }
        });

        // 7. Recién DESPUÉS del commit, y de forma SÍNCRONA: guardar tiene que
        //    dejar la foto visible sin depender de que haya un worker vivo. Un
        //    fallo de copia no puede tumbar un guardado ya confirmado, así que
        //    la media queda `pending` y la reconciliación la levanta.
        if ($uuid !== null) {
            try {
                PromoteFrontendMedia::dispatchSync($uuid);
            } catch (Throwable $e) {
                Log::warning('frontend.media_promotion_deferred', [
                    'media' => $uuid,
                    'entity' => "service:{$service->service_type_code}",
                    'reason' => $e->getMessage(),
                ]);
            }
        }
    }
}
