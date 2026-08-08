<?php

namespace App\Tenancy;

use App\Models\Tenant;
use Throwable;

/**
 * Lo que devuelve un alta.
 *
 * La contraseña viaja acá EN CLARO y por única vez, porque en la base ya quedó
 * hasheada: si quien invita no la muestra ahora, no hay forma de recuperarla —
 * sólo de regenerarla.
 *
 * El fallo de correo viaja como DATO y no como excepción: el alta salió bien
 * aunque el correo no, y son dos cosas distintas. Quien llamó decide — la
 * consola lo muestra junto al acceso impreso, que sigue sirviendo.
 */
class ResultadoDeAlta
{
    public function __construct(
        public readonly Tenant $tenant,
        public readonly string $password,
        public readonly ?Throwable $falloDeCorreo = null,
    ) {}

    public function conFalloDeCorreo(?Throwable $fallo): self
    {
        return new self($this->tenant, $this->password, $fallo);
    }
}
