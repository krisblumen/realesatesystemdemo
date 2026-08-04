<?php

namespace App\Console\Commands;

use App\Enums\TenantEstado;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Marca vencidos los inquilinos que pasaron su fecha. NO borra nada.
 *
 * Marcar y borrar son dos cosas separadas a propósito: marcar es barato,
 * inmediato y confiable; borrar es caro, irreversible y puede fallar. Así el
 * corte de acceso no depende de que el borrado funcione.
 *
 * La ventana que queda en el medio —ya no entra, sus datos existen— es
 * deliberada: da margen para atender un reclamo antes de que sea imposible.
 */
class ExpirarInquilinos extends Command
{
    protected $signature = 'demo:expirar';

    protected $description = 'Marca como expirados los inquilinos que pasaron su fecha';

    public function handle(): int
    {
        $vencidos = Tenant::query()
            ->where('estado', TenantEstado::Activo->value)
            ->where('expira_en', '<=', now())
            ->get();

        foreach ($vencidos as $tenant) {
            $tenant->pasarA(TenantEstado::Expirado);
            $this->components->info("Expirado: {$tenant->slug}");
        }

        $this->components->info($vencidos->isEmpty() ? 'Nada que expirar.' : $vencidos->count().' inquilino(s) expirados.');

        return self::SUCCESS;
    }
}
