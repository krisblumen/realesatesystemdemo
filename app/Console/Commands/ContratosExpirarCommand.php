<?php

namespace App\Console\Commands;

use App\Services\Contratos\ContratoAutomatizacionService;
use Illuminate\Console\Command;

class ContratosExpirarCommand extends Command
{
    protected $signature = 'contratos:expirar {--recordatorio-horas=48 : Horas para el recordatorio previo}';

    protected $description = 'Marca Expirado los contratos sin respuesta y envía recordatorios de firma pendiente.';

    public function handle(ContratoAutomatizacionService $service): int
    {
        $recordados = $service->enviarRecordatorios((int) $this->option('recordatorio-horas'));
        $expirados = $service->expirarSinRespuesta();

        $this->info("Recordatorios enviados: {$recordados}. Contratos expirados: {$expirados}.");

        return self::SUCCESS;
    }
}
