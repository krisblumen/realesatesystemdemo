<?php

namespace App\Tenancy;

use App\Models\Tenant;

/**
 * Lo que devuelve un alta.
 *
 * La contraseña viaja acá EN CLARO y por única vez, porque en la base ya quedó
 * hasheada: si quien invita no la muestra ahora, no hay forma de recuperarla —
 * sólo de regenerarla.
 */
class ResultadoDeAlta
{
    public function __construct(
        public readonly Tenant $tenant,
        public readonly string $password,
    ) {}
}
