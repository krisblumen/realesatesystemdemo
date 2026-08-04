# RFC-061 INTEGRACIÓN DE ZONAS COMERCIALES AL DASHBOARD

## Proyecto

NEW HAUZ

## RFC

RFC-061

## Estado

✅ Listo para implementación

## Rama base

`develop`

## Rama de trabajo

`feature/rfc-061-zonas-dashboard`

## Responsable Principal

Kristian

## Participantes

### Arquitectura

- Kristian  
- Edgar

### QA

- Sebastián

## Fecha

2026-06-18

---

# Seguimiento del Pipeline Multimodelo

| Etapa | Agente | Estado | Fecha |
| :---- | :---- | :---- | :---- |
| 1\. Generación del RFC | Claude (Arquitecto) | ✅ Completado | 2026-06-18 |
| 2\. Auditoría de diseño | Antigravity | ✅ Completado | 2026-06-18 |
| 3\. Aplicación de correcciones | Claude (Agente Impl.) | ✅ Completado | 2026-06-18 |
| 4\. Implementación | Codex | ✅ Completado | 2026-06-18 |
| 5\. Auditoría de implementación | Antigravity | ✅ Completado | 2026-06-18 |
| 6\. Cierre técnico | Claude (Arquitecto) | ⬜ Pendiente | — |

---

# Objetivo

Exponer la **Épica 3 (Zonas Comerciales)** en el dashboard de Filament, en dos frentes:

1. **Landing del agente** — crear `App\Filament\Pages\AgentDashboard`, que **activa el hook de redirect ya existente en RFC-060** (`Login::getRedirectUrl()`), mostrando al agente sus zonas asignadas.  
2. **Visión administrativa** — widgets de resumen de zonas en el dashboard principal, visibles para owner y admin.

Este RFC **cumple el contrato CD-1** que RFC-060 dejó diferido a Épica 3: el redirect del agente se activa con solo crear la clase, sin tocar `Login.php`.

---

# Contexto y Dependencias

## Consume de RFC-060 (Login Real) — contrato duro

`app/Filament/Pages/Auth/Login.php`, método `getRedirectUrl()`, contiene (DIV-3 del cierre de RFC-060):

```php
if (auth()->user()?->hasRole('agente') && class_exists(AgentDashboard::class)) {
    return AgentDashboard::getUrl();
}
```

**Restricción absoluta:** la clase debe ser exactamente `App\Filament\Pages\AgentDashboard`. Cualquier otro nombre/namespace deja el hook inerte y el agente cae al dashboard por defecto. Ver R-2.

## Consume de Épica 3 (Zonas Comerciales) — ya implementada

| Contrato | Origen | Uso en este RFC |
| :---- | :---- | :---- |
| Modelo `Zone` (campos: name, slug, description, municipality, status) | RFC-015 | Lectura para widgets |
| Relación `User ↔ Zone` (asignación de agentes, M:N) | RFC-017 | Zonas del agente en su landing |
| `ZoneResource` (CRUD Filament) | RFC-016 | No se modifica; se enlaza desde widgets |
| Polígonos PostGIS | RFC-018 | **Fuera de alcance visual** en esta fase (solo metadatos/conteos) |

**Contrato confirmado (R-1 cerrada):** la relación `User::zones()` ya existe en `app/Models/User.php:95-99` — `belongsToMany(Zone::class, 'agent_zone', 'agent_id', 'zone_id')->withTimestamps()`. No requiere modificación. `User.php` **no se toca** en este RFC.

## Consume de Épica 2 (Usuarios y Seguridad)

- Roles `owner`, `admin`, `agente`.  
- `User::canAccessPanel()` → ya retorna `true` para los tres roles (contrato RFC-060).  
- `UserPolicy` (no se toca).

## Consume de Épica 1

- Panel Filament `/admin` y su `Dashboard` por defecto.

---

# Alcance

## Lo que entrega este RFC

