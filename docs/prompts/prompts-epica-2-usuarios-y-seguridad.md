# Prompts multiagente — Épica 2 — Usuarios y Seguridad (RFC-011 → RFC-014)

**Proyecto:** New Hauz — Plataforma Monolítica Inmobiliaria **Stack:** Laravel 13.x \+ Filament \+ Livewire \+ Tailwind CSS \+ PostgreSQL \+ PostGIS \+ `spatie/laravel-permission` **Rama:** `feature/epica-2-usuarios-y-seguridad` **Tag objetivo:** `v0.2.0-usuarios-y-seguridad` (siguiente minor tras el cierre de la Épica 1\) **Documento de Épica:** `docs/epicas/epica-2-usuarios-y-seguridad.md` **Auditorías:** `docs/audits/` \+ registro en **engram** (memoria del proyecto) **Responsable de arquitectura:** Edgar · **Arquitectura de apoyo:** Kristian · **QA:** Sebastián **Restricción global:** proyecto monolítico independiente. No microservicios, no auth externo, no orquestación externa. Se reutiliza todo lo entregado en la Épica 1; nada se reescribe.

---

## Contexto de continuidad

Esta épica llega **después de la Épica 1 — Fundación Técnica (RFC-001 → RFC-010), ya implementada y APROBADA** y mergeada en `main`/`develop`. Precondiciones disponibles y operativas:

```
FASE 1 — Fundación Técnica (RFC-001 → RFC-010) — IMPLEMENTADA Y APROBADA

RFC-001  — Laravel 13.x instalado y corriendo
RFC-002  — PostgreSQL configurado (proyecto ya NO depende de SQLite)
RFC-003  — PostGIS habilitado (geometry/geography disponibles)
RFC-004  — Filament instalado, panel /admin operativo, usuario admin inicial
RFC-005  — Livewire instalado y reactivo
RFC-006  — spatie/laravel-permission instalado; User ya usa el trait HasRoles
RFC-007  — Media Library instalado (tabla media + storage validado)
RFC-008  — Ambientes Local/Dev/Staging/Production + .env.example
RFC-009  — Git Flow definido (main, develop, feature/rfc-xxx-nombre, release/)
RFC-010  — Docker local reproducible (app, nginx, postgres/postgis)
```

**Qué hace esta épica:** construir la **capa de autenticación, autorización y administración de usuarios** que servirá de base a todos los módulos funcionales posteriores (inmuebles, leads, zonas). Cubre el modelo `User`, roles y permisos, el CRUD administrativo en Filament y el control de acceso por suspensión/reactivación con auditoría.

**RFCs de la épica:**

```
RFC-011  — Modelo Usuario          (entidad núcleo de autenticación)
RFC-012  — Roles y Permisos        (Owner / Admin / Agente sobre Spatie)
RFC-013  — CRUD Usuarios           (Filament UserResource)
RFC-014  — Suspensión y Reactivación (estados, bloqueo de login, auditoría)
```

**Dependencias técnicas (ya satisfechas por la Épica 1):** RFC-001 (Laravel 13), RFC-004 (Filament), RFC-006 (Spatie Permission).

**Decisiones de arquitectura ya tomadas (documentar, NO reabrir):**

```
- El modelo User EXISTE (Laravel default + trait HasRoles del RFC-006). Esta épica lo
  EXTIENDE; no lo recrea desde cero.
- Roles base: owner, admin, agente. Owner = control total del sistema.
- Permisos base: users.view, users.create, users.update, users.delete,
  properties.manage, leads.manage, zones.manage.
- Estados de usuario: status (activo / suspendido). Suspendido NO puede iniciar sesión.
- Eliminación de usuarios: SOFT DELETE (deleted_at), nunca borrado físico.
- Auditoría de suspensión: usuario afectado, responsable, fecha, motivo.
- Regla de protección: Admin NO puede suspender ni degradar a un Owner.
- Las relaciones User→Properties y User→Leads se declaran como CONTRATO/diferidas:
  los modelos Property y Lead pertenecen a épicas futuras. No inventar esas tablas aquí.
```

**Reutilizaciones obligatorias (consumir, NO reescribir):**

