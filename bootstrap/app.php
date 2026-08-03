<?php

use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

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
        // Detrás del reverse proxy de CloudPanel: confiar en los headers
        // X-Forwarded-* para que Laravel detecte el esquema HTTPS original.
        $middleware->trustProxies(at: '*');

        $middleware->prependToPriorityList(
            AuthenticatesRequests::class,
            EnsureUserIsActive::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