- `App\Filament\Pages\AgentDashboard` — página Filament, landing del agente. Activa el hook de redirect de RFC-060.  
- `AgentZonesWidget` — lista las zonas asignadas al agente autenticado. Maneja el caso borde **agente sin zonas** con un estado vacío explícito.  
- `ZonesOverviewWidget` — tarjetas de resumen (StatsOverview) para owner/admin en el dashboard principal: total de zonas, zonas activas, agentes asignados.  
- Control de acceso por rol en cada página/widget (`canAccess()` / `canView()`).  
- Confirmación de la relación `User::zones()` (ya operativa desde Épica 3 — sin modificación de `User.php`).

## Lo que NO entrega este RFC

- Métricas **comerciales** por zona (leads, conversión) → Épica 7 (RFC-040).  
- Mapa interactivo / render de polígonos PostGIS en el dashboard → Épica 7 (RFC-043).  
- Conteo de **inmuebles por zona** → depende de Épica 4 (modelo `Property`). Se difiere hasta confirmar esa épica. Ver R-3.  
- Edición de zonas (ya vive en `ZoneResource`, RFC-016).  
- Cualquier cambio a `Login.php` (el hook ya existe; este RFC solo crea la clase que lo activa).  
- UX/UI definitiva del `AgentDashboard` (CD-2 sigue diferido; esta fase entrega la versión funcional).

---

# Diseño Técnico

## 5.1 Topología de dashboards (CERRADO)

```
Login (RFC-060) ── redirect por rol ──┬─ owner / admin → /admin (Dashboard por defecto)
                                       │                    └─ ZonesOverviewWidget
                                       └─ agente          → /admin/mi-zona (AgentDashboard)
                                                            └─ AgentZonesWidget
```

- El **Dashboard por defecto** de Filament hospeda `ZonesOverviewWidget` (gated owner/admin).  
- `AgentDashboard` es una página nueva con `AgentZonesWidget` (gated agente).  
- La separación por `canView()` evita que el agente vea widgets administrativos y que owner/admin vean el widget del agente.

## 5.2 `AgentDashboard` (CERRADO — contrato con RFC-060)

```php
// app/Filament/Pages/AgentDashboard.php
namespace App\Filament\Pages;

use Filament\Pages\Page;

class AgentDashboard extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-map';
    protected static ?string $navigationLabel = 'Mi Zona';
    protected static ?string $title           = 'Mi Zona';
    protected static ?string $slug            = 'mi-zona';
    protected static string  $view            = 'filament.pages.agent-dashboard';

    public static function canAccess(): bool
    {
        // Landing del agente. Owner/admin no la necesitan (tienen el dashboard).
        return auth()->user()?->hasRole('agente') ?? false;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\AgentZonesWidget::class,
        ];
    }
}
```

El nombre de clase y namespace son **inamovibles** (contrato RFC-060). El `slug` (`mi-zona`) sí es libre: `getUrl()` lo resuelve solo.

## 5.3 `AgentZonesWidget` (CERRADO)

Lista las zonas del agente. Caso borde explícito: sin zonas → estado vacío con guía.

```php
// app/Filament/Widgets/AgentZonesWidget.php
namespace App\Filament\Widgets;

use App\Enums\ZoneStatus;
use Filament\Widgets\Widget;

class AgentZonesWidget extends Widget
{
    protected static string $view = 'filament.widgets.agent-zones';
    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('agente') ?? false;
    }

    public function getZones()
    {
        return auth()->user()
            ->zones()
            ->where('status', ZoneStatus::Active->value)  // 'activa' — confirmado en Épica 3
            ->orderBy('name')
            ->get();
    }
}
```

Vista (resumen — sin polígonos, solo metadatos):

