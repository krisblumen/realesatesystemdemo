<?php

namespace App\Console\Commands;

use Database\Seeders\DemoTemplateSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Construye una plantilla nueva. NO cambia cuál está vigente.
 *
 * Son dos actos separados a propósito: así se puede construir la siguiente
 * mientras la actual sigue sirviendo altas. Si construir cambiara la vigente,
 * habría una ventana en la que las altas copian una plantilla a medio sembrar.
 *
 * La plantilla se versiona en vez de migrarse en su lugar porque Postgres
 * rechaza copiar una plantilla que tenga cualquier conexión encima: migrar la
 * vigente sería una carrera contra cada alta.
 */
class BuildDemoTemplate extends Command
{
    protected $signature = 'demo:plantilla:construir
                            {nombre : Nombre de la base de la plantilla nueva}
                            {--force : No preguntar antes de reemplazar una plantilla existente}';

    protected $description = 'Crea, migra, siembra y verifica una plantilla de inquilino';

    /**
     * Conexión propia y efímera hacia la plantilla que se está construyendo.
     *
     * No se reutiliza `pgsql` apuntándola con `Config::set`: los sembradores y
     * las migraciones resuelven su conexión por caminos distintos, y basta con
     * que uno la resuelva antes del cambio para que escriba en otro lado. Con un
     * nombre propio, `--database` no deja lugar a interpretación.
     */
    private const CONEXION = 'plantilla_en_construccion';

    public function handle(): int
    {
        $nombre = (string) $this->argument('nombre');

        $this->validarNombre($nombre);

        if ($nombre === config('tenancy.plantilla_vigente') && ! $this->option('force')) {
            $this->error('Esa es la plantilla vigente. Construí una nueva y después cambiá la versión.');

            return self::FAILURE;
        }

        $anterior = Config::get('database.connections.pgsql.database');

        try {
            $this->recrear($nombre);

            // MIGRAR Y SEMBRAR EN UN PROCESO APARTE, con la base apuntada desde
            // el arranque.
            //
            // Hacerlo en este proceso no alcanza y ya falló dos veces. Reapuntar
            // la conexión por defecto a mitad de ejecución deja atrás todo lo
            // que ya la resolvió: en el lote B fue el registro de las
            // migraciones; acá fue el almacén de caché, del que Spatie guarda su
            // propia referencia al arrancar y que ninguna purga posterior mueve.
            //
            // Un proceso nuevo no tiene nada resuelto. Es la única forma de que
            // migraciones, sembradores, caché y registro de permisos vean todos
            // la misma base.
            $this->components->task('Migrando', fn (): bool => $this->enUnProcesoAparte($nombre, 'migrate --force'));

            $this->components->task('Sembrando', fn (): bool => $this->enUnProcesoAparte(
                $nombre,
                'db:seed --force --class='.str_replace('\\', '\\\\', DemoTemplateSeeder::class),
            ));

            $this->apuntar($nombre);

            $resumen = $this->verificar();
        } finally {
            Config::set('database.connections.pgsql.database', $anterior);
            Config::set('database.default', 'pgsql');
            DB::purge(self::CONEXION);
            DB::purge('pgsql');
        }

        $this->newLine();
        $this->components->info("Plantilla «{$nombre}» lista.");
        $this->table(['Qué', 'Cuánto'], collect($resumen)->map(fn ($v, $k) => [$k, $v])->values()->all());
        $this->comment('No se cambió la plantilla vigente. Para usarla: TENANCY_PLANTILLA='.$nombre);

        return self::SUCCESS;
    }

    /**
     * El nombre se interpola en DDL porque Postgres no acepta parámetros
     * enlazados para identificadores. Se valida contra un formato cerrado
     * inmediatamente antes de usarlo, y no sólo donde nace.
     */
    private function validarNombre(string $nombre): void
    {
        if (preg_match('/^[a-z][a-z0-9_]{3,62}$/', $nombre) !== 1) {
            throw new RuntimeException("Nombre de plantilla inválido: «{$nombre}».");
        }
    }

