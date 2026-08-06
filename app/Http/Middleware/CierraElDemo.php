<?php

namespace App\Http\Middleware;

use App\Tenancy\CompartirElSitio;
use App\Tenancy\InquilinoActual;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * El sitio de un inquilino exige su sesión, y no lo indexa nadie.
 *
 * El demo sirve para mostrar cómo funciona el producto, no para publicar
 * sitios. El inquilino recorre su sitio ENTERO —con el mismo render y el mismo
 * caché que vería un visitante— y nadie de afuera puede navegarlo.
 *
 * SE ACTIVA CUANDO HAY INQUILINO, no con una bandera de configuración. Una
 * bandera es algo que alguien puede olvidarse de encender el día del
 * despliegue, y el síntoma de olvidarla es un demo abierto que parece cerrado.
 * Con esta regla, todo lo que se sirve bajo el subdominio de un inquilino está
 * cerrado por construcción.
 *
 * LÍMITE CONOCIDO Y ACEPTADO (RFC-14): esto cierra el HTML, no los bytes. Las
 * imágenes publicadas viven en el disco `public` y el servidor web las sirve
 * sin pasar por Laravel. Quien tenga la URL de una imagen la abre. Por eso el
 * comando de invitación imprime el aviso de no subir nada que no pueda ser
 * público.
 */
class CierraElDemo
{
    /**
     * Rutas que siguen abiertas, con su porqué.
     *
     * TODA excepción se justifica acá. Una ruta abierta que no figure en esta
     * lista es un error, no una decisión que alguien no anotó.
     */
    private const ABIERTAS = [
        // Firma de contratos. Un cliente recibe un enlace y firma sin tener
        // cuenta: es una de las funciones que el demo quiere lucir, y cerrarla
        // haría que no se pueda mostrar. El control de acceso ahí no es la
        // sesión sino el token, que es de un solo uso y tiene límite de
        // frecuencia — y sigue resolviendo por subdominio, así que el token de
        // un inquilino no alcanza a otro.
        'contratos.publico.',
        'contratos.verificacion',

        // El canje del enlace para mostrar el sitio. Quien llega con él todavía
        // no tiene sesión —esta ruta es la que se la crea—, así que cerrarla
        // haría que nadie pudiera canjear nunca.
        'muestra.canjear',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! app(InquilinoActual::class)->hayInquilino()) {
            return $next($request);
        }

        if ($this->exigeSesion($request) && ! $this->tienePase($request)) {
            return redirect()->guest(route('filament.admin.auth.login'));
        }

        $respuesta = $next($request);

        // Redundante con el cierre y va igual: una ruta que alguien abra por
        // error mañana no debería además terminar indexada.
        $respuesta->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $respuesta;
    }

    /**
     * Quién puede ver el sitio de este inquilino.
     *
     * Dos formas, y la segunda no es una puerta trasera: es una decisión de
     * quien cargó los datos. Un prospecto que armó su demo quiere enseñárselo a
     * su socio, y sin esta salida la única alternativa sería prestarle su
     * cuenta — peor de todas las maneras.
     *
     * El pase alcanza al SITIO, no al panel: no crea ningún usuario, así que la
     * autenticación de Filament lo rechaza igual que a cualquier anónimo.
     */
    private function tienePase(Request $request): bool
    {
        return $request->user() !== null
            || $request->session()->get(CompartirElSitio::CLAVE_DE_SESION) === true;
    }

    private function exigeSesion(Request $request): bool
    {
        // LIVEWIRE ES EL TRANSPORTE, NO UNA PÁGINA.
        //
        // El formulario de acceso de Filament no se envía por HTTP normal: lo
        // manda Livewire a `POST livewire/update`, que está en el grupo `web` y
        // por lo tanto pasa por acá. Sin esta excepción el cierre lo redirigía
        // al login por no haber sesión todavía — o sea, IMPEDÍA INICIAR LA
        // SESIÓN QUE ÉL MISMO EXIGE. Nadie podía entrar, y no quedaba rastro en
        // el log, porque un 302 no es un error.
        //
        // Se compara por RUTA y no por nombre porque el nombre de esa ruta
        // depende del panel que la registre, y porque la que sirve el JavaScript
        // no tiene nombre.
        //
        // No abre nada que el cierre estuviera protegiendo: las páginas que
        // Livewire transporta siguen aplicando su propia autorización, y el
        // panel la suya. Lo único que se deja pasar es el sobre, no la carta.
        if ($request->is('livewire/*')) {
            return false;
        }

        $nombre = (string) $request->route()?->getName();

        foreach (self::ABIERTAS as $prefijo) {
            if (str_starts_with($nombre, $prefijo)) {
                return false;
            }
        }

        // El panel tiene su propia autenticación; interceptarlo acá dejaría al
        // inquilino sin forma de entrar.
        return ! str_starts_with($nombre, 'filament.');
    }
}
