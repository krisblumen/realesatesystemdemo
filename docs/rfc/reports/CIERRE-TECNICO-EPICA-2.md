# Cierre técnico — Épica 2 — Usuarios y Seguridad

**Proyecto:** New Hauz — Plataforma Inmobiliaria  
**Rama:** `feature/epica-2-usuarios-y-seguridad`  
**Fecha de cierre:** 16 de Junio, 2026  
**Arquitecto responsable:** Edgar  
**QA:** Sebastián  
**Revisión:** Kristian Alvarez

---

## Estado final

> **✅ APROBADO PARA MERGE**
>
> La implementación es completa, correcta y supera en calidad al diseño original en varios puntos. Las dos auditorías de Gemini (diseño e implementación) emitieron veredicto **Aprobado**. Los 25 tests pasan en verde con 144 aserciones. No hay hallazgos críticos ni medios pendientes. La rama está lista para commit, PR, revisión de QA, merge a `develop` y tag `v0.2.0-usuarios-y-seguridad`.

---

## 1. Alcance implementado (RFC-011 → RFC-014)

### RFC-011 — Modelo Usuario

| Entregable | Estado | Nota |
| :--- | :---: | :--- |
| Migración ALTER `users` (phone, whatsapp, avatar, status, last_login_at, deleted_at) | ✅ | Con CHECK constraint PostgreSQL en `status` |
| `UserStatus` enum (Active/Suspended → 'activo'/'suspendido') | ✅ | Cases en inglés, values en español — convención PHP |
| `User` extendido: SoftDeletes + HasRoles + FilamentUser | ✅ | `canAccessPanel()` añadido — gate nativo Filament |
| Scopes `isActive()`, `isSuspended()` | ✅ | |
| Relaciones `properties()` y `leads()` como contratos diferidos | ✅ | Comentario de activación en código |
| Relación `statusLogs()` | ✅ | Nombre final: `UserStatusLog` |

### RFC-012 — Roles y Permisos

| Entregable | Estado | Nota |
| :--- | :---: | :--- |
| `PermissionSeeder` con constantes `PERMISSIONS` y `ROLE_PERMISSIONS` | ✅ | Idempotente; `forgetCachedPermissions()` al inicio y al final |
| 7 permisos asignados según la matriz | ✅ | |
| `UserPolicy` con `viewAny`, `view`, `create`, `update`, `delete`, `restore`, `forceDelete`, `suspend`, `reactivate`, `assignRole` | ✅ | Método privado `isProtectedOwnerFor()` — código limpio |
| Gate registrado en `AppServiceProvider` | ✅ | |
| `RoleSeeder` como wrapper compatible de `PermissionSeeder` | ✅ | Backward compatibility con Épica 1 |

### RFC-013 — CRUD Usuarios en Filament

| Entregable | Estado | Nota |
| :--- | :---: | :--- |
| `UserResource` con form, table, filtros, validaciones | ✅ | |
| Campos `roles` y `status` visibles para owner y admin | ✅ | Corrección de auditoría de diseño aplicada |
| Select de roles filtra `owner` para actor `admin` | ✅ | `assignableRoleOptions()` + validación en Policy |
| Acciones suspend/reactivate controladas por Policy | ✅ | |
| Soft delete solo para owner; `TrashedFilter` solo para owner | ✅ | |
| Password hasheada en form, no en modelo | ✅ | Sin riesgo de doble hash |

### RFC-014 — Suspensión, Reactivación y Auditoría

| Entregable | Estado | Nota |
| :--- | :---: | :--- |
| Tabla `user_status_logs` (from_status, to_status, changed_by, reason) | ✅ | Richer que el diseño; changed_by con nullOnDelete |
| `UserStatusLog` model (UPDATED_AT null — append-only) | ✅ | |
| `UserStatusService.suspend()` + `reactivate()` con DB transaction | ✅ | Atomicidad garantizada |
| Middleware `EnsureUserIsActive` en grupo `web` | ✅ | Ruta de Filament: `filament.admin.auth.login` |
| Listener `InvalidateSuspendedLogin` con Log::warning() | ✅ | IP + user-agent registrado |
| Protección de Owner en Policy | ✅ | Verificado en auditoría de implementación |

