# RFC-060 LOGIN REAL

## Proyecto
NEW HAUZ

## RFC
RFC-060

## Estado
✅ IMPLEMENTADO

## Rama base
`develop`

## Rama de trabajo
`feature/rfc-060-login-real`

## Responsable Principal
Edgar

## Participantes

### Arquitectura
- Edgar
- Kristian

### QA
- Sebastián

## Fecha
2026-06-18

---

# Seguimiento del Pipeline Multimodelo

| Etapa | Agente | Estado | Fecha |
|---|---|---|---|
| 1. Generación del RFC | Claude (Arquitecto) | ✅ Completado | 2026-06-18 |
| 2. Auditoría de diseño | Antigravity (Auditor Senior) | ✅ Completado | 2026-06-18 |
| 3. Aplicación de correcciones | Claude (Agente Impl.) | ✅ Completado | 2026-06-18 |
| 4. Implementación | Codex / Edgar | ✅ Completado | 2026-06-18 |
| 5. Auditoría de implementación | Gemini CLI | ⚠️ Sin doc. formal — revisada por Claude vía commits | 2026-06-18 |
| 6. Cierre técnico | Claude (Arquitecto) | ✅ Completado | 2026-06-18 |

---

# Objetivo

Promover el login del panel `/admin` —ya temado por RFC-059— a un login funcionalmente completo:

- Corregir el defecto `D-1` que bloquea a usuarios con rol `agente` al verificar un permiso que ese rol no posee
- Corregir el orden de los middlewares de autenticación para garantizar que las sesiones de usuarios suspendidos se invaliden correctamente (`C-1`)
- Eliminar los guardas `class_exists` temporales introducidos como workaround en RFC-059
- Añadir protección defensiva en `UserPolicy::delete` contra eliminación del rol `owner`
- Establecer el hook de redirect post-login por rol (auto-activable por Épica 3 vía `class_exists`)
- Cubrir el flujo completo con tests de integración sobre PostgreSQL real (PHPUnit 12)

---

# Contexto y Dependencias

## Consume de RFC-059 (Theme Admin + Login)

**Estado:** ✅ Cerrado y mergeado en `develop`

| Contrato | Estado |
|---|---|
| `AdminPanelProvider` con theme, colores, logos y renderHook | ✅ Operativo |
| `app/Filament/Pages/Auth/Login.php` extendiendo la página base de Filament | ✅ Operativo |
| Middleware `EnsureUserIsActive` registrado en `authMiddleware` (con guard `class_exists` temporal) | ✅ Funciona, pero el orden y el guard deben corregirse |
| Pantalla `/admin/login` con glassmorphism y pie GESIF | ✅ Operativo |

## Consume de Épica 2 (RFC-011 → RFC-014)

**Estado:** ✅ Cerrado y mergeado en `develop`

| Contrato | Archivo | Estado |
|---|---|---|
| `User::isActive()` / `User::isSuspended()` | `app/Models/User.php` | ✅ Operativo |
| `User::canAccessPanel()` | `app/Models/User.php` | ⚠️ **Defecto D-1** — ver Diseño Técnico |
| `EnsureUserIsActive` middleware | `app/Http/Middleware/EnsureUserIsActive.php` | ✅ Operativo |
| `UserPolicy::delete` — protección de `owner` | `app/Policies/UserPolicy.php` | ⚠️ **Defecto D-6** — ver Diseño Técnico |
| `UserPolicy::suspend()` / `::reactivate()` | `app/Policies/UserPolicy.php` | ✅ Operativo |
| `UserStatusService::suspend()` / `::reactivate()` | `app/Services/UserStatusService.php` | ✅ Operativo |
| Listener `UpdateUserLastLoginAt` registrado en `AppServiceProvider` | `app/Listeners/UpdateUserLastLoginAt.php` | ✅ Operativo |
| Roles: `owner`, `admin`, `agente` | `database/seeders/PermissionSeeder.php` | ✅ Sembrados |
| Permisos base (users.\*, properties.manage, leads.manage, zones.manage) | `database/seeders/PermissionSeeder.php` | ✅ Sembrados |

