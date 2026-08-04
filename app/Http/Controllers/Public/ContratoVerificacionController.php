<?php

namespace App\Http\Controllers\Public;

use App\Enums\EstadoContrato;
use App\Http\Controllers\Controller;
use App\Models\ContratoIntermediacion;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Verificación pública de integridad de un contrato por folio (RFC-068). Respuesta
 * UNIFORME (M-5/P-3): sin subir un PDF, la página no revela si el folio existe o su
 * estatus; solo confirma integridad tras comparar el hash del PDF subido. Nunca expone
 * datos personales del cliente ni del inmueble.
 */
class ContratoVerificacionController extends Controller
{
    public function show(string $folio): View
    {
        return view('public.contratos.verificar', [
            'folio' => $folio,
            'resultado' => null,
        ]);
    }

    public function comparar(Request $request, string $folio): View
    {
        $request->validate([
            'documento' => ['required', 'file', 'mimetypes:application/pdf', 'max:20480'],
        ]);

        $hash = hash('sha256', (string) file_get_contents($request->file('documento')->getRealPath()));

        // withTrashed: un expediente purgado por retención (soft-deleted) conserva folio y
        // hash, así que su PDF firmado sigue siendo verificable por integridad (P-4).
        $contrato = ContratoIntermediacion::withTrashed()
            ->where('folio', $folio)
            ->where('estado', EstadoContrato::Firmado->value)
            ->whereNotNull('documento_hash')
            ->first();

        // Uniforme: folio inexistente y PDF alterado dan el mismo "no coincide".
        $integro = $contrato !== null && hash_equals($contrato->documento_hash, $hash);

        return view('public.contratos.verificar', [
            'folio' => $folio,
            'resultado' => [
                'integro' => $integro,
                'fecha_firma' => $integro ? $contrato->firmado_at : null,
            ],
        ]);
    }
}
