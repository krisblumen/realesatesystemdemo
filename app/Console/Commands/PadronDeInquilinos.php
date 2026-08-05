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
    protected $signature = 'demo:padron {--estado= : Filtrar por estado} {--correos : Mostrar el correo de cada inquilino}';

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

        // EL CORREO SALE SÓLO SI SE PIDE.
        //
        // La decisión original era no mostrarlo nunca —«no hace falta para
        // operar»— y el primer despliegue real la desmintió: sin el correo no se
        // sabe de quién es un inquilino ni con qué usuario se entra, y la salida
        // fue abrir `tinker`. Un padrón que obliga a escribir consultas a mano no
        // es un padrón.
        //
        // Pero sigue siendo el único dato personal de esta tabla, y esta tabla es
        // lo que uno muestra en una pantalla compartida o pega en un chat. Por
        // eso se pide, y no viene puesto.
        $conCorreos = (bool) $this->option('correos');

        $encabezados = ['Slug', 'Estado', 'Nació', 'Vence', 'Plantilla', 'Intentos', 'Motivo de falla'];

        if ($conCorreos) {
            array_splice($encabezados, 1, 0, ['Correo']);
        }

        $this->table(
            $encabezados,
            $inquilinos->map(function (Tenant $t) use ($conCorreos): array {
                $fila = [
                    $t->slug,
                    $t->estado->value,
                    $t->created_at?->format('Y-m-d'),
                    $t->expira_en?->format('Y-m-d'),
                    $t->template_version,
                    // Distingue «falló recién» de «lleva noches fallando».
                    $t->intentos_de_borrado > 0 ? (string) $t->intentos_de_borrado : '',
                    $t->motivo_falla === null ? '' : mb_substr($t->motivo_falla, 0, 60),
                ];

                if ($conCorreos) {
                    array_splice($fila, 1, 0, [$t->email]);
                }

                return $fila;
            })->all(),
        );

        $activos = $inquilinos->where('estado', TenantEstado::Activo)->count();
        $this->components->info("{$activos} activo(s) de {$inquilinos->count()} registrado(s).");

        return self::SUCCESS;
    }
}