## Consume de Épica 3 (RFC-015 → RFC-018)

**Estado:** ✅ Cerrado y mergeado en `develop`

| Contrato | Archivo | Estado |
|---|---|---|
| `User::zones()` BelongsToMany<Zone> | `app/Models/User.php` | ✅ Operativo |
| Página de landing del agente post-login | No existe aún | ⏳ **Contrato diferido CD-1** |

El hook `Login::getRedirectUrl()` usa `class_exists` para activarse automáticamente cuando Épica 3 cree `App\Filament\Pages\AgentDashboard`, sin requerir cambios adicionales en este archivo.

---

# Alcance

## Lo que NO entrega este RFC

- Recuperación de contraseña vía email (diferido a Épica 8)
- Página de landing del agente post-login (contrato diferido CD-1, activado por `class_exists` en Épica 3)
- Autenticación de dos factores (2FA) — fuera de scope
- Login vía terceros (OAuth, SSO) — fuera de scope
- Auditoría persistente de intentos de login fallidos — diferido a Épica 8
- Auditoría de logins bloqueados por ausencia de roles — diferido a Épica 8
- Bloqueo permanente de cuenta tras N intentos (el throttle temporal de Filament es suficiente en esta fase)
- Modificación de `UpdateUserLastLoginAt` para validar roles — el listener es correcto: actualiza `last_login_at` cuando las credenciales son válidas; el audit log de accesos denegados va a Épica 8
- Nuevas migraciones, nuevos modelos, nuevas rutas

## Lo que entrega este RFC

- Corrección de `User::canAccessPanel()` para permitir acceso a los tres roles válidos (`owner`, `admin`, `agente`)
- Corrección del orden de middlewares en `AdminPanelProvider`: `EnsureUserIsActive` antes de `Authenticate` para garantizar logout e invalidación de sesión en usuarios suspendidos
- Simplificación de `AdminPanelProvider`: eliminación de guards `class_exists` temporales y del método `epicTwoResources()` redundante
- Protección defensiva en `UserPolicy::delete` contra eliminación accidental o futura del rol `owner`
- `Login::getRedirectUrl()`: hook con `class_exists` auto-activable, sin código comentado
- Suite de tests `tests/Feature/Auth/AdminLoginTest.php` (PHPUnit 12) con 9 tests cubriendo todos los roles, el usuario suspendido, el usuario sin roles y el `last_login_at`

---

# Diseño Técnico

## D-1 — Corrección de `User::canAccessPanel()` [CERRADA]

**Problema:** El método actual verifica `users.view`, permiso que el rol `agente` no tiene.

```php
// Estado actual — DEFECTUOSO para agentes:
public function canAccessPanel(Panel $panel): bool
{
    return $this->isActive() && $this->can('users.view');
}
```

**Decisión:** Cambiar la verificación a control por rol. `canAccessPanel()` es la puerta de entrada al panel, no una operación CRUD. Los roles son la fuente correcta de verdad aquí (la Policy aplica a operaciones de negocio).

```php
public function canAccessPanel(Panel $panel): bool
{
    return $this->isActive() && $this->hasAnyRole(['owner', 'admin', 'agente']);
}
```

**Por qué rol y no un permiso `panel.access`:** Requeriría modificar dos archivos de Épica 2 (`User.php` + `PermissionSeeder.php`). El check de rol modifica solo `User.php` y minimiza la superficie.

---

## D-2 — Simplificación y corrección de `AdminPanelProvider` [CERRADA]

Dos problemas en el provider actual:

**Problema 1 (C-1 — Auditoría):** El orden `[Authenticate, EnsureUserIsActive]` es incorrecto. El middleware `Authenticate` de Filament evalúa `canAccessPanel()` en requests autenticados. Cuando un usuario está suspendido, `canAccessPanel()` retorna `false` (porque `isActive()` = false) y Filament lanza una excepción de acceso denegado. La ejecución se interrumpe y `EnsureUserIsActive` **nunca llega a ejecutarse** — por lo tanto, ni `auth()->logout()` ni la invalidación de sesión ocurren. La sesión del usuario suspendido permanece activa en el navegador.

