<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Contratos\ContratoAccesoService;
use App\Services\Contratos\ContratoFirmaService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/**
 * Recibe la firma o el rechazo del cliente desde el formulario público (RFC-067). El token
 * se resuelve primero; si es inválido, 410 sin filtrar datos. La validación del payload
 * ocurre dentro del servicio ANTES de consumir el token (M-2).
 */
class ContratoFirmaController extends Controller
{
    public function firmar(Request $request, string $token, ContratoAccesoService $accesos, ContratoFirmaService $firma): View|RedirectResponse|Response
    {
        $acceso = $accesos->resolver($token);

        if ($acceso === null) {
            return response()->view('public.contratos.invalido', ['motivo' => 'invalido'], 410);
        }

        try {
            $contrato = $firma->firmar($acceso, [
                'firma_png_base64' => (string) $request->input('firma'),
                'privacidad_aceptada' => $request->boolean('privacidad'),
                'cliente' => (array) $request->input('cliente', []),
                'identificacion_anverso_base64' => $request->input('identificacion_anverso'),
                'identificacion_reverso_base64' => $request->input('identificacion_reverso'),
            ]);
        } catch (ValidationException $e) {
            // Payload inválido: el token NO se consumió (M-2). Vuelve al formulario con errores.
            return back()->withErrors($e->errors())->withInput();
        }

        return view('public.contratos.completado', ['contrato' => $contrato, 'accion' => 'firmado']);
    }

    public function rechazar(Request $request, string $token, ContratoAccesoService $accesos, ContratoFirmaService $firma): View|Response
    {
        $acceso = $accesos->resolver($token);

        if ($acceso === null) {
            return response()->view('public.contratos.invalido', ['motivo' => 'invalido'], 410);
        }

        $contrato = $firma->rechazar($acceso, $request->input('motivo'));

        return view('public.contratos.completado', ['contrato' => $contrato, 'accion' => 'rechazado']);
    }
}
