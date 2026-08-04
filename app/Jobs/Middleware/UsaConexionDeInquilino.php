<?php

namespace App\Jobs\Middleware;

use App\Tenancy\CorreParaInquilino;
use Closure;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Apunta la conexión por defecto a la base del inquilino del trabajo, y la
 * devuelve a su lugar al terminar.
 *
 * EXISTE PORQUE LA DISCIPLINA NO ES PROTECCIÓN. El diseño podría decir «cada
 * trabajo resuelve y restaura su conexión», y sería cierto hasta el primer
 * trabajo que alguien escriba sin acordarse. Como middleware obligatorio, el
 * trabajo declara de quién es y el resto ocurre solo.
 *
 * El modo de falla que cubre no lanza excepción: un worker es un proceso largo
 * que atiende inquilinos distintos uno detrás del otro, y si un trabajo deja la
 * conexión movida, el siguiente escribe —bien— en la base equivocada.
 */
class UsaConexionDeInquilino
{
    /**
     * El valor con el que arrancó el proceso.
     *
     * Se captura UNA vez y se restaura siempre a él, nunca a «lo que había
     * antes de este trabajo». Restaurar al valor anterior encadenaría los
     * trabajos: si uno no restauró, el siguiente volvería a la base de ese
     * inquilino y el problema sobreviviría disfrazado de correcto.
     *
     * Cuando el lote D introduzca el centinela, ese pasa a ser este valor sin
     * tocar esta clase.
     */
    private static ?string $alArrancar = null;

    public function handle(object $trabajo, Closure $next): mixed
    {
        self::$alArrancar ??= Config::get('database.connections.pgsql.database');

        $base = $trabajo instanceof CorreParaInquilino
            ? $trabajo->baseDeInquilino()
            : null;

        if ($base === null) {
            return $next($trabajo);
        }

        $this->apuntarA($base);

        try {
            return $next($trabajo);
        } finally {
            // En `finally` y no al final del camino feliz: una excepción que
            // dejara la conexión movida contaminaría todos los trabajos
            // siguientes del worker, en silencio.
            $this->apuntarA(self::$alArrancar);
        }
    }

    private function apuntarA(?string $base): void
    {
        Config::set('database.connections.pgsql.database', $base);

        // Descartar la conexión viva no es opcional acá, y es la diferencia con
        // una petición web: en una petición nada se conectó todavía cuando
        // corre el middleware. En un worker la conexión YA está abierta contra
        // la base del trabajo anterior, así que cambiar la configuración sola
        // no mueve nada.
        DB::purge('pgsql');
    }
}
