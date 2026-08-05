<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Dónde viven los cerrojos
|--------------------------------------------------------------------------
|
| `withoutOverlapping()` y `onOneServer()` guardan su cerrojo en el caché, y el
| caché usa la conexión POR DEFECTO — que desde consola es el centinela, porque
| un comando no tiene subdominio del cual resolver un inquilino.
|
| Sin esto, `demo:borrar` falla antes de mirar un solo inquilino. Y no se nota
| en desarrollo, donde esa conexión apunta a una base que existe.
|
*/

Schedule::useCache('central');

/*
|--------------------------------------------------------------------------
| Tareas de INQUILINO
|--------------------------------------------------------------------------
|
| Tocan datos que viven en la base de cada inquilino, así que corren una vez por
| cada uno. Agendadas directo —como venían de la plataforma de origen, donde
| había una sola base— apuntan al centinela y mueren.
|
| El síntoma sería ruidoso: fallan cada pocos minutos. Lo que importa es
| silencioso: el trabajo del inquilino nunca ocurre.
|
*/

Schedule::command('demo:por-cada-inquilino leads:reconcile')
    ->everyTenMinutes()
    ->withoutOverlapping();

// Promoción de media del frontend (Épica 12.1 §7.8): reencola promociones que
// perdieron su callback y limpia flags `pending_promotion` que quedaron sin
// referencia. Es idempotente y NO borra archivos — el scheduler no registra
// ningún comando que borre media (§16.4).
Schedule::command('demo:por-cada-inquilino frontend:media:reconcile')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// Contratos de intermediación (RFC-069): expiración/recordatorio, vencimiento y
// retención. Van al recorrido por inquilino porque los contratos son suyos, y
// firmar es una de las funciones que el demo existe para mostrar.
Schedule::command('demo:por-cada-inquilino contratos:expirar')->hourly()->withoutOverlapping();
Schedule::command('demo:por-cada-inquilino contratos:vencer')->dailyAt('01:00');
Schedule::command('demo:por-cada-inquilino contratos:retencion')->dailyAt('02:00');

/*
| `mail:sync-unseen` NO entra en el recorrido.
|
| Sincroniza bandejas por IMAP, y un inquilino de demo no tiene cuentas de correo
| reales configuradas: correrlo por cada uno cada cinco minutos es levantar
| Laravel N veces para no hacer nada. Vuelve el día que el demo muestre correo.
*/

/*
|--------------------------------------------------------------------------
| Tareas CENTRALES
|--------------------------------------------------------------------------
|
| La cola está anclada a la central (RFC-03): un worker no tiene subdominio del
| cual resolver, y el trabajo que crea la base de un inquilino corre cuando esa
| base todavía no existe. Cada trabajo lleva su inquilino adentro.
|
*/

Schedule::command('queue:work --stop-when-empty --tries=3 --max-time=55')->everyMinute()->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| Ciclo de vida de los inquilinos del demo (EPICA-DEMO, RFC-09)
|--------------------------------------------------------------------------
|
| Dos tareas y no una, por lo mismo que son dos comandos: marcar vencido es
| barato, inmediato y confiable; borrar es caro, irreversible y puede fallar.
| Con tareas separadas, el corte de acceso ocurre aunque el borrado esté
| fallando, y entre una y otra queda la ventana para atender un reclamo.
|
*/

Schedule::command('demo:expirar')->hourly();
// `withoutOverlapping()` porque el borrado puede tardar y alguien puede lanzar
// un reintento manual cerca de la hora: dos procesos sobre el mismo inquilino
// hacen que los contadores de falla se pisen.
Schedule::command('demo:borrar')->dailyAt('03:30')->withoutOverlapping();
