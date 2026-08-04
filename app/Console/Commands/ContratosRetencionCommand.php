<?php

namespace App\Console\Commands;

use App\Services\Contratos\ContratoAutomatizacionService;
use Illuminate\Console\Command;

class ContratosRetencionCommand extends Command
{
    protected $signature = 'contratos:retencion';

    protected $description = 'Marca para eliminación pendiente los contratos que cumplieron 2 años y avisa al Owner (no borra).';

    public function handle(ContratoAutomatizacionService $service): int
    {
        $pendientes = $service->marcarRetencionPendiente();

        $this->info("Contratos movidos a eliminación pendiente: {$pendientes}.");

        return self::SUCCESS;
    }
}
