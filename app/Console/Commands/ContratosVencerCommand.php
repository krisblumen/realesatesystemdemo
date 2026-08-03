<?php

namespace App\Console\Commands;

use App\Services\Contratos\ContratoAutomatizacionService;
use Illuminate\Console\Command;

class ContratosVencerCommand extends Command
{
    protected $signature = 'contratos:vencer';

    protected $description = 'Marca Vencido los contratos firmados cuya vigencia terminó.';

    public function handle(ContratoAutomatizacionService $service): int
    {
        $vencidos = $service->marcarVencidos();

        $this->info("Contratos vencidos: {$vencidos}.");

        return self::SUCCESS;
    }
}
