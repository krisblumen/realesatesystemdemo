<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

/**
 * Quién puede pedir un alta sin pasar por el formulario (RFC-078).
 *
 * SIN SECRETO CONFIGURADO CONTESTA 404, no 401, y no es cosmética. Un 401 dice
 * «acá hay una puerta, traé la llave»; un 404 no dice nada. Mientras la función
 * no esté en uso, no tiene por qué anunciarse a quien recorre rutas. Y falla en
 * la dirección correcta: si alguien despliega sin configurar el secreto, la
 * puerta queda cerrada en vez de abierta.
 *
 * LA COMPARACIÓN ES `hash_equals` Y NO `===`. Comparar cadenas con `===` corta
 * en el primer byte distinto, así que el tiempo de respuesta filtra cuántos
 * bytes acertó quien prueba. Con un secreto largo el ataque es incómodo, pero
 * «incómodo» no es una defensa: `hash_equals` compara en tiempo constante y
 * cuesta lo mismo escribirlo.
 *
 * NO ES UNA IDENTIDAD, es un secreto compartido. No distingue a un llamador de
 * otro ni deja rastro de quién pidió qué. El día que haya más de un consumidor y
 * eso importe, esto se cambia por credenciales por cliente — y este middleware
 * es el único lugar a tocar.
 */
class ExigeElSecretoDeAltas
{
    public function handle(Request $request, Closure $next): Response
    {
        $secreto = (string) Config::get('tenancy.api.secreto', '');

        abort_if($secreto === '', 404);

        $presentado = (string) $request->bearerToken();

        abort_unless(
            $presentado !== '' && hash_equals($secreto, $presentado),
            401,
            'Credencial inválida.',
        );

        return $next($request);
    }
}
