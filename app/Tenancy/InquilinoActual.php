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

    /**
     * ¿Lo que se está generando acá es una demostración?
     *
     * ES LO MISMO QUE «hay inquilino», y tiene nombre propio a propósito: en el
     * punto de uso lo que se pregunta no es de quién es la petición, sino si el
     * documento que sale tiene que ir marcado. Con `hayInquilino()` había que
     * saber, además, que un inquilino SIEMPRE es un demo — y eso es cierto hoy
     * y no está escrito en ninguna parte del nombre.
     *
     * No es una bandera de configuración, y ese es el punto: una bandera es algo
     * que alguien puede olvidarse de encender, y el síntoma de olvidarla es un
     * contrato o una lona de demostración que parecen reales. Una instalación
     * sin inquilinos —la plataforma corriendo para un cliente propio— emite
     * documentos sin marca sin que nadie tenga que acordarse de apagarla.
     */
    public function esUnaDemostracion(): bool
    {
        return $this->hayInquilino();
    }
}