```
- spatie/laravel-permission (RFC-006) para roles y permisos.
- Filament (RFC-004) para el panel y el UserResource.
- PostgreSQL (RFC-002) como motor; migraciones compatibles con PG.
- Convención Git Flow (RFC-009): rama feature, commits feat:/fix:/docs:/test:.
- Ambientes y .env.example (RFC-008): cualquier variable nueva se documenta ahí.
```

---

## Orden de ejecución

```
1.  Claude   → Generar documento técnico de la Épica 2 (diseño consolidado)
2.  Gemini   → Auditar el diseño de la Épica 2  → docs/audits/ + engram
3.  Claude   → Cierre / aprobación del diseño
4.  Codex    → Lote A  RFC-011  Modelo Usuario (entidad, migración, relaciones, estados)
5.  Codex    → Lote B  RFC-012  Roles y Permisos (seeder, permisos, policies)
6.  Codex    → Lote C  RFC-013  CRUD Usuarios (Filament UserResource)
7.  Codex    → Lote D  RFC-014  Suspensión y Reactivación (estados, bloqueo login, auditoría)
8.  Codex    → Lote E  Tests + Docs + Validación
9.  Gemini   → Auditoría completa de implementación → docs/audits/ + engram
10. Codex    → Correcciones post-auditoría
11. Codex    → Validación final
12. Claude   → Cierre técnico de la Épica 2
13. Usuario  → commit, PR, merge, tag v0.2.0-usuarios-y-seguridad
```

---

## 1\. Claude — Generar documento técnico de la Épica 2

Actúa como arquitecto técnico senior (Laravel 13 \+ Filament \+ Livewire \+ PostgreSQL).

Contexto:

- Proyecto New Hauz, monolito. Épica 1 (RFC-001 → RFC-010) implementada y aprobada.  
- El modelo `User` ya existe (Laravel default \+ trait `HasRoles` de Spatie del RFC-006).  
- Documento destino: `docs/epicas/epica-2-usuarios-y-seguridad.md`.

Objetivo:

Diseñar de forma consolidada la capa de usuarios y seguridad: modelo `User` extendido, roles/permisos, CRUD administrativo en Filament y control de acceso por suspensión/reactivación con auditoría. Debe quedar implementable de forma incremental por lotes (A→E).

Decisiones de arquitectura ya tomadas (documéntalas, no las reabras):

- `User` se EXTIENDE, no se recrea. Campos objetivo: id, name, email, password, phone, whatsapp, avatar, status, last\_login\_at, timestamps, deleted\_at (soft delete).  
- Roles base: owner, admin, agente. Owner \= control total.  
- Permisos base: users.view, users.create, users.update, users.delete, properties.manage, leads.manage, zones.manage.  
- Estados: status \= activo | suspendido. Suspendido no puede iniciar sesión.  
- Suspensión auditada: usuario afectado, responsable, fecha, motivo (tabla de auditoría dedicada).  
- Admin NO puede suspender ni degradar a un Owner.  
- Relaciones User→Properties y User→Leads: se declaran como CONTRATO/diferidas (modelos de épicas futuras). No crear tablas Property/Lead aquí.  
- Reutilizar Spatie (RFC-006), Filament (RFC-004), PostgreSQL (RFC-002).

El documento debe incluir:

```
1.  Título y estado
2.  Contexto (dependencia de la Épica 1)
3.  Objetivos y no-objetivos
4.  Alcance funcional y alcance técnico
5.  RFC-011 Modelo Usuario: campos, casts, soft delete, estados, relaciones (incl. las diferidas)
6.  RFC-012 Roles y Permisos: matriz rol→permiso, seeder, policies, guard
7.  RFC-013 CRUD Usuarios: UserResource (form, table, filtros, validaciones, soft delete, permisos)
8.  RFC-014 Suspensión y Reactivación: máquina de estados, punto de bloqueo de login,
    tabla de auditoría, regla de protección de Owner
9.  Modelo de datos (migraciones nuevas y alteraciones a users)
10. Seguridad: dónde se aplica cada permiso (policy/gate, no sólo UI)
11. Estrategia de testing (modelo, roles, CRUD, suspensión, bloqueo de acceso, regresión)
12. Riesgos técnicos y decisiones abiertas
13. Criterios de aceptación verificables (mapeados a QA-011…QA-017 de la épica)
14. Plan de implementación por lotes Codex (A→E como en el orden de ejecución)
15. Checklist de cierre técnico
```

