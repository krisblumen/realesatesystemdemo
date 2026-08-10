<?php

use App\Http\Controllers\Api\AltaDeDemoController;
use Illuminate\Support\Facades\Route;

/*
|-------------------------------------------------------------------------------
| API interna
|-------------------------------------------------------------------------------
|
| No es una API pública ni pretende serlo: la consume un sitio propio con un
| secreto compartido. No hay versionado en la ruta a propósito — versionar tiene
| sentido cuando hay consumidores que no controlás y no podés desplegar a la vez.
| Acá los dos lados son nuestros.
|
| EL `throttle` SIGUE PUESTO aunque la ruta exija secreto, y no es redundante.
| Protege dos cosas distintas: contra alguien sin secreto, que el 401 no salga
| gratis y se pueda martillar la ruta probando llaves; contra la landing misma,
| que un bucle nuestro no inunde la cola de altas. Los topes de RFC-10 tampoco lo
| reemplazan: ésos cuidan la instancia de Postgres, éste cuida el proceso web.
|
| El límite es más holgado que el de `/guest` porque acá una petición equivale a
| un visitante que ya llenó un formulario completo, no a un clic.
|
*/

Route::post('/demos', [AltaDeDemoController::class, 'store'])
    ->middleware(['secreto.altas', 'throttle:30,1'])
    ->name('api.demos.alta');