**Corrección:** `EnsureUserIsActive` debe ir **antes** de `Authenticate`. El middleware es null-safe (`$request->user()` devuelve `null` para usuarios no autenticados y el condicional `?->isSuspended()` retorna `null` seguramente), por lo que no interfiere con el flujo de usuarios no autenticados.

**Problema 2:** Los guards `class_exists` en `adminAuthMiddleware()` y `epicTwoResources()` eran workarounds de RFC-059. Ambas clases existen en `develop`.

```php
// ANTES (workaround RFC-059 + orden incorrecto):
->authMiddleware($this->adminAuthMiddleware())
// ... con epicTwoResources() y class_exists

// DESPUÉS (correcto):
->authMiddleware([
    EnsureUserIsActive::class,   // primero: invalida sesión del suspendido
    Authenticate::class,         // segundo: bloquea no autenticados y verifica canAccessPanel
])
// ->resources($this->epicTwoResources()) eliminado — discoverResources() lo cubre
```

Los métodos privados `adminAuthMiddleware()` y `epicTwoResources()` se eliminan.

---

## D-3 — Post-login redirect por rol con hook auto-activable [CERRADA]

**Decisión (corregida por auditoría SE-1):** En lugar de código comentado, usar `class_exists` activo. El hook se auto-activa sin modificación adicional cuando Épica 3 cree `AgentDashboard`. Es consistente con el patrón de extensión diferida del proyecto.

```php
// app/Filament/Pages/Auth/Login.php
protected function getRedirectUrl(): string
{
    if (auth()->user()?->hasRole('agente')
        && class_exists(\App\Filament\Pages\AgentDashboard::class)) {
        return \App\Filament\Pages\AgentDashboard::getUrl();
    }

    return parent::getRedirectUrl();
}
```

**Estado actual:** todos los roles → `/admin` (el `class_exists` retorna `false` porque la clase no existe).
**CD-1:** Épica 3 crea `App\Filament\Pages\AgentDashboard` y el hook se activa sin más cambios.

---

## D-4 — Rate limiting en login [CERRADA]

La implementación override `authenticate()` en `Login.php` e invoca `$this->rateLimit(5)` de `DanHarrin\LivewireRateLimiting` — **límite explícito de 5 intentos**, no el built-in de Filament como planeó el diseño. El resultado es más restrictivo. QA-041 refleja el límite real: 6.º intento genera throttle.

---

## D-5 — Orden de verificaciones en el flujo de login [CERRADA]

```
POST /admin/login
  └─ 1. Filament valida credenciales email/password
         → fallo: "Credenciales incorrectas" (rate limit aplicado)
  └─ 2. canAccessPanel() verifica isActive() + hasAnyRole([...])
         → fallo: "No tiene acceso al panel" (sin sesión creada)
  └─ 3. Autenticación exitosa → sesión creada
         → Login event dispatched
         → UpdateUserLastLoginAt listener actualiza last_login_at
  └─ 4. getRedirectUrl() → /admin (o AgentDashboard si class_exists)
  └─ 5. Requests subsiguientes → EnsureUserIsActive (primero en authMiddleware)
         → usuario suspendido: logout() + invalidate() + redirect a /admin/login
         → usuario activo: pasa → Authenticate verifica canAccessPanel()
```

---

## D-6 — Protección del rol `owner` en `UserPolicy::delete` [CERRADA]

**Problema (M-1 — Auditoría):** `UserPolicy::delete` actualmente permite que cualquier usuario con `users.delete` elimine a otros, siempre que no sea a sí mismo. El rol `owner` es el nivel máximo del sistema y no debe ser eliminable por ningún rol subordinado, presente ni futuro.

