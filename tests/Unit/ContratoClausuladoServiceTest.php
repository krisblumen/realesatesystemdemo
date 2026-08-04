<?php

namespace Tests\Unit;

use App\Enums\TipoOperacionContrato;
use App\Services\Contratos\ContratoClausuladoService;
use PHPUnit\Framework\TestCase;

class ContratoClausuladoServiceTest extends TestCase
{
    private ContratoClausuladoService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ContratoClausuladoService;
    }

    public function test_clausula_pago_varies_by_operation(): void
    {
        $venta = $this->service->clausulaPago(TipoOperacionContrato::Venta);
        $renta = $this->service->clausulaPago(TipoOperacionContrato::Renta);
        $opcion = $this->service->clausulaPago(TipoOperacionContrato::RentaOpcionCompra);

        $this->assertStringContainsString('VENTA', $venta);
        $this->assertStringContainsString('escritura pública', $venta);
        $this->assertStringContainsString('RENTA', $renta);
        $this->assertStringContainsString('arrendamiento', $renta);
        $this->assertStringContainsString('OPCIÓN A COMPRA', $opcion);

        $this->assertNotSame($venta, $renta);
        $this->assertNotSame($renta, $opcion);
    }

    public function test_clausula_exclusividad_varies_by_flag(): void
    {
        $con = $this->service->clausulaExclusividad(true);
        $sin = $this->service->clausulaExclusividad(false);

        $this->assertStringContainsString('CON EXCLUSIVIDAD', $con);
        $this->assertStringContainsString('SIN EXCLUSIVIDAD', $sin);
        $this->assertNotSame($con, $sin);
    }

    public function test_modalidad_texto(): void
    {
        $this->assertSame('CON EXCLUSIVIDAD', $this->service->modalidadTexto(true));
        $this->assertSame('SIN EXCLUSIVIDAD', $this->service->modalidadTexto(false));
    }
}
