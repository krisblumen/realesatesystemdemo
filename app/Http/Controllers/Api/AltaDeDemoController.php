<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Tenancy\InquilinoActual;
use App\Tenancy\LimiteAlcanzado;
use App\Tenancy\SolicitaUnAlta;
use App\Tenancy\YaHayUnDemo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * El alta de un demo pedida por un sitio propio (RFC-078).
 *
 * PARA QUÉ EXISTE. La landing captura nombre, inmobiliaria, teléfono y correo, y
 * el visitante no debería volver a escribir el correo en un segundo formulario.
 * Reusar `POST /guest` desde el servidor no servía por tres motivos, y el tercero
 * es el que decide:
 *
 *  1. Devuelve un redirect y espera sesión de navegador; un llamador de servidor
 *     tendría que interpretar un 302 y una variable de sesión para saber si
 *     funcionó.
 *  2. Vive en el grupo `web`, con CSRF: habría que pedir un token antes de cada
 *     alta, o abrirle una excepción a CSRF, que es peor.
 *  3. `LimiteDeAltas` cuenta por `$request->ip()`. Llamado desde el servidor de
 *     la landing, TODAS las altas llegarían con la misma dirección y el tope por
 *     origen —tres por día— cortaría el embudo entero al tercer visitante. No
 *     fallaría al instante ni ruidosamente: fallaría un jueves a la tarde.
 *
 * POR ESO EL ORIGEN VIAJA EN EL CUERPO Y ES OBLIGATORIO. Que sea obligatorio no
 * es rigor por gusto: si fuera opcional, el llamador podría omitirlo sin querer
 * y el tope por origen se apagaría en silencio, que es exactamente el fallo que
 * esta ruta viene a no cometer. Ese dato es declarado, así que vale lo que valga
 * el secreto; el tope duro de la instancia se comprueba igual y ése no lo
 * declara nadie.
 *
 * DEVUELVE 202 Y NO 200: el alta se aceptó para hacerse, no se hizo. Cuando esta
 * respuesta sale, el inquilino todavía no existe y el acceso no se mandó. Decir
 * 200 sería prometer algo que ocurre un minuto después.
 *
 * NO LLEVA SEÑUELO. El del formulario existe porque ahí escribe cualquiera; acá
 * escribe un sistema con secreto, y un campo trampa no le diría nada a nadie.
 */
class AltaDeDemoController extends Controller
{
    public function store(Request $request, SolicitaUnAlta $altas, InquilinoActual $inquilino): JsonResponse
    {
        // VIVE SÓLO EN EL HOST CENTRAL, por lo mismo que el formulario: en un
        // subdominio de inquilino, dar de alta inquilinos no significa nada.
        abort_unless($inquilino->esElHostCentral(), 404);

        $datos = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:190'],
            'origen' => ['required', 'ip'],
        ]);

        try {
            $altas->encolar($datos['email'], $datos['origen']);
        } catch (YaHayUnDemo $e) {
            return response()->json([
                'estado' => 'ya_existe',
                'mensaje' => $e->getMessage(),
            ], 409);
        } catch (LimiteAlcanzado $e) {
            // 429 Y NO 503: lo que se agotó es un cupo atribuible a quien pide o
            // al momento, y la respuesta lleva cuándo reintentar cuando se sabe.
            // Un 503 diría «el servicio está roto», y no lo está.
            return response()->json([
                'estado' => 'sin_lugar',
                'mensaje' => $e->getMessage(),
                'reintentar_desde' => $e->reintentarDesde()?->toIso8601String(),
            ], 429);
        }

        return response()->json([
            'estado' => 'encolado',
            'mensaje' => 'El alta quedó encolada. El acceso se entrega por correo.',
        ], 202);
    }
}
