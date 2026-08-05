<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

/**
 * Corre un comando artisan una vez por inquilino activo, cada uno en su base.
 *
 * POR QUÉ EXISTE. Un comando de consola no tiene subdominio del cual resolver un
 * inquilino, así que su conexión por defecto se queda en el centinela. Las
 * tareas que tocan datos de un inquilino —leads, media, contratos— agendadas de
 * forma global apuntan ahí y mueren. Vinieron así de la plataforma de origen,
 * donde había una sola base y la distinción no existía.
 *
 * EN UN PROCESO APARTE, y es el mismo motivo que en la construcción de la
 * plantilla: reapuntar la conexión por defecto dentro de un proceso vivo deja
 * atrás todo lo que ya la resolvió y memoizó —el caché es el caso clásico—, así
 * que un recorrido de veinte inquilinos acumularía veinte oportunidades de que
 * algo escriba en la base del anterior. El hijo arranca leyendo `DB_DATABASE`:
 * su conexión ES la del inquilino desde la primera línea, y muere con el
 * proceso.
 *
 * Cuesta un arranque de Laravel por inquilino. Se paga con gusto: la alternativa
 * es que el aislamiento dependa de que nadie se olvide de limpiar un singleton.
 */
class PorCadaInquilino extends Command
{
    protected $signature = 'demo:por-cada-inquilino {comando : El comando artisan a correr en cada inquilino}';

    protected $description = 'Corre un comando artisan una vez por inquilino activo, cada uno contra su propia base';

    /**
     * Comandos que este recorrido no ejecuta nunca.
     *
     * Un recorrido por TODOS los inquilinos es el peor lugar posible para que se
     * cuele algo destructivo: multiplica el daño por la cantidad de gente que
     * confió en el demo, y lo hace sin preguntar porque viene del programador de
     * tareas.
     *
     * @var array<int, string>
     */
    private const PROHIBIDOS = [
        'migrate:fresh',
        'migrate:refresh',
        'migrate:reset',
        'migrate:rollback',
        'db:wipe',
    ];

    public function handle(): int
    {
        $comando = trim((string) $this->argument('comando'));
        $partes = preg_split('/\s+/', $comando) ?: [];
        $nombre = $partes[0] ?? '';

        if ($nombre === '') {
            $this->components->error('Falta el comando a correr.');

            return self::FAILURE;
        }

        if (in_array($nombre, self::PROHIBIDOS, true)) {
            $this->components->error("«{$nombre}» no se corre sobre todos los inquilinos.");
            $this->line('  Si hace falta en uno puntual, se hace a mano y a conciencia.');

            return self::FAILURE;
        }

        $inquilinos = Tenant::query()->queRecibenTareas()->orderBy('id')->get();

        if ($inquilinos->isEmpty()) {
            $this->components->info('No hay inquilinos activos.');

            return self::SUCCESS;
        }

        $fallaron = 0;

        foreach ($inquilinos as $inquilino) {
            $resultado = Process::path(base_path())
                ->env([
                    'DB_DATABASE' => $inquilino->database,
                ])
                // Generoso a propósito: se prefiere una tarea lenta a una tarea
                // cortada a la mitad, que en un reconciliador deja el trabajo
                // hecho por partes.
                ->timeout(300)
                // En arreglo y no en cadena: así no pasa por una shell, y el
                // nombre del comando no puede convertirse en otra cosa.
                ->run(array_merge(['php', 'artisan'], $partes));

            if ($resultado->successful()) {
                $this->components->twoColumnDetail($inquilino->slug, '<fg=green>ok</>');

                continue;
            }

            $fallaron++;

            // Se REPORTA y se SIGUE. Con veinte inquilinos, que el tercero
            // tumbe a los diecisiete de atrás convierte un fallo puntual en un
            // apagón.
            $this->components->twoColumnDetail($inquilino->slug, '<fg=red>falló</>');
            $this->line('  '.trim($resultado->errorOutput() ?: $resultado->output()));
        }

        if ($fallaron > 0) {
            $this->newLine();
            $this->components->error("«{$nombre}» falló en {$fallaron} de {$inquilinos->count()} inquilino(s).");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
