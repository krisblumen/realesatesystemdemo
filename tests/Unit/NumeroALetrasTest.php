<?php

namespace Tests\Unit;

use App\Support\NumeroALetras;
use PHPUnit\Framework\TestCase;

class NumeroALetrasTest extends TestCase
{
    public function test_converts_common_amounts_to_mexican_peso_words(): void
    {
        $this->assertSame('un millón de pesos 00/100 M.N.', NumeroALetras::pesos(1000000));
        $this->assertSame('un peso 00/100 M.N.', NumeroALetras::pesos(1));
        $this->assertSame('cero pesos 00/100 M.N.', NumeroALetras::pesos(0));
        $this->assertSame('quinientos mil pesos 00/100 M.N.', NumeroALetras::pesos(500000));
        $this->assertSame('dos millones quinientos mil pesos 00/100 M.N.', NumeroALetras::pesos(2500000));
    }

    public function test_handles_cents_and_apocope(): void
    {
        $this->assertSame('mil quinientos pesos 50/100 M.N.', NumeroALetras::pesos(1500.50));
        $this->assertSame('veintiún pesos 00/100 M.N.', NumeroALetras::pesos(21));
        $this->assertSame('ciento un pesos 00/100 M.N.', NumeroALetras::pesos(101));
    }

    public function test_handles_hundreds_and_tens(): void
    {
        $this->assertSame('trescientos cuarenta y cinco pesos 00/100 M.N.', NumeroALetras::pesos(345));
        $this->assertSame('cien pesos 00/100 M.N.', NumeroALetras::pesos(100));
    }
}
