<?php

namespace Tests\Feature\Tenancy;

use App\Jobs\Middleware\UsaConexionDeInquilino;
use App\Tenancy\CorreParaInquilino;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Un trabajo en segundo plano no puede heredar la conexión del anterior.
 *
 * ESTE ES EL MODO DE FALLA MÁS SILENCIOSO DE LA ÉPICA. El worker es un proceso
 * largo que atiende inquilinos distintos uno detrás del otro. Si un trabajo deja
 * la conexión por defecto apuntando al inquilino A, el siguiente la hereda — y
 * no lanza ninguna excepción. Escribe, y escribe bien, en la base equivocada.
 *
 * Se descubre cuando alguien nota datos ajenos, que puede ser semanas después y
 * sin forma de saber cuántos trabajos pasaron por ahí.
 */
class ConexionDeTrabajosTest extends TestCase
{
    private const A = 'demo_probe_t_aaa';

    private const B = 'demo_probe_t_bbb';

    private string $original;

    protected function setUp(): void
    {
        parent::setUp();

        $this->original = config('database.connections.pgsql.database');

        foreach ([self::A, self::B] as $base) {
            DB::connection('maintenance')->statement('DROP DATABASE IF EXISTS "'.$base.'"');
            DB::connection('maintenance')->statement('CREATE DATABASE "'.$base.'"');
            $this->enLaBase($base, fn () => DB::statement('CREATE TABLE marca (quien text)'));
        }
    }

    protected function tearDown(): void
    {
        config(['database.connections.pgsql.database' => $this->original]);
        DB::purge('pgsql');

        foreach ([self::A, self::B] as $base) {
            DB::connection('maintenance')->statement('DROP DATABASE IF EXISTS "'.$base.'"');
        }

        parent::tearDown();
    }

    private function enLaBase(string $base, callable $fn): void
    {
        config(['database.connections.pgsql.database' => $base]);
        DB::purge('pgsql');
        $fn();
        config(['database.connections.pgsql.database' => $this->original]);
        DB::purge('pgsql');
    }

    private function filasDe(string $base): array
    {
        $filas = [];
        $this->enLaBase($base, function () use (&$filas): void {
            $filas = DB::table('marca')->pluck('quien')->all();
        });

        return $filas;
    }

    public function test_two_jobs_in_a_row_each_write_to_their_own_database(): void
    {
        // EL DEFECTO: sin el middleware, el segundo trabajo hereda la conexión
        // del primero y sus datos terminan en la base del inquilino A.
        (new UsaConexionDeInquilino)->handle(
            new TrabajoDePrueba(self::A),
            fn (TrabajoDePrueba $t) => $t->handle(),
        );

        (new UsaConexionDeInquilino)->handle(
            new TrabajoDePrueba(self::B),
            fn (TrabajoDePrueba $t) => $t->handle(),
        );

        $this->assertSame([self::A], $this->filasDe(self::A));
        $this->assertSame([self::B], $this->filasDe(self::B));
    }

    public function test_a_job_that_throws_still_restores_the_connection(): void
    {
        // Si la restauración viviera en el camino feliz, una excepción dejaría
        // la conexión movida y TODOS los trabajos posteriores del worker
        // escribirían en la base de ese inquilino.
        try {
            (new UsaConexionDeInquilino)->handle(
                new TrabajoDePrueba(self::A),
                fn () => throw new RuntimeException('revienta'),
            );
            $this->fail('La excepción tiene que propagarse.');
        } catch (RuntimeException) {
            // esperado
        }

        $this->assertSame(
            $this->original,
            config('database.connections.pgsql.database'),
            'La conexión quedó apuntando al inquilino después de una excepción.',
        );
    }

    public function test_restoring_returns_to_the_boot_value_not_to_the_previous_job(): void
    {
        // Restaurar «lo que había antes» encadenaría los trabajos: si A no
        // restauró, B restauraría a la base de A y el problema sobreviviría
        // disfrazado de correcto.
        (new UsaConexionDeInquilino)->handle(
            new TrabajoDePrueba(self::A),
            fn (TrabajoDePrueba $t) => $t->handle(),
        );

        $this->assertSame($this->original, config('database.connections.pgsql.database'));
    }

    public function test_a_job_without_a_tenant_never_reaches_a_tenant_database(): void
    {
        $visto = null;

        (new UsaConexionDeInquilino)->handle(
            new TrabajoSinInquilino,
            function () use (&$visto): void {
                $visto = config('database.connections.pgsql.database');
            },
        );

        $this->assertSame($this->original, $visto);
    }

    public function test_the_queue_lives_in_the_central_database(): void
    {
        // No puede vivir en la base del inquilino por una razón elemental: el
        // trabajo que crea esa base corre cuando la base todavía no existe.
        $this->assertSame('central', config('queue.connections.database.connection'));
    }
}

class TrabajoDePrueba implements CorreParaInquilino
{
    public function __construct(private string $base) {}

    public function baseDeInquilino(): ?string
    {
        return $this->base;
    }

    public function handle(): void
    {
        DB::table('marca')->insert(['quien' => $this->base]);
    }
}

class TrabajoSinInquilino
{
    public function handle(): void {}
}
