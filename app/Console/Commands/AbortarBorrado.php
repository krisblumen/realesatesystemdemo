<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Tenancy\BorraInquilinos;
use Illuminate\Console\Command;

/**
 * Deshace el cierre de puerta de un borrado que no se completó.
 *
 * EXISTE POR UN ESTADO QUE NO PARECE UN ERROR. Si el borrado muere entre
 * `CONNECTION LIMIT 0` y el `DROP` —el proceso se mata, se reinicia el VPS— la
 * base queda viva pero inalcanzable: el padrón muestra un inquilino sano y ese
 * inquilino no abre.
 *
 * Sin este comando, atender ese caso significa entrar por tinker o por SQL a
 * ejecutar a mano lo que el diseño ya había previsto ofrecer. El método existía;
 * lo que faltaba era el camino para usarlo.
 */
class AbortarBorrado extends Command
{
    protected $signature = 'demo:abortar-borrado {--slug= : Inquilino cuya puerta hay que reabrir}';

    protected $description = 'Restaura el acceso a un inquilino cuyo borrado quedó a medias';

    public function handle(BorraInquilinos $borrador): int
    {
        $slug = $this->option('slug');

        if (! $slug) {
            $this->components->error('Hace falta --slug: abortar es una acción sobre un inquilino concreto.');

            return self::FAILURE;
        }

        $tenant = Tenant::query()->firstWhere('slug', $slug);

        if ($tenant === null) {
            $this->components->error("No hay ningún inquilino con slug «{$slug}».");

            return self::FAILURE;
        }

        $borrador->abortar($tenant);

        $this->components->info("Puerta reabierta para «{$tenant->slug}». El inquilino vuelve a aceptar conexiones.");
        $this->line('  El estado NO cambia: si estaba expirado, sigue expirado.');

        return self::SUCCESS;
    }
}