```php
// ANTES:
public function delete(User $auth, User $target): bool
{
    if (! $auth->can('users.delete')) { return false; }
    if ($auth->is($target)) { return false; }
    return true;
}

// DESPUÉS (protección defensiva añadida):
public function delete(User $auth, User $target): bool
{
    if (! $auth->can('users.delete')) { return false; }
    if ($auth->is($target)) { return false; }
    if ($target->hasRole('owner')) { return false; }
    return true;
}
```

**Justificación de tocar `UserPolicy.php` (Épica 2):** El cambio es una línea, estrictamente defensiva, y cierra un vector de seguridad real antes de que cualquier Épica futura pueda activarlo inadvertidamente.

---

# Alcance Técnico

## Árbol de archivos

```
Modificar:
  app/Models/User.php
    → canAccessPanel(): $this->can('users.view') → $this->hasAnyRole(['owner','admin','agente'])

  app/Providers/Filament/AdminPanelProvider.php
    → Agregar import: use App\Http\Middleware\EnsureUserIsActive;
    → authMiddleware inline con orden correcto: [EnsureUserIsActive, Authenticate]
    → Eliminar ->resources($this->epicTwoResources())
    → Eliminar métodos privados adminAuthMiddleware() y epicTwoResources()

  app/Policies/UserPolicy.php
    → delete(): añadir guard $target->hasRole('owner') → return false

  app/Filament/Pages/Auth/Login.php
    → Añadir getRedirectUrl() con class_exists activo para AgentDashboard

Crear:
  tests/Feature/Auth/AdminLoginTest.php
    → Suite de integración PHPUnit 12 con 9 tests
```

## Archivos que NO se tocan

```
app/Http/Middleware/EnsureUserIsActive.php       ← Épica 2 — operativo, no modificar
app/Services/UserStatusService.php               ← Épica 2 — operativo, no modificar
app/Listeners/UpdateUserLastLoginAt.php          ← Épica 2 — operativo, no modificar
app/Providers/AppServiceProvider.php             ← Épica 2 — operativo, no modificar
database/seeders/PermissionSeeder.php            ← Épica 2 — no agregar panel.access
database/migrations/*                           ← sin cambios
app/Filament/Resources/*                         ← Épicas 2/3 — no modificar
app/Models/Zone.php                              ← Épica 3 — no modificar
resources/css/filament/admin/theme.css           ← RFC-059 — no modificar
```

---

# Plan de Implementación por Lotes

## Lote A — Corrección de acceso, orden de middleware y hardening de policy

**Objetivo:** Los tres roles pueden autenticarse. Las sesiones de usuarios suspendidos se invalidan correctamente. `AdminPanelProvider` sin workarounds. `UserPolicy::delete` protege al `owner`.

### Archivos
- `app/Models/User.php`
- `app/Providers/Filament/AdminPanelProvider.php`
- `app/Policies/UserPolicy.php`

### Pasos

**1. Corregir `canAccessPanel()` en `User.php`:**

```php
public function canAccessPanel(Panel $panel): bool
{
    return $this->isActive() && $this->hasAnyRole(['owner', 'admin', 'agente']);
}
```

**2. Limpiar y corregir `AdminPanelProvider.php`:**

Agregar al bloque de imports:
```php
use App\Http\Middleware\EnsureUserIsActive;
```

Reemplazar el bloque de authMiddleware (orden corregido):
```php
// EnsureUserIsActive va PRIMERO para garantizar logout del suspendido
->authMiddleware([
    EnsureUserIsActive::class,
    Authenticate::class,
])
```

Eliminar `->resources($this->epicTwoResources())`.
Eliminar los métodos privados `adminAuthMiddleware()` y `epicTwoResources()`.

**3. Añadir guard en `UserPolicy::delete`:**

```php
public function delete(User $auth, User $target): bool
{
    if (! $auth->can('users.delete')) {
        return false;
    }

    if ($auth->is($target)) {
        return false;
    }

    if ($target->hasRole('owner')) {
        return false;
    }

    return true;
}
```

**4. Verificar:**
```bash
php artisan config:clear && php artisan filament:optimize-clear
php artisan about
```

### DoD del Lote A

