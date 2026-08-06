<?php

namespace App\Support\Frontend;

use App\Tenancy\GeneradorDeSlug;
use App\Tenancy\InquilinoActual;
use InvalidArgumentException;
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
    /**
     * Valida el nombre de la plantilla ANTES de que sea una ruta de disco.
     *
     * NO se reusa `GeneradorDeSlug::validar()`: valida el formato de un slug de
     * inquilino —minúsculas y dígitos, sin guiones bajos— y una plantilla se
     * llama `demo_template_v3`. Reusarla habría reventado en la primera
     * construcción.
     *
     * La regla propia es la del nombre de una base de Postgres, que es lo que
     * esto es. Lo que importa es lo que RECHAZA: cualquier cosa con `/` o `..`
     * saldría del directorio de medios.
     */
    private static function nombreDePlantillaSeguro(string $nombre): string
    {
        if (preg_match('/^[a-z][a-z0-9_]{0,62}$/', $nombre) !== 1) {
            throw new InvalidArgumentException("Nombre de plantilla inválido para una ruta: «{$nombre}».");
        }

        return $nombre;
    }

    protected function getBasePath(Media $media): string
    {
        $base = parent::getBasePath($media);

        $slug = app(InquilinoActual::class)->slug();

        if ($slug === null) {
            // Sin inquilino, pero puede haber PLANTILLA: construirla es un
            // proceso de consola, y sus archivos necesitan su propio lugar o
            // caen en `1/` junto a los de cualquier otro proceso sin inquilino.
            // El alta después los copia a `tenants/{slug}/`.
            $plantilla = (string) config('tenancy.medios_de_plantilla', '');

            return $plantilla === ''
                ? $base
                : 'plantillas/'.self::nombreDePlantillaSeguro($plantilla).'/'.$base;
        }

        // Se valida aunque venga del padrón y ya esté validado en el alta: la
        // validación va pegada al uso, porque el segundo camino hasta acá lo va
        // a escribir alguien que no leyó esto. Y acá el valor termina siendo una
        // ruta de disco.
        return 'tenants/'.GeneradorDeSlug::validar($slug).'/'.$base;
    }
}
