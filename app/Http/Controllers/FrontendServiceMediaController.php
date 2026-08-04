<?php

namespace App\Http\Controllers;

use App\Models\FrontendService;
use App\Services\Frontend\FrontendMediaReference;
use App\Services\Frontend\Media\ServiceMediaReference;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * La imagen EN BORRADOR de un servicio, para el panel (Épica 12.3 §8.1).
 *
 * Desde 12.3 la colección `image` vive en el disco privado, así que no existe
 * una URL `/storage` hasta que una promoción la copia. Sin esta ruta, el
 * uploader del panel mostraría una imagen rota justo cuando el owner la acaba
 * de subir.
 *
 * Calcado de `FrontendSectionMediaController`, y a propósito: dos controladores
 * con la misma responsabilidad y distinto criterio de autorización es cómo
 * aparece el hueco que nadie revisa.
 *
 * Toda falla responde **404, uniformemente** — anónimo, autenticado sin
 * permiso, servicio inexistente, uuid ajeno y uuid mal formado son
 * indistinguibles desde afuera. Un 403 donde va un 404 confirma que el recurso
 * existe, que es exactamente lo que un preview privado no debe hacer.
 */
class FrontendServiceMediaController extends Controller
{
    public function __construct(private readonly FrontendMediaReference $references) {}

    public function __invoke(FrontendService $service, string $uuid): BinaryFileResponse
    {
        abort_unless(Auth::check(), 404);
        abort_unless(Gate::allows('view', $service), 404);

        // El uuid debe estar bien formado Y pertenecer a la colección `image` de
        // ESTE servicio. resolve() rechaza un uuid mal formado antes de tocar la
        // columna uuid nativa (§7.10), así que basura en la URL es un 404 y
        // nunca un SQLSTATE 22P02.
        $media = $this->references->resolve($uuid, $service, ServiceMediaReference::COLLECTION);

        abort_if($media === null, 404);

        // Bytes servidos INLINE desde el disco privado — nunca una URL pública,
        // que sería un preview que sólo funciona cuando ya no hace falta.
        return response()->file($media->getPath());
    }
}
