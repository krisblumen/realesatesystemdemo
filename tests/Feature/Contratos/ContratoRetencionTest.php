<?php

namespace Tests\Feature\Contratos;

use App\Enums\EstadoContrato;
use App\Filament\Resources\ContratoIntermediacionResource\Pages\ListContratos;
use App\Models\ContratoIntermediacion;
use App\Models\User;
use App\Services\Contratos\ContratoAccesoService;
use App\Services\Contratos\ContratoEnvioService;
use App\Services\Contratos\ContratoFirmaService;
use App\Services\Contratos\ContratoRetencionService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ContratoRetencionTest extends TestCase
{
    use RefreshDatabase;

    private const FIRMA_PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M8AAAMBAQDJ/pLvAAAAAElFTkSuQmCC';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        Storage::fake('local');
    }

    /** Firma un contrato de verdad (PDF + hash reales) y lo deja en eliminación pendiente. */
    private function contratoFirmadoPendiente(): ContratoIntermediacion
    {
        $contrato = ContratoIntermediacion::factory()->enEstado(EstadoContrato::Generado)->create();
        $token = app(ContratoEnvioService::class)->enviar($contrato);
        $contrato->addMediaFromString('anverso')->usingFileName('a.png')->toMediaCollection('identificacion-anverso');
        $contrato->addMediaFromString('reverso')->usingFileName('r.png')->toMediaCollection('identificacion-reverso');

        $acceso = app(ContratoAccesoService::class)->resolver($token);
        $firmado = app(ContratoFirmaService::class)->firmar($acceso, [
            'firma_png_base64' => self::FIRMA_PNG,
            'privacidad_aceptada' => true,
            'cliente' => ['cliente_nombre' => 'María', 'cliente_telefono' => '55'],
        ]);

        $firmado->eliminacion_pendiente = true;
        $firmado->save();

        return $firmado;
    }

    public function test_confirming_deletion_purges_personal_media_but_keeps_pdf_and_soft_deletes(): void
    {
        $owner = User::factory()->active()->withRole('owner')->create();
        $contrato = $this->contratoFirmadoPendiente();

        app(ContratoRetencionService::class)->confirmarEliminacion($contrato, $owner);

        $contrato->refresh();
        $this->assertFalse($contrato->hasMedia('identificacion-anverso'));
        $this->assertFalse($contrato->hasMedia('firma'));
        $this->assertTrue($contrato->hasMedia('documento-final'));      // PDF conservado (P-4)
        $this->assertSoftDeleted($contrato);
        $this->assertDatabaseHas('contrato_eventos', [
            'contrato_intermediacion_id' => $contrato->id,
            'tipo' => 'eliminacion_confirmada',
            'actor_id' => $owner->id,
        ]);
    }

    public function test_verification_still_works_after_retention_deletion(): void
    {
        $owner = User::factory()->active()->withRole('owner')->create();
        $contrato = $this->contratoFirmadoPendiente();
        $bytes = file_get_contents($contrato->getFirstMedia('documento-final')->getPath());

        app(ContratoRetencionService::class)->confirmarEliminacion($contrato, $owner);

        // El PDF real sigue verificando como íntegro pese al soft delete (withTrashed / P-4).
        $this->post(route('contratos.verificar.comparar', $contrato->folio), [
            'documento' => UploadedFile::fake()->createWithContent('c.pdf', $bytes),
        ])->assertOk()->assertSee('Documento íntegro');
    }

    public function test_confirmar_eliminacion_rejects_a_non_owner_caller(): void
    {
        // Mn-IMP-1: el servicio autoriza internamente; un admin no puede purgar el expediente
        // aunque llame directo al servicio saltándose la UI.
        $admin = User::factory()->active()->withRole('admin')->create();
        $contrato = $this->contratoFirmadoPendiente();

        $this->expectException(AuthorizationException::class);

        app(ContratoRetencionService::class)->confirmarEliminacion($contrato, $admin);
    }

    public function test_only_owner_sees_the_confirm_deletion_action(): void
    {
        $contrato = $this->contratoFirmadoPendiente();
        $owner = User::factory()->active()->withRole('owner')->create();
        $admin = User::factory()->active()->withRole('admin')->create();

        $this->actingAs($owner);
        Livewire::test(ListContratos::class)->assertTableActionVisible('confirmarEliminacion', $contrato);

        $this->actingAs($admin);
        Livewire::test(ListContratos::class)->assertTableActionHidden('confirmarEliminacion', $contrato);
    }
}
