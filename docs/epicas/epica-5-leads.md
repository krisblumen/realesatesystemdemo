# Épica 5 — Leads (RFC-025 → RFC-028)

**Proyecto:** New Hauz — Plataforma Monolítica Inmobiliaria
**Stack:** Laravel 13.x · PHP 8.3 · PostgreSQL · Filament v3 · Livewire 3 · `spatie/laravel-permission` · Laravel Notifications
**Rama:** `feature/epica-5-leads` · **Tag objetivo:** `v0.5.0-leads`
**RFCs origen:** `docs/rfc/RFC-025-MODELO-LEAD.md`, `RFC-026-CAPTURA-DE-LEADS.md`, `RFC-027-ASIGNACION-AUTOMATICA.md`, `RFC-028-NOTIFICACIONES-DE-LEADS.md`
**Responsable:** Edgar · **Apoyo:** Kristian · **QA:** Sebastián
**Estado:** ✅ **Aprobado para implementación** (cierre de diseño — paso 3, auditoría aplicada)

---

## 1. Título y estado

Diseño técnico consolidado de la **Épica 5 — Leads**: captación y distribución de prospectos comerciales. Cubre el modelo `Lead`, la captura desde formularios públicos, la asignación automática a agentes y las notificaciones al agente asignado. Implementable de forma incremental por lotes **A→E**.

**Estado:** diseño cerrado y aprobado para implementación. La auditoría de diseño (paso 2) fue aplicada; ver §16 "Cierre técnico del diseño".

---

## 2. Contexto

Esta épica se apoya en épicas previas ya implementadas y aprobadas:

| Épica | Aporta a Leads |
| :--- | :--- |
| **1 — Fundación** (RFC-001→010) | Laravel 13, PostgreSQL, Filament, Livewire. |
| **2 — Usuarios y Seguridad** (RFC-011→014) | Modelo `User`, roles `owner/admin/agente`, `User.status` (`UserStatus`), permiso **`leads.manage`** ya sembrado. |
| **4 — Inmuebles** | Modelo `Property` con `agent_id` (belongsTo `User`), `zone_id`, `status`, y el patrón `scopeVisibleTo(User)`. |

**Relación opcional con la Épica 3 — Zonas:** está en curso por separado. La asignación por zona es **diferida**: si el contrato `Zone` geográfico no está mergeado, la regla de zona no se activa y la asignación cae a round-robin. La Épica 5 **no se bloquea** por la Épica 3.

**Contratos que se consumen (NO se reescriben):** `User` (Épica 2), `Property` (Épica 4), `spatie/laravel-permission`, Filament, Livewire, PostgreSQL, Laravel Notifications.

---

## 3. Objetivos y no-objetivos

### Objetivos
- Modelar el prospecto (`Lead`) como entidad de primera clase con su máquina de estados.
- Capturar leads desde formularios públicos (general y por inmueble) sin autenticación, con validación y anti-spam.
- Asignar automáticamente cada lead nuevo a un agente, de forma determinista, justa e idempotente.
- Notificar al agente asignado por base de datos y correo.
- Aplicar autorización real con `leads.manage` y acotar a cada agente a sus propios leads.

### No-objetivos
- No se construye un CRM completo (pipeline kanban, scoring, automatizaciones de marketing).
- No se integran proveedores externos de email/SMS de pago; se usa el mailer del proyecto.
- No se implementa la regla de asignación por zona hasta que cierre la Épica 3 (queda preparada).
- No se recrean `User` ni `Property`; se consumen como contrato.

---

## 4. Alcance

### Alcance funcional
- Formulario público general de contacto y formulario por inmueble (precarga `property_id`).
- Registro de todo lead en el sistema con su origen (`source`) y estado (`status`).
- Asignación automática al crear el lead, con reasignación manual para owner/admin.
- Bandeja de gestión (LeadResource) en `/admin` con filtros, búsqueda y cambio de estado.
- Notificación al agente asignado (database + mail) y registro para auditoría.

