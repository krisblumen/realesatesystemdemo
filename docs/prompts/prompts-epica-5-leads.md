# Prompts multiagente — Épica 5 — Leads (RFC-025 → RFC-028)

**Proyecto:** New Hauz — Plataforma Monolítica Inmobiliaria **Stack:** Laravel 13.x + Filament + Livewire + Tailwind CSS + PostgreSQL + PostGIS + `spatie/laravel-permission` + Laravel Notifications **Rama:** `feature/epica-5-leads` **Tag objetivo:** `v0.5.0-leads` **Documento de Épica:** `docs/epicas/epica-5-leads.md` **RFCs origen:** `docs/rfc/EPICA-5-LEADS.md` **Auditorías:** `docs/audits/` + registro en **engram** (memoria del proyecto) **Responsable de arquitectura:** Edgar · **Arquitectura de apoyo:** Kristian · **QA:** Sebastián **Restricción global:** proyecto monolítico independiente. No microservicios, no auth externo, no orquestación externa. Se reutiliza todo lo entregado en las épicas previas; nada se reescribe.

---

## Contexto de continuidad

Esta épica llega **después de las épicas previas ya implementadas y aprobadas**. Precondiciones disponibles y operativas:

```
FASE 1 — Fundación Técnica (RFC-001 → RFC-010) — IMPLEMENTADA Y APROBADA
ÉPICA 2 — Usuarios y Seguridad (RFC-011 → RFC-014) — IMPLEMENTADA Y APROBADA
ÉPICA 4 — Inmuebles / Properties — IMPLEMENTADA (mergeada en develop)

De esas épicas se consume, sin reescribir:
- Laravel 13 + PostgreSQL + Filament + Livewire (Fase 1).
- Modelo User + roles owner/admin/agente (Épica 2).
- Permiso `leads.manage` YA sembrado en la Épica 2 (matriz rol→permiso).
- Modelo Property (Épica 4): un lead puede referenciar un inmueble.
```

> **Nota sobre Zonas (Épica 3):** está en curso por separado. La asignación por zona es **opcional**: si el contrato `Zone` no está mergeado al iniciar esta épica, `zone_id` se trata como relación **diferida/nullable** y la asignación cae a la estrategia round-robin. No bloquear la Épica 5 por la Épica 3.

**Qué hace esta épica:** construir la **captación y distribución de prospectos (leads)**: el modelo `Lead`, la captura desde formularios públicos, la asignación automática de cada lead a un agente, y las notificaciones al agente asignado. Es el embudo comercial que alimenta el trabajo de los agentes.

**RFCs de la épica:**

```
RFC-025  — Modelo Lead            (entidad núcleo del prospecto)
RFC-026  — Captura de Leads       (formularios públicos + LeadResource de gestión)
RFC-027  — Asignación Automática  (distribución de leads a agentes)
RFC-028  — Notificaciones de Leads (aviso al agente asignado)
```

**Dependencias técnicas (ya satisfechas):** RFC-004 (Filament), RFC-005 (Livewire), RFC-006 (Spatie Permission), Épica 2 (`User`, `leads.manage`), Épica 4 (`Property`).

**Decisiones de arquitectura ya tomadas (documentar, NO reabrir):**

```
- Modelo Lead NUEVO. Campos objetivo: id, name, email, phone, message, source,
  status, property_id (FK nullable → properties), agent_id (FK nullable → users),
  assigned_at (nullable), timestamps, deleted_at (soft delete).
- source (origen del lead): web | landing | inmueble | manual | telefono.
- status (máquina de estados): nuevo | contactado | en_seguimiento |
  cerrado_ganado | cerrado_perdido. Estado inicial: nuevo.
- Eliminación de leads: SOFT DELETE (deleted_at), nunca borrado físico.
- Captura pública SIN autenticación, con validación y anti-spam (honeypot + throttle).
- Asignación automática por evento al crear el lead. Estrategia por prioridad:
  (1) si el lead trae un inmueble con agente → ese agente;
  (2) si hay zona con agente (cuando Épica 3 esté disponible) → agente de la zona;
  (3) si no → round-robin entre agentes activos (rol agente + status activo).
- Notificaciones al agente asignado vía Laravel Notifications (canales database + mail).
- Autorización: el LeadResource y sus acciones se rigen por el permiso `leads.manage`
  (ya sembrado en la Épica 2). Un agente sólo ve/gestiona SUS leads asignados.
- Relación Lead → Zone: CONTRATO diferido si la Épica 3 no está mergeada (nullable).
```

