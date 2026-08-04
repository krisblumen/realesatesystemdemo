<?php

namespace Tests\Feature\Contratos;

use App\Enums\EstadoContrato;
use App\Enums\TipoOperacionContrato;
use App\Models\ContratoIntermediacion;
use App\Models\User;
use App\Services\Contratos\ContratoCreacionService;
use App\Services\Contratos\FolioGenerator;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ContratoCreacionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    private function datosValidos(): array
    {
        return [
            'cliente_nombre' => 'Cliente Prueba',
            'cliente_telefono' => '5512345678',
            'inmueble_tipo' => 'casa',
            'tipo_operacion' => TipoOperacionContrato::Venta,
            'inmueble_direccion' => 'Calle Falsa 123',
            'comision_porcentaje' => 5.0,
        ];
    }

    public function test_folio_generator_produces_eight_chars_from_unambiguous_alphabet(): void
    {
        $folio = app(FolioGenerator::class)->generar();

        $this->assertSame(8, strlen($folio));
        $this->assertMatchesRegularExpression('/^[23456789ABCDEFGHJKMNPQRSTUVWXYZ]{8}$/', $folio);
    }

    public function test_crear_persists_contract_with_folio_agent_and_generado_event(): void
    {
        $agente = User::factory()->activeAgent()->create();

        $contrato = app(ContratoCreacionService::class)->crear($this->datosValidos(), $agente);

        $this->assertSame($agente->id, $contrato->agente_id);
        $this->assertSame(EstadoContrato::Generado, $contrato->estado);
        $this->assertSame(8, strlen($contrato->folio));
        $this->assertDatabaseHas('contrato_eventos', [
            'contrato_intermediacion_id' => $contrato->id,
            'tipo' => 'generado',
            'actor_id' => $agente->id,
        ]);
    }

    public function test_crear_retries_on_folio_collision_then_succeeds(): void
    {
        // QA-183: si el folio choca con el índice UNIQUE, el servicio reintenta el create.
        $agente = User::factory()->activeAgent()->create();
        $existente = ContratoIntermediacion::factory()->create(['folio' => 'AAAA2345']);

        // FolioGenerator devuelve primero el folio ya existente (colisión), luego uno libre.
        $folios = Mockery::mock(FolioGenerator::class);
        $folios->shouldReceive('generar')->once()->andReturn($existente->folio);
        $folios->shouldReceive('generar')->once()->andReturn('BBBB2345');
        $this->app->instance(FolioGenerator::class, $folios);

        $contrato = app(ContratoCreacionService::class)->crear($this->datosValidos(), $agente);

        $this->assertSame('BBBB2345', $contrato->folio);
        $this->assertSame(2, ContratoIntermediacion::count());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
