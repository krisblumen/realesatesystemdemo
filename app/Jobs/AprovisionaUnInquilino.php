<?php

namespace App\Jobs;

use App\Tenancy\AprovisionaInquilinos;
use App\Tenancy\ResultadoDeAlta;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * El alta de un inquilino, como trabajo.
 *
 * DOS CAMINOS, UNA MAQUINARIA. El registro público lo encola —no puede sostener
 * una petición HTTP mientras se copia una base— y `demo:invitar` lo corre en el
 * momento, porque quien está en la terminal quiere saber si funcionó y ver el
 * acceso. Una línea de diferencia entre `Bus::dispatchNow()` y `dispatch()`.
 *
 * Y ES `dispatchNow`, NO `dispatchSync`, que suena a lo mismo y no lo es: con un
 * trabajo `ShouldQueue`, `dispatchSync()` no llama a `handle()` sino que empuja
 * el trabajo al driver `sync` de la cola, y devuelve lo que devuelve la cola.
 * O sea `null`: el acceso se pierde en el camino.
 *
 * EN SU PROPIA COLA, y eso es infraestructura y no orden. Crear una base exige
 * `CREATEDB`, que tiene `demo_provisioner` y NO `demo_app` —el rol con el que
 * corre la aplicación, a propósito—. Con una cola aparte, el único proceso que
 * lleva ese privilegio es el que atiende altas; el worker general sigue sin él.
 *
 * Si este trabajo cayera en la cola general, el worker lo tomaría y moriría con
 * «permission denied to create database», reintentando hasta agotar intentos.
 *
 * NO REINTENTA. El alta ya limpia lo suyo cuando falla —borra la base a medias y
 * marca el inquilino como fallido— y volver a intentar con el mismo correo
 * crearía un segundo inquilino para la misma persona. El padrón guarda el motivo
 * de la falla; reintentar es una decisión de quien opera, no del sistema.
 */
class AprovisionaUnInquilino implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly string $email,
        public readonly ?string $origenHash = null,
    ) {
        $this->onQueue('altas');
    }

    public function handle(AprovisionaInquilinos $alta): ResultadoDeAlta
    {
        return $alta->crear($this->email, $this->origenHash);
    }
}