**Reutilizaciones obligatorias (consumir, NO reescribir):**

```
- spatie/laravel-permission (Épica 2): permiso `leads.manage` y roles owner/admin/agente.
- Filament (RFC-004) para el LeadResource y las acciones de gestión.
- Livewire (RFC-005) para el formulario público de captura.
- Modelo Property (Épica 4) como relación; NO recrear la tabla properties.
- Modelo User (Épica 2) como agente asignable; NO tocar su esquema.
- PostgreSQL (RFC-002) como motor; migraciones compatibles con PG.
- Laravel Notifications (framework) para los avisos; sin servicios externos de pago.
- Convención Git Flow (RFC-009): rama feature, commits feat:/fix:/docs:/test:.
- Ambientes y .env.example (RFC-008): cualquier variable nueva se documenta ahí.
```

---

## Orden de ejecución

```
1.  Claude   → Generar documento técnico de la Épica 5 (diseño consolidado)
2.  Gemini   → Auditar el diseño de la Épica 5  → docs/audits/ + engram
3.  Claude   → Cierre / aprobación del diseño
4.  Codex    → Lote A  RFC-025  Modelo Lead (entidad, migración, relaciones, estados)
5.  Codex    → Lote B  RFC-026  Captura de Leads (formulario público + LeadResource)
6.  Codex    → Lote C  RFC-027  Asignación Automática (estrategia + evento/listener)
7.  Codex    → Lote D  RFC-028  Notificaciones de Leads (Laravel Notifications)
8.  Codex    → Lote E  Tests + Docs + Validación
9.  Gemini   → Auditoría completa de implementación → docs/audits/ + engram
10. Codex    → Correcciones post-auditoría
11. Codex    → Validación final
12. Claude   → Cierre técnico de la Épica 5
13. Usuario  → commit, PR, merge, tag v0.5.0-leads
```

---

## 1. Claude — Generar documento técnico de la Épica 5

Actúa como arquitecto técnico senior (Laravel 13 + Filament + Livewire + PostgreSQL).

Contexto:

- Proyecto New Hauz, monolito. Épicas 1, 2 y 4 implementadas y aprobadas.
- El modelo `User` y el permiso `leads.manage` ya existen (Épica 2). El modelo `Property` existe (Épica 4).
- Documento destino: `docs/epicas/epica-5-leads.md`. RFCs origen: `docs/rfc/EPICA-5-LEADS.md`.

Objetivo:

Diseñar de forma consolidada la captación y distribución de leads: modelo `Lead`, captura pública, asignación automática a agentes y notificaciones. Debe quedar implementable de forma incremental por lotes (A→E).

Decisiones de arquitectura ya tomadas (documéntalas, no las reabras):

- `Lead` es un modelo NUEVO. Campos: id, name, email, phone, message, source, status, property_id (FK nullable → properties), agent_id (FK nullable → users), assigned_at (nullable), timestamps, deleted_at.
- source: web | landing | inmueble | manual | telefono.
- status (máquina de estados): nuevo | contactado | en_seguimiento | cerrado_ganado | cerrado_perdido. Inicial: nuevo.
- Captura pública sin auth, con validación y anti-spam (honeypot + throttle).
- Asignación automática por evento, por prioridad: inmueble con agente → zona con agente (si Épica 3 está) → round-robin entre agentes activos.
- Notificaciones al agente asignado vía Laravel Notifications (database + mail).
- Autorización con `leads.manage`; el agente sólo ve sus leads asignados.
- Lead → Zone: contrato diferido/nullable si la Épica 3 no está mergeada.
- Reutilizar User (Épica 2), Property (Épica 4), Spatie, Filament, Livewire, PostgreSQL.

