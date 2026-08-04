<?php

namespace App\Http\Controllers;

use App\Models\ContratoIntermediacion;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Único camino para ver la media privada de un contrato (identificación, firma, PDF).
 * Vive en disco 'local' (C-2): NO hay URL /storage/... equivalente. Cada colección se
 * autoriza con su método de Policy (M-3): identificación y firma solo Owner.
 */
class ContratoMediaController extends Controller
{
    private const ABILITIES = [
        'identificacion-anverso' => 'verIdentificacion',
        'identificacion-reverso' => 'verIdentificacion',
        'firma' => 'verFirma',
        'documento-final' => 'verDocumentoFinal',
    ];

    public function __invoke(ContratoIntermediacion $contrato, string $coleccion): BinaryFileResponse|Response
    {
        $ability = self::ABILITIES[$coleccion] ?? abort(404);

        Gate::authorize($ability, $contrato);

        $media = $contrato->getFirstMedia($coleccion);

        if ($media === null) {
            abort(404);
        }

        // Bytes servidos desde disco privado — nunca una URL pública.
        return response()->file($media->getPath());
    }
}