---

## 2. Decisiones técnicas cerradas

### User extendido (no recreado) + SoftDeletes + HasRoles
**CERRADO.** La migración `0001_01_01_000000_create_users_table.php` de Épica 1 no fue modificada. Todos los campos nuevos se añadieron vía migración de alteración (`2026_06_17_013005_add_profile_status_and_soft_deletes_to_users_table.php`). El trait `HasRoles` de Spatie ya existía desde RFC-006; en esta épica se añadió `SoftDeletes` y la interfaz `FilamentUser`.

### Roles base owner/admin/agente y matriz de permisos
**CERRADO.** La matriz es inmutable para esta épica. `PermissionSeeder` la implementa con constantes de clase (`PERMISSIONS`, `ROLE_PERMISSIONS`) que funcionan como fuente única de verdad para tests y para el seeder de producción.

| Permiso | owner | admin | agente |
| :--- | :---: | :---: | :---: |
| users.view | ✅ | ✅ | ❌ |
| users.create | ✅ | ✅ | ❌ |
| users.update | ✅ | ✅ | ❌ |
| users.delete | ✅ | ❌ | ❌ |
| properties.manage | ✅ | ✅ | ✅ |
| leads.manage | ✅ | ✅ | ✅ |
| zones.manage | ✅ | ✅ | ❌ |

### Autorización en policies/gates (backend), no solo UI
**CERRADO.** `UserPolicy` es la única fuente de verdad. El Resource de Filament la consume pero no la reemplaza. `UserStatusService` llama a `cannot()` antes de ejecutar cualquier transición. La UI puede cambiar; la Policy no.

### CRUD Filament con soft delete y validaciones
**CERRADO.** `UserResource` implementa los 10 funcionales del alcance. El soft delete es exclusivo del rol owner. La restauración también. Los campos `roles` y `status` son visibles para owner y admin; las opciones del Select de roles están filtradas dinámicamente.

### Suspensión/reactivación con bloqueo de login efectivo y auditoría
**CERRADO.** Doble defensa activa:
- **Middleware** `EnsureUserIsActive`: actúa en cada request, invalida sesiones activas que hayan sido suspendidas.
- **Listener** `InvalidateSuspendedLogin`: actúa en el evento `Login`, previene generación de nuevas sesiones con logging de seguridad.
- **`canAccessPanel()`**: gate nativo Filament — bloqueo a nivel de framework, previo a cualquier middleware HTTP.

La tabla `user_status_logs` registra cada transición con `from_status`, `to_status`, responsable, timestamp y motivo. `UPDATED_AT = null` garantiza que los registros son verdaderamente inmutables.

### Protección de Owner frente a Admin
**CERRADO.** `UserPolicy::suspend()` y `UserPolicy::update()` bloquean incondicionalmente cualquier acción de un `admin` sobre un `owner`. `UserPolicy::assignRole()` impide que `admin` asigne el rol `owner`. Verificado en auditoría de implementación (Sección 6: "Protección de Owner: Verificado").

### Relaciones Properties/Leads como contrato diferido
**CERRADO.** Los métodos `properties()` y `leads()` existen en el modelo User y devuelven una colección vacía segura (`whereRaw('1 = 0')`). Los comentarios en el código indican exactamente qué cambiar y cuándo (Épicas 3 y 4). No generan ruido en la ejecución actual.

---

## 3. Validaciones realizadas

### Auditoría de diseño — Gemini CLI (16/06/2026)
- **Veredicto:** Aprobado con observaciones
- **Hallazgo bloqueante resuelto:** visibilidad de campos `roles`/`status` corregida para incluir admin
- **Hallazgo menor resuelto:** `SuspensionAction` enum → la implementación adoptó una solución superior: `from_status`/`to_status` casteados a `UserStatus`
- **Archivo:** `docs/audits/epica-2-auditoria-diseno.md`

