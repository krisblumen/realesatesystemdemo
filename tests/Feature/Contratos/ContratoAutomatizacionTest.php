<?php

namespace Tests\Feature\Contratos;

use App\Enums\EstadoContrato;
use App\Models\ContratoIntermediacion;
use App\Models\User;
use App\Notifications\ContratoPorExpirar;
use App\Notifications\ContratoRecordatorioFirma;
use App\Notifications\ContratoRetencionPendiente;
use App\Services\Contratos\ContratoAutomatizacionService;
use App\Services\Contratos\ContratoEnvioService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ContratoAutomatizacionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    private function service(): ContratoAutomatizacionService
    {
        return app(ContratoAutomatizacionService::class);
    }

    public function test_expira_contracts_whose_token_has_expired(): void
    {
        $contrato = ContratoIntermediacion::factory()->enEstado(EstadoContrato::Generado)->create();
        app(ContratoEnvioService::class)->enviar($contrato);
        // Expira el token manualmente.
        $contrato->accesos()->update(['expira_at' => now()->subHour()]);

        $expirados = $this->service()->expirarSinRespuesta();

        $this->assertSame(1, $expirados);
        $this->assertSame(EstadoContrato::Expirado, $contrato->fresh()->estado);
    }

    public function test_does_not_expire_contracts_with_a_live_token(): void
    {
        $contrato = ContratoIntermediacion::factory()->enEstado(EstadoContrato::Generado)->create();
        app(ContratoEnvioService::class)->enviar($contrato); // token vigente 72h

        $this->assertSame(0, $this->service()->expirarSinRespuesta());
        $this->assertSame(EstadoContrato::Enviado, $contrato->fresh()->estado);
    }

    public function test_marks_signed_contracts_past_validity_as_vencido(): void
    {
        $contrato = ContratoIntermediacion::factory()
            ->enEstado(EstadoContrato::Firmado)
            ->create(['vigencia_fin' => now()->subDay()->toDateString()]);

        $this->assertSame(1, $this->service()->marcarVencidos());
        $this->assertSame(EstadoContrato::Vencido, $contrato->fresh()->estado);
    }

    public function test_retencion_marks_pending_and_notifies_owner_without_deleting(): void
    {
        Notification::fake();
        $owner = User::factory()->active()->withRole('owner')->create();
        $contrato = ContratoIntermediacion::factory()
            ->enEstado(EstadoContrato::Firmado)
            ->create(['retencion_revisar_at' => now()->subDay()]);

        $marcados = $this->service()->marcarRetencionPendiente();

        $this->assertSame(1, $marcados);
        $this->assertTrue($contrato->fresh()->eliminacion_pendiente);
        // NO borra: el contrato sigue presente y sin soft delete.
        $this->assertDatabaseHas('contratos_intermediacion', ['id' => $contrato->id, 'deleted_at' => null]);
        Notification::assertSentTo($owner, ContratoRetencionPendiente::class);
    }

    public function test_reminders_are_sent_once_and_not_duplicated(): void
    {
        Notification::fake();
        $contrato = ContratoIntermediacion::factory()->enEstado(EstadoContrato::Generado)->create(['cliente_email' => 'c@example.test']);
        app(ContratoEnvioService::class)->enviar($contrato);
        // Simula que el envío fue hace 49 horas (token sigue vigente 72h).
        $contrato->forceFill(['enviado_at' => now()->subHours(49)])->save();

        $this->assertSame(1, $this->service()->enviarRecordatorios(48));
        Notification::assertSentOnDemand(ContratoRecordatorioFirma::class);
        Notification::assertSentTo($contrato->agente, ContratoPorExpirar::class);

        // Segunda corrida: no se duplica.
        $this->assertSame(0, $this->service()->enviarRecordatorios(48));
    }
}
