<?php

namespace App\Http\Controllers;

use App\Models\ContratoIntermediacion;
use App\Services\Contratos\ContratoPdfService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Vista previa del contrato, sin firmar.
 *
 * Sirve para dos cosas que hasta ahora obligaban a mandar el contrato para
 * poder verlo: revisar que los datos estén bien antes de enviarlo, y mostrárselo
 * al cliente en pantalla antes de que firme.
 *
 * SE RENDERIZA AL VUELO Y NO SE GUARDA. El documento del contrato nace una sola
 * vez, al firmar, y con él su hash: dejar un borrador en la colección de media
 * daría dos archivos donde el sistema promete uno, y el reporte de verificación
 * dejaría de poder decir cuál es el bueno.
 *
 * Se autoriza con `view`, la misma capacidad que permite abrir el contrato en el
 * panel: quien puede leer sus datos en pantalla puede leerlos en PDF. No usa
 * `verDocumentoFinal`, que es la del documento SELLADO y es más restrictiva por
 * buenas razones — pero acá no hay documento sellado que proteger.
 */
class ContratoBorradorController extends Controller
{
    public function __invoke(ContratoIntermediacion $contrato, ContratoPdfService $pdf): Response
    {
        Gate::authorize('view', $contrato);

        // `inline` y no `attachment`: el pedido era mostrarlo en pantalla, y una
        // descarga deja copias sueltas de un documento que no vale como contrato.
        return response($pdf->borrador($contrato), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="vista-previa-'.$contrato->folio.'.pdf"',
            // Un borrador refleja los datos DE AHORA: si el navegador lo cachea,
            // el owner corrige el contrato y vuelve a mirarlo, vería el viejo y
            // creería que su corrección no se guardó.
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }
}
