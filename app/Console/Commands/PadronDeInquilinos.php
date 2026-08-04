<?php

namespace App\Console\Commands;

use App\Enums\TenantEstado;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * El padrón: qué inquilinos hay y qué les pasó.
 *
 * NO MUESTRA NADA DE ADENTRO de ningún inquilino, y la garantía es estructural:
 * este comando consulta SÓLO la conexión central. No abre la base de nadie, así
 * que no hay desde dónde leer el contenido aunque alguien lo intentara.
 *
 * Tampoco hay «entrar como». Un demo es exactamente el lugar donde esa función
 * se ve peor: el producto que se está mostrando promete que los datos de un
 * cliente son de ese cliente.
 *
 * En fase 1 es de consola, como el resto del flujo. La pantalla web de RFC-12
 * llega cuando el demo se abra y haya un operador que no sea quien invita.
 */
class PadronDeInquilinos extends Command
{
    protected $signature = 'demo:padron {--estado= : Filtrar por estado}';

    protected $description = 'Lista los inquilinos del demo y su estado';

    public function handle(): int
    {
        $inquilinos = Tenant::query()
            ->when($this->option('estado'), fn ($q) => $q->where('estado', $this->option('estado')))
            ->orderByDesc('created_at')
            ->get();

        if ($inquilinos->isEmpty()) {
            $this->components->info('No hay inquilinos.');

            return self::SUCCESS;
        }

        $this->table(
            ['Slug', 'Estado', 'Nació', 'Vence', 'Plantilla', 'Motivo de falla'],
            $inquilinos->map(fn (Tenant $t): array => [
                $t->slug,
                $t->estado->value,
                $t->created_at?->format('Y-m-d'),
                $t->expira_en?->format('Y-m-d'),
                $t->template_version,
                // El correo NO se muestra: no hace falta para operar y es el
                // único dato personal del padrón.
                $t->motivo_falla === null ? '' : mb_substr($t->motivo_falla, 0, 60),
            ])->all(),
        );

        $activos = $inquilinos->where('estado', TenantEstado::Activo)->count();
        $this->components->info("{$activos} activo(s) de {$inquilinos->count()} registrado(s).");

        return self::SUCCESS;
    }
}