### Auditoría de implementación — Gemini CLI (16/06/2026)
- **Veredicto:** Aprobado (sin hallazgos críticos ni medios)
- **Hallazgo menor pendiente:** comentarios obsoletos en `UserPolicy` — verificado: los comentarios ya no existen en el código final
- **Hallazgo de mantenimiento:** redundancia `RoleSeeder`/`PermissionSeeder` — aceptado como deuda técnica (ver Sección 7)
- **Archivo:** `docs/audits/epica-2-auditoria-implementacion.md`

### Verificación de seguridad (auditada)
| Control | Verificado |
| :--- | :---: |
| Admin no puede suspender Owner | ✅ |
| Admin no puede asignar rol Owner | ✅ |
| Admin no puede editar Owner | ✅ |
| Usuario suspendido no puede hacer login | ✅ |
| Sesión activa invalidada al suspender | ✅ |
| Autorización en backend (Policy), no solo UI | ✅ |
| Historial inmutable (`UPDATED_AT = null`) | ✅ |

---

## 4. Tests confirmados (QA-011 → QA-017)

**Suite ejecutada:** `php artisan test` → **25 tests, 144 aserciones, 0 fallos**

| Archivo de test | Tests | Cubre |
| :--- | :---: | :--- |
| `Feature/Auth/UserCoreTest.php` | — | Modelo, casts, scopes, soft delete, contratos diferidos |
| `Feature/Auth/UserPolicyTest.php` | — | Todas las reglas de UserPolicy |
| `Feature/Auth/PermissionSeederTest.php` | — | Idempotencia del seeder, matriz completa |
| `Feature/Auth/UserStatusServiceTest.php` | — | Suspensión, reactivación, transacción, guardas |
| `Feature/Auth/EnsureUserIsActiveTest.php` | — | Middleware: bloqueo de sesión activa |
| `Feature/Filament/UserResourceTest.php` | — | CRUD, permisos por rol, soft delete, suspensión desde UI |

| QA | Descripción | Test |
| :--- | :--- | :--- |
| QA-011 | Crear usuario | `UserResourceTest` |
| QA-012 | Asignar rol | `UserResourceTest` + `UserPolicyTest` |
| QA-013 | Editar usuario | `UserResourceTest` |
| QA-014 | Suspender usuario | `UserStatusServiceTest` + `UserResourceTest` |
| QA-015 | Reactivar usuario | `UserStatusServiceTest` |
| QA-016 | Validar permisos | `UserPolicyTest` + `PermissionSeederTest` |
| QA-017 | Bloqueo de acceso | `EnsureUserIsActiveTest` |
| QA-018 | Protección de Owner | `UserPolicyTest` |
| QA-019 | Soft delete y restauración | `UserResourceTest` |
| QA-020 | Historial inmutable | `UserStatusServiceTest` (UPDATED_AT null) |
| QA-021 | Admin no asigna rol owner | `UserPolicyTest` + `UserResourceTest` |

> **Pendiente de QA manual:** Sebastián debe ejecutar los casos QA-011 → QA-017 manualmente contra el panel Filament antes de aprobar el PR.

---

## 5. Integración con la Épica 1

| Contrato Épica 1 | Forma de consumo | Estado |
| :--- | :--- | :--- |
| RFC-004 Filament v3 | `UserResource` extiende el panel `/admin`. `FilamentUser::canAccessPanel()` integrado en el modelo | ✅ Sin ruptura |
| RFC-006 Spatie Permission + roles base | `PermissionSeeder` extiende los roles existentes con permisos. `HasRoles` ya estaba en `User` | ✅ Sin ruptura |
| RFC-002 PostgreSQL | Migraciones con `DB::getDriverName() === 'pgsql'` para CHECK constraint. Tests contra `inmo_test` | ✅ Sin ruptura |
| RFC-007 Media Library | No consumido en esta épica (avatar es string). Disponible para Épica 4 | ✅ No interferencia |
| Todos los demás RFC | Sin modificación | ✅ |