```
{{-- resources/views/filament/widgets/agent-zones.blade.php --}}
<x-filament-widgets::widget>
    <x-filament::section heading="Mis zonas asignadas">
        @php($zones = $this->getZones())
        @if($zones->isEmpty())
            <p class="text-sm text-gray-500">
                Aún no tienes zonas asignadas. Contacta al administrador.
            </p>
        @else
            <ul class="divide-y">
                @foreach($zones as $zone)
                    <li class="py-2">
                        <span class="text-sm font-medium">{{ $zone->name }}</span>
                        <span class="text-sm text-gray-500 ml-1">— {{ $zone->municipality }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
```

> **Sin enlace a `ZoneResource` (R-4 cerrada):** `ZonePolicy::view` requiere `zones.manage`; el rol `agente` sólo tiene `properties.manage` y `leads.manage` (confirmado en `ZonePolicy.php` + `PermissionSeeder`). Cualquier enlace produciría un 403. Si en el futuro se quiere vista de detalle para el agente, crear página propia dentro de `AgentDashboard` — no usar el Resource existente.

## 5.4 `ZonesOverviewWidget` (CERRADO)

Resumen estructural (no comercial) para owner/admin en el dashboard principal.

```php
// app/Filament/Widgets/ZonesOverviewWidget.php
namespace App\Filament\Widgets;

use App\Enums\ZoneStatus;
use App\Models\Zone;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ZonesOverviewWidget extends BaseWidget
{
    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['owner', 'admin']) ?? false;
    }

    protected function getStats(): array
    {
        return [
            Stat::make('Zonas totales', Zone::count()),
            Stat::make('Zonas activas', Zone::where('status', ZoneStatus::Active->value)->count()),
            Stat::make('Agentes asignados', \App\Models\User::role('agente')->count()),
        ];
    }
}
```

`ZonesOverviewWidget` se **auto-descubre** (carpeta `app/Filament/Widgets`) y aparece en el dashboard por defecto. `canView()` lo restringe a owner/admin. `AgentZonesWidget` también se auto-descubre, pero su `canView()` (solo agente) impide que aparezca para owner/admin; se registra explícitamente en `AgentDashboard` para garantizar su lugar.

## 5.5 Relación `User::zones()` (CONFIRMADA — sin modificación)

**Confirmado por auditoría de diseño (Mn-1):** la relación ya existe en `app/Models/User.php:95-99` implementada por Épica 3 (RFC-017):

```php
// app/Models/User.php — EXISTENTE, no crear ni modificar
public function zones(): BelongsToMany
{
    return $this->belongsToMany(Zone::class, 'agent_zone', 'agent_id', 'zone_id')
        ->withTimestamps();
}
```

Tabla pivote: `agent_zone`. Clave local: `agent_id`. Clave foránea: `zone_id`. **`app/Models/User.php` queda fuera del alcance de este RFC.**

---

# Alcance Técnico

## Árbol de archivos

```
Crear:
  app/Filament/Pages/AgentDashboard.php
  app/Filament/Widgets/AgentZonesWidget.php
  app/Filament/Widgets/ZonesOverviewWidget.php
  resources/views/filament/pages/agent-dashboard.blade.php
  resources/views/filament/widgets/agent-zones.blade.php
  tests/Feature/Dashboard/AgentDashboardTest.php
  tests/Feature/Dashboard/ZonesWidgetsTest.php

```

## Archivos que NO se tocan

```
app/Filament/Pages/Auth/Login.php          ← RFC-060 — el hook ya existe, sólo lectura
app/Filament/Resources/ZoneResource*       ← Épica 3 — consumido, no modificado
app/Models/Zone.php                         ← Épica 3 — consumido, no modificado
app/Models/User.php                         ← zones() ya operativa (Mn-1 cerrada) — sin modificación
app/Providers/Filament/AdminPanelProvider.php ← Filament auto-descubre Pages y Widgets; no requiere cambios
bootstrap/app.php                           ← RFC-060 — intacto
```

Filament v3 auto-descubre `app/Filament/Pages` y `app/Filament/Widgets`. **No** se registra nada manualmente en el panel provider (evita la superficie de conflicto del archivo más disputado del repo).

---

# Plan de Implementación por Lotes