- `php artisan about` sin errores.
- `AdminPanelProvider.php` sin ninguna llamada a `class_exists`.
- Login manual con usuario `owner` → `/admin` ✓
- Login manual con usuario `admin` → `/admin` ✓
- Login manual con usuario `agente` → `/admin` ✓ (antes bloqueado — fix central)
- Login con usuario suspendido → mensaje de error visible, sin sesión activa ✓
- `UserPolicy::delete` con target `owner` → retorna `false` (verificable via Tinker)

---

## Lote B — Hook de redirect post-login auto-activable

**Objetivo:** `Login::getRedirectUrl()` implementado con `class_exists`, sin código comentado.

### Archivos
- `app/Filament/Pages/Auth/Login.php`

### Pasos

Agregar el método después de `getSubheading()`:

```php
protected function getRedirectUrl(): string
{
    if (auth()->user()?->hasRole('agente')
        && class_exists(\App\Filament\Pages\AgentDashboard::class)) {
        return \App\Filament\Pages\AgentDashboard::getUrl();
    }

    return parent::getRedirectUrl();
}
```

### DoD del Lote B

- Login con cada uno de los tres roles → redirección a `/admin` ✓ (`class_exists` retorna false — `AgentDashboard` no existe aún)
- El método `getRedirectUrl()` existe y no contiene código comentado ✓

---

## Lote C — Tests de integración (PHPUnit 12)

**Objetivo:** Suite de 9 tests sobre PostgreSQL real con sintaxis PHPUnit 12.

### Archivos
- `tests/Feature/Auth/AdminLoginTest.php` (nuevo)

### Estructura del test

```php
<?php

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(PermissionSeeder::class);
    }
}
```

> `forgetCachedPermissions()` en `setUp` previene comportamientos inconsistentes
> entre tests por estado compartido de la caché de Spatie.

### Tests a implementar (nombres PHPUnit 12)

```php
public function test_owner_can_access_admin_panel(): void
public function test_admin_can_access_admin_panel(): void
public function test_agente_can_access_admin_panel(): void
public function test_active_user_without_allowed_roles_is_blocked(): void
public function test_suspended_user_is_blocked_at_can_access_panel_without_creating_session(): void
public function test_unauthenticated_user_is_redirected_to_login(): void
public function test_suspended_user_during_active_session_is_logged_out_by_middleware(): void
public function test_last_login_at_is_updated_on_successful_login_event(): void
public function test_all_roles_redirect_to_admin_dashboard_after_login(): void
```

### Verificación

```bash
composer test -- --filter=AdminLoginTest
# Debe mostrar 9/9 en verde sobre PostgreSQL
```

### DoD del Lote C

- Los 9 tests pasan en verde sobre PostgreSQL real.
- Tests usan `RefreshDatabase`.
- `setUp` llama `forgetCachedPermissions()` antes de sembrar permisos.
- No hay dependencias de SQLite in-memory.

---

# Criterios de Aceptación / Casos QA

| ID | Caso | Verificación |
|---|---|---|
| QA-038 | Owner activo puede iniciar sesión | POST /admin/login con credenciales de owner activo → HTTP 302 → /admin |
| QA-039 | Admin activo puede iniciar sesión | POST /admin/login con credenciales de admin activo → HTTP 302 → /admin |
| QA-040 | Agente activo puede iniciar sesión | POST /admin/login con credenciales de agente activo → HTTP 302 → /admin (**caso bloqueado antes de este RFC**) |
| QA-041 | Rate limiting en formulario de login | 6.º intento fallido desde el mismo cliente → `TooManyRequestsException` → Filament muestra notificación de throttle. Límite: 5 intentos por ventana (`$this->rateLimit(5)` en `Login::authenticate()`). |
| QA-042 | Usuario suspendido bloqueado en el momento del login | POST /admin/login con usuario suspendido → `canAccessPanel()` retorna `false` → error en pantalla, sin sesión creada, sin cookie de sesión persistida |
| QA-043 | Usuario suspendido durante sesión activa: sesión invalidada | Sesión válida → otro admin suspende al usuario → siguiente GET /admin → `EnsureUserIsActive` detecta suspensión → `auth()->logout()` ejecutado → redirect /admin/login + mensaje "Tu cuenta está suspendida" → sin sesión activa en servidor |
| QA-044 | `last_login_at` actualizado tras login exitoso | Login con credenciales válidas → DB: `users.last_login_at` != NULL y ≤ now() + 5s |
| QA-045 | Redirect post-login a /admin para todos los roles | Owner, admin y agente post-login → destino final: /admin (dashboard) |
| QA-046 | Usuario activo sin roles permitidos es bloqueado | Usuario activo sin ningún rol (owner/admin/agente) → `canAccessPanel()` retorna `false` → `Login::authenticate()` llama `Filament::auth()->logout()` + `throwFailureValidationException()` → error genérico "Estas credenciales no coinciden con nuestros registros." (sin sesión activa). Mensaje genérico es intencional: anti-enumeración. |

