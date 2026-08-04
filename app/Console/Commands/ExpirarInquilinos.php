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
    protected $signature = 'demo:expirar {--slug= : Vencer AHORA este inquilino, sin esperar su fecha}';

    protected $description = 'Marca como expirados los inquilinos que pasaron su fecha';

    public function handle(): int
    {
        // Con `--slug` es una acción del operador —«cortá este demo hoy»— y no
        // el barrido por fecha. Sin ella, atender un pedido así termina siendo
        // un UPDATE a mano en la base central, sin rastro de nada.
        $vencidos = Tenant::query()
            ->where('estado', TenantEstado::Activo->value)
            ->when(
                $this->option('slug'),
                fn ($q) => $q->where('slug', $this->option('slug')),
                fn ($q) => $q->where('expira_en', '<=', now()),
            )
            ->get();

        foreach ($vencidos as $tenant) {
            $tenant->pasarA(TenantEstado::Expirado);
            $this->components->info("Expirado: {$tenant->slug}");
        }

        $this->components->info($vencidos->isEmpty() ? 'Nada que expirar.' : $vencidos->count().' inquilino(s) expirados.');

        return self::SUCCESS;
    }
}
