<?php

namespace Tests\Unit;

use App\Enums\EstadoContrato;
use App\Enums\OrigenAccesoContrato;
use App\Enums\TipoOperacionContrato;
use PHPUnit\Framework\TestCase;

class ContratoEnumsTest extends TestCase
{
    public function test_estado_contrato_exposes_the_eight_states(): void
    {
        $this->assertSame(
            ['generado', 'enviado', 'leido', 'firmado', 'rechazado', 'expirado', 'cancelado', 'vencido'],
            array_column(EstadoContrato::cases(), 'value'),
        );
    }

    public function test_tipo_operacion_contrato_includes_renta_opcion_compra(): void
    {
        $this->assertSame(
            ['venta', 'renta', 'renta_opcion_compra'],
            array_column(TipoOperacionContrato::cases(), 'value'),
        );
    }

    public function test_origen_acceso_contrato_values(): void
    {
        $this->assertSame(['inicial', 'reenvio'], array_column(OrigenAccesoContrato::cases(), 'value'));
    }

    public function test_only_firmado_cancelado_vencido_are_terminal(): void
    {
        foreach (EstadoContrato::cases() as $estado) {
            $terminal = in_array($estado, [EstadoContrato::Firmado, EstadoContrato::Cancelado, EstadoContrato::Vencido], true);
            $this->assertSame($terminal, $estado->esTerminal(), $estado->value);
        }
    }

    public function test_rechazado_and_expirado_can_only_go_back_to_enviado(): void
    {
        $this->assertSame([EstadoContrato::Enviado], EstadoContrato::Rechazado->siguientes());
        $this->assertSame([EstadoContrato::Enviado], EstadoContrato::Expirado->siguientes());
    }

    public function test_state_machine_allows_expected_transitions(): void
    {
        $this->assertTrue(EstadoContrato::Generado->puedeTransicionarA(EstadoContrato::Enviado));
        $this->assertTrue(EstadoContrato::Enviado->puedeTransicionarA(EstadoContrato::Leido));
        $this->assertTrue(EstadoContrato::Leido->puedeTransicionarA(EstadoContrato::Firmado));
        $this->assertTrue(EstadoContrato::Firmado->puedeTransicionarA(EstadoContrato::Vencido));
    }

    public function test_state_machine_rejects_invalid_transitions(): void
    {
        // Firmado no puede volver a Enviado ni a ningún estado previo.
        $this->assertFalse(EstadoContrato::Firmado->puedeTransicionarA(EstadoContrato::Enviado));
        $this->assertFalse(EstadoContrato::Generado->puedeTransicionarA(EstadoContrato::Firmado));
        $this->assertFalse(EstadoContrato::Cancelado->puedeTransicionarA(EstadoContrato::Enviado));
        $this->assertSame([], EstadoContrato::Vencido->siguientes());
    }
}
