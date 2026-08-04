<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('leads:reconcile')->everyTenMinutes();
Schedule::command('mail:sync-unseen')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('queue:work --stop-when-empty --tries=3 --max-time=55')->everyMinute()->withoutOverlapping();

// Promoción de media del frontend (Épica 12.1 §7.8): reencola promociones que
// perdieron su callback y limpia flags `pending_promotion` que quedaron sin
// referencia. Es idempotente y NO borra archivos — el scheduler no registra
// ningún comando que borre media (§16.4).
Schedule::command('frontend:media:reconcile')->everyFifteenMinutes()->withoutOverlapping()->onOneServer();

// Contratos de intermediación (RFC-069): expiración/recordatorio, vencimiento y retención.
Schedule::command('contratos:expirar')->hourly()->withoutOverlapping();
Schedule::command('contratos:vencer')->dailyAt('01:00');
Schedule::command('contratos:retencion')->dailyAt('02:00');

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
Schedule::command('demo:borrar')->dailyAt('03:30');
