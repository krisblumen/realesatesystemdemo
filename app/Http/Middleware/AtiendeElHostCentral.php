<?php

namespace App\Http\Middleware;

use App\Tenancy\InquilinoActual;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

/**
 * Qué contesta el dominio base cuando no hay subdominio.
 *
 * EL 500 QUE ESTO RESUELVE NO ERA UN BUG: era una decisión que faltaba. El host
 * central apunta su conexión por defecto a la base central a propósito, para que
 * no pueda tocar datos de ningún inquilino. Y esa base sólo tiene el padrón, las
 * sesiones y la cola — no tiene páginas. Cualquier ruta del sitio moría ahí
 * buscando tablas del CMS que no existen ni deben existir.
 *
 * La respuesta tiene DOS niveles, y el orden importa:
 *
 *  1. Una página propia, mínima, que no consulta nada. Es el piso.
 *  2. Si hay un sitio promocional configurado, redirige.
 *
 * Al revés —redirigir siempre— el host central quedaría atado a que exista otra
 * cosa: mientras esa otra cosa no esté lista, se cambia un 500 por el 500 del
 * otro dominio. Con la página propia de piso, la redirección es una mejora y no
 * un requisito. Se enciende con `TENANCY_SITIO_PROMOCIONAL` y sin tocar código.
 *
 * ATIENDE TODAS LAS RUTAS, no sólo la portada: el 500 aparecía en cualquiera, y
 * quien llega con un enlace viejo cae en una cualquiera. La excepción es el
 * chequeo de salud, que tiene que seguir contestando lo suyo.
 *
 * El día que exista un panel de operación en la central (RFC-12), sus rutas se
 * suman a esa excepción.
 */
class AtiendeElHostCentral
{
    /**
     * Rutas que el host central sigue atendiendo por su cuenta.
     *
     * @var array<int, string>
     */
    private const PROPIAS = [
        'up',

        // El registro público de un demo (fase 2). Sin esta excepción, el host
        // central redirigiría al sitio promocional y la única puerta de entrada
        // al demo quedaría inalcanzable.
        'guest',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! app(InquilinoActual::class)->esElHostCentral()) {
            return $next($request);
        }

        if ($request->is(...self::PROPIAS)) {
            return $next($request);
        }

        $promocional = (string) Config::get('tenancy.sitio_promocional', '');

        if ($promocional !== '') {
            return redirect()->away($promocional);
        }

        return response()->view('central.portada');
    }
}
