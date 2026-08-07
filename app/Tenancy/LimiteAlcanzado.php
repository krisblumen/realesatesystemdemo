<?php

namespace App\Tenancy;

use Carbon\CarbonInterface;
use RuntimeException;

/**
 * Se llegó a un tope y el alta no procede.
 *
 * LLEVA CUÁNDO REINTENTAR, y no es un adorno: «un visitante que quería probar el
 * producto y recibe *algo salió mal* no vuelve» (RFC-10). El mensaje tiene que
 * decir qué pasó y cuándo se puede volver a intentar.
 *
 * Cuando el tope es de la instancia y no del visitante, no hay fecha que
 * prometer —depende de que se libere lugar— y ahí `reintentarDesde()` es nulo.
 */
class LimiteAlcanzado extends RuntimeException
{
    public function __construct(
        string $mensaje,
        private readonly ?CarbonInterface $reintentarDesde = null,
    ) {
        parent::__construct($mensaje);
    }

    public function reintentarDesde(): ?CarbonInterface
    {
        return $this->reintentarDesde;
    }
}
