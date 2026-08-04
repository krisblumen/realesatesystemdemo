<?php

namespace App\Console\Commands;

use Database\Seeders\DemoTemplateSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
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
            $this->apuntar($nombre);

            // `--database` explícito, y NO sólo la conexión por defecto mutada.
            //
            // Mutar `pgsql` y purgar hacía que el REGISTRO de las migraciones
            // fuera a la plantilla y el DDL de alguna no: quedaban 46
            // migraciones anotadas como corridas y la tabla `permissions` sin
            // crear, así que la plantilla nacía rota y decía estar completa. Un
            // nombre de conexión propio no deja lugar a esa ambigüedad.
            $this->components->task('Migrando', fn (): bool => $this->callSilent('migrate', [
                '--database' => self::CONEXION,
                '--force' => true,
            ]) === 0 || throw new RuntimeException('Falló la migración de la plantilla.'));

            $this->components->task('Sembrando', fn (): bool => $this->callSilent('db:seed', [
                '--class' => DemoTemplateSeeder::class,
                '--database' => self::CONEXION,
                '--force' => true,
            ]) === 0 || throw new RuntimeException('Falló el sembrado de la plantilla.'));

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
    }

    private function recrear(string $nombre): void
    {
        $this->components->task("Creando {$nombre}", function () use ($nombre): bool {
            // `CREATE DATABASE` no corre dentro de una transacción ni sobre la
            // base que se está creando: por eso la conexión de mantenimiento.
            DB::connection('maintenance')->statement('DROP DATABASE IF EXISTS '.$this->citar($nombre));
            DB::connection('maintenance')->statement('CREATE DATABASE '.$this->citar($nombre));

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
