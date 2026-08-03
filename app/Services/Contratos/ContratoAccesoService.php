<?php

namespace App\Services\Contratos;

use App\Enums\EstadoContrato;
use App\Enums\OrigenAccesoContrato;
use App\Models\ContratoAcceso;
use App\Models\ContratoIntermediacion;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Emite, resuelve, consume y renueva los tokens de acceso al formulario público
 * (RFC-064). El token en claro solo se devuelve para construir la URL; en BD vive su
 * hash SHA-256. El consumo es atómico para blindar el "un solo uso" (R-2).
 */
class ContratoAccesoService
{
    public function __construct(private readonly ContratoEventoService $eventos) {}

    /**
     * Emite un token nuevo e invalida cualquier token activo anterior (reenvío).
     * Devuelve el token EN CLARO (nunca se persiste; solo para armar la URL).
     */
    public function emitir(ContratoIntermediacion $contrato, OrigenAccesoContrato $origen = OrigenAccesoContrato::Inicial): string
    {
        $contrato->accesos()->whereNull('usado_at')->update(['usado_at' => now()]);

        $token = Str::random(48);

        $contrato->accesos()->create([
            'token_hash' => hash('sha256', $token),
            'expira_at' => now()->addHours((int) config('contratos.token_ttl_horas', 72)),
            'emitido_por' => $origen->value,
        ]);

        return $token;
    }

    /**
     * Resuelve un token en claro a su acceso vigente (no usado y no expirado), o null.
     */
    public function resolver(string $token): ?ContratoAcceso
    {
        return ContratoAcceso::query()
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('usado_at')
            ->where('expira_at', '>', now())
            ->first();
    }

    /**
     * Consumo atómico del "un solo uso": marca usado_at solo si aún estaba libre.
     * Devuelve true si este llamador ganó la carrera; false si otro ya lo consumió.
     */
    public function consumir(ContratoAcceso $acceso): bool
    {
        return ContratoAcceso::query()
            ->whereKey($acceso->id)
            ->whereNull('usado_at')
            ->update(['usado_at' => now()]) === 1;
    }

    /**
     * Reenvía un contrato en Rechazado o Expirado: conserva el MISMO folio, emite un
     * token nuevo (invalidando el previo) y vuelve a Enviado. Devuelve el token en claro.
     */
    public function reenviar(ContratoIntermediacion $contrato, ?User $actor = null): string
    {
        if (! in_array($contrato->estado, [EstadoContrato::Rechazado, EstadoContrato::Expirado], true)) {
            throw ValidationException::withMessages([
                'estado' => 'Solo se puede reenviar un contrato rechazado o expirado.',
            ]);
        }

        $token = $this->emitir($contrato, OrigenAccesoContrato::Reenvio);

        $this->eventos->registrar($contrato, 'reenviado', $actor);
        $contrato->transicionarA(EstadoContrato::Enviado, $actor, $this->eventos->contextoHttp());

        // Reinicia el ciclo de notificación al cliente (mismo folio, nuevo token).
        app(ContratoEnvioService::class)->notificarCliente($contrato, $token);

        return $token;
    }
}
