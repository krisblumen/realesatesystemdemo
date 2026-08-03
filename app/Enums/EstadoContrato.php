<?php

namespace App\Enums;

/**
 * Estados del contrato de intermediación y su máquina de transiciones (RFC-063).
 * `siguientes()` es la fuente única de la máquina de estados; toda transición pasa
 * por ContratoIntermediacion::transicionarA() — hallazgo M-1 de la auditoría.
 */
enum EstadoContrato: string
{
    case Generado = 'generado';
    case Enviado = 'enviado';
    case Leido = 'leido';
    case Firmado = 'firmado';
    case Rechazado = 'rechazado';
    case Expirado = 'expirado';
    case Cancelado = 'cancelado';
    case Vencido = 'vencido';

    public function label(): string
    {
        return match ($this) {
            self::Generado => 'Generado',
            self::Enviado => 'Enviado',
            self::Leido => 'Leído / Visto',
            self::Firmado => 'Firmado',
            self::Rechazado => 'Rechazado',
            self::Expirado => 'Expirado',
            self::Cancelado => 'Cancelado',
            self::Vencido => 'Vencido',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Generado => 'gray',
            self::Enviado => 'info',
            self::Leido => 'warning',
            self::Firmado => 'success',
            self::Rechazado => 'danger',
            self::Expirado => 'danger',
            self::Cancelado => 'gray',
            self::Vencido => 'gray',
        };
    }

    /**
     * "Terminal de negocio": el contrato ya no admite cancelación ni reenvío.
     * OJO (Mn-1): NO significa "estado terminal del flujo público" — Rechazado
     * y Expirado quedan fuera justamente porque SÍ admiten reenvío.
     */
    public function esTerminal(): bool
    {
        return in_array($this, [self::Firmado, self::Cancelado, self::Vencido], true);
    }

    /**
     * Transiciones válidas desde este estado. Fuente única de la máquina de estados.
     *
     * @return list<self>
     */
    public function siguientes(): array
    {
        return match ($this) {
            self::Generado => [self::Enviado, self::Cancelado],
            self::Enviado => [self::Leido, self::Firmado, self::Rechazado, self::Expirado, self::Cancelado],
            self::Leido => [self::Firmado, self::Rechazado, self::Expirado, self::Cancelado],
            self::Rechazado => [self::Enviado],  // reenvío, mismo folio
            self::Expirado => [self::Enviado],   // reenvío, mismo folio
            self::Firmado => [self::Vencido],    // solo por fin de vigencia
            self::Cancelado => [],
            self::Vencido => [],
        };
    }

    public function puedeTransicionarA(self $destino): bool
    {
        return in_array($destino, $this->siguientes(), true);
    }
}
