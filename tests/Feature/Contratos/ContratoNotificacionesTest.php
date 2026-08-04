<?php

namespace Tests\Feature\Contratos;

use App\Enums\EstadoContrato;
use App\Models\ContratoIntermediacion;
use App\Notifications\ContratoEnlaceEnviado;
use App\Notifications\ContratoFirmado;
use App\Notifications\ContratoRechazado;
use App\Services\Contratos\ContratoAccesoService;
use App\Services\Contratos\ContratoEnvioService;
use App\Services\Contratos\ContratoFirmaService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContratoNotificacionesTest extends TestCase
{
    use RefreshDatabase;

    private const FIRMA_PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M8AAAMBAQDJ/pLvAAAAAElFTkSuQmCC';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        Storage::fake('local');
    }

    public function test_enviar_notifies_the_client_by_email(): void
    {
        Notification::fake();
        $contrato = ContratoIntermediacion::factory()->enEstado(EstadoContrato::Generado)->create(['cliente_email' => 'cliente@example.test']);

        app(ContratoEnvioService::class)->enviar($contrato);

        Notification::assertSentOnDemand(ContratoEnlaceEnviado::class);
    }

    public function test_reenviar_notifies_the_client_again(): void
    {
        Notification::fake();
        $contrato = ContratoIntermediacion::factory()->enEstado(EstadoContrato::Rechazado)->create(['cliente_email' => 'cliente@example.test']);

        app(ContratoAccesoService::class)->reenviar($contrato);

        Notification::assertSentOnDemand(ContratoEnlaceEnviado::class);
    }

    public function test_signing_notifies_the_agent(): void
    {
        Notification::fake();
        $contrato = ContratoIntermediacion::factory()->enEstado(EstadoContrato::Generado)->create();
        $token = app(ContratoEnvioService::class)->enviar($contrato);
        $contrato->addMediaFromString('a')->usingFileName('a.png')->toMediaCollection('identificacion-anverso');
        $contrato->addMediaFromString('r')->usingFileName('r.png')->toMediaCollection('identificacion-reverso');

        $acceso = app(ContratoAccesoService::class)->resolver($token);
        app(ContratoFirmaService::class)->firmar($acceso, [
            'firma_png_base64' => self::FIRMA_PNG,
            'privacidad_aceptada' => true,
            'cliente' => ['cliente_nombre' => 'Ana', 'cliente_telefono' => '55'],
        ]);

        Notification::assertSentTo($contrato->agente, ContratoFirmado::class);
    }

    public function test_rejecting_notifies_the_agent(): void
    {
        Notification::fake();
        $contrato = ContratoIntermediacion::factory()->enEstado(EstadoContrato::Generado)->create();
        $token = app(ContratoEnvioService::class)->enviar($contrato);

        $acceso = app(ContratoAccesoService::class)->resolver($token);
        app(ContratoFirmaService::class)->rechazar($acceso, 'No me interesa.');

        Notification::assertSentTo($contrato->agente, ContratoRechazado::class);
    }
}
