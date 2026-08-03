<?php

namespace App\Services\Contratos;

use App\Models\ContratoEvento;
use App\Models\ContratoIntermediacion;
use App\Models\User;

/**
 * Resuelve el contexto (IP, user-agent) de un evento de auditoría según HTTP o CLI y
 * lo pasa explícito al modelo — evita acoplar el modelo a request() (hallazgo Mn-2).
 * En consola request() existe pero ip()/userAgent() son null, que es el valor correcto.
 */
class ContratoEventoService
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function registrar(ContratoIntermediacion $contrato, string $tipo, ?User $actor = null, array $meta = []): ContratoEvento
    {
        return $contrato->registrarEvento($tipo, $actor, $this->contextoHttp($meta));
    }

    /**
     * Contexto para eventos disparados dentro de una petición HTTP.
     *
     * @param  array<string, mixed>  $meta
     * @return array{ip: ?string, user_agent: ?string, meta: array<string, mixed>}
     */
    public function contextoHttp(array $meta = []): array
    {
        return [
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'meta' => $meta,
        ];
    }
}