El documento debe incluir:

```
1.  Título y estado
2.  Contexto (dependencia de Épicas 1, 2 y 4; relación opcional con Épica 3)
3.  Objetivos y no-objetivos
4.  Alcance funcional y alcance técnico
5.  RFC-025 Modelo Lead: campos, casts, enums (source/status), soft delete, relaciones
    (property, agent, zona diferida)
6.  RFC-026 Captura de Leads: formulario público Livewire, validación, anti-spam,
    LeadResource de gestión (form, table, filtros, scope por agente, permisos)
7.  RFC-027 Asignación Automática: estrategia por prioridad, evento LeadCaptured,
    listener/servicio de asignación, reasignación manual, idempotencia
8.  RFC-028 Notificaciones: Laravel Notifications (database + mail), disparo tras asignación,
    campana de notificaciones en Filament
9.  Modelo de datos (migración leads; relaciones; índices)
10. Seguridad: dónde se aplica `leads.manage` (policy/gate, no sólo UI); scope por agente
11. Estrategia de testing (modelo, captura, anti-spam, asignación, notificaciones, regresión)
12. Riesgos técnicos y decisiones abiertas
13. Criterios de aceptación verificables (mapeados a QA-041…QA-055)
14. Plan de implementación por lotes Codex (A→E como en el orden de ejecución)
15. Checklist de cierre técnico
```

Restricciones:

- No reescribir lo entregado en épicas previas. Consumir sus contratos (User, Property, permisos).
- No introducir auth externo ni servicios de pago para notificaciones.
- No bloquear por la Épica 3: la relación con Zona es diferida/opcional.
- No sobreingeniería.

Entrega el documento completo en Markdown, tono técnico, orientado a implementación real.

---

## 2. Gemini — Auditar el diseño de la Épica 5

Actúa como auditor técnico estricto (Laravel 13 + Filament + Livewire + PostgreSQL + Spatie Permission).

Vas a auditar: `docs/epicas/epica-5-leads.md`

Contexto:

- Proyecto New Hauz, monolito. Épicas 1, 2 y 4 cerradas. Épica 3 (Zonas) en curso por separado.
- Épica 5 = leads: modelo `Lead`, captura pública, asignación automática y notificaciones.

Audita con estos criterios:

```
1.  El modelo Lead define bien source/status como enums y tiene soft delete.
2.  Relaciones correctas: property (Épica 4) y agent (User, Épica 2); zona como contrato diferido.
3.  La captura pública valida entradas y trae anti-spam real (honeypot + throttle), no sólo UI.
4.  La asignación automática es determinista, con prioridad clara y round-robin justo.
5.  La asignación es idempotente y no deja leads sin agente cuando hay agentes activos.
6.  Las notificaciones usan Laravel Notifications (database + mail), sin servicios de pago.
7.  Autorización con `leads.manage` aplicada en policies/gates (backend), no sólo en la UI.
8.  El agente sólo ve/gestiona SUS leads (scope efectivo en backend).
9.  Soft delete real; sin borrado físico.
10. Migraciones compatibles con PostgreSQL; FKs nullable correctas (property/agent/zona).
11. No se recrean User ni Property; se consumen como contrato.
12. Implementabilidad incremental por lotes (A→E) y criterios de aceptación comprobables.
13. Sobreingeniería y deuda técnica oculta.
14. Cobertura de testing: modelo, captura, anti-spam, asignación, notificaciones, regresión.
```

Entrega en Markdown:

