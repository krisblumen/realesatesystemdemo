# Reporte: Indicadores de Leads y Correo Nuevo en el Topbar

**Para:** Edgar
**Contexto:** Rama `feature/new-mails-and-leads-indicators` — dos indicadores nuevos en el panel admin (leads pendientes + mails sin leer), más la configuración de servidor que los sostiene.
**Estado:** Deployado y verificado en producción.
**Rama:** `feature/new-mails-and-leads-indicators`

---

## TL;DR

- **Indicador de leads:** badge circular rojo junto a "Leads" en el menú. Owner/admin ven leads abiertos sin asignar; cada agente ve sus propios leads abiertos.
- **Bug encontrado y corregido en el camino:** los leads del formulario público (sin inmueble ni zona) se estaban auto-asignando a cualquier agente activo del sistema en vez de quedar en espera. Corregido — ahora quedan sin asignar hasta que owner/admin decida.
- **Indicador de mail:** ícono de sobre en el topbar, junto a la campanita, **solo visible para usuarios con email `@newhauz.com.mx`**. Badge rojo con el conteo de mails no leídos. Click abre `webmail.newhauz.com.mx`.
- **Cómo se obtiene el conteo:** un comando de Laravel (`mail:sync-unseen`, cron cada 5 min) consulta `doveadm` por IMAP para cada agente activo del dominio. La consulta corre como el usuario `vmail` (dueño real de los Maildirs en `/var/mail/vhosts/`), nunca como root, vía un wrapper script + regla de `sudo` acotada a ese script exacto.
- **Nada de esto vive en el código de Laravel** salvo lo que se especifica abajo — el wrapper script, el sudoers y el cron son configuración de servidor que armamos juntos por SSH y quedan documentados acá.

---

## 1. Indicador de leads pendientes

**Archivo:** `app/Filament/Resources/LeadResource.php::getNavigationBadge()`

| Rol | Qué cuenta |
|---|---|
| Owner / Admin | Leads abiertos (no cerrados) **sin agente asignado** — esperan triage manual |
| Agente | Sus propios leads abiertos (`getEloquentQuery()` ya scopea a "solo mis leads") |
| Cualquier otro rol | No ve el badge |

El badge no se muestra si el conteo es 0.

### Bug de auto-asignación (encontrado durante esta tarea)

`LeadAssignmentService::resolveAgent()` tenía un tercer nivel de fallback: si un lead no tenía inmueble ni zona (el caso del formulario público de `/contacto`), el sistema lo asignaba a **cualquier agente activo** vía round-robin. Esto contradecía la regla de negocio ("un lead del formulario de contacto queda sin asignar hasta que owner/admin lo revise") y hacía que el indicador de leads pendientes nunca mostrara nada en operación normal.

**Corrección:** se eliminó ese fallback. Ahora, sin inmueble ni zona con agente disponible, el lead queda `agent_id: null` para asignación manual. El comando `leads:reconcile` (cron cada 10 min, ya existía) sigue vivo para los casos en que un inmueble/zona gana agente después de creado el lead.

**Archivo:** `app/Services/LeadAssignmentService.php`

---

## 2. Indicador de correo nuevo

### 2.1 Visibilidad y UI

**Archivo:** `resources/views/filament/mail-indicator.blade.php`, registrado como render hook en `app/Providers/Filament/AdminPanelProvider.php` (`PanelsRenderHook::GLOBAL_SEARCH_AFTER`, topbar, justo antes de la campanita de notificaciones).

```blade
@if ($user && $user->hasNewhauzMailbox())
    <x-filament::icon-button
        tag="a"
        href="{{ config('mail_indicator.webmail_url') }}"
        target="_blank"
        icon="heroicon-o-envelope"
        :badge="$user->mail_unseen_count ?: null"
        badge-color="danger"
        class="fi-topbar-mail-btn"
    />
@endif
```

`User::hasNewhauzMailbox()` hace `str_ends_with(strtolower($email), '@newhauz.com.mx')`. Si el usuario logueado no tiene ese dominio, el ícono ni se renderiza.

Estilo: círculo rojo sólido + número blanco (mismo CSS que ya usa el badge de Leads), en `resources/css/filament/admin/theme.css`.

