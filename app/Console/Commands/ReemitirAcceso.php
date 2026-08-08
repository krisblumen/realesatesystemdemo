<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Tenancy\ReemiteAcceso;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Le devuelve el acceso a un inquilino que perdió su contraseña.
 *
 * POR QUÉ EXISTE. La contraseña se muestra una sola vez al invitar y en la base
 * queda hasheada: no se recupera, se regenera. Sin este comando la única salida
 * era abrir `tinker` y escribir consultas a mano contra la base del inquilino —
 * que fue exactamente lo que hubo que hacer con el primer inquilino real.
 *
 * Eso no es operar. Es meter la mano adentro con la esperanza de no romper nada,
 * y deja al operador escribiendo el tipo de consulta que este diseño existe para
 * que nadie tenga que escribir.
 *
 * NO ES «ENTRAR COMO». No abre una sesión ni muestra nada del contenido del
 * inquilino: cambia una contraseña y la imprime. Quien entra sigue siendo la
 * persona invitada, con su usuario. La distinción importa porque el producto que
 * este demo muestra promete que los datos de un cliente son de ese cliente.
 */
class ReemitirAcceso extends Command
{
    /**
     * El nombre de la conexión que abre este comando.
     *
     * Propia y no compartida con la del alta: cada operación es dueña de su
     * ranura, así que purgar una nunca deja a otra con una conexión a la base
     * equivocada. Es la misma razón por la que el alta tiene la suya.
     */
    private const CONEXION = 'inquilino_en_reemision';

    protected $signature = 'demo:reemitir-acceso {slug : El slug del inquilino}';

    protected $description = 'Genera una contraseña nueva para el owner de un inquilino';

    public function handle(ReemiteAcceso $reemite): int
    {
        $slug = (string) $this->argument('slug');

        $tenant = Tenant::query()->where('slug', $slug)->first();

        if ($tenant === null) {
            $this->components->error("No hay ningún inquilino con el slug «{$slug}».");
            $this->line('  `php artisan demo:padron` los lista.');

            return self::FAILURE;
        }

        // Se pregunta por `resuelvePeticiones()` y no por el estado: si el
        // inquilino no puede atender una petición, devolverle una contraseña no
        // le sirve de nada. Y uno expirado o borrado puede no tener base, así
        // que el comando moriría hablando de Postgres en vez de decir qué pasa.
        if (! $tenant->estado->resuelvePeticiones()) {
            $this->components->error("El inquilino «{$slug}» está {$tenant->estado->value}.");
            $this->line('  Sólo un inquilino activo puede recibir acceso.');

            return self::FAILURE;
        }

        $password = $reemite->para($this->conexionA($tenant->database), $tenant->email);

        if ($password === null) {
            $this->components->error("El inquilino «{$slug}» no tiene un usuario «{$tenant->email}».");
            $this->line('  Es un alta que se cayó después de copiar la base.');
            $this->line('  Conviene expirarlo e invitar de nuevo antes que remendarlo.');

            return self::FAILURE;
        }

        $this->mostrarAcceso($tenant, $password);

        return self::SUCCESS;
    }

    private function conexionA(string $base): Connection
    {
        Config::set('database.connections.'.self::CONEXION, array_merge(
            Config::get('database.connections.pgsql'),
            ['database' => $base],
        ));

        DB::purge(self::CONEXION);

        return DB::connection(self::CONEXION);
    }

    private function mostrarAcceso(Tenant $tenant, string $password): void
    {
        $dominio = config('tenancy.dominio_base', 'demo.localhost');

        $this->newLine();
        $this->components->info('Acceso reemitido.');

        $this->table(['Dato', 'Valor'], [
            ['Dirección', "https://{$tenant->slug}.{$dominio}/admin"],
            ['Usuario', $tenant->email],
            ['Contraseña', $password],
            ['Vence', $tenant->expira_en?->format('Y-m-d')],
        ]);

        $this->components->warn('La contraseña no se vuelve a mostrar. Cópiala ahora.');
        $this->line('  La anterior dejó de servir, y las sesiones recordadas también.');
    }
}