```
# Auditoría de diseño — Épica 5 — Leads

## Veredicto (Aprobado / Aprobado con observaciones / Rechazado)
## Hallazgos críticos
## Hallazgos medios
## Hallazgos menores
## Sobreingeniería detectada
## Riesgos de implementación
## Riesgos de seguridad
## Recomendaciones obligatorias
## Recomendaciones opcionales
## Preguntas abiertas
## Checklist de corrección para Claude
## Checklist de implementación para Codex
```

Persistencia obligatoria del resultado:

- Guarda el informe completo en `docs/audits/epica-5-auditoria-diseno.md`.
- Registra en **engram** un resumen estructurado con: veredicto, hallazgos críticos, correcciones obligatorias, fecha y ruta del archivo de auditoría, bajo la clave `audit:epica-5:diseno`, para que los demás agentes consulten el estado.

No reescribas el documento completo. Audita, cuestiona y corrige el rumbo.

---

## 3. Claude — Cierre / aprobación del diseño

Actúa como arquitecto técnico senior responsable del cierre del diseño.

Contexto:

- Proyecto New Hauz. Documento: `docs/epicas/epica-5-leads.md`.
- El diseño fue auditado por Gemini (ver `docs/audits/epica-5-auditoria-diseno.md` y la entrada `audit:epica-5:diseno` en engram).

Tareas:

```
1. Leer el documento de la épica y la auditoría de Gemini (archivo + engram).
2. Aplicar únicamente las observaciones válidas.
3. Marcar decisiones cerradas y decisiones diferidas (relación con Zona / Épica 3).
4. Confirmar: modelo Lead con enums y soft delete, captura pública con anti-spam,
   asignación automática por prioridad + round-robin, notificaciones por Laravel Notifications,
   autorización `leads.manage` en backend, scope del agente a sus leads.
5. Confirmar que los criterios de aceptación son verificables y mapean a QA-041…QA-055.
6. Confirmar que el plan por lotes (A→E) es incremental.
```

Entrega:

- Documento de la épica corregido y completo.
- Sección final "Cierre técnico del diseño".
- Lista de cambios aplicados desde la auditoría.
- Lista de puntos diferidos / fuera de alcance.
- Estado final: "Aprobado para implementación".

---

## 4. Codex — Lote A: RFC-025 Modelo Lead (entidad, migración, relaciones, estados)

Actúa como desarrollador senior Laravel.

Contexto:

- Proyecto New Hauz. Rama `feature/epica-5-leads`. Épica 5 aprobada.
- `User` (Épica 2) y `Property` (Épica 4) ya existen. Esta épica crea el modelo `Lead` y lo relaciona con ellos.

Objetivo:

Dejar la entidad `Lead` lista como núcleo del prospecto, con campos, casts, enums, estados y soft delete. Sin captura pública ni UI todavía.

Tareas:

```
1. Migración que CREA la tabla leads:
   name, email, phone (nullable), message (text, nullable), source (default 'web'),
   status (default 'nuevo'), property_id (FK nullable → properties, nullOnDelete),
   agent_id (FK nullable → users, nullOnDelete), assigned_at (nullable),
   timestamps y soft delete (deleted_at). Índices en status, agent_id, property_id.
2. Enums PHP: LeadSource (web|landing|inmueble|manual|telefono) y
   LeadStatus (nuevo|contactado|en_seguimiento|cerrado_ganado|cerrado_perdido).
3. Modelo Lead: fillable/casts (source/status como enums); trait SoftDeletes;
   helpers isNew()/isAssigned()/isClosed(); scopes unassigned(), byAgent(), byStatus().
4. Relaciones:
   - Lead → property (belongsTo Property, Épica 4).
   - Lead → agent (belongsTo User, Épica 2).
   - Lead → zona: declarar como CONTRATO diferido (método con comentario y zone_id nullable
     SÓLO si la Épica 3 ya está mergeada; si no, no migrar la columna todavía). No romper la suite.
5. NO construir la captura pública (Lote B). NO asignar (Lote C). NO notificar (Lote D).
```

Criterios de aceptación:

