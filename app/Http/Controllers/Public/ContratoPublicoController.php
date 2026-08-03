<?php

namespace App\Http\Controllers\Public;

use App\Enums\EstadoContrato;
use App\Http\Controllers\Controller;
use App\Services\Contratos\ContratoAccesoService;
use App\Services\Contratos\ContratoClausuladoService;
use App\Services\Contratos\ContratoEventoService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

/**
 * Formulario público del cliente (RFC-066). Sin login: el acceso se resuelve solo por el
 * token de un solo uso (RFC-064). No expone datos ante token inválido/expirado/usado.
 */
class ContratoPublicoController extends Controller
{
    public function __construct(
        private readonly ContratoAccesoService $accesos,
        private readonly ContratoClausuladoService $clausulado,
        private readonly ContratoEventoService $eventos,
    ) {}

    public function show(string $token): View|Response
    {
        $acceso = $this->accesos->resolver($token);

        if ($acceso === null) {
            // Token inexistente, expirado o ya usado: 410 Gone, sin filtrar datos.
            return response()->view('public.contratos.invalido', ['motivo' => 'invalido'], 410);
        }

        $contrato = $acceso->contrato;

        if ($contrato->estado === EstadoContrato::Cancelado) {
            return response()->view('public.contratos.invalido', ['motivo' => 'cancelado'], 410);
        }

        // Primera apertura: Enviado → Leído (vía la API única de transición — M-1).
        if ($contrato->estado === EstadoContrato::Enviado) {
            $contrato->transicionarA(EstadoContrato::Leido, null, $this->eventos->contextoHttp());
        }

        return view('public.contratos.show', [
            'contrato' => $contrato,
            'token' => $token,
            'clausulaPago' => $this->clausulado->clausulaPago($contrato->tipo_operacion),
            'clausulaExclusividad' => $this->clausulado->clausulaExclusividad($contrato->exclusividad),
            'modalidad' => $this->clausulado->modalidadTexto($contrato->exclusividad),
        ]);
    }
}
