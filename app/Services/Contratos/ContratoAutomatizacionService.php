<?php

namespace App\Services\Contratos;

use App\Enums\EstadoContrato;
use App\Models\ContratoIntermediacion;
use App\Models\User;
use App\Notifications\ContratoPorExpirar;
use App\Notifications\ContratoRecordatorioFirma;
use App\Notifications\ContratoRetencionPendiente;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Notification;

/**
 * Lógica de las automatizaciones del ciclo de vida del contrato (RFC-069). Se invoca desde
 * los comandos programados (routes/console.php). El job de retención NO borra: solo marca
 * la lista de eliminación pendiente y avisa al Owner, que confirma manualmente (D-10).
 */
class ContratoAutomatizacionService
{
    /**
     * Marca Expirado los contratos Enviado/Leído cuyo token de acceso ya venció (sin ningún
     * acceso vigente). Devuelve la cantidad de contratos expirados.
     */
    public function expirarSinRespuesta(): int
    {
        $contratos = ContratoIntermediacion::query()
            ->whereIn('estado', [EstadoContrato::Enviado->value, EstadoContrato::Leido->value])
            ->whereDoesntHave('accesos', fn (Builder $q) => $q->whereNull('usado_at')->where('expira_at', '>', now()))
            ->get();

        foreach ($contratos as $contrato) {
            $contrato->transicionarA(EstadoContrato::Expirado);
        }

        return $contratos->count();
    }

    /**
     * Recuerda al cliente (y avisa al agente) los contratos sin firma tras N horas, con token
     * aún vigente. Deduplica por evento 'recordatorio' emitido después del último envío.
     */
    public function enviarRecordatorios(int $horas = 48): int
    {
        $limite = now()->subHours($horas);

        $contratos = ContratoIntermediacion::query()
            ->with('agente')
            ->whereIn('estado', [EstadoContrato::Enviado->value, EstadoContrato::Leido->value])
            ->whereNotNull('enviado_at')
            ->where('enviado_at', '<=', $limite)
            ->whereHas('accesos', fn (Builder $q) => $q->whereNull('usado_at')->where('expira_at', '>', now()))
            ->get()
            ->filter(fn (ContratoIntermediacion $c) => ! $this->yaRecordado($c));

        foreach ($contratos as $contrato) {
            if ($contrato->cliente_email) {
                Notification::route('mail', $contrato->cliente_email)
                    ->notify(new ContratoRecordatorioFirma($contrato));
            }
            $contrato->agente->notify(new ContratoPorExpirar($contrato));
            $contrato->registrarEvento('recordatorio');
        }

        return $contratos->count();
    }

    /** Marca Vencido los contratos Firmados cuya vigencia terminó. */
    public function marcarVencidos(): int
    {
        $contratos = ContratoIntermediacion::query()
            ->where('estado', EstadoContrato::Firmado->value)
            ->whereNotNull('vigencia_fin')
            ->whereDate('vigencia_fin', '<', now())
            ->get();

        foreach ($contratos as $contrato) {
            $contrato->transicionarA(EstadoContrato::Vencido);
        }

        return $contratos->count();
    }

    /**
     * Marca los contratos que cumplieron su retención (2 años desde la firma) para eliminación
     * pendiente y avisa al Owner. NO borra: la eliminación la confirma el Owner (D-10 / R-7).
     */
    public function marcarRetencionPendiente(): int
    {
        $contratos = ContratoIntermediacion::query()
            ->whereIn('estado', [EstadoContrato::Firmado->value, EstadoContrato::Vencido->value])
            ->where('eliminacion_pendiente', false)
            ->whereNotNull('retencion_revisar_at')
            ->where('retencion_revisar_at', '<=', now())
            ->get();

        if ($contratos->isEmpty()) {
            return 0;
        }

        $owners = User::query()
            ->whereHas('roles', fn (Builder $q) => $q->where('roles.name', 'owner'))
            ->get();

        foreach ($contratos as $contrato) {
            // eliminacion_pendiente no es fillable (dato de sistema): asignación directa.
            $contrato->eliminacion_pendiente = true;
            $contrato->save();

            Notification::send($owners, new ContratoRetencionPendiente($contrato));
        }

        return $contratos->count();
    }

    private function yaRecordado(ContratoIntermediacion $contrato): bool
    {
        return $contrato->eventos()
            ->where('tipo', 'recordatorio')
            ->where('created_at', '>=', $contrato->enviado_at)
            ->exists();
    }
}
