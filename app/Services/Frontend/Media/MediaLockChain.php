<?php

namespace App\Services\Frontend\Media;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * El resultado de tomar la cadena de locks de UNA media (Épica 12.3 §3.1).
 *
 * Existe porque los dueños tienen jerarquías de distinta profundidad: una
 * sección de página cuelga de `page → section → media`, y un servicio de
 * `service → media`. Lo que el job necesita de vuelta es siempre lo mismo —el
 * dueño de más arriba y la media—, así que el tipo lo dice en vez de devolver
 * una tupla que cada llamador desarma a mano.
 *
 * `isComplete()` es la única pregunta que el job hace: si falta cualquiera de
 * los dos, no hay nada que promover y se sale sin tocar la fila.
 */
final class MediaLockChain
{
    public function __construct(
        public readonly ?Model $owner,
        public readonly ?Media $media,
    ) {}

    public function isComplete(): bool
    {
        return $this->owner !== null && $this->media !== null;
    }

    /** Una cadena vacía: la media no existe o su dueño no es de este tipo. */
    public static function none(): self
    {
        return new self(null, null);
    }
}