**Regresión verificada:** la auditoría de implementación confirma "Ninguna. Las funcionalidades de la Épica 1 siguen operativas."

---

## 6. Divergencias de implementación respecto al diseño

Todas las divergencias son **mejoras**, no defectos. Se documentan para trazabilidad.

| Diseño especificó | Implementación entregó | Veredicto |
| :--- | :--- | :--- |
| `UserSuspension` model + `user_suspensions` table | `UserStatusLog` + `user_status_logs` con `from_status`/`to_status` | ✅ Mejor — historial de transición completo |
| `UserSuspensionService` | `UserStatusService` con `DB::transaction()` | ✅ Mejor — atomicidad garantizada |
| `SuspensionAction` enum | No existe; `from_status`/`to_status` casteados a `UserStatus` | ✅ Simplificación válida |
| `UserStatus` cases en español | Cases en inglés (`Active`/`Suspended`), values en español | ✅ Convención PHP estándar |
| Sin `FilamentUser` en diseño | `FilamentUser::canAccessPanel()` implementado | ✅ Mejor — gate nativo de Filament |
| Sin CHECK constraint DB | `ALTER TABLE users ADD CONSTRAINT users_status_check` para PostgreSQL | ✅ Mejor — integridad a nivel de BD |
| `changed_by` sin especificar ON DELETE | `changed_by` con `nullOnDelete()` | ✅ Mejor — historial sobrevive borrado de responsable |

---

## 7. Deuda técnica aceptada

| # | Deuda | Impacto | Épica sugerida |
| :--- | :--- | :--- | :--- |
| DT-1 | `RoleSeeder` existe como wrapper de `PermissionSeeder`. No es redundante (mantiene compatibilidad con `DatabaseSeeder` de Épica 1) pero podría unificarse | Bajo — no afecta comportamiento | Mantenimiento |
| DT-2 | Test unitario aislado para `EnsureUserIsActive` sugerido en auditoría | Bajo — el Feature test ya cubre el comportamiento | Épica siguiente |
| DT-3 | `canAccessPanel()` usa `users.view` como gate de acceso al panel. Los agentes (`properties.manage`, `leads.manage`) no podrán acceder al panel en Épicas 3/4 | Medio — requiere revisión cuando agentes necesiten el panel | Épica 3 o 4 |
| DT-4 | `UserStatus` sin métodos `label()`/`color()` en el enum (el diseño los especificó; la implementación los omitió) | Bajo — Filament configura colores en el Resource directamente | Épica siguiente si se requieren fuera de Filament |

---

## 8. Pendientes fuera de alcance (módulos de épicas futuras)

| Módulo | Épica | Nota |
| :--- | :--- | :--- |
| Tabla y modelo `Property` | Épica 3 | Contrato diferido activo en `User::properties()` |
| Tabla y modelo `Lead` | Épica 4 | Contrato diferido activo en `User::leads()` |
| Gestión de Zonas | Épica 3 | Permiso `zones.manage` ya asignado a owner y admin |
| Galería de imágenes / avatar con MediaLibrary | Épica 4 | Campo `avatar` como string en esta épica |
| API REST de usuarios | Épica 8 | Middleware `EnsureUserIsActive` debe aplicarse al grupo `api` |
| Recuperación de contraseña | Épica 8 | |
| Decisión D-1: ¿Admin puede crear otros admin? | Requiere input de Kristian | Actualmente sí, según la matriz |
| Acceso al panel para rol `agente` | Épica 3 o 4 | `canAccessPanel()` requiere `users.view`; resolver al construir módulos de agente |

---

## 9. Riesgos residuales