Restricciones:

- No reescribir lo entregado en la Épica 1\. Consumir sus contratos.  
- No introducir auth externo ni paquetes de pago.  
- No inventar las tablas Property/Lead: sólo declarar el contrato de relación.  
- No sobreingeniería.

Entrega el documento completo en Markdown, tono técnico, orientado a implementación real.

---

## 2\. Gemini — Auditar el diseño de la Épica 2

Actúa como auditor técnico estricto (Laravel 13 \+ Filament \+ Livewire \+ PostgreSQL \+ Spatie Permission).

Vas a auditar: `docs/epicas/epica-2-usuarios-y-seguridad.md`

Contexto:

- Proyecto New Hauz, monolito. Épica 1 cerrada y aprobada.  
- Épica 2 \= usuarios y seguridad: modelo `User` extendido, roles/permisos, CRUD Filament y suspensión/reactivación con auditoría.

Audita con estos criterios:

```
1.  El modelo User se EXTIENDE (no se recrea) y conserva el trait HasRoles del RFC-006.
2.  Campos completos: phone, whatsapp, avatar, status, last_login_at, soft delete (deleted_at).
3.  Roles base correctos (owner/admin/agente) y permisos base completos y coherentes.
4.  La matriz rol→permiso no deja huecos ni privilegios excesivos al Agente.
5.  La autorización se aplica en policies/gates (backend), no sólo en la UI de Filament.
6.  Soft delete real en el CRUD; sin borrado físico.
7.  Bloqueo de login del usuario suspendido en un punto de control efectivo (no sólo visual).
8.  Auditoría de suspensión completa: afectado, responsable, fecha, motivo.
9.  Regla de protección de Owner aplicada en policy (Admin no puede tocar Owner).
10. Relaciones Properties/Leads tratadas como contrato diferido, sin inventar tablas.
11. Migraciones compatibles con PostgreSQL; sin dependencias de SQLite.
12. Implementabilidad incremental por lotes (A→E) y criterios de aceptación comprobables.
13. Sobreingeniería y deuda técnica oculta.
14. Cobertura de testing planteada: modelo, roles, CRUD, suspensión, bloqueo de acceso, regresión.
```

Entrega en Markdown:

```
# Auditoría de diseño — Épica 2 — Usuarios y Seguridad

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

- Guarda el informe completo en `docs/audits/epica-2-auditoria-diseno.md`.  
- Registra en **engram** (memoria del proyecto) un resumen estructurado con: veredicto, hallazgos críticos, correcciones obligatorias, fecha y ruta del archivo de auditoría, bajo una clave trazable (p. ej. `audit:epica-2:diseno`), para que los demás agentes consulten el estado.

No reescribas el documento completo. Audita, cuestiona y corrige el rumbo.

---

## 3\. Claude — Cierre / aprobación del diseño

Actúa como arquitecto técnico senior responsable del cierre del diseño.

Contexto:

- Proyecto New Hauz. Documento: `docs/epicas/epica-2-usuarios-y-seguridad.md`.  
- El diseño fue auditado por Gemini (ver `docs/audits/epica-2-auditoria-diseno.md` y la entrada `audit:epica-2:diseno` en engram).

Tareas:

```
1. Leer el documento de la épica y la auditoría de Gemini (archivo + engram).
2. Aplicar únicamente las observaciones válidas.
3. Marcar decisiones cerradas y decisiones diferidas.
4. Confirmar: User extendido (no recreado), roles/permisos base, autorización en policies,
   soft delete, bloqueo de login del suspendido, auditoría completa, protección de Owner,
   relaciones Properties/Leads como contrato diferido.
