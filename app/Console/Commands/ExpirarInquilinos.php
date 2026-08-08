<?php

namespace App\Console\Commands;

use App\Enums\TenantEstado;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\Config;

/**
 * Marca vencidos los inquilinos que pasaron su fecha. NO borra nada.
 *
 * Marcar y borrar son dos cosas separadas a propósito: marcar es barato,
 * inmediato y confiable; borrar es caro, irreversible y puede fallar. Así el
 * corte de acceso no depende de que el borrado funcione.
 *
 * La ventana que queda en el medio —ya no entra, sus datos existen— es
 * deliberada: da margen para atender un reclamo antes de que sea imposible.
 *
 * EXPIRA POR DOS MOTIVOS, y el segundo llegó con el registro público:
 *
 *  1. Se pasó de su fecha. El plazo que se le prometió.
 *  2. Nadie entró en `tenancy.dias_sin_uso` días. La mayoría de las altas
 *     públicas no vuelve nunca, y esa base ocupa disco y conexiones —de las 100
 *     que compartimos con la producción vecina— igual que una que se usa.
 *
 * El segundo SÓLO ACORTA. Un demo que se usa sigue venciendo en su fecha, nunca
 * después: `expira_en` se fija al crear y nada lo mueve.
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
                fn ($q) => $q->where(fn ($q) => $q
                    ->where('expira_en', '<=', now())
                    ->orWhere(fn ($q) => $this->sinUso($q)),

                ),
            )
            ->get();

        foreach ($vencidos as $tenant) {
            $tenant->pasarA(TenantEstado::Expirado);

            // POR QUÉ, y no sólo que pasó. Un operador que ve un demo cortado
            // antes de su fecha necesita saber si fue el plazo o el desuso: es
            // la diferencia entre «funcionó como se esperaba» y «bajamos
            // demasiado el número».
            $motivo = $tenant->expira_en->isPast() ? 'venció su plazo' : 'nadie entró';

            $this->components->info("Expirado: {$tenant->slug} ({$motivo})");
        }

        $this->components->info($vencidos->isEmpty() ? 'Nada que expirar.' : $vencidos->count().' inquilino(s) expirados.');

        return self::SUCCESS;
    }

    /**
     * Los que nadie tocó en el plazo de desuso.
     *
     * `COALESCE` y no dos consultas: `ultimo_acceso_en` en `null` significa que
     * nadie entró NUNCA, y ahí el reloj corre desde el alta. Tratarlo aparte
     * daría el mismo resultado con el doble de código y una rama más donde
     * equivocarse.
     */
    private function sinUso(Builder $query): Builder
    {
        $dias = (int) Config::get('tenancy.dias_sin_uso', 0);

        // Cero lo apaga. Sin esta salida, poner cero expiraría TODO lo activo en
        // el siguiente barrido — el interruptor de apagado sería el botón de
        // demolición.
        if ($dias <= 0) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereRaw(
            'COALESCE(ultimo_acceso_en, created_at) <= ?',
            [now()->subDays($dias)],
        );
    }
}