### Alcance técnico
- Migración `leads` (PostgreSQL) con FKs nullable a `properties` y `users`.
- Enums PHP `LeadSource` y `LeadStatus`.
- Evento `LeadCaptured` + listener/servicio de asignación (`LeadAssignmentService`).
- Notification `LeadAssignedNotification` (canales `database`, `mail`).
- Policy `LeadPolicy` y scope por agente reutilizando el patrón `scopeVisibleTo`.

---

## 5. RFC-025 — Modelo `Lead`

### Campos

| Campo | Tipo | Notas |
| :--- | :--- | :--- |
| `id` | bigserial PK | |
| `name` | varchar | requerido |
| `email` | varchar | requerido (validación email) |
| `phone` | varchar nullable | |
| `message` | text nullable | |
| `source` | varchar (enum) | `LeadSource`, default `web` |
| `status` | varchar (enum) | `LeadStatus`, default `nuevo` |
| `property_id` | bigint FK nullable → `properties.id` | `nullOnDelete` |
| `agent_id` | bigint FK nullable → `users.id` | `nullOnDelete` (ver nota de nomenclatura §12) |
| `assigned_at` | timestamp nullable | momento de la asignación |
| `created_at/updated_at` | timestamps | |
| `deleted_at` | timestamp nullable | **soft delete** |

### Enums

```
LeadSource:  web | landing | inmueble | manual | telefono
LeadStatus:  nuevo | contactado | en_seguimiento | cerrado_ganado | cerrado_perdido
```

**Máquina de estados:** estado inicial `nuevo`. Transiciones válidas (validadas en el modelo/servicio; sin saltos arbitrarios):

```
nuevo          → contactado
contactado     → en_seguimiento | cerrado_ganado | cerrado_perdido
en_seguimiento → cerrado_ganado | cerrado_perdido
cerrado_*      → (terminal; no reabre)
```

**Decisión (pregunta abierta de auditoría):** un lead puede cerrarse desde `contactado` o `en_seguimiento` sin exigir paso obligatorio por `en_seguimiento`, pero **no** puede cerrarse directamente desde `nuevo` (primero debe haber contacto).

### Casts y traits
- `source` ⇒ `LeadSource::class`, `status` ⇒ `LeadStatus::class`.
- `use SoftDeletes`.
- Helpers: `isNew()`, `isAssigned()`, `isClosed()`.
- Scopes: `unassigned()`, `byAgent(User $u)`, `byStatus(LeadStatus $s)`.

### Relaciones
- `property(): BelongsTo` → `Property` (Épica 4). **No** se modifica `Property`.
- `agent(): BelongsTo` → `User` (Épica 2), por `agent_id`.
- `zone()`: **incluida** (`zone_id` nullable, FK a `zones`). **Decisión revisada respecto a la auditoría de diseño:** los casos de negocio de reasignación operativa (un agente renuncia o se va de vacaciones → sus leads abiertos los cubre **otro agente de la misma zona**) convierten a la zona en un criterio **necesario**, no en sobreingeniería. El acoplamiento real es mínimo: la FK depende solo de `zones.id` (estable); la Épica 3 puede evolucionar las columnas internas de `zones` sin afectar `leads.zone_id`. **Pendiente de activación:** hoy `zone_id` no se puebla automáticamente; para activar la prioridad/cobertura por zona debe poblarse desde `property.zone_id` al capturar (ver §12).

---

## 6. RFC-026 — Captura de Leads