```
- Migración corre limpia en PostgreSQL.
- Lead tiene campos, casts (enums), SoftDeletes y relaciones operativas.
- status default 'nuevo'; helpers y scopes funcionan.
- Relación con Property y User resuelve; relación con Zona diferida sin romper la suite.
- Tests existentes (épicas previas) siguen pasando.
```

Entrega: archivos modificados, resumen técnico, comandos ejecutados, riesgos. Commit con convención `feat: ...`.

---

## 5. Codex — Lote B: RFC-026 Captura de Leads (formulario público + LeadResource)

Actúa como desarrollador senior Laravel + Filament + Livewire.

Contexto:

- Proyecto New Hauz. Rama `feature/epica-5-leads`. Épica 5 aprobada.
- Existe el modelo `Lead` (Lote A). Filament y Livewire operativos. Permiso `leads.manage` sembrado (Épica 2).

Objetivo:

Capturar leads desde un formulario público y gestionarlos desde el panel, respetando permisos. La asignación automática NO se implementa aquí (Lote C), pero la captura debe DISPARAR el evento que el Lote C consumirá.

Tareas:

```
1. Componente Livewire público (sin auth) para captura: name, email, phone, message,
   property_id opcional (cuando el lead nace desde una ficha de inmueble), source.
   - Validación de entradas (email válido, requeridos).
   - Anti-spam: honeypot + rate limiting (throttle por IP). No CAPTCHA de pago.
   - Al enviar: crea el Lead con status 'nuevo' y dispara el evento LeadCaptured($lead).
2. Ruta pública en routes/web.php y/o punto de integración en la ficha de inmueble (Épica 4).
3. LeadResource (Filament) para gestión interna:
   - Form y tabla: name, email, phone, source, status, property, agent, created_at.
   - Filtros por status, source y agent. Búsqueda por nombre/email/teléfono.
   - Acción de cambiar status (máquina de estados). Soft delete con restore.
   - Autorización por `leads.manage`; el AGENTE sólo ve/gestiona SUS leads (scope backend),
     owner/admin ven todos.
4. NO implementar asignación automática (Lote C) ni notificaciones (Lote D).
```

Criterios de aceptación:

```
- El formulario público crea leads válidos y rechaza spam (honeypot + throttle), verificado en backend.
- El evento LeadCaptured se dispara al crear el lead.
- LeadResource operativo en /admin con filtros, búsqueda y cambio de status.
- El agente sólo ve sus leads; owner/admin ven todos (scope en backend, no sólo UI).
- Soft delete con restore; permisos respetados. Tests existentes siguen pasando.
```

Entrega: archivos modificados, resumen técnico, descripción del form público y del Resource, comandos, riesgos. Commit `feat: ...`.

---

## 6. Codex — Lote C: RFC-027 Asignación Automática (estrategia + evento/listener)

Actúa como desarrollador senior Laravel.

Contexto:

- Proyecto New Hauz. Rama `feature/epica-5-leads`. Épica 5 aprobada.
- Existen el modelo `Lead` (A) y la captura que dispara LeadCaptured (B). User con rol agente y status activo (Épica 2).

Objetivo:

Asignar automáticamente cada lead nuevo a un agente, de forma determinista, idempotente y justa.

Tareas:

```
1. Servicio de asignación (p. ej. LeadAssignmentService) con estrategia por prioridad:
   (1) si el lead tiene property con agente asignado → ese agente;
   (2) si hay zona con agente (SÓLO si la Épica 3 está disponible) → agente de la zona;
   (3) si no → round-robin entre agentes activos (rol 'agente' + status 'activo').
   Setear agent_id y assigned_at. Round-robin justo (el que tenga menos leads recientes o turno).
2. Listener del evento LeadCaptured → invoca el servicio de asignación.
   Idempotente: si el lead ya tiene agente, no reasignar.
3. Acción de reasignación MANUAL en LeadResource (sólo owner/admin), auditable.
4. Configuración en config/leads.php (+ .env.example): estrategia activa, on/off de auto-asignación.
5. Caso borde: si no hay agentes activos, el lead queda 'nuevo' sin agente (no error) y queda
   visible para owner/admin para asignación manual.
6. NO implementar notificaciones (Lote D), pero dejar el punto de enganche tras la asignación.
```