5. Confirmar que los criterios de aceptación son verificables y mapean a QA-011…QA-017.
6. Confirmar que el plan por lotes (A→E) es incremental.
```

Entrega:

- Documento de la épica corregido y completo.  
- Sección final "Cierre técnico del diseño".  
- Lista de cambios aplicados desde la auditoría.  
- Lista de puntos diferidos / fuera de alcance.  
- Estado final: "Aprobado para implementación".

---

## 4\. Codex — Lote A: RFC-011 Modelo Usuario (entidad, migración, relaciones, estados)

Actúa como desarrollador senior Laravel.

Contexto:

- Proyecto New Hauz. Rama `feature/epica-2-usuarios-y-seguridad`. Épica 2 aprobada.  
- El modelo `User` ya existe (Laravel default \+ trait `HasRoles` del RFC-006). Lo EXTIENDES.

Objetivo:

Dejar la entidad `User` lista como núcleo de autenticación, con todos los campos, casts, estados y soft delete. Sin UI todavía.

Tareas:

```
1. Migración que ALTERA la tabla users (sin perder datos existentes) para agregar:
   phone (nullable), whatsapp (nullable), avatar (nullable), status
   (default 'activo'; valores activo|suspendido), last_login_at (nullable),
   y soft delete (deleted_at).
2. Modelo User: fillable/casts; trait SoftDeletes; conservar HasRoles; cast de status
   (enum PHP o constantes); helpers isActive()/isSuspended().
3. Relaciones:
   - User → roles: ya provista por Spatie (verificar, no duplicar).
   - User → properties (hasMany) y User → leads (hasMany): declarar como CONTRATO
     diferido (método presente con comentario y referencia a épica futura, sin migrar
     tablas Property/Lead). No romper si los modelos aún no existen.
4. Actualización de last_login_at al autenticarse (listener del evento Login o equivalente).
5. NO construir el CRUD (Lote C). NO implementar suspensión funcional (Lote D).
```

Criterios de aceptación:

```
- Migraciones corren limpias en PostgreSQL sin pérdida de datos.
- User tiene los campos, casts, SoftDeletes y HasRoles operativos.
- status default 'activo'; helpers isActive()/isSuspended() funcionan.
- Relaciones diferidas declaradas sin romper la suite.
- Tests existentes (Épica 1) siguen pasando.
```

Entrega: archivos modificados, resumen técnico, comandos ejecutados, riesgos. Commit con convención `feat: ...`.

---

## 5\. Codex — Lote B: RFC-012 Roles y Permisos (seeder, permisos, policies)

Actúa como desarrollador senior Laravel \+ Spatie Permission.

Contexto:

- Proyecto New Hauz. Rama `feature/epica-2-usuarios-y-seguridad`. Épica 2 aprobada.  
- spatie/laravel-permission ya instalado (RFC-006). User ya usa HasRoles. Existe el modelo del Lote A.

Objetivo:

Definir roles, permisos base y la matriz rol→permiso, con seeders idempotentes y policies que apliquen la autorización en backend.

Tareas:

```
1. Seeder de permisos base: users.view, users.create, users.update, users.delete,
   properties.manage, leads.manage, zones.manage (guard 'web' salvo que el proyecto use otro).
2. Seeder de roles: owner, admin, agente. Asignación:
   - owner: todos los permisos (control total).
   - admin: gestión de usuarios completa + manage de properties/leads/zones según diseño.
   - agente: gestión comercial limitada (sin users.delete; alcance acotado).
   Hacerlos IDEMPOTENTES (re-ejecutables sin duplicar).
3. Policies/Gates para User (viewAny/view/create/update/delete) basadas en los permisos.
4. Regla de protección de Owner como parte de la policy (un Admin no puede update/delete
   ni cambiar rol de un Owner). Preparar el gancho que el Lote D reutilizará para suspensión.
5. Registrar el seeder en DatabaseSeeder de forma segura para todos los ambientes.
6. NO construir el CRUD (Lote C). NO implementar suspensión (Lote D).
```

Criterios de aceptación:

```
- Seeders crean roles y permisos sin duplicar al re-ejecutar.
- La matriz rol→permiso coincide con el diseño aprobado.
- Las policies bloquean acciones no autorizadas a nivel backend (no sólo UI).
- Admin no puede afectar a un Owner (verificable por policy).
- Tests existentes siguen pasando.
```

Entrega: archivos modificados, matriz rol→permiso final, resumen técnico, comandos, riesgos. Commit `feat: ...`.

---

## 6\. Codex — Lote C: RFC-013 CRUD Usuarios (Filament UserResource)

Actúa como desarrollador senior Laravel \+ Filament.

Contexto:

- Proyecto New Hauz. Rama `feature/epica-2-usuarios-y-seguridad`. Épica 2 aprobada.  
- Existen el modelo (Lote A) y roles/permisos/policies (Lote B). Filament operativo (RFC-004).

Objetivo:

Administrar usuarios desde el panel Filament respetando permisos y soft delete. Sin lógica de suspensión funcional aún (eso es Lote D), pero el campo `status` puede mostrarse.

Tareas:

```
1. UserResource con form: name, email, phone, whatsapp, avatar, password (sólo al crear/
   reset), y asignación de rol (single/multiple según diseño).
