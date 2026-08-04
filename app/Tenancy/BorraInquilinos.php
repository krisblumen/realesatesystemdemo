<?php

namespace App\Tenancy;

use App\Enums\TenantEstado;
use App\Models\Tenant;
use DomainException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Borra un inquilino vencido: su base y sus archivos.
 *
 * El orden de los pasos no es negociable y cada uno está por un motivo
 * distinto. Escribirlos en otro orden no produce un borrado más lento: produce
 * un borrado que falla, o uno que deja el sistema peor que antes de empezar.
 */
class BorraInquilinos
{
    /**
     * Borra, y se puede reintentar.
     *
     * Cada paso comprueba si ya está hecho: un borrado interrumpido a la mitad
     * no puede quedar en un estado que sólo se arregle a mano.
     */
    public function borrar(Tenant $tenant): void
    {
        $this->negarseSiNoEsDeUnInquilino($tenant);

        if ($this->existe($tenant->database)) {
            // 1. CERRAR LA PUERTA. Terminar las sesiones sin esto deja una
            //    ventana entre terminar y borrar por la que el navegador
            //    reconecta y el DROP falla. Con pestañas abiertas eso no es
            //    raro: es lo normal.
            //
            //    Se usa CONNECTION LIMIT 0 en vez de revocar permisos porque es
            //    una sola sentencia, no hay que enumerar roles, y se deshace
            //    igual de fácil si el borrado se aborta.
            $this->cerrarLaPuerta($tenant);

            // 2. Terminar lo que ya estaba adentro.
            $this->terminarSesiones($tenant->database);

            // 3. Borrar la base ANTES que los archivos. Si se borraran los
            //    archivos primero y el DROP fallara, quedaría un inquilino vivo
            //    con las imágenes rotas: un estado peor que no haber empezado.
            DB::connection('maintenance')->statement('DROP DATABASE IF EXISTS '.$this->citar($tenant->database));
        }

        // 4. Los archivos. Media huérfana en disco es una fuga con retardo: el
        //    inquilino ya no existe, la fila ya no lo apunta, y los bytes siguen
        //    ahí servidos por una ruta que alguien puede tener guardada.
        Storage::disk(Config::get('media-library.disk_name', 'public'))
            ->deleteDirectory('tenants/'.$tenant->slug);

        // 5. La fila SOBREVIVE. Sirve para medir el uso del demo y para que el
        //    padrón pueda decir qué pasó con cada inquilino.
        //
        //    Sólo transiciona el que PUEDE. `fallido` es terminal para el ciclo de
        // vida y aun así se le barre la base: son dos cosas distintas y hay que
        // no confundirlas. Un alta que murió después del CREATE DATABASE deja
        // una base viva sin dueño, pero el inquilino nunca llegó a existir —
        // marcarlo «borrado» diría que estuvo activo y no lo estuvo.
        if ($tenant->estado->puedePasarA(TenantEstado::Borrado)) {
            $tenant->pasarA(TenantEstado::Borrado);
        }
    }

    /**
     * Impide nuevas conexiones sin tocar permisos ni roles.
     */
    public function cerrarLaPuerta(Tenant $tenant): void
    {
        $this->negarseSiNoEsDeUnInquilino($tenant);

        DB::connection('maintenance')->statement(
            'ALTER DATABASE '.$this->citar($tenant->database).' CONNECTION LIMIT 0',
        );
    }

    /**
     * Deshace el cierre de puerta cuando se aborta un borrado.
     *
     * Sin este camino, un borrado abortado —por un reclamo del inquilino, por
     * ejemplo— deja la base con el límite en cero: el padrón muestra un
     * inquilino sano y ese inquilino no abre. Es el peor estado posible, porque
     * no parece un error.
     */
    public function abortar(Tenant $tenant): void
    {
        $this->negarseSiNoEsDeUnInquilino($tenant);

        DB::connection('maintenance')->statement(
            'ALTER DATABASE '.$this->citar($tenant->database).' CONNECTION LIMIT -1',
        );
    }

    /**
     * La última red.
     *
     * Un borrado que pudiera nombrar la central se llevaría el padrón entero;
     * uno que nombrara la plantilla dejaría al demo sin poder dar de alta a
     * nadie. Se comprueba pegado al uso, no donde nace el nombre.
     */
    private function negarseSiNoEsDeUnInquilino(Tenant $tenant): void
    {
        $prohibidas = array_filter([
            Config::get('database.connections.central.database'),
            Config::get('database.connections.pgsql.database'),
            Config::get('tenancy.plantilla_vigente'),
        ]);

        if (in_array($tenant->database, $prohibidas, true)) {
            throw new DomainException("Un borrado no puede nombrar «{$tenant->database}».");
        }

        // Los prefijos salen de configuración y no de un literal: el de pruebas
        // es distinto del de producción A PROPÓSITO, para que un barrido de
        // tests jamás pueda rozar una base real.
        //
        // Y EL DE PRUEBAS SÓLO VALE EN EL ENTORNO DE PRUEBAS. Aceptarlo siempre
        // ampliaba en producción la lista de bases que la última red deja
        // borrar: una fila mal cargada con ese prefijo habría pasado el control.
        $prefijos = array_filter([
            (string) Config::get('tenancy.prefijo_inquilino'),
            app()->environment('testing') ? (string) Config::get('tenancy.prefijo_pruebas') : null,
        ]);

        foreach ($prefijos as $prefijo) {
            if (str_starts_with($tenant->database, $prefijo)) {
                return;
            }
        }

        throw new DomainException("«{$tenant->database}» no tiene el prefijo de una base de inquilino.");
    }

    private function terminarSesiones(string $base): void
    {
        DB::connection('maintenance')->select(
            'SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = ? AND pid <> pg_backend_pid()',
            [$base],
        );
    }

    private function existe(string $base): bool
    {
        return DB::connection('maintenance')
            ->selectOne('SELECT 1 AS x FROM pg_database WHERE datname = ?', [$base]) !== null;
    }

    private function citar(string $identificador): string
    {
        return '"'.str_replace('"', '""', $identificador).'"';
    }
}
