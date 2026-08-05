<?php

namespace App\Services\Contratos;

use App\Enums\EstadoContrato;
use App\Enums\OrigenAccesoContrato;
use App\Models\ContratoIntermediacion;
use App\Models\User;
use App\Notifications\ContratoEnlaceEnviado;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Envío inicial del contrato al cliente (RFC-065/069). En el Lote C emite el token y
 * transiciona a Enviado; el despacho real de notificaciones (email + wa.me) se conecta
 * en el Lote G. WhatsApp es un enlace wa.me ASISTIDO: no existe WhatsApp Business API
 * (RFC-044 es solo tracking) — decisión D-9 / P-5.
 */
class ContratoEnvioService
{
    public function __construct(
        private readonly ContratoAccesoService $accesos,
        private readonly ContratoEventoService $eventos,
    ) {}

    /**
     * Emite el token inicial y pasa el contrato de Generado a Enviado. Devuelve el token
     * en claro para construir la URL pública. Solo aplica desde Generado (el reenvío desde
     * Rechazado/Expirado vive en ContratoAccesoService::reenviar).
     */
    public function enviar(ContratoIntermediacion $contrato, ?User $actor = null): string
    {
        if ($contrato->estado !== EstadoContrato::Generado) {
            throw ValidationException::withMessages([
                'estado' => 'Solo se puede enviar un contrato recién generado. Para rechazados o expirados, usa reenviar.',
            ]);
        }

        $token = $this->accesos->emitir($contrato, OrigenAccesoContrato::Inicial);

        $contrato->transicionarA(EstadoContrato::Enviado, $actor, $this->eventos->contextoHttp());

        $this->notificarCliente($contrato, $token);

        return $token;
    }

    /** Envía el enlace al cliente por email (on-demand). WhatsApp es wa.me asistido (D-9). */
    public function notificarCliente(ContratoIntermediacion $contrato, string $token): void
    {
        if ($contrato->cliente_email) {
            Notification::route('mail', $contrato->cliente_email)
                ->notify(new ContratoEnlaceEnviado($contrato, $token));
        }
    }

    /** URL pública del formulario para un token dado. */
    public function urlPublica(string $token): string
    {
        return route('contratos.publico.show', ['token' => $token]);
    }

    /**
     * Enlace wa.me prellenado (envío asistido por el agente) con folio + URL del token.
     * No es un envío automático: Landra no tiene WhatsApp Business API (D-9 / DIF-2).
     */
    public function whatsappLink(ContratoIntermediacion $contrato, string $tokenUrl): string
    {
        $texto = rawurlencode("Hola {$contrato->cliente_nombre}, aquí está tu contrato Landra (folio {$contrato->folio}): {$tokenUrl}");
        $telefono = preg_replace('/\D/', '', (string) $contrato->cliente_telefono);

        return "https://wa.me/{$telefono}?text={$texto}";
    }
}