Criterios de aceptación:

```
- Un lead nuevo se asigna automáticamente según la prioridad definida.
- Round-robin reparte de forma justa entre agentes activos.
- La asignación es idempotente (no reasigna un lead ya asignado).
- Sin agentes activos, el lead no rompe el flujo y queda para asignación manual.
- Reasignación manual disponible para owner/admin. Tests existentes siguen pasando.
```

Entrega: archivos modificados, resumen técnico, descripción de la estrategia, comandos, riesgos. Commit `feat: ...`.

---

## 7. Codex — Lote D: RFC-028 Notificaciones de Leads (Laravel Notifications)

Actúa como desarrollador senior Laravel.

Contexto:

- Proyecto New Hauz. Rama `feature/epica-5-leads`. Épica 5 aprobada.
- Existen modelo (A), captura (B) y asignación (C). El punto de enganche tras asignación está listo.

Objetivo:

Avisar al agente asignado cuando se le asigna un lead, vía Laravel Notifications, con canales database y mail.

Tareas:

```
1. Notification (p. ej. LeadAssignedNotification) con canales 'database' y 'mail'.
   - Contenido: datos clave del lead (nombre, contacto, inmueble si aplica) y enlace al LeadResource.
2. Disparar la notificación al agente tras la asignación (listener encadenado al Lote C).
3. Campana de notificaciones en Filament (database notifications) para que el agente las vea en /admin.
4. Configuración de mailer en .env.example (sin proveedor de pago obligatorio; mailer por defecto).
5. Caso borde: si el lead queda sin agente (sin agentes activos), no se notifica a nadie
   (o se notifica a owner/admin según diseño aprobado).
6. Respetar que el envío no bloquee la request pública (encolar si el proyecto tiene queue).
```

Criterios de aceptación:

```
- Al asignar un lead, el agente recibe notificación database + mail.
- La campana de Filament muestra la notificación al agente.
- Sin agente asignado, no se emite notificación huérfana (o va a owner/admin según diseño).
- El envío no rompe ni bloquea la captura pública. Tests existentes siguen pasando.
```

Entrega: archivos modificados, resumen técnico, descripción del flujo de notificación, comandos, riesgos. Commit `feat: ...`.

---

## 8. Codex — Lote E: Tests + Docs + Validación

Actúa como desarrollador senior Laravel especializado en testing.

Contexto:

- Proyecto New Hauz. Rama `feature/epica-5-leads`. Épica 5 aprobada.

Objetivo:

Cobertura real y documentación de la capa de leads. Mapear los tests a la matriz QA de la épica (QA-041…QA-055) y a los casos de regresión.

Tareas:

```
1. Tests de modelo: campos, casts (enums source/status), SoftDeletes, helpers, scopes, relaciones.
2. Tests de captura: creación válida desde el form público; rechazo de spam (honeypot + throttle);
   disparo de LeadCaptured; creación desde ficha de inmueble (property_id).
3. Tests de asignación: prioridad (inmueble con agente, zona si aplica, round-robin);
   idempotencia; caso sin agentes activos; reasignación manual (owner/admin).
4. Tests de notificaciones: Notification::fake() — el agente asignado recibe la notificación
   database + mail; no hay notificación huérfana sin agente.
5. Tests de autorización: `leads.manage`; el agente sólo ve sus leads; owner/admin ven todos.
6. Factories: LeadFactory con estados y origen; ampliar UserFactory si hace falta (agentes activos).
7. Documentación: docs/modulos/leads.md (modelo, enums, captura, anti-spam, estrategia de
   asignación, notificaciones, autorización y scope, integración con Property/User, relación
   diferida con Zona).
8. Ejecutar: php artisan test, ./vendor/bin/pint, ./vendor/bin/phpstan analyse
   (si está configurado), npm run build.
```

