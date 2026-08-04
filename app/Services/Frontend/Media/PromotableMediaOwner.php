<?php

namespace App\Services\Frontend\Media;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Lo ÚNICO que difiere entre dueños de media promovible (Épica 12.3 §3.1c).
 *
 * El pipeline —copiar, verificar, voltear el disco, idempotencia por lock— es
 * uno solo y vive en `PromoteFrontendMedia`. La máquina de estados es una sola
 * y vive en `MediaPromotionState`. Lo que cambia por dueño es qué significa
 * «vigente» y cómo se llega a él:
 *
 * - una sección de página está vigente si su uuid aparece en la **revisión
 *   publicada**, y su cadena es `page → section → media`;
 * - un servicio está vigente si su uuid **es la columna `image_media_id`** de
 *   una fila viva, y su cadena es `service → media`.
 *
 * Preguntar «¿está en la revisión publicada?» sobre un servicio no es una
 * consulta difícil: es una consulta sin sentido, porque en servicios guardar es
 * publicar y no hay revisiones. Por eso el predicado se abstrae y el mecanismo
 * no.
 */
interface PromotableMediaOwner
{
    /**
     * El morph configurado del dueño. Se pide como método y no se hardcodea un
     * FQCN porque el proyecto puede tener morph map: el string correcto es el
     * que devuelve `getMorphClass()`, igual que en `FrontendMediaReference`.
     */
    public function modelType(): string;

    /** La colección que esta estrategia promueve. */
    public function collection(): string;

    /**
     * Toma la cadena de locks de este dueño, en su orden declarado, y devuelve
     * el dueño de más arriba junto con la media. El llamador ya debe estar
     * dentro de una transacción.
     */
    public function acquireLockChain(string $uuid): MediaLockChain;

    /**
     * ¿El uuid sigue referenciado por el contenido VIGENTE de su dueño?
     *
     * Recibe el dueño YA BLOQUEADO a propósito: releerlo bajo el lock es lo que
     * impide promover una imagen que dejó de ser la actual mientras se copiaba.
     */
    public function isReferencedByLiveContent(string $uuid, Model $lockedOwner): bool;

    /**
     * Filas `pending` de este dueño que ningún contenido vigente referencia —
     * lo que queda cuando se suelta una referencia antes de que corra el job.
     * La reconciliación las limpia.
     *
     * @return iterable<Media>
     */
    public function danglingPending(): iterable;

    /**
     * Contexto para el log de una promoción: IDENTIDAD del dueño, nunca
     * contenido editorial (RFC-077).
     *
     * @return array<string, scalar|null>
     */
    public function logContext(Model $owner): array;
}
