<?php

namespace App\Services\Frontend\Media;

use App\Models\FrontendService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * La estrategia de promoción de `FrontendService.image` (Épica 12.3 §2, §3).
 *
 * Su única diferencia con la de páginas es el predicado, y no es un detalle: en
 * páginas «vigente» significa «lo nombra la revisión publicada», porque hay
 * borrador y publicación. En servicios **no hay dos estados** —guardar es
 * publicar (estrategia A, enmienda C-G-1)—, así que preguntar por una revisión
 * publicada no es una consulta difícil, es una consulta sin sentido.
 *
 * El equivalente exacto es la columna:
 *
 * > un uuid está vigente si es el `image_media_id` de un servicio VIVO.
 *
 * Todo lo demás —copiar, verificar, voltear el disco, la máquina de estados, la
 * idempotencia por lock— lo pone el pipeline común.
 */
class ServiceMediaReference implements PromotableMediaOwner
{
    /** La única colección de media de un servicio. */
    public const COLLECTION = 'image';

    public function modelType(): string
    {
        return (new FrontendService)->getMorphClass();
    }

    public function collection(): string
    {
        return self::COLLECTION;
    }

    /**
     * La cadena `service (withTrashed) → media` (§3.3).
     *
     * Es más corta que la de páginas porque la jerarquía tiene un nivel menos, y
     * son jerarquías DISJUNTAS: una media pertenece a una sección o a un
     * servicio, nunca a las dos, así que ningún actor toma ambas y no hay ciclo
     * posible entre los dos órdenes.
     *
     * `withTrashed()`: un servicio dado de baja sigue siendo el dueño legítimo de
     * su archivo. El lock es de propiedad, no de vigencia — la vigencia la
     * decide el predicado, que sí excluye a los borrados.
     */
    public function acquireLockChain(string $uuid): MediaLockChain
    {
        // La sintaxis es parte de la frontera (§7.10): `media.uuid` es una
        // columna uuid NATIVA de PostgreSQL, y consultarla con una cadena mal
        // formada lanza SQLSTATE 22P02 — una excepción, no un «no encontrado».
        if (! Str::isUuid($uuid)) {
            return MediaLockChain::none();
        }

        // Descubrimiento SIN lock de qué filas bloquear. El dueño de una media es
        // inmutable, así que esto no puede elegir el objetivo equivocado, y
        // hacerlo primero es lo que permite tomar los locks en el orden exacto.
        $media = Media::query()->where('uuid', $uuid)->first();

        if ($media === null || $media->model_type !== $this->modelType()) {
            return MediaLockChain::none();
        }

        // 1. service
        $lockedService = FrontendService::withTrashed()
            ->whereKey($media->model_id)
            ->lockForUpdate()
            ->first();

        if ($lockedService === null) {
            return MediaLockChain::none();
        }

        // 2. media
        $lockedMedia = Media::query()->where('uuid', $uuid)->lockForUpdate()->first();

        return new MediaLockChain($lockedService, $lockedMedia);
    }

    /**
     * EL predicado. Se evalúa releyendo la columna del servicio YA BLOQUEADO:
     * entre que se encoló el job y que corre, el owner pudo cambiar la foto, y
     * promover la anterior dejaría pública una imagen que ya nadie referencia.
     *
     * Un servicio soft-deleted no está vigente: `$lockedOwner->trashed()` lo
     * excluye aunque siga siendo el dueño de la media.
     */
    public function isReferencedByLiveContent(string $uuid, Model $lockedOwner): bool
    {
        if (! Str::isUuid($uuid) || ! $lockedOwner instanceof FrontendService) {
            return false;
        }

        return ! $lockedOwner->trashed() && $lockedOwner->image_media_id === $uuid;
    }

    /**
     * Media `pending` de servicios que ninguna columna viva referencia: lo que
     * queda cuando el owner cambia la foto antes de que corra el job.
     *
     * @return iterable<Media>
     */
    public function danglingPending(): iterable
    {
        $candidates = Media::query()
            ->where('model_type', $this->modelType())
            ->where('collection_name', self::COLLECTION)
            ->where('custom_properties->'.MediaPromotionState::PENDING, true)
            ->cursor();

        foreach ($candidates as $media) {
            // Acá NO hay lock: la reconciliación es un barrido, y quien actúe
            // sobre cada fila vuelve a tomar la cadena y a revalidar.
            $vigente = FrontendService::query()
                ->where('image_media_id', (string) $media->uuid)
                ->exists();

            if (! $vigente) {
                yield $media;
            }
        }
    }

    /** @return array<string, scalar|null> */
    public function logContext(Model $owner): array
    {
        return $owner instanceof FrontendService
            ? ['entity' => "service:{$owner->service_type_code}"]
            : ['entity' => 'service:?'];
    }
}