Criterios de aceptación:

```
- Tests nuevos pasan; suite verde; Pint/PHPStan limpios; build ok.
- Cobertura real (no cosmética) de modelo, captura, anti-spam, asignación, notificaciones, scope.
- Todos los casos QA-041…QA-055 quedan cubiertos por al menos un test.
- Documentación fiel a lo implementado.
```

Entrega: archivos test/doc, mapeo test→QA, comandos, resultado de pruebas, pendientes. Commit `test: ...` / `docs: ...`.

---

## 9. Gemini — Auditoría completa de implementación

Actúa como auditor técnico estricto para una implementación Laravel 13 + Filament + Livewire + Spatie Permission sobre PostgreSQL.

Contexto:

- Proyecto New Hauz. Rama `feature/epica-5-leads`. Épica 5 implementada (Lotes A→E).

Audita la implementación completa:

```
1.  Modelo Lead: campos, casts (enums), SoftDeletes, relaciones (property/agent/zona diferida).
2.  Captura pública: validación real y anti-spam EFECTIVO (honeypot + throttle), no sólo UI.
3.  Evento LeadCaptured disparado al crear; sin acoplar la captura a la asignación.
4.  Asignación: prioridad correcta; round-robin justo; idempotente; caso sin agentes activos.
5.  Notificaciones: database + mail al agente asignado; sin notificación huérfana; no bloquea la request.
6.  Autorización `leads.manage` en policies/gates (backend); el agente sólo ve sus leads.
7.  No se recrearon User ni Property; se consumieron como contrato.
8.  Migraciones compatibles con PostgreSQL en entorno limpio; FKs nullable correctas.
9.  Soft delete real; sin borrado físico.
10. Tests reales (no cosméticos) cubriendo QA-041…QA-055 y regresión. Sobreingeniería. Regresiones.
```

Verifica especialmente:

```
- Que el anti-spam funcione en el backend (honeypot + throttle), no sólo en el HTML.
- Que ningún lead quede sin asignar cuando hay agentes activos.
- Que la asignación NO reasigne un lead ya asignado (idempotencia).
- Que el agente NO pueda ver leads de otros (scope real, no sólo filtro visual).
- Que la notificación llegue exactamente al agente asignado.
```

Entrega en Markdown:

```
# Auditoría de implementación — Épica 5 — Leads

## Veredicto (Aprobado / Aprobado con correcciones / Rechazado)
## Hallazgos críticos
## Hallazgos medios
## Hallazgos menores
## Regresiones detectadas
## Riesgos de seguridad
## Riesgos de mantenimiento
## Tests faltantes
## Correcciones obligatorias para Codex
## Correcciones recomendadas
## Checklist final antes de merge
```

Persistencia obligatoria del resultado:

- Guarda el informe completo en `docs/audits/epica-5-auditoria-implementacion.md`.
- Registra en **engram** un resumen estructurado con: veredicto, hallazgos críticos, riesgos de seguridad, correcciones obligatorias, fecha y ruta del archivo, bajo la clave `audit:epica-5:implementacion`, para trazabilidad entre agentes y para el cierre técnico.

---

## 10. Codex — Correcciones post-auditoría

Actúa como desarrollador senior Laravel.

Contexto:

- Proyecto New Hauz. Rama `feature/epica-5-leads`. Épica 5.
- Existe la auditoría de Gemini (ver `docs/audits/epica-5-auditoria-implementacion.md` y la clave `audit:epica-5:implementacion` en engram).

Tareas:

```
1. Leer la auditoría (archivo + engram).
2. Clasificar hallazgos: críticos, medios, menores, diferidos.
3. Corregir críticos y medios. Corregir menores sólo si son seguros.
4. Priorizar los riesgos de seguridad (anti-spam, scope del agente, autorización backend).
5. Actualizar/añadir tests cuando aplique.
6. Ejecutar la suite relevante.
7. No agregar alcance nuevo. No romper épicas previas. No reabrir decisiones aprobadas
   salvo bug claro. No recrear User/Property.
```

