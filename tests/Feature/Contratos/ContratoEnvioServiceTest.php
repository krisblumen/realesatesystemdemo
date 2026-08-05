<?php

namespace Tests\Feature\Contratos;

use App\Enums\EstadoContrato;
use App\Models\ContratoIntermediacion;
use App\Services\Contratos\ContratoEnvioService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ContratoEnvioServiceTest extends TestCase
{
    use RefreshDatabase;

    private ContratoEnvioService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->service = app(ContratoEnvioService::class);
    }

    public function test_enviar_emits_token_and_moves_generado_to_enviado(): void
    {
        $contrato = ContratoIntermediacion::factory()->enEstado(EstadoContrato::Generado)->create();

        $token = $this->service->enviar($contrato);

        $this->assertSame(EstadoContrato::Enviado, $contrato->fresh()->estado);
        $this->assertSame(48, strlen($token));
        $this->assertNotNull($contrato->accesoVigente());
    }

    public function test_enviar_rejects_a_contract_not_in_generado(): void
    {
        $contrato = ContratoIntermediacion::factory()->enEstado(EstadoContrato::Enviado)->create();

        $this->expectException(ValidationException::class);

        $this->service->enviar($contrato);
    }

    public function test_whatsapp_link_contains_folio_and_normalized_phone(): void
    {
        $contrato = ContratoIntermediacion::factory()->create([
            'cliente_nombre' => 'Ana',
            'cliente_telefono' => '55 1234 5678',
            'folio' => 'ABCD2345',
        ]);

        $link = $this->service->whatsappLink($contrato, 'https://landra.test/contrato/xyz');

        $this->assertStringStartsWith('https://wa.me/5512345678?text=', $link);
        $this->assertStringContainsString('ABCD2345', rawurldecode($link));
    }
}