### Tests de referencia (PHPUnit 12 — nombres de métodos)

```php
public function test_owner_can_access_admin_panel(): void
public function test_admin_can_access_admin_panel(): void
public function test_agente_can_access_admin_panel(): void
public function test_active_user_without_allowed_roles_is_blocked(): void
public function test_suspended_user_is_blocked_at_can_access_panel_without_creating_session(): void
public function test_unauthenticated_user_is_redirected_to_login(): void
public function test_suspended_user_during_active_session_is_logged_out_by_middleware(): void
public function test_last_login_at_is_updated_on_successful_login_event(): void
public function test_all_roles_redirect_to_admin_dashboard_after_login(): void
```

---

# Riesgos Técnicos y Mitigaciones

## R-1 — Regresión en tests existentes por cambio de `canAccessPanel()`

**Riesgo:** Algún test existente asume implícitamente que un agente no puede acceder al panel.
**Mitigación:** Ningún test actual depende del comportamiento erróneo (los tests prueban el middleware y el CRUD, no el gate de Filament). Verificar con `composer test` antes del commit.
**Probabilidad:** Baja.

## R-2 — `discoverResources` encuentra recursos no autorizados

**Riesgo:** Al eliminar `->resources($this->epicTwoResources())`, un Resource no aprobado podría ser descubierto automáticamente.
**Mitigación:** `->discoverResources` ya estaba activo antes de este RFC. No hay cambio de comportamiento.
**Impacto:** Ninguno para el estado actual.

## R-3 — `hasAnyRole` en contexto de Panel

**Riesgo:** `canAccessPanel()` podría fallar si `HasRoles` no está cargado en ese contexto.
**Mitigación:** `User` usa `HasRoles` desde Épica 2 y funciona en todos los contextos donde el modelo está hidratado. QA-038/039/040 confirman el comportamiento.
**Probabilidad:** Muy baja.

## R-4 — Contrato diferido CD-1 y `class_exists`

**Riesgo:** Si `App\Filament\Pages\AgentDashboard` se crea con una firma de `getUrl()` diferente a la esperada por Filament, el hook fallará en runtime.
**Mitigación:** `getUrl()` es un método estático estándar de todas las páginas Filament. Cualquier clase que extienda `\Filament\Pages\Page` lo tiene. Épica 3 debe extender `Page`.

## R-5 — Conflictos de merge en `AdminPanelProvider.php`

**Riesgo:** El provider es frecuentemente modificado; el rebase puede generar conflictos.
**Mitigación:** Rebase sobre `develop` actualizado antes de iniciar el Lote A. Resolver conflictos preservando el orden correcto de middlewares (`EnsureUserIsActive` primero).

---

# Decisiones Diferidas / Abiertas