### Formulario público (Livewire, sin auth)
- **Form general** (`/contacto` o componente embebible) y **form por inmueble** (precarga `property_id` y `source = inmueble`).
- Campos: `name`, `email`, `phone`, `message`, (`property_id` oculto cuando aplica).
- **Validación** server-side: `name` requerido, `email` válido, `phone` requerido (formato MX laxo), `message` opcional con límite de longitud.
- **Validación de `property_id`** (form por inmueble): debe existir, estar **publicada** y tener **agente asignado**. Un `property_id` inexistente/inactivo se rechaza (evita leads huérfanos hacia inmuebles fantasma).
- **Anti-spam (backend, no sólo UI):**
  - **Honeypot**: campo señuelo oculto; si llega con valor, se descarta silenciosamente.
  - **Throttle**: rate limiting **dentro del método de envío del componente Livewire** usando `RateLimiter` de Laravel (p. ej. 5/min por IP). **No** se aplica a nivel de ruta global de Livewire (afectaría toda la app).
- **Sanitización de inputs**: `name` y `message` se desinfectan (escape/purificado HTML) **antes de persistir**, para prevenir **XSS almacenado** al renderizarlos en el panel de Filament.
- Al enviar válido: crea `Lead` con `status = nuevo` y dispara el evento **`LeadCaptured($lead)`**. La captura **no** ejecuta la asignación directamente (desacople; lo hace el listener del RFC-027).

### LeadResource (Filament, gestión interna)
- **Form**: `name`, `email`, `phone`, `message`, `source`, `status`, `property`, `agent`.
- **Tabla**: `name`, `email`, `phone`, `source` (badge), `status` (badge), `property`, `agent`, `created_at`.
- **Filtros**: por `status`, `source` y `agent`. **Búsqueda** por nombre/email/teléfono.
- **Acciones**: cambiar `status` (respeta la máquina de estados); reasignar agente (owner/admin); soft delete con restore. **Force delete deshabilitado** (se eliminan `ForceDeleteAction`/`ForceDeleteBulkAction`): nadie borra leads físicamente.
- **Autorización y scope** (ver §10): `leads.manage`; el **agente sólo ve sus leads** (`agent_id = auth()->id()`), owner/admin ven todos.

---

## 7. RFC-027 — Asignación Automática

### Estrategia por prioridad
Al recibir `LeadCaptured`, `LeadAssignmentService` resuelve el agente así:

```
(1) Si el lead trae property con agente asignado  → ese agente (property.agent_id).
(2) Si hay zona con agente (SÓLO cuando Épica 3 esté disponible) → agente de la zona.   [diferido]
(3) En otro caso → round-robin entre agentes activos (rol 'agente' + status Active).
```

- **Round-robin por carga** (decisión revisada): se elige al agente activo con **menor carga**, con desempate determinista por `MAX(assigned_at)` más antiguo y luego por `id`. El criterio de carga sirve directamente a los casos de cobertura (balancear cuando un agente falta), que el "turno persistido puro" no contempla. La consulta de carga se apoya en el índice `(agent_id, assigned_at)`; a escala inmobiliaria un `COUNT` indexado es aceptable. **Mejora recomendada:** contar leads **abiertos** (estado no cerrado) en lugar de leads recientes, para reflejar la carga real de trabajo.
- Setea `agent_id` y `assigned_at`. **No cambia `status`** (sigue `nuevo` hasta que el agente lo trabaje) — decisión cerrada.

### Evento / listener
- Evento `LeadCaptured` (RFC-026) → listener `AssignLeadToAgent` invoca el servicio.
- **Idempotencia**: si el lead ya tiene `agent_id`, no reasigna.
- **Reasignación manual**: acción en LeadResource para owner/admin; queda registrada (auditoría, RFC-028).
- **Caso sin agentes activos**: el lead queda `nuevo` sin agente (no error) y visible para owner/admin para asignación manual.

### Reconciliación de fallos (decisión de auditoría)
- Comando Artisan **`leads:reconcile`** registrado en el scheduler (cada ~10 min): busca leads con `status = nuevo` y `agent_id IS NULL` creados hace más de X minutos y fuerza su asignación. Cubre el caso de un listener que falle (worker caído, error temporal de BD) y evita leads huérfanos permanentes.

### Configuración
- `config/leads.php` (+ `.env.example`): activar/desactivar auto-asignación, estrategia y ventana de reconciliación.

---

