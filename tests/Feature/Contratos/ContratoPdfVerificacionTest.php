<?php

namespace Tests\Feature\Contratos;

use App\Enums\EstadoContrato;
use App\Models\ContratoIntermediacion;
use App\Services\Contratos\ContratoAccesoService;
use App\Services\Contratos\ContratoEnvioService;
use App\Services\Contratos\ContratoFirmaService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContratoPdfVerificacionTest extends TestCase
{
    use RefreshDatabase;

    private const FIRMA_PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M8AAAMBAQDJ/pLvAAAAAElFTkSuQmCC';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        Storage::fake('local');
    }

    private function firmarContrato(): ContratoIntermediacion
    {
        $contrato = ContratoIntermediacion::factory()->enEstado(EstadoContrato::Generado)->create();
        $token = app(ContratoEnvioService::class)->enviar($contrato);
        $contrato->addMediaFromString('anverso')->usingFileName('a.png')->toMediaCollection('identificacion-anverso');
        $contrato->addMediaFromString('reverso')->usingFileName('r.png')->toMediaCollection('identificacion-reverso');

        $acceso = app(ContratoAccesoService::class)->resolver($token);

        return app(ContratoFirmaService::class)->firmar($acceso, [
            'firma_png_base64' => self::FIRMA_PNG,
            'privacidad_aceptada' => true,
            'cliente' => ['cliente_nombre' => 'María Propietaria', 'cliente_telefono' => '4421234567'],
        ]);
    }

    public function test_signing_generates_final_pdf_and_stores_matching_hash(): void
    {
        $contrato = $this->firmarContrato();

        $media = $contrato->getFirstMedia('documento-final');
        $this->assertNotNull($media, 'El PDF final debe adjuntarse al firmar.');
        $this->assertNotNull($contrato->documento_hash);

        $bytes = file_get_contents($media->getPath());
        $this->assertSame($contrato->documento_hash, hash('sha256', $bytes));
        $this->assertStringStartsWith('%PDF', $bytes);
    }

    public function test_verificacion_page_does_not_leak_personal_data(): void
    {
        $contrato = $this->firmarContrato();

        $this->get(route('contratos.verificar', $contrato->folio))
            ->assertOk()
            ->assertSee($contrato->folio)
            ->assertDontSee($contrato->cliente_nombre)
            ->assertDontSee($contrato->inmueble_direccion);
    }

    public function test_correct_pdf_verifies_as_integro(): void
    {
        $contrato = $this->firmarContrato();
        $bytes = file_get_contents($contrato->getFirstMedia('documento-final')->getPath());

        $this->post(route('contratos.verificar.comparar', $contrato->folio), [
            'documento' => UploadedFile::fake()->createWithContent('contrato.pdf', $bytes),
        ])->assertOk()->assertSee('Documento íntegro');
    }

    public function test_altered_pdf_does_not_verify(): void
    {
        $contrato = $this->firmarContrato();

        $this->post(route('contratos.verificar.comparar', $contrato->folio), [
            'documento' => UploadedFile::fake()->createWithContent('otro.pdf', '%PDF-1.4 contenido alterado'),
        ])->assertOk()->assertSee('No se pudo verificar');
    }

    public function test_nonexistent_folio_responds_uniformly(): void
    {
        // Uniforme: un folio inexistente da el mismo "no se pudo verificar" (M-5).
        $this->post(route('contratos.verificar.comparar', 'ZZZZ2345'), [
            'documento' => UploadedFile::fake()->createWithContent('x.pdf', '%PDF-1.4 cualquier cosa'),
        ])->assertOk()->assertSee('No se pudo verificar');
    }
}
