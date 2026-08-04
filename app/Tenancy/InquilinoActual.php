<?php

namespace App\Tenancy;

use App\Models\Tenant;

/**
 * El inquilino de la petición en curso, expuesto por un ÚNICO punto.
 *
 * No es una variable global ni un helper suelto a propósito: si cada lugar
 * pudiera preguntar «¿de quién es esto?» por su cuenta, cada lugar podría
 * responderlo distinto. Con un solo punto, cambiar cómo se resuelve —hoy el
 * `Host`, mañana otra cosa— es cambiar un archivo.
 */
class InquilinoActual
{
    private ?Tenant $tenant = null;

    public function fijar(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function tenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function slug(): ?string
    {
        return $this->tenant?->slug;
    }

    public function hayInquilino(): bool
    {
        return $this->tenant !== null;
    }
}
