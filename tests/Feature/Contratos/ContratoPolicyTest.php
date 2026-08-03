<?php

namespace Tests\Feature\Contratos;

use App\Enums\EstadoContrato;
use App\Models\ContratoIntermediacion;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ContratoPolicyTest extends TestCase
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

    public function test_generar_permission_is_granted_to_agente_admin_owner(): void
    {
        $this->assertTrue($this->user('owner')->can('contratos.manage'));
        $this->assertTrue($this->user('admin')->can('contratos.manage'));
        $this->assertTrue($this->user('agente')->can('contratos.manage'));
    }

    public function test_agente_sees_only_their_own_contracts(): void
    {
        $agente = $this->user('agente');
        $otro = $this->user('agente');
        $propio = ContratoIntermediacion::factory()->for($agente, 'agente')->create();
        $ajeno = ContratoIntermediacion::factory()->for($otro, 'agente')->create();

        $this->assertTrue(Gate::forUser($agente)->allows('view', $propio));
        $this->assertFalse(Gate::forUser($agente)->allows('view', $ajeno));
    }

    public function test_admin_and_owner_see_all_contracts(): void
    {
        $ajeno = ContratoIntermediacion::factory()->create();

        $this->assertTrue(Gate::forUser($this->user('admin'))->allows('view', $ajeno));
        $this->assertTrue(Gate::forUser($this->user('owner'))->allows('view', $ajeno));
    }

    public function test_only_admin_and_owner_can_cancel_and_not_when_terminal(): void
    {
        $agente = $this->user('agente');
        $contrato = ContratoIntermediacion::factory()->for($agente, 'agente')->create();

        $this->assertFalse(Gate::forUser($agente)->allows('cancel', $contrato));
        $this->assertTrue(Gate::forUser($this->user('admin'))->allows('cancel', $contrato));

        $firmado = ContratoIntermediacion::factory()->enEstado(EstadoContrato::Firmado)->create();
        $this->assertFalse(Gate::forUser($this->user('owner'))->allows('cancel', $firmado));
    }

    public function test_only_owner_can_view_identificacion_and_firma(): void
    {
        $contrato = ContratoIntermediacion::factory()->create();

        $this->assertTrue(Gate::forUser($this->user('owner'))->allows('verIdentificacion', $contrato));
        $this->assertFalse(Gate::forUser($this->user('admin'))->allows('verIdentificacion', $contrato));
        $this->assertFalse(Gate::forUser($this->user('admin'))->allows('verFirma', $contrato));
    }
}
