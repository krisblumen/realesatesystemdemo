<?php

namespace Tests\Feature\Tenancy;

use App\Jobs\AprovisionaUnInquilino;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Concerns\UsaBaseCentral;
use Tests\TestCase;

/**
 * El alta vive en un trabajo, y cada camino lo despacha como le conviene.
 *
 * POR QUÉ UN TRABAJO. El registro público no puede sostener una petición HTTP
 * mientras se copia una base: responde al instante y el alta ocurre atrás. Pero
 * `demo:invitar` lo corre alguien frente a una terminal, que quiere saber si
 * funcionó y ver el acceso — un «encolado» lo obliga a ir al padrón a averiguar
 * si salió bien.
 *
 * Misma maquinaria, dos formas de despacharla. Una línea de diferencia entre
 * `dispatchSync` y `dispatch`, y evita el problema real: dos implementaciones
 * del alta que se separan con el tiempo.
 *
 * VA EN SU PROPIA COLA, y no es organización. El alta necesita crear bases, y
 * ese privilegio lo tiene `demo_provisioner` — no `demo_app`, con el que corre
 * la aplicación. Con una cola aparte, el único proceso que lleva ese privilegio
 * es el que procesa altas, en vez del worker general que atiende todo.
 */
class AltaEnColaTest extends TestCase
{
    use UsaBaseCentral;

    public function test_the_job_travels_on_its_own_queue(): void
    {
        // Si cayera en la cola general, el worker que corre con `demo_app` la
        // tomaría y moriría con «permission denied to create database» — y el
        // trabajo se reintentaría hasta agotar los intentos.
        Queue::fake();

        AprovisionaUnInquilino::dispatch('invitado@ejemplo.com');

        Queue::assertPushedOn('altas', AprovisionaUnInquilino::class);
    }

    public function test_a_failed_job_can_still_be_recorded(): void
    {
        // EL FALLO DEL FALLO, que es el que deja sin rastro.
        //
        // Un worker no tiene subdominio: su conexión por defecto se queda en el
        // centinela. El registro de trabajos fallidos heredaba esa conexión, así
        // que anotar el fallo fallaba también — y del alta que reventó no
        // quedaba nada, ni en la cola ni en el padrón ni en el log de fallos.
        //
        // Se simula el worker apuntando la conexión por defecto a una base que
        // no existe, que es exactamente la situación del centinela.
        config([
            'database.connections.centinela' => array_merge(
                config('database.connections.pgsql'),
                ['database' => 'demo_sin_resolver'],
            ),
            'database.default' => 'centinela',
        ]);

        $this->app->forgetInstance('queue.failer');

        $payload = json_encode([
            'uuid' => (string) Str::uuid(),
            'displayName' => AprovisionaUnInquilino::class,
        ]);

        $uuid = $this->app->make('queue.failer')->log(
            'database',
            'altas',
            $payload,
            new RuntimeException('la plantilla estaba en uso'),
        );

        $this->assertNotNull(
            DB::connection('central')->table('failed_jobs')->where('uuid', $uuid)->first(),
            'El fallo tiene que quedar anotado en la central: es el único rastro que deja un alta que revienta.',
        );
    }
}