2. Tabla: columnas clave (name, email, rol, status, last_login_at), búsqueda y filtros
   (por rol y por status). Acción de eliminar = soft delete (con papelera/restore de Filament).
3. Validaciones: email único, campos obligatorios, password con regla mínima al crear.
4. Autorización: el Resource y sus acciones respetan las policies del Lote B
   (users.view/create/update/delete). Admin no puede editar/eliminar a un Owner.
5. Integrar avatar con Media Library si aplica (RFC-007) o file upload simple; no sobreingeniería.
6. NO implementar suspender/reactivar como acción funcional (va en Lote D); si se muestra el
   estado, que sea de sólo lectura por ahora.
```

Criterios de aceptación:

```
- CRUD completo operativo desde /admin con validaciones activas.
- Soft delete con restore; sin borrado físico.
- Filtros por rol y status funcionan.
- Las acciones respetan permisos y la protección de Owner.
- Tests existentes siguen pasando.
```

Entrega: archivos modificados, resumen técnico, descripción visual del Resource, comandos, riesgos. Commit `feat: ...`.

---

## 7\. Codex — Lote D: RFC-014 Suspensión y Reactivación (estados, bloqueo de login, auditoría)

Actúa como desarrollador senior Laravel \+ Filament.

Contexto:

- Proyecto New Hauz. Rama `feature/epica-2-usuarios-y-seguridad`. Épica 2 aprobada.  
- Existen modelo (A), roles/policies (B) y CRUD (C). status ya está en users.

Objetivo:

Implementar el control de acceso por estado: suspender/reactivar usuarios, bloquear el login del suspendido en un punto de control efectivo, y registrar auditoría completa.

Tareas:

```
1. Migración de auditoría: tabla user_status_logs (o equivalente) con:
   user_id (afectado), changed_by (responsable), from_status, to_status, reason, created_at.
2. Acciones de Filament en UserResource: "Suspender" (pide motivo) y "Reactivar".
   - Cambian status, escriben en user_status_logs y respetan policies.
   - Un Admin NO puede suspender a un Owner (regla del Lote B reutilizada).
3. Bloqueo de login del usuario suspendido en un punto de control EFECTIVO:
   - Filament: impedir acceso al panel a usuarios suspendidos (canAccessPanel / authenticate).
   - Si existe auth pública adicional, bloquear ahí también. No basta ocultar en UI.
4. Mensaje claro al usuario suspendido al intentar autenticarse.
5. Reactivación devuelve el acceso y queda registrada en la auditoría.
6. NO ampliar alcance a notificaciones por correo salvo que el diseño lo exija.
```

Criterios de aceptación:

```
- Suspender/reactivar funcionan desde el panel y respetan permisos.
- Usuario suspendido NO puede iniciar sesión (verificado en el punto de control, no sólo UI).
- Cada cambio registra afectado, responsable, fecha y motivo en user_status_logs.
- Admin no puede suspender a un Owner.
- Tests existentes siguen pasando.
```

Entrega: archivos modificados, resumen técnico, descripción del flujo de bloqueo, comandos, riesgos. Commit `feat: ...`.

---

## 8\. Codex — Lote E: Tests \+ Docs \+ Validación

Actúa como desarrollador senior Laravel especializado en testing.

Contexto:

- Proyecto New Hauz. Rama `feature/epica-2-usuarios-y-seguridad`. Épica 2 aprobada.

Objetivo:

Cobertura real y documentación de la capa de usuarios y seguridad. Mapear los tests a la matriz QA de la épica (QA-011…QA-017) y a los casos de regresión.

Tareas:

```
1. Tests de modelo: campos, casts, status, SoftDeletes, helpers isActive/isSuspended,
   actualización de last_login_at.