| ID | Decisión | Razón del diferimiento | Épica destino |
|---|---|---|---|
| CD-1 | Página de landing del agente post-login (`AgentDashboard`) | Hook preparado con `class_exists`; se activa solo cuando la clase exista | Épica 3 |
| CD-2 | UX/UI definitiva de `AgentDashboard` | Decisión de negocio/producto fuera del scope de este RFC | Épica 3 |
| CD-3 | Password reset vía email (`->passwordReset()`) | Requiere config SMTP; diferido en RFC-059 | Épica 8 |
| CD-4 | Notificación de suspensión con razón de `user_status_logs` | Requiere cambio en `EnsureUserIsActive` o en la vista de login; decisión de UX | Épica 8 |
| CD-5 | Auditoría persistente de logins fallidos y bloqueados | El throttle temporal de Filament es suficiente ahora | Épica 8 |
| CD-6 | 2FA / login vía OAuth / SSO | Fuera del scope actual | Épica 8 |

---

# Checklist de Cierre Técnico

## Pre-commit (Edgar)

- [ ] `User::canAccessPanel()` usa `hasAnyRole(['owner', 'admin', 'agente'])`
- [ ] `AdminPanelProvider.php` no contiene ninguna llamada a `class_exists`
- [ ] Orden de `authMiddleware`: `EnsureUserIsActive` primero, `Authenticate` segundo
- [ ] Métodos privados `adminAuthMiddleware()` y `epicTwoResources()` eliminados
- [ ] `EnsureUserIsActive` importado directamente en `AdminPanelProvider.php`
- [ ] `->resources($this->epicTwoResources())` eliminado
- [ ] `UserPolicy::delete` incluye guard `$target->hasRole('owner') → return false`
- [ ] `Login::getRedirectUrl()` usa `class_exists` activo (sin código comentado)
- [ ] `AdminLoginTest.php` creado con los 9 tests en PHPUnit 12
- [ ] `setUp` del test llama `forgetCachedPermissions()` antes de sembrar
- [ ] `composer test -- --filter=AdminLoginTest` → 9/9 en verde sobre PostgreSQL
- [ ] `composer test` completo sin regresiones
- [ ] `php artisan about` sin errores
- [ ] Login manual owner → `/admin` ✓
- [ ] Login manual admin → `/admin` ✓
- [ ] Login manual agente → `/admin` ✓ (el caso que antes estaba bloqueado)
- [ ] Login con usuario suspendido → error en pantalla, sin sesión activa ✓
- [ ] Suspender usuario con sesión activa → logout + redirect + mensaje ✓

## Pre-merge (Sebastián — QA)

- [ ] QA-038 Owner login ✓
- [ ] QA-039 Admin login ✓
- [ ] QA-040 Agente login ✓ ← verificar con especial atención (era el bug)
- [ ] QA-041 Rate limiting (6.º intento → throttle) ✓
- [ ] QA-042 Suspendido en login → sin sesión ✓
- [ ] QA-043 Suspendido durante sesión activa → logout + redirect + mensaje ✓
- [ ] QA-044 `last_login_at` actualizado ✓
- [ ] QA-045 Redirect a /admin para todos los roles ✓
- [ ] QA-046 Usuario activo sin roles → bloqueado, sin sesión ✓
- [ ] Regresión RFC-059: QA-026 a QA-037 (theme y glassmorphism) sin cambios visuales ✓
- [ ] Regresión Épica 2: CRUD de usuarios accesible para owner y admin ✓
- [ ] Regresión Épica 3: ZoneResource visible para owner y admin ✓

## Post-merge (Kristian)

- [ ] Merge `feature/rfc-060-login-real` → `develop`
- [ ] Crear tag `v0.X.0-login-real`
- [ ] Actualizar estado de este RFC a `✅ IMPLEMENTADO`
- [ ] Documentar CD-1 y CD-2 (`AgentDashboard` + hook `class_exists` en `Login::getRedirectUrl()`) en el diseño de Épica 3

---

# Estimación

**Responsable de implementación:** Edgar

**Duración estimada:** 2–3 horas (0.25 Sprint)

**Complejidad:** Baja

**Detalle:**
- Lote A (corrección + limpieza + policy): ~45 min
- Lote B (hook redirect): ~15 min
- Lote C (9 tests PHPUnit 12): ~1.5 h
- QA manual: ~30 min

