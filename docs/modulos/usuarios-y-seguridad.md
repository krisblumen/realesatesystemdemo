# Usuarios y Seguridad

Este módulo centraliza la administración de usuarios de New Hauz en el panel Filament (`/admin`). Cubre perfil de usuario, roles y permisos Spatie, CRUD con soft delete, suspensión/reactivación, auditoría y bloqueo efectivo de acceso.

## Integraciones base

| RFC | Integración | Uso en este módulo |
| --- | --- | --- |
| RFC-004 | Filament v3 | `UserResource`, login personalizado y acciones administrativas. |
| RFC-006 | Spatie Permission | Roles `owner`, `admin`, `agente` y permisos backend. |
| RFC-007 | Media Library disponible | No se usa todavía; avatar usa `FileUpload` simple en disco `public`. |

## Modelo `User`

`App\Models\User` extiende el modelo Laravel default y conserva `HasRoles`. Campos agregados: `phone`, `whatsapp`, `avatar`, `status`, `last_login_at`, `deleted_at`.

- `status` castea a `App\Enums\UserStatus` (`activo`, `suspendido`).
- `SoftDeletes` habilita papelera y restauración.
- `isActive()` / `isSuspended()` encapsulan reglas de estado.
- `canAccessPanel()` exige usuario activo y permiso `users.view`.
- `properties()` y `leads()` quedan como contratos diferidos para épicas futuras.

## Matriz rol → permiso

| Permiso | owner | admin | agente |
| --- | :---: | :---: | :---: |
| `users.view` | ✅ | ✅ | ❌ |
| `users.create` | ✅ | ✅ | ❌ |
| `users.update` | ✅ | ✅ | ❌ |
| `users.delete` | ✅ | ❌ | ❌ |
| `properties.manage` | ✅ | ✅ | ✅ |
| `leads.manage` | ✅ | ✅ | ✅ |
| `zones.manage` | ✅ | ✅ | ❌ |

`PermissionSeeder` es idempotente: usa `firstOrCreate`, `syncPermissions()` y limpia cache de Spatie antes/después.

## CRUD Filament

`App\Filament\Resources\UserResource` administra usuarios en `/admin/users`.

- Formulario: nombre, email, teléfono, WhatsApp, avatar, password y rol.
- Password: requerido al crear; opcional en edición para reset.
- Rol: selector único. `owner` ve todos; `admin` sólo ve/asigna `admin` y `agente`.
- Status: visible como lectura; se cambia sólo mediante acciones de estado.
- Tabla: avatar, nombre, email, rol, estado y último login.
- Filtros: rol, estado y papelera.
- Eliminación: soft delete; restauración disponible según policy.

## Policies y protección Owner

`UserPolicy` aplica autorización backend. No depende de visibilidad UI.

- `viewAny`, `view`, `create`, `update`, `delete` usan permisos `users.*`.
- `admin` no puede actualizar ni afectar a un `owner`.
- `admin` no tiene `users.delete`.
- `assignRole()` impide que `admin` asigne `owner`.
- `suspend()` bloquea auto-suspensión y cualquier suspensión de `owner`.

## Máquina de estados

```text
activo --Suspender(motivo)--> suspendido
suspendido --Reactivar--> activo
```

`UserStatusService` ejecuta cambios dentro de transacción y escribe auditoría en `user_status_logs`.

| Campo | Significado |
| --- | --- |
| `user_id` | Usuario afectado. |
| `changed_by` | Usuario responsable. |
| `from_status` | Estado anterior. |
| `to_status` | Estado nuevo. |
| `reason` | Motivo administrativo. |
| `created_at` | Fecha del cambio. |

## Bloqueo de login y sesión

Hay doble control efectivo:

1. `App\Filament\Pages\Auth\Login` bloquea login de usuarios suspendidos, cierra sesión y muestra: `Tu cuenta está suspendida. Contacta al administrador.`
2. `EnsureUserIsActive` corre después de `StartSession` y antes de `Authenticate` en el panel. Si un usuario ya logueado fue suspendido, invalida la sesión y redirige al login.

`UpdateUserLastLoginAt` ignora usuarios suspendidos para no registrar login exitoso cuando Filament los bloquea.

## Mapeo QA → tests

| QA | Caso | Test principal |
| --- | --- | --- |
| QA-011 | Crear usuario con rol | `UserResourceTest::test_owner_can_create_user_with_role_from_resource` |
| QA-012 | Asignar/cambiar rol | `UserResourceTest::test_owner_can_change_user_role_from_resource_form` |
| QA-013 | Editar usuario y proteger Owner | `UserResourceTest::test_admin_can_edit_agent_phone_but_cannot_edit_owner` |
| QA-014 | Suspender con motivo y auditoría | `UserStatusServiceTest::test_admin_can_suspend_and_reactivate_agent_with_audit_logs`, `UserResourceTest::test_owner_can_suspend_and_reactivate_user_from_resource_table` |
| QA-015 | Reactivar con auditoría | `UserStatusServiceTest::test_admin_can_suspend_and_reactivate_agent_with_audit_logs`, `UserResourceTest::test_owner_can_suspend_and_reactivate_user_from_resource_table` |
| QA-016 | Permisos/acceso | `UserPolicyTest::test_agente_cannot_access_user_administration`, `UserResourceTest::test_agente_cannot_access_user_resource` |
| QA-017 | Bloqueo de login suspendido | `UserResourceTest::test_suspended_user_cannot_access_panel_and_sees_clear_login_message` |

## Regresiones cubiertas

- Modelo: campos, casts, helpers, soft delete, `last_login_at` y factories de estado/rol.
- Roles/permisos: seeder idempotente y matriz completa.
- CRUD: crear, editar, validar, asignar rol, soft delete y restore.
- Seguridad: policies, protección Owner, bloqueo de agente y usuario suspendido.
- Suspensión: transición, reactivación y auditoría.

## Decisiones diferidas

- `Property` y `Lead` no existen aún; relaciones en `User` son contratos para épicas futuras.
- Avatar con Spatie Media Library queda diferido; el Resource usa upload simple.
- Auth pública adicional deberá reutilizar `EnsureUserIsActive` o un control equivalente.
