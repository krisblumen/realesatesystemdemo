<?php

namespace Tests\Feature\Contratos;

use App\Enums\EstadoContrato;
use App\Models\ContratoIntermediacion;
use App\Services\Contratos\ContratoAccesoService;
use App\Services\Contratos\ContratoEnvioService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContratoFirmaTest extends TestCase
{
    use RefreshDatabase;

    // PNG 1x1 válido (magic bytes \x89PNG).
    private const FIRMA_PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M8AAAMBAQDJ/pLvAAAAAElFTkSuQmCC';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        Storage::fake('local');
    }

    /** @return array{ContratoIntermediacion, string} */
    private function contratoConTokenYId(): array
    {
        $contrato = ContratoIntermediacion::factory()->enEstado(EstadoContrato::Generado)->create();
        $token = app(ContratoEnvioService::class)->enviar($contrato);

        // Identificación ya cargada por el agente (para no tener que subirla en cada test).
        $contrato->addMediaFromString('anverso')->usingFileName('a.png')->toMediaCollection('identificacion-anverso');
        $contrato->addMediaFromString('reverso')->usingFileName('r.png')->toMediaCollection('identificacion-reverso');

        return [$contrato, $token];
    }

    private function payloadFirma(array $overrides = []): array
    {
        return array_merge([
            'firma' => self::FIRMA_PNG,
            'privacidad' => '1',
            'cliente' => [
                'cliente_nombre' => 'María Propietaria',
                'cliente_telefono' => '4421234567',
            ],
        ], $overrides);
    }

    public function test_firmar_persists_signature_evidence_and_moves_to_firmado(): void
    {
        [$contrato, $token] = $this->contratoConTokenYId();

        $this->post(route('contratos.publico.firmar', $token), $this->payloadFirma())
            ->assertOk()
            ->assertSee('firmado');

        $contrato->refresh();
        $this->assertSame(EstadoContrato::Firmado, $contrato->estado);
        $this->assertNotNull($contrato->firmado_at);
        $this->assertNotNull($contrato->retencion_revisar_at);
        $this->assertNotNull($contrato->getFirstMedia('firma'));
        $this->assertNotNull($contrato->evidenciaFirma);
        $this->assertNotEmpty($contrato->evidenciaFirma->firma_hash);
        // Token invalidado tras firmar.
        $this->assertNull(app(ContratoAccesoService::class)->resolver($token));
    }

    public function test_invalid_signature_payload_does_not_consume_the_token(): void
    {
        // QA-185 / M-2: un POST con firma corrupta no debe quemar el enlace.
        [$contrato, $token] = $this->contratoConTokenYId();

        $this->post(route('contratos.publico.firmar', $token), $this->payloadFirma([
            'firma' => 'data:image/png;base64,'.base64_encode('esto-no-es-una-imagen'),
        ]))->assertSessionHasErrors('firma');

        // El token sigue vivo y el contrato no firmó (sigue en Enviado, sin abrir el form).
        $this->assertNotNull(app(ContratoAccesoService::class)->resolver($token));
        $this->assertSame(EstadoContrato::Enviado, $contrato->fresh()->estado);
    }

    public function test_cannot_sign_without_accepting_privacy(): void
    {
        [, $token] = $this->contratoConTokenYId();

        $this->post(route('contratos.publico.firmar', $token), $this->payloadFirma(['privacidad' => '0']))
            ->assertSessionHasErrors('privacidad');

        $this->assertNotNull(app(ContratoAccesoService::class)->resolver($token));
    }

    public function test_cannot_sign_without_both_identification_sides(): void
    {
        // QA-189 / M-6: sin ambas caras (frente y reverso) no se puede firmar.
        $contrato = ContratoIntermediacion::factory()->enEstado(EstadoContrato::Generado)->create();
        $token = app(ContratoEnvioService::class)->enviar($contrato);

        // Solo el frente: falta el reverso → error.
        $this->post(route('contratos.publico.firmar', $token), $this->payloadFirma([
            'identificacion_anverso' => self::FIRMA_PNG,
        ]))->assertSessionHasErrors('identificacion');

        $this->assertNotNull(app(ContratoAccesoService::class)->resolver($token));
    }

    public function test_client_captures_both_identification_sides_when_signing(): void
    {
        // Ambas caras se capturan como foto en vivo (base64), no como archivo de galería.
        $contrato = ContratoIntermediacion::factory()->enEstado(EstadoContrato::Generado)->create();
        $token = app(ContratoEnvioService::class)->enviar($contrato);

        $this->post(route('contratos.publico.firmar', $token), $this->payloadFirma([
            'identificacion_anverso' => self::FIRMA_PNG,
            'identificacion_reverso' => self::FIRMA_PNG,
        ]))->assertOk();

        $contrato->refresh();
        $this->assertSame(EstadoContrato::Firmado, $contrato->estado);
        $this->assertTrue($contrato->tieneIdentificacionCompleta());
    }

    public function test_signing_is_blocked_when_identification_is_a_non_image_payload(): void
    {
        // Anti-fraude: un base64 que no es imagen (p.ej. galería manipulada) se rechaza.
        $contrato = ContratoIntermediacion::factory()->enEstado(EstadoContrato::Generado)->create();
        $token = app(ContratoEnvioService::class)->enviar($contrato);

        $this->post(route('contratos.publico.firmar', $token), $this->payloadFirma([
            'identificacion_anverso' => 'data:image/png;base64,'.base64_encode('no-soy-imagen'),
        ]))->assertSessionHasErrors('identificacion');

        $this->assertNotNull(app(ContratoAccesoService::class)->resolver($token));
    }

    public function test_reused_token_is_rejected_after_signing(): void
    {
        [, $token] = $this->contratoConTokenYId();

        $this->post(route('contratos.publico.firmar', $token), $this->payloadFirma())->assertOk();

        // Segundo intento con el mismo token: ya no resuelve → 410.
        $this->post(route('contratos.publico.firmar', $token), $this->payloadFirma())->assertStatus(410);
    }

    public function test_rechazar_records_motivo_and_moves_to_rechazado(): void
    {
        [$contrato, $token] = $this->contratoConTokenYId();

        $this->post(route('contratos.publico.rechazar', $token), ['motivo' => 'No estoy de acuerdo con la comisión.'])
            ->assertOk()
            ->assertSee('rechazado');

        $contrato->refresh();
        $this->assertSame(EstadoContrato::Rechazado, $contrato->estado);
        $this->assertSame('No estoy de acuerdo con la comisión.', $contrato->motivo_rechazo);
        $this->assertNull(app(ContratoAccesoService::class)->resolver($token));
    }
}