### 2.2 De dónde sale el conteo

**No hay llamada a IMAP en el request del usuario.** El dashboard solo lee `users.mail_unseen_count`, una columna que se sincroniza por cron.

**Migración:** `database/migrations/2026_07_08_155856_add_mail_unseen_count_to_users_table.php` — agrega `mail_unseen_count` (nullable) y `mail_unseen_synced_at` (nullable) a `users`. No toca ninguna columna ni fila existente.

**Comando:** `app/Console/Commands/SyncMailUnseenCountsCommand.php` (`php artisan mail:sync-unseen`)

1. Toma todos los usuarios activos cuyo email termina en `@newhauz.com.mx`.
2. Para cada uno, corre `Process::run(['sudo', '-u', 'vmail', $scriptPath, $user->email])`.
3. Parsea `unseen=(\d+)` de la salida y actualiza `mail_unseen_count` + `mail_unseen_synced_at`.
4. Si un buzón falla, loggea el warning y sigue con el resto (no aborta el batch).

**Programado en `routes/console.php`:**
```php
Schedule::command('mail:sync-unseen')->everyFiveMinutes()->withoutOverlapping();
```

**Config (`config/mail_indicator.php`, todo overrideable por `.env` si hace falta — los defaults ya matchean lo que armamos en el servidor):**
```php
return [
    'domain' => env('MAIL_INDICATOR_DOMAIN', 'newhauz.com.mx'),
    'script_path' => env('MAIL_INDICATOR_SCRIPT_PATH', '/usr/local/bin/newhauz-mail-unseen.sh'),
    'sudo_user' => env('MAIL_INDICATOR_SUDO_USER', 'vmail'),
    'webmail_url' => env('MAIL_INDICATOR_WEBMAIL_URL', 'https://webmail.newhauz.com.mx'),
];
```

### 2.3 Infraestructura de servidor (fuera del repo de Laravel)

Esto se armó a mano por SSH en `srv650075` — **no está versionado en git**, queda documentado acá para que quede registro.

**Wrapper script** — `/usr/local/bin/newhauz-mail-unseen.sh`, dueño `root:vmail`, modo `750`:

```bash
#!/usr/bin/env bash
set -euo pipefail

EMAIL="${1:-}"

if [[ ! "$EMAIL" =~ ^[A-Za-z0-9._%+-]+@newhauz\.com\.mx$ ]]; then
    echo "invalid mailbox" >&2
    exit 1
fi

exec /usr/bin/doveadm mailbox status -u "$EMAIL" unseen INBOX
```

**Sudoers** — `/etc/sudoers.d/hauznew-mail-unseen`, modo `440`:
```
hauznew ALL=(vmail) NOPASSWD: /usr/local/bin/newhauz-mail-unseen.sh
```

**Cron del scheduler de Laravel** — `crontab -u hauznew`, agregado durante esta tarea (**no existía antes**, así que `leads:reconcile`, que ya estaba en el código, tampoco corría solo hasta ahora):
```
* * * * * /usr/bin/php8.3 /home/hauznew/htdocs/www.newhauz.com.mx/artisan schedule:run >> /home/hauznew/schedule.log 2>&1
```

> **Nota de ruta:** el sitio real que sirve PHP-FPM es `/home/hauznew/htdocs/www.newhauz.com.mx` (confirmado vía el pool `/etc/php/8.3/fpm/pool.d/www.newhauz.com.mx.conf`, `user = hauznew`). Existe otra carpeta `/home/hauznew/htdocs/newhauz` sin `vendor/` instalado — no es el deploy activo, quedó como false positive la primera vez que buscamos la ruta del proyecto.

---

## 3. Análisis de seguridad (a pedido de Edgar)

**Lo que está resuelto:**

| Control | Detalle |
|---|---|
| Sin root | `hauznew` solo puede escalar a `vmail` vía sudoers. Verificado: intentar sin `-u vmail` responde `sudo: a password is required`. |
| Sin inyección de shell | `Process::run(['sudo', '-u', $sudoUser, $scriptPath, $user->email])` pasa argumentos como array (Symfony Process los escapa); el wrapper usa `exec` + `"$EMAIL"` citado, sin sub-shell. |
| Validación de dominio en el wrapper | El regex rechaza cualquier email fuera de `@newhauz.com.mx`, incluso si alguien invoca el script directo. |
| Sin credenciales de correo en la app | No hay passwords de buzones en la base de Laravel ni en `.env`. La escalada la resuelve `sudoers`, no un secreto conocido por la app. |
| Auditable | Cada invocación de `sudo` queda en el log de auth del sistema. |
| Sin superficie de DoS nueva | El sync corre por cron, no hay endpoint HTTP que dispare `doveadm` a demanda. |