| # | Riesgo | Probabilidad | Impacto | Estado |
| :--- | :--- | :---: | :---: | :--- |
| RR-1 | `canAccessPanel()` bloquea agentes del panel en épicas futuras | Media | Medio | Documentado en DT-3. Resolver en Épica 3/4 al construir módulos de agente |
| RR-2 | Decisión D-1 no resuelta (admin crea admins) | Baja | Bajo | Aceptable para esta épica. Escalar a Kristian antes de Épica 3 |
| RR-3 | `changed_by nullOnDelete` — si se borra al responsable, el log pierde la FK pero conserva el registro | Baja | Bajo | Diseñado intencionalmente. El log nunca se pierde |
| RR-4 | `EnsureUserIsActive` no está en grupo `api` (no existe aún) | N/A | Bajo | Pendiente Épica 8 — documentado |

---

## 10. Recomendación final

La Épica 2 entrega una capa de seguridad de producción para el proyecto New Hauz. La implementación es correcta, completa y mejora el diseño original en puntos críticos (transacciones, historial enriquecido, gate nativo de Filament, restricción de BD). Los tests cubren todos los casos de negocio especificados en el QA. No existen regresiones sobre la Épica 1.

**La rama `feature/epica-2-usuarios-y-seguridad` está lista para seguir el flujo de cierre.**

---

## 11. Checklist para Edgar

### Pre-commit
- [ ] Revisar el diff completo de la rama (`git diff develop...HEAD`)
- [ ] Confirmar que solo `.atl/` está modificado en working tree (no hay archivos sin commit)
- [ ] Ejecutar `./vendor/bin/pint` y commitear correcciones de estilo si las hay
- [ ] Ejecutar `composer test` localmente → resultado esperado: **25 tests, 0 fallos**

### Commit y push
- [ ] Stagear archivos de `.atl/` si corresponde: `git add .atl/`
- [ ] Crear commit final de cierre:
  ```bash
  git commit -m "docs: close epic 2 technical design and implementation"
  ```
- [ ] Push: `git push origin feature/epica-2-usuarios-y-seguridad`

### Pull Request
- [ ] Abrir PR en GitHub: `feature/epica-2-usuarios-y-seguridad` → `develop`
- [ ] Título: `feat: epic 2 — user management and security layer`
- [ ] Descripción: referenciar este documento + `docs/audits/epica-2-auditoria-implementacion.md`
- [ ] Asignar reviewer: Kristian Alvarez
- [ ] Asignar QA: Sebastián

### Revisión QA (Sebastián)
- [ ] QA-011: Crear usuario como owner y como admin
- [ ] QA-012: Asignar rol — verificar que admin no ve opción `owner`
- [ ] QA-013: Editar usuario — verificar que admin no puede editar owner
- [ ] QA-014: Suspender usuario con motivo — verificar registro en `user_status_logs`
- [ ] QA-015: Reactivar usuario — verificar segundo registro en historial
- [ ] QA-016: Acceder a `/admin/users` como agente → debe redirigir con 403
- [ ] QA-017: Login con usuario suspendido → debe mostrar error "cuenta suspendida"

### Merge y tag
- [ ] Merge a `develop` (squash o merge commit — según convenio de equipo)
- [ ] Desde `develop`, crear tag:
  ```bash
  git tag -a v0.2.0-usuarios-y-seguridad -m "Epic 2: user management and security layer"
  git push origin v0.2.0-usuarios-y-seguridad
  ```
- [ ] Confirmar que `develop` incluye el tag en GitHub

### Post-merge
- [ ] Actualizar `docs/epicas/epica-2-usuarios-y-seguridad.md` con fecha real de merge
- [ ] Notificar al equipo: rama disponible en `develop`, listo para Épica 3
- [ ] Resolver Decisión D-1 con Kristian antes de iniciar Épica 3

---

*Cierre técnico emitido el 16 de Junio, 2026*  
*Auditorías de referencia: `docs/audits/epica-2-auditoria-diseno.md` · `docs/audits/epica-2-auditoria-implementacion.md`*