```
Lote A → Lote B → Lote C → Lote D
Contratos  Agente   Admin    Tests
+ Página            widgets
```

## Lote A — Confirmación de contratos \+ `AgentDashboard` base

**Archivos:**

1. `app/Filament/Pages/AgentDashboard.php`  
2. `resources/views/filament/pages/agent-dashboard.blade.php` (mínima)

> `User::zones()` ya está operativa — no se modifica `User.php`.

**Verificación:**

```shell
# 1. Confirmar que el hook de RFC-060 ahora resuelve la clase
php artisan tinker --execute="var_dump(class_exists(App\Filament\Pages\AgentDashboard::class));" # true
# 2. Login como agente → debe aterrizar en /admin/mi-zona (no en /admin)
```

**DoD:** `class_exists(AgentDashboard::class)` es `true`. Un agente logueado es redirigido a `/admin/mi-zona` (el hook de RFC-060 se activa). `User::zones()` resuelve sin error (ya existía). Owner/admin **no** ven `AgentDashboard` en navegación.

---

## Lote B — Widget de zonas del agente

**Archivos:**

1. `app/Filament/Widgets/AgentZonesWidget.php`  
2. `resources/views/filament/widgets/agent-zones.blade.php`

**Puntos críticos:**

- `canView()` gateado a `agente`.  
- Estado vacío explícito (agente sin zonas).  
- Sin enlace a `ZoneResource` — agente no tiene `zones.manage` (R-4 cerrada). Texto plano únicamente.

**Verificación:**

```shell
# Agente con zonas: las ve listadas. Agente sin zonas: estado vacío con guía.
# Owner/admin: el widget NO aparece (canView falso).
```

**DoD:** Agente con zonas las ve; agente sin zonas ve el mensaje guía; el widget no se filtra a owner/admin.

---

## Lote C — Widget de resumen de zonas (owner/admin)

**Archivos:**

1. `app/Filament/Widgets/ZonesOverviewWidget.php`

**Verificación:**

```shell
# Owner/admin en /admin: ven las tres tarjetas (totales, activas, agentes).
# Agente en /admin/mi-zona: NO ve este widget.
```

**DoD:** Tarjetas correctas para owner/admin; ausentes para agente. Conteos coinciden con la base.

---

## Lote D — Tests, QA y regresión

**Archivos:**

1. `tests/Feature/Dashboard/AgentDashboardTest.php`  
2. `tests/Feature/Dashboard/ZonesWidgetsTest.php`

**Verificación:**

```shell
php artisan test --testsuite=Feature --filter=Dashboard
# Verde, sin regresiones de RFC-060 (redirect por rol) ni Épica 3 (ZoneResource).
```

**DoD:** QA-047 → QA-055 cubiertos por tests. Sebastián valida los casos manuales.

---

# Criterios de Aceptación / Casos QA

| ID | Caso | Verificación |
| :---- | :---- | :---- |
| QA-047 | Redirect del agente activa el hook | Agente hace login → aterriza en `/admin/mi-zona` (no en `/admin`) |
| QA-048 | Zonas del agente | `AgentDashboard` lista las zonas asignadas al agente autenticado |
| QA-049 | Agente sin zonas | Estado vacío con mensaje guía; sin error |
| QA-050 | Resumen para owner/admin | Dashboard principal muestra `ZonesOverviewWidget` con conteos correctos |
| QA-051 | Aislamiento de widgets | Agente no ve widgets administrativos; owner/admin no ven el widget del agente |
| QA-052 | Acceso a `AgentDashboard` | Un no-agente que navega directo a `/admin/mi-zona` → bloqueado por `canAccess()` |
| QA-053 | Regresión RFC-060 | Redirect por rol intacto; owner/admin siguen yendo al dashboard por defecto |
| QA-054 | Regresión Épica 3 | `ZoneResource` (CRUD de zonas) sigue operativo sin cambios |
| QA-055 | Usuario activo sin rol | Un usuario activo sin ningún rol no accede a ningún dashboard (bloqueado en `canAccessPanel`) |

