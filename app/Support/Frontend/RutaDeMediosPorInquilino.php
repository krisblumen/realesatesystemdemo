<?php

namespace App\Support\Frontend;

use App\Tenancy\GeneradorDeSlug;
use App\Tenancy\InquilinoActual;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\DefaultPathGenerator;

/**
 * Antepone el inquilino a la ruta física de cada archivo.
 *
 * La librería de medios deriva la ruta del identificador de la fila, y con una
 * base por inquilino esos identificadores ARRANCAN EN 1 EN CADA BASE. Dos
 * inquilinos suben su primera imagen y los dos escriben en `1/`. El disco es uno
 * solo: se pisan.
 *
 * La base de datos no protege de esto por la misma razón que no protege el
 * caché — el disco es otro almacén.
 *
 * ES `path_generator` Y NO `url_generator`: la pieza que decide dónde se ESCRIBE
 * el archivo es esta. Cambiar sólo el generador de URL haría que las
 * direcciones se vean distintas y el disco colisione igual.
 *
 * ESTO RESUELVE COLISIÓN, NO CONFIDENCIALIDAD. La media publicada vive en el
 * disco `public` y el servidor web la sirve sin pasar por Laravel: quien tenga
 * la URL la abre. Ese límite está aceptado por escrito en RFC-14 y el comando de
 * invitación lo advierte. No hay que suponer que esta clase lo cubre.
 */
class RutaDeMediosPorInquilino extends DefaultPathGenerator
{
    protected function getBasePath(Media $media): string
    {
        $base = parent::getBasePath($media);

        $slug = app(InquilinoActual::class)->slug();

        if ($slug === null) {
            return $base;
        }

        // Se valida aunque venga del padrón y ya esté validado en el alta: la
        // validación va pegada al uso, porque el segundo camino hasta acá lo va
        // a escribir alguien que no leyó esto. Y acá el valor termina siendo una
        // ruta de disco.
        return 'tenants/'.GeneradorDeSlug::validar($slug).'/'.$base;
    }
}