    /**
     * Registra la conexión efímera y la deja como la por defecto.
     *
     * Lo segundo hace falta porque los sembradores usan modelos, y un modelo sin
     * `$connection` declarada resuelve la por defecto.
     */
    private function apuntar(string $nombre): void
    {
        Config::set('database.connections.'.self::CONEXION, array_merge(
            Config::get('database.connections.pgsql'),
            ['database' => $nombre],
        ));
        Config::set('database.default', self::CONEXION);

        DB::purge(self::CONEXION);

        // EL ALMACÉN DE CACHÉ TAMBIÉN, y no es un detalle.
        //
        // El caché usa el driver `database` sin conexión declarada, así que
        // resuelve la por defecto — pero se memoiza la PRIMERA vez que alguien
        // lo pide. Si ya se resolvió contra `pgsql`, cambiar la por defecto no
        // lo mueve, y una migración que vacíe permisos escribe en la base
        // equivocada.
        //
        // En desarrollo eso no fallaba: `pgsql` apunta a una base que existe,
        // así que el borrado iba a otro lado y nadie se enteraba. Sólo apareció
        // en el servidor, donde esa conexión apunta al centinela y la base no
        // existe. Es exactamente para lo que el centinela está.
        Cache::forgetDriver(Config::get('cache.default'));
    }

    /**
     * Corre un comando artisan con `DB_DATABASE` apuntando a la plantilla.
     *
     * El proceso hijo arranca leyendo esa variable, así que su conexión por
     * defecto ES la plantilla desde la primera línea: no hay nada resuelto de
     * antes que pueda quedar apuntando a otro lado.
     */
    private function enUnProcesoAparte(string $nombre, string $comando): bool
    {
        $resultado = Process::path(base_path())
            ->env([
                'DB_DATABASE' => $nombre,

                // SE QUITAN las credenciales heredadas, para que el hijo lea las
                // del `.env` — que son las del rol de la APLICACIÓN.
                //
                // Este comando se invoca con el rol de aprovisionamiento, el
                // único con CREATEDB. Si el hijo lo heredara, las tablas
                // quedarían de ese rol: copiar una base preserva el dueño de
                // cada tabla, así que TODOS los inquilinos nacerían con tablas
                // que la aplicación no puede leer. El síntoma es cruel —el alta
                // reporta éxito y el primer request muere con «permission denied
                // for table»— y apareció en el primer inquilino real.
                //
                // Crear la base necesita privilegio; crear las TABLAS no. Cada
                // paso corre con el rol que le corresponde.
                'DB_USERNAME' => false,
                'DB_PASSWORD' => false,
            ])
            ->timeout(600)
            ->run('php artisan '.$comando);

        if (! $resultado->successful()) {
            throw new RuntimeException(
                "Falló «{$comando}» sobre la plantilla:\n".trim($resultado->errorOutput() ?: $resultado->output()),
            );
        }

        return true;
    }

    private function recrear(string $nombre): void
    {
        $this->components->task("Creando {$nombre}", function () use ($nombre): bool {
            // `CREATE DATABASE` no corre dentro de una transacción ni sobre la
            // base que se está creando: por eso la conexión de mantenimiento.
            DB::connection('maintenance')->statement('DROP DATABASE IF EXISTS '.$this->citar($nombre));
            $rol = (string) config('tenancy.rol_aplicacion');
            $dueno = $rol === '' ? '' : ' OWNER '.$this->citar($rol);

            DB::connection('maintenance')->statement('CREATE DATABASE '.$this->citar($nombre).$dueno);

            return true;
        });
    }

    /**
     * Verificar acá y no más tarde.
     *
     * Una plantilla mal sembrada no falla al construirse: falla en cada
     * inquilino que nazca de ella, y en un lugar que no señala a la plantilla.
     *
     * @return array<string, int|string>
     */
    private function verificar(): array
    {
        $owners = DB::table('users')
            ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', 'owner')
            ->count();

        if ($owners > 0) {
            throw new RuntimeException(
                'La plantilla tiene un usuario `owner`. Cada inquilino crea el suyo en el alta; '.
                'uno en la plantilla sería una cuenta que no es de nadie y con contraseña conocida.',
            );
        }

        $resumen = [
            'páginas del CMS' => DB::table('frontend_pages')->count(),
            'estados' => DB::table('states')->count(),
            'códigos postales' => DB::table('postal_codes')->count(),
            'características' => DB::table('features')->count(),
            'roles' => DB::table('roles')->count(),
            'zonas' => DB::table('zones')->count(),
            'inmuebles' => DB::table('properties')->count(),
            'clientes' => DB::table('property_owners')->count(),
            'agentes de muestra' => DB::table('users')->count(),
            'PostGIS' => DB::selectOne('SELECT postgis_version() AS v')->v,
        ];

        foreach (['páginas del CMS', 'roles', 'zonas', 'inmuebles', 'códigos postales'] as $clave) {
            if ((int) $resumen[$clave] === 0) {
                throw new RuntimeException("La plantilla quedó sin «{$clave}»: no sirve para dar de alta a nadie.");
            }
        }

        return $resumen;
    }

    private function citar(string $nombre): string
    {
        return '"'.str_replace('"', '""', $nombre).'"';
    }
}