## Tests de referencia (PHPUnit 12 nativo, sobre PostgreSQL real)

```php
// tests/Feature/Dashboard/AgentDashboardTest.php
public function test_redirects_agente_to_agent_dashboard_after_login(): void
public function test_lists_assigned_zones_of_authenticated_agente(): void
public function test_shows_empty_state_when_agente_has_no_zones(): void
public function test_blocks_non_agente_from_accessing_agent_dashboard(): void
public function test_blocks_active_user_without_any_role_from_accessing_any_dashboard(): void

// tests/Feature/Dashboard/ZonesWidgetsTest.php
public function test_shows_zones_overview_widget_to_owner_and_admin(): void
public function test_hides_zones_overview_widget_from_agente(): void
public function test_hides_agent_zones_widget_from_owner_and_admin(): void
public function test_zone_counts_match_the_database(): void
```

---

# Riesgos Técnicos y Mitigaciones

## R-1 — Nombre real de la relación `User ↔ Zone` ✅ CERRADA

**Riesgo original:** El diseño asumía `User::zones()`. Épica 3 pudo nombrarla distinto.  
**Resolución (auditoría Mn-1):** Confirmado en `app/Models/User.php:95-99` — `belongsToMany(Zone::class, 'agent_zone', 'agent_id', 'zone_id')->withTimestamps()`. La relación existe y tiene el nombre esperado. **`User.php` queda fuera del alcance de este RFC.**

## R-2 — Nombre de clase de `AgentDashboard` (contrato RFC-060)

**Riesgo:** Si la clase no es exactamente `App\Filament\Pages\AgentDashboard`, el hook `class_exists(...)` de RFC-060 nunca se cumple y el agente cae al dashboard por defecto, **sin error visible**. **Mitigación:** QA-047 lo verifica en vivo (login de agente → `/admin/mi-zona`). Es el primer DoD del Lote A.

## R-3 — Inmuebles por zona depende de Épica 4

**Riesgo:** Un widget "inmuebles por zona" requiere el modelo `Property` que quizá aún no existe (Épica 4). **Mitigación:** Fuera de alcance en este RFC. Si se quisiera adelantar, guardar con `class_exists(\App\Models\Property::class)` / `Schema::hasTable('properties')`. Diferido a Épica 4\.

## R-4 — Permiso de lectura de zonas para el agente ✅ CERRADA

**Riesgo original:** El enlace "Ver" en el widget del agente podía generar un 403.  
**Resolución (auditoría M-2):** Confirmado en `app/Policies/ZonePolicy.php` — `view()` requiere `zones.manage`. El rol `agente` sólo tiene `properties.manage` y `leads.manage` (confirmado en `PermissionSeeder`). El enlace ha sido **eliminado** de la vista; se muestran nombre y municipalidad como texto plano. Si en el futuro se quiere vista de detalle para el agente, crear página propia dentro de `AgentDashboard` (Op-1 de la auditoría).

## R-5 — Auto-descubrimiento de widgets se filtra entre dashboards

**Riesgo:** Un widget auto-descubierto sin `canView()` correcto aparece donde no debe. **Mitigación:** Cada widget define `canView()` por rol. Tests QA-051 verifican el aislamiento en ambas direcciones.

## R-6 — Render de polígonos PostGIS costoso

**Riesgo:** Mostrar geometrías en el dashboard sería pesado. **Mitigación:** Esta fase muestra **sólo metadatos y conteos**, nunca geometrías. El mapa se difiere a Épica 7 (RFC-043).

---

# Decisiones Diferidas / Abiertas

