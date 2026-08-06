<?php

use App\Http\Middleware\AtiendeElHostCentral;
use App\Http\Middleware\CierraElDemo;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;

return Application::configure(basePath: dirname(__DIR__))
    // Application::configure() habilita el auto-discovery de listeners por
    // default (cualquier clase en app/Listeners cuyo handle() tipe-hintee un
    // evento queda enlazada automaticamente). Esta app registra sus listeners
    // a mano en AppServiceProvider::boot() -- con el discovery activo,
    // ademas quedaban enlazados por convencion, duplicando cada evento
    // (ej. WelcomeNotification generaba dos tokens, el segundo invalidando
    // el primero; SendLeadAssignedNotification mandaba doble mail).
    ->withEvents(discover: false)
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // SÓLO EL BUCLE LOCAL, y el `'*'` que había antes no era un atajo
        // inofensivo.
        //
        // Entre los encabezados que Laravel confía a un proxy está
        // `X-Forwarded-Host`, y el inquilino se resuelve del `Host`. Confiando
        // en cualquier origen, el `Host` efectivo lo elige quien manda la
        // petición: la frontera entre inquilinos deja de ser una frontera. No
        // entrega datos por sí solo —siguen haciendo falta credenciales— pero
        // convierte en elegible por el cliente algo que el diseño decide.
        //
        // El bucle local es la respuesta correcta sin importar cómo esté armado
        // el servidor. Si nginx habla con PHP por FastCGI, `REMOTE_ADDR` es la
        // dirección real del cliente: nunca es el bucle, así que sus encabezados
        // se ignoran y el esquema HTTPS sale de los parámetros que pone el vhost.
        // Si en cambio hay un proxy delante en el mismo host, `REMOTE_ADDR` sí
        // es el bucle y sus encabezados se respetan. Los dos casos quedan bien.
        //
        // Si algún día el proxy pasa a estar en otra máquina —una CDN, un
        // balanceador—, ACÁ hay que poner su dirección. No `'*'`.
        $middleware->trustProxies(at: [
            '127.0.0.1',
            '::1',
        ]);

        $middleware->prependToPriorityList(
            AuthenticatesRequests::class,
            EnsureUserIsActive::class,
        );

        // El inquilino se resuelve ANTES de que arranque la sesión, y no es
        // preferencia: la sesión se guarda en base de datos, así que arrancarla
        // antes de saber a qué base conectarse la leería de la equivocada.
        //
        // Va en la lista de prioridad y no sólo al principio del grupo `web`
        // porque el orden dentro del grupo no garantiza nada frente a un
        // paquete que se registre después.
        $middleware->prependToPriorityList(
            StartSession::class,
            ResolveTenant::class,
        );

        // Va JUSTO DESPUÉS de resolver, porque necesita saber si el host es el
        // central — y antes que nada más, porque su trabajo es cortar la
        // petición. Todo lo que corra en el medio sería trabajo tirado.
        $middleware->prependToGroup('web', AtiendeElHostCentral::class);
        $middleware->prependToGroup('web', ResolveTenant::class);

        // El cierre va al FINAL del grupo: necesita la ruta ya resuelta para
        // saber si es una de las excepciones, y el usuario ya autenticado para
        // saber si hay sesión.
        $middleware->appendToGroup('web', CierraElDemo::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