## 8. RFC-028 — Notificaciones

- **`LeadAssignedNotification`** (Laravel Notifications), canales **`database`** y **`mail`**.
  - Contenido: datos clave del lead (nombre, contacto, inmueble si aplica) + enlace al registro en LeadResource.
- **Disparo**: tras la asignación (encadenado al RFC-027), notifica al agente asignado. Aviso opcional a administrador según diseño aprobado.
- **Campana de Filament**: las notificaciones `database` se muestran en `/admin` para el agente.
- **Eventos cubiertos** (RFC-028 origen): nuevo lead asignado, cambio de estado, reasignación. Cada notificación queda registrada (tabla `notifications`) para auditoría.
- **No bloqueante**: el envío se encola en una **cola dedicada `leads-notifications`** (separada de la cola principal) con **reintentos limitados** (`tries`/`backoff`), para que un fallo de correo no bloquee ni sature el procesamiento prioritario ni demore la captura pública.
- **Caso sin agente**: no se emite notificación huérfana; si el diseño lo pide, se avisa a owner/admin.

---

## 9. Modelo de datos

### Migración `create_leads_table`

```
leads
├── id                bigserial PK
├── name              varchar          NOT NULL
├── email             varchar          NOT NULL
├── phone             varchar          NULL
├── message           text             NULL
├── source            varchar          NOT NULL  default 'web'
├── status            varchar          NOT NULL  default 'nuevo'
├── property_id       bigint FK → properties.id   NULL  nullOnDelete
├── agent_id          bigint FK → users.id        NULL  nullOnDelete
├── zone_id           bigint FK → zones.id         NULL  nullOnDelete
├── assigned_at       timestamp        NULL
├── timestamps
└── deleted_at        timestamp        NULL   (soft delete)
   índices: (status), (agent_id), (agent_id, assigned_at), (property_id), (zone_id)
   CHECK: source ∈ {web,landing,inmueble,manual,telefono}; status ∈ {nuevo,contactado,en_seguimiento,cerrado_ganado,cerrado_perdido}
```

- **`zone_id` incluido** (FK nullable a `zones`) para soportar cobertura/reasignación por zona (ver §5). Acoplamiento mínimo y estable (solo el `id`).
- **Índice compuesto `(agent_id, assigned_at)`** para la consulta de carga del round-robin.
- **CHECK constraints** a nivel BD para `source` y `status` (integridad además de los enums PHP).
- Migración compatible con PostgreSQL; FKs nullable para no acoplar el ciclo de vida del lead.
- No se altera `properties` ni `users`.

---

## 10. Seguridad

- **Permiso base**: `leads.manage` (ya sembrado en Épica 2). Controla acceso al LeadResource y a sus acciones.
- **Autorización en backend** (`LeadPolicy`, no sólo UI):
  - `viewAny/view/update/delete` exigen `leads.manage`.
  - **Scope por agente**: el agente sólo ve/gestiona leads con `agent_id = auth()->id()`; owner/admin ven todos. Se aplica en `LeadResource::getEloquentQuery()` (backend) reutilizando el patrón `scopeVisibleTo(User)` de `Property` — no es un filtro visual.
  - **Bloqueo de secuestro de leads (crítico de auditoría)**: en `LeadPolicy@update` se **deniega cualquier cambio de `agent_id`** si el usuario no es `owner`/`admin`. Un agente NO puede reasignarse leads modificando la petición HTTP. La reasignación es exclusiva de owner/admin.
- **Captura pública**: endpoint sin auth pero con honeypot + throttle (en el método Livewire); valida `property_id` (existe/publicada/con agente); **sanitiza `name`/`message`** antes de persistir (anti-XSS almacenado); nunca expone datos de otros leads.
- Soft delete real; **force delete deshabilitado** en el Resource. Sin borrado físico bajo ninguna circunstancia.

---

## 11. Estrategia de testing

