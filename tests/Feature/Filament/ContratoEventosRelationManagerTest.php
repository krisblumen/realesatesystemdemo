<?php

namespace Tests\Feature\Filament;

use App\Enums\EstadoContrato;
use App\Filament\Resources\ContratoIntermediacionResource\Pages\ViewContrato;
use App\Filament\Resources\ContratoIntermediacionResource\RelationManagers\EventosRelationManager;
use App\Models\ContratoIntermediacion;
use App\Models\User;
use App\Services\Contratos\ContratoEnvioService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContratoEventosRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_audit_history_lists_lifecycle_events(): void
    {
        $owner = User::factory()->active()->withRole('owner')->create();
        $contrato = ContratoIntermediacion::factory()->enEstado(EstadoContrato::Generado)->create();
        $contrato->registrarEvento('generado');
        app(ContratoEnvioService::class)->enviar($contrato);

        $this->actingAs($owner);

        Livewire::test(EventosRelationManager::class, [
            'ownerRecord' => $contrato,
            'pageClass' => ViewContrato::class,
        ])
            ->assertCanSeeTableRecords($contrato->eventos()->get())
            ->assertCanRenderTableColumn('tipo');
    }
}