Entrega: correcciones aplicadas, hallazgos diferidos con razón, archivos modificados, comandos, resultado de tests, estado final recomendado. Commit `fix: ...`.

---

## 11. Codex — Validación final

Actúa como responsable de validación técnica final.

Contexto:

- Proyecto New Hauz. Rama `feature/epica-5-leads`. Épica 5.
- Ya se aplicaron correcciones post-auditoría.

Ejecuta o verifica:

```
1.  php artisan test
2.  ./vendor/bin/pint
3.  ./vendor/bin/phpstan analyse  (si está configurado)
4.  npm run build
5.  Migraciones en entorno limpio (migrate:fresh --seed) sobre PostgreSQL.
6.  Captura pública crea leads y rechaza spam; evento disparado.
7.  Asignación automática reparte correctamente; idempotente; sin agentes no rompe.
8.  Notificación llega al agente asignado (database + mail).
9.  Agente sólo ve sus leads; owner/admin ven todos.
10. Casos QA-041…QA-055 verificados; regresión de épicas previas OK.
11. Sin dependencias de pago ni auth externo introducidos.
```

Entrega:

```
# Validación final — Épica 5 — Leads

## Resultado general (Aprobado / Aprobado con observaciones / No aprobado)
## Comandos ejecutados
## Resultado de pruebas
## Validaciones manuales
## Riesgos restantes
## Pendientes fuera de alcance
## Recomendación (Listo para cierre técnico / Requiere correcciones / No mergear)
```

---

## 12. Claude — Cierre técnico de la Épica 5

Actúa como arquitecto técnico responsable del cierre.

Contexto:

- Proyecto New Hauz. Rama `feature/epica-5-leads`. Épica 5.
- Existen auditoría de Gemini (archivo + engram), correcciones de Codex y validación final.

Tareas:

```
1. Revisar el documento de la épica, las auditorías (docs/audits/ + engram), las correcciones
   y la validación final.
2. Emitir el cierre técnico.
3. Identificar deuda técnica aceptada y pendientes fuera de alcance.
4. Confirmar si la rama está lista para commit, PR, merge y tag.
```

Entrega:

```
# Cierre técnico — Épica 5 — Leads

## Estado final
## Alcance implementado (RFC-025 → RFC-028)
## Decisiones técnicas cerradas
   - Modelo Lead con enums (source/status) + SoftDeletes
   - Captura pública con anti-spam (honeypot + throttle) y evento LeadCaptured
   - Asignación automática por prioridad + round-robin, idempotente
   - Notificaciones al agente (database + mail) vía Laravel Notifications
   - Autorización `leads.manage` en backend; scope del agente a sus leads
   - Relaciones con Property (Épica 4) y User (Épica 2); Zona como contrato diferido
## Validaciones realizadas
## Tests confirmados (mapeo QA-041…QA-055)
## Integración con épicas previas (Épica 2 usuarios/permisos, Épica 4 inmuebles)
## Deuda técnica aceptada
## Pendientes fuera de alcance (integración con Zonas cuando cierre la Épica 3)
## Riesgos residuales
## Recomendación final
## Checklist para Edgar
   - revisar diff
   - ejecutar tests
   - commit
   - push
   - PR a develop
   - revisión (QA: Sebastián)
   - merge
   - tag v0.5.0-leads
```

---

## Después de cerrar la Épica 5

El embudo comercial queda operativo: los leads se capturan, se asignan a agentes y se notifican. Cuando cierre la **Épica 3 — Zonas (con PostGIS)**, la asignación automática puede incorporar la prioridad por zona (hoy diferida): un lead sobre un inmueble dentro de una zona se asigna al agente de esa zona. Esa integración se hace en su momento sin reabrir el modelo `Lead` entregado aquí, que ya deja `agent_id`/`property_id` y el servicio de asignación preparados para esa regla adicional.
