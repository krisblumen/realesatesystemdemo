<?php

namespace App\Services\Frontend\Media;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * La máquina de estados de promoción: `draft → pending → promoted`.
 *
 * Extraída de `PublishedMediaReference` (Épica 12.3 §3.1b) con los cuerpos
 * TEXTUALES: depende únicamente de `custom_properties`, así que es idéntica
 * para cualquier dueño y no había razón para que viviera dentro de la clase que
 * sabe de páginas. `PublishedMediaReference` conserva sus métodos públicos
 * delegando acá, de modo que las pruebas de 12.1-A/B siguen valiendo sin
 * tocarse — ese es el criterio de aceptación de la extracción.
 *
 * Las tres invariantes que sostiene, y que ningún llamador puede relajar:
 *
 * 1. `promoted` es TERMINAL: una media ya pública no vuelve a `pending`.
 * 2. `promoted` ⇒ sin `pending`.
 * 3. `pending` ⇒ referenciada por el contenido vigente de su dueño (eso lo
 *    verifica la estrategia, no esta clase).
 */
class MediaPromotionState
{
    /** Referenciada por el contenido vigente, todavía no copiada al disco público. */
    public const PENDING = 'pending_promotion';

    /** Copiada, verificada y volteada al disco público. Estado TERMINAL. */
    public const PROMOTED = 'promoted';

    /** La revisión que autorizó la promoción (sólo observabilidad). */
    public const AUTHORIZING_REVISION = 'promotion_revision';

    public function isPromoted(Media $media): bool
    {
        return $media->getCustomProperty(self::PROMOTED) === true;
    }

    public function isPending(Media $media): bool
    {
        return $media->getCustomProperty(self::PENDING) === true;
    }

    /**
     * `draft → pending`. El llamador DEBE tener el lock de la fila: estos
     * helpers leen-modifican-escriben `custom_properties`, así que operar sobre
     * un modelo cargado antes del lock pisaría a un escritor concurrente (§7.9 —
     * mezclar, nunca sobrescribir el JSON entero).
     */
    public function markPending(Media $lockedMedia, int $authorizingRevision): void
    {
        // Invariante 1: `promoted` es terminal — republicar media que ya es
        // pública no debe resucitar un flag de pendiente.
        if ($this->isPromoted($lockedMedia)) {
            return;
        }

        $lockedMedia->setCustomProperty(self::PENDING, true);
        $lockedMedia->setCustomProperty(self::AUTHORIZING_REVISION, $authorizingRevision);
        $lockedMedia->save();
    }

    /** `pending → draft`. Se usa cuando se suelta la referencia (§7.8). */
    public function clearPending(Media $lockedMedia): void
    {
        if (! $lockedMedia->hasCustomProperty(self::PENDING)) {
            return;
        }

        $lockedMedia->forgetCustomProperty(self::PENDING);
        $lockedMedia->save();
    }

    /** `pending → promoted`. Pone el flag terminal y saca el de pendiente. */
    public function markPromoted(Media $lockedMedia): void
    {
        $lockedMedia->setCustomProperty(self::PROMOTED, true);
        $lockedMedia->forgetCustomProperty(self::PENDING);
        $lockedMedia->save();
    }
}
