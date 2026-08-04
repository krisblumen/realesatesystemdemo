<?php

namespace Tests\Feature\Filament;

use App\Enums\EstadoContrato;
use App\Enums\TipoOperacionContrato;
use App\Filament\Resources\ContratoIntermediacionResource;
use App\Filament\Resources\ContratoIntermediacionResource\Pages\CreateContrato;
use App\Models\ContratoIntermediacion;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContratoIntermediacionResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    private function user(string $role): User
    {
        return User::factory()->active()->withRole($role)->create();
    }

    public function test_agente_admin_and_owner_can_access_the_resource(): void
    {
        foreach (['agente', 'admin', 'owner'] as $role) {
            $this->actingAs($this->user($role))
                ->get(ContratoIntermediacionResource::getUrl('index'))
                ->assertOk();
        }
    }

    public function test_agente_only_sees_their_own_contracts_in_the_list(): void
    {
        $agente = $this->user('agente');
        $propio = ContratoIntermediacion::factory()->for($agente, 'agente')->create(['cliente_nombre' => 'Cliente Propio']);
        $ajeno = ContratoIntermediacion::factory()->create(['cliente_nombre' => 'Cliente Ajeno']);

        $this->actingAs($agente);

        Livewire::test(ContratoIntermediacionResource\Pages\ListContratos::class)
            ->assertCanSeeTableRecords([$propio])
            ->assertCanNotSeeTableRecords([$ajeno]);
    }

    public function test_creating_a_contract_generates_folio_and_generado_event(): void
    {
        $agente = $this->user('agente');
        $this->actingAs($agente);

        Livewire::test(CreateContrato::class)
            ->fillForm([
                'cliente_nombre' => 'Propietario Prueba',
                'cliente_telefono' => '5512345678',
                'cliente_email' => 'propietario@example.test',
                'cliente_direccion' => 'Av. Siempre Viva 742',
                'inmueble_tipo' => 'casa',
                'tipo_operacion' => TipoOperacionContrato::Venta->value,
                'inmueble_direccion' => 'Calle Falsa 123',
                'precio_autorizado' => 1000000,
                'comision_porcentaje' => 5,
                'vigencia_inicio' => now()->toDateString(),
                'vigencia_fin' => now()->addMonth()->toDateString(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $contrato = ContratoIntermediacion::where('agente_id', $agente->id)->firstOrFail();
        $this->assertSame(8, strlen($contrato->folio));
        $this->assertSame(EstadoContrato::Generado, $contrato->estado);
        $this->assertDatabaseHas('contrato_eventos', [
            'contrato_intermediacion_id' => $contrato->id,
            'tipo' => 'generado',
            'actor_id' => $agente->id,
        ]);
    }

    public function test_owner_sees_identification_evidence_of_a_signed_contract_but_admin_does_not(): void
    {
        $agente = $this->user('agente');
        $contrato = ContratoIntermediacion::factory()->for($agente, 'agente')
            ->enEstado(EstadoContrato::Firmado)->create();
        $contrato->addMediaFromString('frente')->usingFileName('a.png')->toMediaCollection('identificacion-anverso');

        $urlId = route('contratos.media', ['contrato' => $contrato, 'coleccion' => 'identificacion-anverso']);

        // Owner: ve la sección de identificación con la imagen (ruta autorizada).
        $this->actingAs($this->user('owner'))
            ->get(ContratoIntermediacionResource::getUrl('view', ['record' => $contrato]))
            ->assertOk()
            ->assertSee('Identificación del firmante', false)
            ->assertSee($urlId, false);

        // Admin: no ve la identificación.
        $this->actingAs($this->user('admin'))
            ->get(ContratoIntermediacionResource::getUrl('view', ['record' => $contrato]))
            ->assertOk()
            ->assertDontSee('Identificación del firmante', false);
    }

    public function test_enviar_action_is_available_on_the_view_page_after_creation(): void
    {
        $agente = $this->user('agente');
        $contrato = ContratoIntermediacion::factory()->for($agente, 'agente')->create();

        $this->actingAs($agente);

        Livewire::test(ContratoIntermediacionResource\Pages\ViewContrato::class, ['record' => $contrato->getKey()])
            ->assertActionVisible('enviar')
            ->callAction('enviar');

        $contrato->refresh();
        $this->assertSame(EstadoContrato::Enviado, $contrato->estado);
        $this->assertNotNull($contrato->accesoVigente());
    }

    public function test_enviar_action_emits_token_and_moves_to_enviado(): void
    {
        $agente = $this->user('agente');
        $contrato = ContratoIntermediacion::factory()->for($agente, 'agente')->create();

        $this->actingAs($agente);

        Livewire::test(ContratoIntermediacionResource\Pages\ListContratos::class)
            ->callTableAction('enviar', $contrato);

        $contrato->refresh();
        $this->assertSame(EstadoContrato::Enviado, $contrato->estado);
        $this->assertNotNull($contrato->accesoVigente());
    }
}
