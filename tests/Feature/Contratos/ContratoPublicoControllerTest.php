<?php

namespace Tests\Feature\Contratos;

use App\Enums\EstadoContrato;
use App\Enums\TipoOperacionContrato;
use App\Models\ContratoIntermediacion;
use App\Services\Contratos\ContratoAccesoService;
use App\Services\Contratos\ContratoEnvioService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContratoPublicoControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    private function contratoEnviado(array $attrs = []): array
    {
        $contrato = ContratoIntermediacion::factory()->enEstado(EstadoContrato::Generado)->create($attrs);
        $token = app(ContratoEnvioService::class)->enviar($contrato);

        return [$contrato, $token];
    }

    public function test_valid_token_opens_the_form_and_moves_enviado_to_leido(): void
    {
        [$contrato, $token] = $this->contratoEnviado();

        $this->get(route('contratos.publico.show', $token))
            ->assertOk()
            ->assertSee($contrato->folio)
            ->assertSee('CONTRATO DE PRESTACIÓN DE SERVICIOS DE INTERMEDIACIÓN PARA LA', false)
            ->assertSee('PROFESIONAL INMOBILIARIO', false);

        $this->assertSame(EstadoContrato::Leido, $contrato->fresh()->estado);
        $this->assertDatabaseHas('contrato_eventos', [
            'contrato_intermediacion_id' => $contrato->id,
            'tipo' => 'leido',
        ]);
    }

    public function test_second_open_does_not_transition_again(): void
    {
        [$contrato, $token] = $this->contratoEnviado();

        $this->get(route('contratos.publico.show', $token))->assertOk();
        $this->get(route('contratos.publico.show', $token))->assertOk();

        // Solo un evento 'leido' pese a dos aperturas.
        $this->assertSame(1, $contrato->eventos()->where('tipo', 'leido')->count());
    }

    public function test_unknown_token_returns_410_without_leaking_data(): void
    {
        [$contrato] = $this->contratoEnviado(['cliente_nombre' => 'Secreto Cliente']);

        $this->get(route('contratos.publico.show', 'token-inexistente'))
            ->assertStatus(410)
            ->assertDontSee('Secreto Cliente');
    }

    public function test_used_token_returns_410(): void
    {
        [$contrato, $token] = $this->contratoEnviado();
        // Simula token consumido (firma/rechazo).
        $acceso = app(ContratoAccesoService::class)->resolver($token);
        app(ContratoAccesoService::class)->consumir($acceso);

        $this->get(route('contratos.publico.show', $token))->assertStatus(410);
    }

    public function test_cancelled_contract_shows_unavailable_message(): void
    {
        [$contrato, $token] = $this->contratoEnviado();
        $contrato->transicionarA(EstadoContrato::Cancelado);

        $this->get(route('contratos.publico.show', $token))
            ->assertStatus(410)
            ->assertSee('Contrato no disponible');
    }

    public function test_clausulado_varies_by_operation_and_exclusivity(): void
    {
        [, $tokenVentaExcl] = $this->contratoEnviado([
            'tipo_operacion' => TipoOperacionContrato::Venta,
            'exclusividad' => true,
        ]);
        $this->get(route('contratos.publico.show', $tokenVentaExcl))
            ->assertSee('escritura pública')
            ->assertSee('CON EXCLUSIVIDAD');

        [, $tokenRentaSin] = $this->contratoEnviado([
            'tipo_operacion' => TipoOperacionContrato::Renta,
            'exclusividad' => false,
        ]);
        $this->get(route('contratos.publico.show', $tokenRentaSin))
            ->assertSee('arrendamiento')
            ->assertSee('SIN EXCLUSIVIDAD');
    }

    public function test_public_endpoint_is_rate_limited(): void
    {
        [, $token] = $this->contratoEnviado();

        // 20/min por IP: la petición 21 debe recibir 429 (QA-190).
        for ($i = 0; $i < 20; $i++) {
            $this->get(route('contratos.publico.show', $token));
        }

        $this->get(route('contratos.publico.show', $token))->assertStatus(429);
    }
}