```
Modelo:        campos, casts (enums), SoftDeletes, helpers, scopes, relaciones (property/agent).
Captura:       creación válida (form general y por inmueble); honeypot descarta; throttle limita;
               disparo de LeadCaptured.
Asignación:    prioridad (inmueble con agente → round-robin); reparto justo; idempotencia;
               caso sin agentes activos; reasignación manual (owner/admin).
Notificaciones: Notification::fake() — el agente asignado recibe database + mail; sin huérfanas.
Autorización:  leads.manage; el agente sólo ve sus leads; owner/admin ven todos (scope backend).
Regresión:     épicas 2 y 4 intactas; login por rol; suite verde.
```

Tests sobre PostgreSQL (`inmo_test`). Factories: `LeadFactory` (estados/origen), agentes activos.

---

## 12. Riesgos técnicos y decisiones abiertas

Tras la auditoría de diseño (paso 2), las decisiones quedan **CERRADAS**:

| Tema | Decisión final (cerrada) |
| :--- | :--- |
| **Campo de asignación** | **`agent_id`** (ratificado). El RFC-025 lo llamaba `assigned_user_id`; se estandariza a `agent_id` por coherencia con `Property.agent_id`. Registrado formalmente para Codex. |
| **Nombres de estados** | **{nuevo, contactado, en_seguimiento, cerrado_ganado, cerrado_perdido}** (ratificado). Mapea a {Nuevo, Contactado, Seguimiento, Cerrado, Perdido} del RFC. |
| **Asignación por zona** | **Incluida** (`zone_id` en la migración). Decisión revisada por casos de cobertura/reasignación operativa (renuncia, vacaciones). Pendiente: poblar `zone_id` desde `property.zone_id` al capturar. |
| **Round-robin** | **Por carga** con desempate determinista (`assigned_at` más antiguo, luego `id`), apoyado en índice `(agent_id, assigned_at)`. Mejora: contar leads abiertos. |
| **Reasignación masiva** | **Implementada**: bulk action "Reasignar seleccionados" + header action "Reasignar leads de un agente" (todos los abiertos de un agente → otro), para renuncia/vacaciones. Solo owner/admin. |
| **Herencia de zona** | **Implementada**: `lead.zone_id` se hereda de `property.zone_id` al crear (snapshot); activa la prioridad/cobertura por zona. |
| **`status` en auto-asignación** | **Permanece `nuevo`** tras asignar (cerrada). |
| **Transiciones de estado** | Cerrar permitido desde `contactado` o `en_seguimiento`; **no** desde `nuevo` (cerrada). |
| **Aviso a administrador** | Leads sin asignar se ven en la **bandeja de Filament** + se recuperan con `leads:reconcile`. **El correo resumen diario a admin queda FUERA DE ALCANCE** (diferido). |

**Riesgos residuales (vigilar en implementación):** afinar `tries`/`backoff` de la cola `leads-notifications`; aislar efectos de caché en los tests del round-robin (usar `assigned_at` determinista, no caché global, en el entorno de test).

---

## 13. Criterios de aceptación (mapeo QA-041…QA-055)

```
RFC-025 Modelo
 QA-041  Crear lead persiste con status 'nuevo' por defecto.
 QA-042  Enums source/status validados; soft delete funciona (restore disponible).
 QA-043  Relaciones property() y agent() resuelven; lead sin property/agent es válido.

RFC-026 Captura
 QA-044  Form público general crea lead válido y dispara LeadCaptured.
 QA-045  Form por inmueble precarga property_id; rechaza property_id inexistente/no publicada/sin agente.
 QA-046  Honeypot descarta envíos bot; throttle (método Livewire) limita ráfagas; name/message sanitizados (anti-XSS).
 QA-047  Validación rechaza email inválido / nombre vacío.
 QA-048  LeadResource lista, filtra y busca; cambio de status respeta la máquina de estados; force delete deshabilitado.

RFC-027 Asignación
 QA-049  Lead con inmueble con agente se asigna a ese agente.
 QA-050  Sin inmueble/zona, round-robin determinista (por assigned_at más antiguo) reparte de forma justa.
 QA-051  Asignación idempotente (no reasigna un lead ya asignado).
 QA-052  Sin agentes activos, el lead queda sin agente; leads:reconcile lo asigna cuando haya agente.

RFC-028 Notificaciones
 QA-053  Al asignar, el agente recibe notificación database + mail (cola leads-notifications).
 QA-054  La campana de Filament muestra la notificación; queda registrada para auditoría.

Seguridad / scope
 QA-055  El agente sólo ve sus leads y NO puede cambiar agent_id (policy backend); owner/admin ven y reasignan todo.
```

