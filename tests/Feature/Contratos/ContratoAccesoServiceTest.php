<?php

namespace Tests\Feature\Contratos;

use App\Enums\EstadoContrato;
use App\Enums\OrigenAccesoContrato;
use App\Models\ContratoAcceso;
use App\Models\ContratoIntermediacion;
use App\Services\Contratos\ContratoAccesoService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ContratoAccesoServiceTest extends TestCase
{
    use RefreshDatabase;

    private ContratoAccesoService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->service = app(ContratoAccesoService::class);
    }

    public function test_emitir_returns_plain_token_and_stores_only_its_hash(): void
    {
        $contrato = ContratoIntermediacion::factory()->create();

        $token = $this->service->emitir($contrato);

        $this->assertSame(48, strlen($token));
        $this->assertDatabaseMissing('contrato_accesos', ['token_hash' => $token]);
        $this->assertDatabaseHas('contrato_accesos', [
            'contrato_intermediacion_id' => $contrato->id,
            'token_hash' => hash('sha256', $token),
            'emitido_por' => OrigenAccesoContrato::Inicial->value,
        ]);
    }

    public function test_resolver_finds_a_valid_token_and_ignores_expired_ones(): void
    {
        $contrato = ContratoIntermediacion::factory()->create();
        $token = $this->service->emitir($contrato);

        $this->assertNotNull($this->service->resolver($token));

        // Expira el acceso manualmente → ya no resuelve.
        $contrato->accesos()->update(['expira_at' => now()->subHour()]);
        $this->assertNull($this->service->resolver($token));
    }

    public function test_resolver_returns_null_for_unknown_token(): void
    {
        $this->assertNull($this->service->resolver('token-que-no-existe'));
    }

    public function test_consumir_is_atomic_and_single_use(): void
    {
        $contrato = ContratoIntermediacion::factory()->create();
        $token = $this->service->emitir($contrato);
        $acceso = $this->service->resolver($token);

        $this->assertTrue($this->service->consumir($acceso));  // gana la carrera
        $this->assertFalse($this->service->consumir($acceso)); // segundo intento pierde
        $this->assertNotNull($acceso->fresh()->usado_at);
    }

    public function test_emitir_invalidates_previous_active_token(): void
    {
        $contrato = ContratoIntermediacion::factory()->create();

        $primero = $this->service->emitir($contrato);
        $segundo = $this->service->emitir($contrato, OrigenAccesoContrato::Reenvio);

        $this->assertNull($this->service->resolver($primero)); // el anterior quedó invalidado
        $this->assertNotNull($this->service->resolver($segundo));
        $this->assertSame(2, ContratoAcceso::where('contrato_intermediacion_id', $contrato->id)->count());
    }

    public function test_reenviar_keeps_folio_emits_new_token_and_returns_to_enviado(): void
    {
        $contrato = ContratoIntermediacion::factory()->enEstado(EstadoContrato::Rechazado)->create();
        $folioOriginal = $contrato->folio;

        $token = $this->service->reenviar($contrato);

        $this->assertSame($folioOriginal, $contrato->fresh()->folio);
        $this->assertSame(EstadoContrato::Enviado, $contrato->fresh()->estado);
        $this->assertNotNull($this->service->resolver($token));
        $this->assertDatabaseHas('contrato_eventos', [
            'contrato_intermediacion_id' => $contrato->id,
            'tipo' => 'reenviado',
        ]);
    }

    public function test_reenviar_rejects_contracts_not_rechazado_or_expirado(): void
    {
        $contrato = ContratoIntermediacion::factory()->enEstado(EstadoContrato::Generado)->create();

        $this->expectException(ValidationException::class);

        $this->service->reenviar($contrato);
    }
}