2. Tests de roles/permisos: seeder idempotente; matriz rol→permiso; policies bloquean
   acciones no autorizadas; Admin no puede afectar a Owner.
3. Tests de CRUD: crear (QA-011), asignar rol (QA-012), editar (QA-013); validaciones
   (email único, requeridos); soft delete + restore; permisos respetados.
4. Tests de suspensión: suspender (QA-014) y reactivar (QA-015); auditoría escrita correctamente;
   bloqueo de login del suspendido (QA-017); protección de Owner.
5. Tests de permisos/acceso (QA-016) y regresión: login Owner/Admin/Agente, CRUD, roles,
   permisos, suspensión, reactivación.
6. Factories para User con estados y roles (o ampliar las existentes).
7. Documentación: docs/modulos/usuarios-y-seguridad.md (modelo, matriz rol→permiso,
   CRUD, máquina de estados, auditoría, puntos de bloqueo de login, integración con
   RFC-004/RFC-006, decisiones diferidas: relaciones Properties/Leads).
8. Ejecutar: php artisan test, ./vendor/bin/pint, ./vendor/bin/phpstan analyse
   (si está configurado), npm run build.
```

Criterios de aceptación:

```
- Tests nuevos pasan; suite verde; Pint/PHPStan limpios; build ok.
- Cobertura real (no cosmética) de modelo, roles, CRUD, suspensión, bloqueo y regresión.
- Todos los casos QA-011…QA-017 quedan cubiertos por al menos un test.
- Documentación fiel a lo implementado.
```

Entrega: archivos test/doc, mapeo test→QA, comandos, resultado de pruebas, pendientes. Commit `test: ...` / `docs: ...`.

---

## 9\. Gemini — Auditoría completa de implementación

Actúa como auditor técnico estricto para una implementación Laravel 13 \+ Filament \+ Spatie Permission sobre PostgreSQL.

Contexto:

- Proyecto New Hauz. Rama `feature/epica-2-usuarios-y-seguridad`. Épica 2 implementada (Lotes A→E).

Audita la implementación completa:

```
1.  User extendido (no recreado): campos, casts, SoftDeletes, HasRoles, last_login_at.
2.  Roles/permisos: seeder idempotente; matriz rol→permiso correcta; sin privilegios excesivos.
3.  Autorización aplicada en policies/gates (backend), no sólo en la UI de Filament.
4.  CRUD: validaciones reales (email único, requeridos); soft delete real con restore.
5.  Suspensión: bloqueo de login del suspendido en punto de control EFECTIVO; mensaje claro.
6.  Auditoría: user_status_logs registra afectado, responsable, fecha y motivo en cada cambio.
7.  Protección de Owner: Admin no puede suspender/editar/eliminar/degradar a un Owner.
8.  Relaciones Properties/Leads como contrato diferido; sin tablas inventadas.
9.  Migraciones compatibles con PostgreSQL en entorno limpio; sin SQLite.
10. Tests reales (no cosméticos) cubriendo QA-011…QA-017 y regresión. Sobreingeniería. Regresiones.
```

Verifica especialmente:

```
- Que un usuario SUSPENDIDO realmente NO pueda autenticarse (probar el flujo, no la UI).
- Que NINGÚN permiso quede sólo en la capa visual sin policy/gate detrás.
- Que el soft delete no haga borrado físico ni rompa relaciones.
- Que un Admin no pueda escalar contra un Owner por ninguna vía (suspensión, rol, delete).
```

Entrega en Markdown:

```
# Auditoría de implementación — Épica 2 — Usuarios y Seguridad

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

- Guarda el informe completo en `docs/audits/epica-2-auditoria-implementacion.md`.  
- Registra en **engram** un resumen estructurado con: veredicto, hallazgos críticos, riesgos de seguridad, correcciones obligatorias, fecha y ruta del archivo, bajo la clave `audit:epica-2:implementacion`, para trazabilidad entre agentes y para el cierre técnico.

---

## 10\. Codex — Correcciones post-auditoría

Actúa como desarrollador senior Laravel.

Contexto:

