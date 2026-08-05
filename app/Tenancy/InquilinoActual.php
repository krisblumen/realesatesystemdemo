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

    private bool $esCentral = false;

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

    /**
     * El dominio base SIN subdominio.
     *
     * No es lo mismo que «no hay inquilino»: un host ajeno al demo tampoco lo
     * tiene, y a ese no hay que tocarle nada. Distinguirlos acá evita que cada
     * middleware vuelva a comparar cadenas de host por su cuenta — que es
     * exactamente lo que esta clase existe para impedir.
     */
    public function marcarComoCentral(): void
    {
        $this->esCentral = true;
    }

    public function esElHostCentral(): bool
    {
        return $this->esCentral;
    }

    public function hayInquilino(): bool
    {
        return $this->tenant !== null;
    }
}