---

## 14. Plan de implementación por lotes (Codex A→E)

```
Lote A — RFC-025  Modelo Lead: migración leads (SIN zone_id), enums LeadSource/LeadStatus,
                  modelo con SoftDeletes, casts, helpers, scopes, máquina de estados y relaciones
                  (property, agent; zona diferida sin columna).
Lote B — RFC-026  Captura: form público Livewire (general + por inmueble) con validación,
                  validación de property_id (existe/publicada/con agente), sanitización de
                  name/message, anti-spam (honeypot + throttle en el método Livewire), evento
                  LeadCaptured; LeadResource con filtros, búsqueda, cambio de status, scope por
                  agente (getEloquentQuery), force delete deshabilitado y permisos.
Lote C — RFC-027  Asignación: LeadAssignmentService (prioridad + round-robin determinista por
                  turno persistido), listener AssignLeadToAgent (idempotente), reasignación manual
                  (owner/admin), comando leads:reconcile + scheduler, config/leads.php.
Lote D — RFC-028  Notificaciones: LeadAssignedNotification (database + mail) en cola dedicada
                  leads-notifications con reintentos limitados, disparo tras asignación, campana en
                  Filament, mailer en .env.example, envío no bloqueante.
Lote E — Tests + Docs + Validación: cobertura QA-041…QA-055, LeadFactory, docs/modulos/leads.md,
                  pint/phpstan/build, regresión de épicas previas.
```

Dependencias: B depende de A; C depende de A+B (evento); D depende de C (asignación); E cierra.

---

## 15. Checklist de cierre técnico

```
[ ] Migración leads corre limpia en PostgreSQL; FKs nullable correctas.
[ ] Enums y máquina de estados implementados; soft delete operativo.
[ ] Captura pública crea leads y rechaza spam (honeypot + throttle) verificado en backend.
[ ] Evento LeadCaptured desacopla captura de asignación.
[ ] Asignación por prioridad + round-robin, idempotente; caso sin agentes cubierto.
[ ] Notificación al agente (database + mail) sin bloquear la captura.
[ ] leads.manage aplicado en policy; agente acotado a sus leads (scope backend).
[ ] Property y User consumidos como contrato; sin recrearlos.
[ ] Relación con Zona declarada como diferida (Épica 3).
[ ] QA-041…QA-055 cubiertos por tests; suite verde; pint/phpstan/build OK.
[ ] Decisiones abiertas (§12) ratificadas en auditoría.
```

---

---

## 16. Cierre técnico del diseño

Tras la **auditoría de diseño** (`docs/audits/epica-5-auditoria-diseno.md`, veredicto *Aprobado con observaciones*), se aplicaron las observaciones válidas y se cerraron las decisiones abiertas. El diseño queda **listo para implementación por lotes A→E**.

### Confirmaciones (tarea 4 del cierre)
- ✅ Modelo `Lead` con enums (`LeadSource`/`LeadStatus`), máquina de estados y **soft delete**.
- ✅ Captura pública con **anti-spam** (honeypot + throttle en método Livewire) **y sanitización** anti-XSS.
- ✅ Asignación automática por **prioridad + round-robin determinista**, idempotente, con reconciliación.
- ✅ Notificaciones por **Laravel Notifications** (database + mail) en cola dedicada.
- ✅ Autorización **`leads.manage` en backend** (policy + `getEloquentQuery`), con bloqueo de reasignación por agentes.
- ✅ **Scope del agente** a sus propios leads (no filtro visual).
- ✅ Criterios de aceptación **verificables**, mapeados a **QA-041…QA-055** (§13).
- ✅ Plan por lotes **A→E incremental** con dependencias explícitas (§14).