**Riesgo residual (bajo, conocido, no bloqueante):**

El wrapper valida *dominio*, no una allowlist de buzones específicos. Si `hauznew` fuera comprometido (ej. RCE en la app), el atacante podría consultar el conteo de no-leídos de **cualquier** buzón `@newhauz.com.mx`, no solo los de agentes dados de alta en el panel. Es fuga de información menor — un número, no contenido de mails ni acceso a la bandeja — y hoy no es explotable por el flujo normal (el comando solo itera usuarios activos de la tabla `users`). Si se quiere cerrar del todo: cambiar el wrapper de "regex de dominio" a una allowlist explícita de buzones, a costa de mantenerla actualizada en cada alta de agente.

---

## 4. Archivos — Inventario

| Archivo | Operación |
|---|---|
| `app/Filament/Resources/LeadResource.php` | Modificado — `getNavigationBadge()` |
| `app/Services/LeadAssignmentService.php` | Modificado — fix de auto-asignación |
| `app/Models/User.php` | Modificado — `hasNewhauzMailbox()`, casts |
| `app/Providers/Filament/AdminPanelProvider.php` | Modificado — render hook del ícono de mail |
| `app/Console/Commands/SyncMailUnseenCountsCommand.php` | Creado |
| `config/mail_indicator.php` | Creado |
| `database/migrations/2026_07_08_155856_add_mail_unseen_count_to_users_table.php` | Creado |
| `resources/views/filament/mail-indicator.blade.php` | Creado |
| `resources/css/filament/admin/theme.css` + `public/css/filament/admin/theme.css` | Modificado — badge estilo iOS |
| `routes/console.php` | Modificado — schedule de `mail:sync-unseen` |
| `tests/Feature/Filament/LeadResourceTest.php` | Modificado |
| `tests/Feature/Leads/LeadAssignmentServiceTest.php` | Modificado |
| `tests/Feature/Filament/MailIndicatorTest.php` | Creado |
| `tests/Feature/Mail/SyncMailUnseenCountsCommandTest.php` | Creado |
| `/usr/local/bin/newhauz-mail-unseen.sh` | Creado en servidor (fuera de git) |
| `/etc/sudoers.d/hauznew-mail-unseen` | Creado en servidor (fuera de git) |
| `crontab -u hauznew` | Configurado en servidor (fuera de git) |

### Commits

```
374200e fix(leads): no asignar leads sin inmueble ni zona a cualquier agente activo
c30da86 feat(leads): indicador de leads pendientes en el menu
f46b5dd feat(mail): indicador de correo nuevo en el topbar para usuarios @newhauz.com.mx
```

---

## 5. Checklist

- [x] Badge de leads pendientes — owner/admin y agentes, tests en verde
- [x] Fix de auto-asignación de leads sin inmueble/zona
- [x] Migración de `mail_unseen_count` / `mail_unseen_synced_at`
- [x] Comando `mail:sync-unseen` con tests (`Process::fake`)
- [x] Ícono de mail en topbar, scopeado a `@newhauz.com.mx`, estilo iOS
- [x] Wrapper script + sudoers en servidor, verificado sin escalar a root
- [x] Cron del scheduler de Laravel dado de alta (no existía antes)
- [x] Deployado a producción, migrate corrido, verificado en vivo (badge real con datos reales)
- [x] Suite completa: 306/306 tests en verde

## 6. Pendiente / a criterio de Edgar

- [ ] Decidir si se quiere cerrar el riesgo residual de la sección 3 (allowlist de buzones vs. regex de dominio).
- [ ] Confirmar que `/home/hauznew/htdocs/newhauz` (la carpeta sin `vendor/`) no sea necesaria — si es un checkout viejo, se podría limpiar para evitar confusión futura.