**Sin nuevas migraciones. Sin nuevas dependencias. Sin nuevas rutas.**

---

# Registro de Cambios desde la Auditoría

| # | Hallazgo | Tipo | Cambio aplicado | Sección(es) afectada(s) |
|---|---|---|---|---|
| C-1 | Orden inválido del middleware: `EnsureUserIsActive` después de `Authenticate` causa que el suspendido obtenga un 403 sin logout ni invalidación de sesión | Crítico | Invertido el orden en D-2 y Lote A: `[EnsureUserIsActive, Authenticate]`. Añadida explicación del flujo correcto. | D-2, Lote A, D-5, Checklist |
| M-1 | `UserPolicy::delete` no protege al rol `owner` de eliminación por roles futuros | Medio | Añadido guard `$target->hasRole('owner') → false` en D-6 y Lote A. `UserPolicy.php` agregado al árbol de archivos a modificar. | D-6 (nuevo), Alcance Técnico, Lote A, Checklist |
| Mn-1 | Sintaxis de tests en Pest (`it(...)`) inconsistente con el proyecto que usa PHPUnit 12 nativo | Menor | Todos los nombres de tests actualizados a `test_*` (PHPUnit 12). `setUp` con `forgetCachedPermissions()` añadido a la estructura de `AdminLoginTest`. | Lote C, QA |
| Mn-2 | Ausencia de QA para usuario activo sin roles permitidos | Menor | Añadido QA-046 y 9° test `test_active_user_without_allowed_roles_is_blocked`. | QA, Lote C |
| SE-1 | Código comentado para redirect de agente genera ruido técnico en `Login.php` | Sobreingeniería | Reemplazado por `class_exists(\App\Filament\Pages\AgentDashboard::class)` activo en D-3 y Lote B. No requiere cambio en Épica 3. | D-3, Lote B, Alcance |
| Ob-1 | Reordenar middleware | Obligatoria | Aplicado — mismo que C-1 | D-2, Lote A |
| Ob-2 | Implementar redirect con `class_exists` | Obligatoria | Aplicado — mismo que SE-1 | D-3, Lote B |
| Ob-3 | Añadir protección `owner` en `UserPolicy::delete` | Obligatoria | Aplicado — mismo que M-1 | D-6, Lote A |
| Ob-4 | Escribir tests en PHPUnit 12 | Obligatoria | Aplicado — mismo que Mn-1 | Lote C, QA |
| PA-1 | Landing definitiva del agente: ¿vista UX/UI definida? | Pregunta abierta | Cerrada como CD-2: decisión de negocio diferida a Épica 3 | Decisiones Diferidas |
| PA-2 | Notificación de suspensión con razón de `user_status_logs` | Pregunta abierta | Cerrada como CD-4: requiere cambio de UX, diferido a Épica 8 | Decisiones Diferidas |
| RI-2 | Caché de Spatie en tests puede generar inconsistencias | Riesgo de Implementación | `forgetCachedPermissions()` añadido al `setUp` de `AdminLoginTest` | Lote C |

---

# Hallazgos No Aplicados

| # | Hallazgo | Razón |
|---|---|---|
| M-2 | Modificar `UpdateUserLastLoginAt` para no actualizar `last_login_at` si el usuario no tiene roles permitidos | El listener opera sobre el evento `Login` de Laravel, que dispara cuando las credenciales son correctas — comportamiento correcto por diseño. El caso "usuario activo sin roles" es una anomalía operacional, no un flujo estándar. Modificar el listener (archivo Épica 2) por este escenario viola el principio de aditividad mínima. La auditoría ofrece como alternativa un "log de eventos de acceso", que corresponde a la capa de auditoría de Épica 8. |
| Op-1 | Emitir advertencia al log cuando `UpdateUserLastLoginAt` detecte un login de usuario sin roles de panel | Ampliación de funcionalidad del listener (Épica 2). La auditoría de accesos denegados es responsabilidad de Épica 8. Diferido a CD-5. |