### Cambios aplicados desde la auditoría
| # | Origen (severidad) | Cambio aplicado |
| :- | :--- | :--- |
| 1 | Crítico | `LeadPolicy@update` **deniega cambio de `agent_id`** salvo owner/admin (anti-secuestro de leads). §10 |
| 2 | Crítico / Sobreingeniería | **Eliminado `zone_id`** de la migración; lo aportará la Épica 3. §5, §9, §12 |
| 3 | Medio | Round-robin **determinista por turno persistido** (no `COUNT`). §7 |
| 4 | Medio | Comando **`leads:reconcile`** en scheduler para leads huérfanos. §7, §14 |
| 5 | Medio | **Throttle dentro del método Livewire** (no ruta global). §6 |
| 6 | Seguridad | **Sanitización** de `name`/`message` antes de persistir (anti-XSS). §6, §10 |
| 7 | Seguridad | **Validación de `property_id`** (existe/publicada/con agente) en la captura. §6 |
| 8 | Menor | **Force delete deshabilitado** en `LeadResource`. §6, §10 |
| 9 | Menor | Nomenclatura `agent_id` y estados **ratificada y registrada** para Codex. §12 |
| 10 | Opcional | Notificaciones en **cola dedicada** `leads-notifications` con reintentos. §8 |
| 11 | Pregunta abierta | Transiciones de estado definidas (no cerrar desde `nuevo`). §5, §12 |

### Puntos diferidos / fuera de alcance
- **Cobertura temporal automática por vacaciones** (con fecha de retorno y reversión automática): fuera de alcance — hoy se resuelve con reasignación masiva manual (ida y vuelta).
- **Round-robin contando leads abiertos** (hoy cuenta leads recientes): mejora pendiente.
- **Inferir `zone_id` por código postal** cuando no haya inmueble: requiere la Épica 3 mergeada.
- **Correo resumen diario de leads sin asignar a admin**: fuera de alcance (basta bandeja Filament + `leads:reconcile`).
- CRM avanzado (pipeline kanban, scoring, automatizaciones de marketing): fuera de alcance.

### Revisión post-implementación (alineación diseño↔código)
Tras implementar y revisar el commit `424addd`, se **revisaron dos decisiones** del cierre de diseño a la luz de casos de negocio de reasignación operativa (renuncia, vacaciones):

| Decisión de diseño (paso 3) | Revisión (post-implementación) | Motivo |
| :--- | :--- | :--- |
| Eliminar `zone_id` (diferir a Épica 3) | **Mantener `zone_id`** | La cobertura por zona es requisito de la reasignación operativa, no YAGNI. Acoplamiento mínimo (FK al `id`). |
| Round-robin por turno persistido (sin `COUNT`) | **Round-robin por carga** (`COUNT` + índice) | El balance por carga sirve a la cobertura cuando un agente falta; turno puro no. |

Correcciones menores aplicadas en código: `status` en el form respeta la máquina de estados (alta = `nuevo`; edición = solo transiciones válidas); `DeleteBulkAction`/`RestoreBulkAction` visibles solo para owner/admin. Suite: 34 tests de leads en verde tras los cambios.

### Estado final

> **APROBADO PARA IMPLEMENTACIÓN.** Procede el **Lote A** (RFC-025 Modelo Lead) según §14 y el flujo `docs/prompts/prompts-epica-5-leads.md`.

---

> **Nota de proceso:** documento cerrado en el **paso 3** del flujo multiagente. Siguiente: implementación por lotes (Codex, pasos 4→8), luego auditoría de implementación (paso 9).