| \# | Tema | Estado | Destino |
| :---- | :---- | :---- | :---- |
| CD-1 (RFC-060) | Página `AgentDashboard` | **✅ Resuelta por este RFC** | — |
| CD-2 (RFC-060) | UX/UI definitiva del `AgentDashboard` | Versión funcional entregada; refinamiento iterativo | Épica 3 / Épica 6 |
| D-1 | Conteo de inmuebles por zona | Diferido (depende de `Property`) | Épica 4 |
| D-2 | Métricas comerciales por zona (leads, conversión) | Diferido | Épica 7 (RFC-040) |
| D-3 | Mapa / polígonos PostGIS en dashboard | Diferido | Épica 7 (RFC-043) |
| D-4 | Enlace del agente a `ZoneResource` según Policy | **✅ Cerrada** — agente no tiene `zones.manage`; link eliminado, texto plano | — |

---

# Checklist de Cierre Técnico

## Pre-commit (Kristian)

- [ ] Relación `User::zones()` confirmada o añadida (aditiva)  
- [ ] `AgentDashboard` con nombre/namespace exacto del contrato RFC-060  
- [ ] Hook de RFC-060 verificado: agente → `/admin/mi-zona`  
- [ ] `AgentZonesWidget` con estado vacío y `canView()` (agente)  
- [ ] `ZonesOverviewWidget` con `canView()` (owner/admin)  
- [ ] Sin cambios en `Login.php`, `AdminPanelProvider.php`, `ZoneResource`, `Zone`  
- [ ] `php artisan test --filter=Dashboard` en verde

## Pre-merge (Sebastián — QA)

- [ ] QA-047 Redirect del agente ✓  
- [ ] QA-048 Zonas del agente ✓  
- [ ] QA-049 Agente sin zonas ✓  
- [ ] QA-050 Resumen owner/admin ✓  
- [ ] QA-051 Aislamiento de widgets ✓  
- [ ] QA-052 Acceso a `AgentDashboard` ✓  
- [ ] QA-053 Regresión RFC-060 (redirect por rol) ✓  
- [ ] QA-054 Regresión Épica 3 (ZoneResource) ✓  
- [ ] QA-055 Usuario activo sin rol bloqueado ✓

## Post-merge (Edgar)

- [ ] Merge `feature/rfc-061-zonas-dashboard` → `develop`  
- [ ] Tag `v0.X.0-zonas-dashboard`  
- [ ] Estado del RFC → `✅ IMPLEMENTADO`  
- [ ] Confirmar contrato para épicas siguientes: `AgentDashboard::getUrl()` estable

---

# Estimación

Arquitectura: Kristian

Duración estimada: 0.5 Sprint

Complejidad: Baja (los dos contratos de riesgo —relación `User::zones()` y nombre de `AgentDashboard`— están cerrados. El volumen de código es reducido).

---

# Registro de Cambios desde la Auditoría

| # | Hallazgo | Tipo | Cambio aplicado |
| :---- | :---- | :---- | :---- |
| M-1 | Enum `ZoneStatus` — valor `'activo'` incorrecto | Medio | Corregido a `ZoneStatus::Active->value` (`'activa'`) en ambos widgets |
| M-2 | Enlace a `ZoneResource` produce 403 al agente | Medio | Enlace eliminado; texto plano en la vista `agent-zones.blade.php` |
| Mn-1 | `User::zones()` ya existe — modificación innecesaria | Menor | `User.php` removido del alcance; sección 5.5 y árbol de archivos actualizados |
| Mn-2 | Tests en sintaxis Pest sobre entorno PHPUnit 12 | Menor | Nombres de tests convertidos a formato `test_*` de PHPUnit 12 nativo |
| Mn-3 | Ausencia de QA para usuario activo sin roles | Menor | Añadido QA-055 y `test_blocks_active_user_without_any_role_from_accessing_any_dashboard` |

# Hallazgos No Aplicados

| # | Hallazgo | Tipo | Decisión |
| :---- | :---- | :---- | :---- |
| Op-1 | Crear página de detalle exclusiva para agente (futura iteración) | Opcional | Diferido — queda en CD-2 (UX/UI definitiva de `AgentDashboard`). Épica 6 decide la dirección. |

---

Estado: ✅ Listo para implementación.