- Proyecto New Hauz. Rama `feature/epica-2-usuarios-y-seguridad`. Épica 2\.  
- Existe la auditoría de Gemini (ver `docs/audits/epica-2-auditoria-implementacion.md` y la clave `audit:epica-2:implementacion` en engram).

Tareas:

```
1. Leer la auditoría (archivo + engram).
2. Clasificar hallazgos: críticos, medios, menores, diferidos.
3. Corregir críticos y medios. Corregir menores sólo si son seguros.
4. Priorizar los riesgos de seguridad (bloqueo de login, protección de Owner, autorización backend).
5. Actualizar/añadir tests cuando aplique.
6. Ejecutar la suite relevante.
7. No agregar alcance nuevo. No romper la Épica 1. No reabrir decisiones aprobadas
   salvo bug claro. No inventar tablas Property/Lead.
```

Entrega: correcciones aplicadas, hallazgos diferidos con razón, archivos modificados, comandos, resultado de tests, estado final recomendado. Commit `fix: ...`.

---

## 11\. Codex — Validación final

Actúa como responsable de validación técnica final.

Contexto:

- Proyecto New Hauz. Rama `feature/epica-2-usuarios-y-seguridad`. Épica 2\.  
- Ya se aplicaron correcciones post-auditoría.

Ejecuta o verifica:

```
1.  php artisan test
2.  ./vendor/bin/pint
3.  ./vendor/bin/phpstan analyse  (si está configurado)
4.  npm run build
5.  Migraciones en entorno limpio (migrate:fresh --seed) sobre PostgreSQL.
6.  Roles y permisos sembrados correctamente; seeder idempotente.
7.  CRUD de usuarios operativo en /admin con validaciones y soft delete.
8.  Suspender bloquea el login; reactivar lo restaura; auditoría registrada.
9.  Admin no puede afectar a un Owner por ninguna vía.
10. Casos QA-011…QA-017 verificados; regresión de login Owner/Admin/Agente OK.
11. Sin dependencias de pago ni auth externo introducidos.
```

Entrega:

```
# Validación final — Épica 2 — Usuarios y Seguridad

## Resultado general (Aprobado / Aprobado con observaciones / No aprobado)
## Comandos ejecutados
## Resultado de pruebas
## Validaciones manuales
## Riesgos restantes
## Pendientes fuera de alcance
## Recomendación (Listo para cierre técnico / Requiere correcciones / No mergear)
```

---

## 12\. Claude — Cierre técnico de la Épica 2

Actúa como arquitecto técnico responsable del cierre.

Contexto:

- Proyecto New Hauz. Rama `feature/epica-2-usuarios-y-seguridad`. Épica 2\.  
- Existen auditoría de Gemini (archivo \+ engram), correcciones de Codex y validación final.

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
# Cierre técnico — Épica 2 — Usuarios y Seguridad

## Estado final
## Alcance implementado (RFC-011 → RFC-014)
## Decisiones técnicas cerradas
   - User extendido (no recreado) + SoftDeletes + HasRoles
   - Roles base owner/admin/agente y matriz de permisos
   - Autorización en policies/gates (backend), no sólo UI
   - CRUD Filament con soft delete y validaciones
   - Suspensión/reactivación con bloqueo de login efectivo y auditoría
   - Protección de Owner frente a Admin
   - Relaciones Properties/Leads como contrato diferido
## Validaciones realizadas
## Tests confirmados (mapeo QA-011…QA-017)
## Integración con la Épica 1 (RFC-004 Filament, RFC-006 Spatie, RFC-002 PostgreSQL)
## Deuda técnica aceptada
## Pendientes fuera de alcance (módulos Properties, Leads, Zonas — épicas futuras)
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
   - tag v0.2.0-usuarios-y-seguridad
```

---

## Después de cerrar la Épica 2

Quedan como dominios separados, en épicas futuras, los módulos que consumen las relaciones hoy diferidas: **Inmuebles (Properties)**, **Leads** y **Zonas (con PostGIS)**. No deben mezclarse con esta épica: cada uno define su propio modelo y sus migraciones cuando llegue su RFC. La capa de usuarios, roles y permisos entregada aquí es la base de autorización que esos módulos reutilizarán (`properties.manage`, `leads.manage`, `zones.manage` ya quedan sembrados).  
