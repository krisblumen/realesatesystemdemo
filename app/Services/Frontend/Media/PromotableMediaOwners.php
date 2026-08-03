<?php

namespace App\Services\Frontend\Media;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Resuelve `model_type → estrategia` (Épica 12.3 §3.2).
 *
 * **Fail-closed y sin default.** Un `model_type` que nadie declaró NO promueve
 * nada: `for()` devuelve null y el job termina sin tocar la fila. Un default
 * sería exactamente por donde se colaría una promoción indebida — la media de
 * marca de `FrontendSetting`, por ejemplo, comparte tabla y flags con todo lo
 * demás y está deliberadamente fuera de alcance.
 *
 * Las estrategias se registran en el contenedor, así que agregar un dueño
 * nuevo es agregar una implementación y registrarla; nunca tocar el job.
 */
class PromotableMediaOwners
{
    /** @var list<PromotableMediaOwner> */
    private array $owners;

    /** @param list<PromotableMediaOwner> $owners */
    public function __construct(array $owners)
    {
        $this->owners = $owners;
    }

    /** La estrategia de esta media, o null si su tipo/colección no está declarado. */
    public function for(Media $media): ?PromotableMediaOwner
    {
        foreach ($this->owners as $owner) {
            if ($media->model_type === $owner->modelType() && $media->collection_name === $owner->collection()) {
                return $owner;
            }
        }

        return null;
    }

    /**
     * Todas las estrategias, para los barridos que recorren el universo entero
     * (reconciliación y reporte) en vez de una media puntual.
     *
     * @return list<PromotableMediaOwner>
     */
    public function all(): array
    {
        return $this->owners;
    }
}
