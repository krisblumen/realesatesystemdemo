<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Tenancy\BorraInquilinos;
use Illuminate\Console\Command;
use Throwable;

/**
 * Borra las bases de los inquilinos que ya no las necesitan.
 *
 * Barre por `paraBarrer()` y NO por «estado terminal», que es la trampa:
 * `fallido` es terminal y aun así puede tener una base viva si el alta murió
 * después del CREATE DATABASE. Un barrido que filtrara sólo `expirado` la
 * dejaría ahí para siempre, ocupando conexiones y disco, con el padrón
 * mostrándola como si no existiera.
 */
class BorrarInquilinos extends Command
{
    protected $signature = 'demo:borrar {--slug= : Borrar sólo este inquilino}';

    protected $description = 'Borra la base y los archivos de los inquilinos expirados o fallidos';

    public function handle(BorraInquilinos $borrador): int
    {
        $inquilinos = Tenant::query()
            ->paraBarrer()
            ->when($this->option('slug'), fn ($q) => $q->where('slug', $this->option('slug')))
            ->get();

        $fallidos = 0;

        foreach ($inquilinos as $tenant) {
            try {
                $borrador->borrar($tenant);
                $this->components->info("Borrado: {$tenant->slug}");
            } catch (Throwable $e) {
                // No se reintenta para siempre en silencio: queda anotado para
                // que el padrón lo muestre y alguien decida.
                $fallidos++;
                $tenant->forceFill(['motivo_falla' => mb_substr($e->getMessage(), 0, 2000)])->save();
                $this->components->error("No se pudo borrar {$tenant->slug}: ".$e->getMessage());
            }
        }

        $this->components->info($inquilinos->count().' revisado(s), '.$fallidos.' con problemas.');

        return $fallidos > 0 ? self::FAILURE : self::SUCCESS;
    }
}
