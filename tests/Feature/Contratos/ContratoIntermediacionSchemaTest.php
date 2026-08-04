<?php

namespace Tests\Feature\Contratos;

use App\Enums\EstadoContrato;
use App\Enums\TipoOperacionContrato;
use App\Models\ContratoEvento;
use App\Models\ContratoIntermediacion;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContratoIntermediacionSchemaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_factory_persists_a_contract_with_casts(): void
    {
        $contrato = ContratoIntermediacion::factory()->create();

        $this->assertDatabaseHas('contratos_intermediacion', ['id' => $contrato->id]);
        $this->assertInstanceOf(EstadoContrato::class, $contrato->estado);
        $this->assertInstanceOf(TipoOperacionContrato::class, $contrato->tipo_operacion);
        $this->assertSame(EstadoContrato::Generado, $contrato->estado);
        $this->assertSame(8, strlen($contrato->folio));
    }

    public function test_folio_is_globally_unique(): void
    {
        $first = ContratoIntermediacion::factory()->create();

        $this->expectException(QueryException::class);

        ContratoIntermediacion::factory()->create(['folio' => $first->folio]);
    }

    public function test_agente_relation_resolves(): void
    {
        $agente = User::factory()->activeAgent()->create();
        $contrato = ContratoIntermediacion::factory()->for($agente, 'agente')->create();

        $this->assertSame($agente->id, $contrato->agente->id);
    }

    public function test_evento_foreign_key_points_to_contratos_intermediacion(): void
    {
        // QA-181: la FK debe apuntar a la tabla real 'contratos_intermediacion',
        // no a la inferida 'contrato_intermediacions'. Un id inexistente debe fallar.
        $this->expectException(QueryException::class);

        ContratoEvento::create([
            'contrato_intermediacion_id' => 999999,
            'tipo' => 'generado',
        ]);
    }

    public function test_transicionar_a_valid_state_updates_status_timestamp_and_logs_event(): void
    {
        $contrato = ContratoIntermediacion::factory()->create();

        $contrato->transicionarA(EstadoContrato::Enviado);

        $this->assertSame(EstadoContrato::Enviado, $contrato->fresh()->estado);
        $this->assertNotNull($contrato->fresh()->enviado_at);
        $this->assertDatabaseHas('contrato_eventos', [
            'contrato_intermediacion_id' => $contrato->id,
            'tipo' => 'enviado',
        ]);
    }

    public function test_transicionar_a_invalid_state_throws_and_does_not_mutate(): void
    {
        $contrato = ContratoIntermediacion::factory()->create(); // Generado

        try {
            $contrato->transicionarA(EstadoContrato::Firmado); // Generado no puede ir a Firmado
            $this->fail('Se esperaba DomainException por transición inválida.');
        } catch (\DomainException $e) {
            // esperado
        }

        $this->assertSame(EstadoContrato::Generado, $contrato->fresh()->estado);
        $this->assertDatabaseCount('contrato_eventos', 0);
    }

    public function test_transicion_records_actor_and_http_context(): void
    {
        $actor = User::factory()->activeAgent()->create();
        $contrato = ContratoIntermediacion::factory()->create();

        $contrato->transicionarA(EstadoContrato::Enviado, $actor, [
            'ip' => '203.0.113.5',
            'user_agent' => 'PHPUnit',
        ]);

        $this->assertDatabaseHas('contrato_eventos', [
            'contrato_intermediacion_id' => $contrato->id,
            'tipo' => 'enviado',
            'actor_id' => $actor->id,
            'ip' => '203.0.113.5',
            'user_agent' => 'PHPUnit',
        ]);
    }
}
