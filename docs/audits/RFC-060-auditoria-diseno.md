# Auditoría de Diseño: RFC-060 LOGIN REAL

**Proyecto:** NEW HAUZ (Laravel 13 / Filament v3 / PostgreSQL + PostGIS)  
**Fecha:** 2026-06-18  
**Auditor:** Antigravity (Senior Technical Auditor)  
**Documento Auditado:** `docs/rfc/RFC-060-LOGIN-REAL.md`  

---

## 1. Veredicto
**Resultado:** ❌ **RECHAZADO** (Requiere corrección de diseño de middleware y seguridad antes de aprobación)

### Resumen del Veredicto
El diseño propuesto para el RFC-060 es minimalista y persigue objetivos correctos (limpieza de workarounds y corrección del defecto de acceso de agentes). Sin embargo, presenta un **defecto de diseño crítico a nivel de middleware**: registrar `EnsureUserIsActive` después de `Authenticate` en `authMiddleware` del panel de Filament causa que Filament intercepte al usuario suspendido y le lance un error 403 Forbidden antes de que el middleware de suspensión actúe. Como consecuencia directa, **la sesión del usuario suspendido nunca se cierra ni se invalida**, comprometiendo la seguridad y haciendo fallar las pruebas automatizadas existentes del proyecto (lo cual se ha verificado ejecutando la suite sobre PostgreSQL).

---

## 2. Hallazgos Críticos (Bloqueantes)
1. **Orden Inválido del Middleware de Suspensión (Fuga de Sesión y Falla de Test):**
   - **Ubicación:** `app/Providers/Filament/AdminPanelProvider.php` (Sección `D-2` y `Lote A`).
   - **Impacto:** Al proponer el registro de `EnsureUserIsActive` detrás de `Authenticate::class`:
     ```php
     ->authMiddleware([
         Authenticate::class,
         EnsureUserIsActive::class,
     ])
     ```
     El middleware `Authenticate` de Filament se ejecuta primero. Si el usuario está autenticado pero suspendido, `Authenticate` evalúa `$user->canAccessPanel()`, el cual verifica `$this->isActive()`. Dado que el usuario está suspendido, esto retorna `false` y Filament lanza inmediatamente una excepción HTTP `403 Forbidden` (`AccessDeniedHttpException`).
     La ejecución se interrumpe y `EnsureUserIsActive` **nunca se ejecuta**. Por ende, no se ejecuta `auth()->logout()`, no se invalida la sesión y no se regenera el token de seguridad. La sesión del usuario suspendido permanece activa en su navegador. Esto además rompe el test existente `test_suspended_user_cannot_access_panel_and_sees_clear_login_message` (retorna 403 en vez del redirect esperado a login con error).
   - **Corrección:** Invertir el orden de los middlewares en el provider para asegurar que la verificación de suspensión ocurra antes que el bloqueo de acceso del panel:
     ```php
     ->authMiddleware([
         EnsureUserIsActive::class,
         Authenticate::class,
     ])
     ```

---

## 3. Hallazgos Medios
1. **Falta de Protección del Rol `owner` en `UserPolicy::delete`:**
   - **Ubicación:** `app/Policies/UserPolicy.php` (Contrato consumido de la Épica 2).
   - **Impacto:** El método `delete` del policy no protege a los usuarios con rol `owner`. Aunque en `PermissionSeeder.php` el rol `admin` no cuenta actualmente con la capacidad `users.delete`, si en el futuro se asignara este permiso a otros roles, el policy les permitiría eliminar al `owner` del sistema (con la única restricción de que un usuario no puede eliminarse a sí mismo). El rol `owner` es el nivel máximo de administración y no debe ser eliminable por ningún otro usuario en el dominio.
   - **Corrección:** Añadir una salvaguarda explícita en `UserPolicy::delete`:
     ```php
     if ($target->hasRole('owner')) {
         return false;
     }
     ```
2. **Actualización Inapropiada de `last_login_at` en Logins Denegados por Roles:**
   - **Ubicación:** `app/Listeners/UpdateUserLastLoginAt.php` (Contrato consumido de la Épica 2).
   - **Impacto:** Laravel dispara el evento `Login` internamente en `Auth::attempt` al verificar credenciales correctas. Si un usuario activo pero sin roles permitidos (`owner`, `admin`, `agente`) inicia sesión, el listener `UpdateUserLastLoginAt` actualiza su `last_login_at` porque el usuario está activo (no suspendido). Justo después, Filament verifica `canAccessPanel()`, rechaza el login y cierra la sesión. El sistema habrá marcado a este usuario con una fecha de login exitoso a pesar de haber sido bloqueado.
   - **Corrección:** El listener debe validar que el usuario tenga un rol autorizado antes de actualizar la fecha de login, o bien considerar esto en un log de eventos de acceso.

---

## 4. Hallazgos Menores
1. **Sintaxis de Tests Desalineada (Pest vs. PHPUnit 12):**
   - **Ubicación:** `docs/rfc/RFC-060-LOGIN-REAL.md` (Sección 8. Criterios de Aceptación / Casos QA).
   - **Impacto:** El RFC hace referencia a pruebas en sintaxis Pest (`it('allows owner to access admin panel')`). Sin embargo, el proyecto corre bajo **PHPUnit 12** sin dependencias de Pest (confirmado en `composer.json` y en las suites existentes bajo `tests/Feature/Auth/`). Esto puede generar confusión al implementador.
   - **Corrección:** Actualizar la documentación y nombres de referencia en el RFC a métodos PHPUnit convencionales (`test_allows_owner_to_access_admin_panel`).
2. **Ausencia de Caso de Prueba para Usuarios Activos Sin Roles Permitidos:**
   - **Ubicación:** `docs/rfc/RFC-060-LOGIN-REAL.md` (Sección 8. Criterios de Aceptación / Casos QA).
   - **Impacto:** Los casos QA y la suite de tests omiten verificar qué sucede si un usuario activo sin roles permitidos intenta ingresar (debe ser bloqueado y la sesión no debe persistir).
   - **Corrección:** Añadir un caso de prueba (`test_blocks_active_user_without_allowed_roles_from_admin_panel`) y el caso `QA-046` correspondiente.

---

## 5. Sobreingeniería Detectada
1. **Código Comentado/Muerto para Redirección de Agente:**
   - **Ubicación:** `app/Filament/Pages/Auth/Login.php` (Sección `D-3`).
   - **Impacto:** Mantener un bloque de código comentado para `AgentDashboard` en espera de la Épica 3 genera ruido técnico innecesario en la clase.
   - **Corrección:** Evitar el código comentado mediante una verificación dinámica robusta usando `class_exists()` y la comprobación de rol. Esto deja el hook activo desde hoy sin necesidad de cambiarlo en la Épica 3:
     ```php
     protected function getRedirectUrl(): string
     {
         if (auth()->user()?->hasRole('agente') && class_exists('\App\Filament\Pages\AgentDashboard')) {
             return \App\Filament\Pages\AgentDashboard::getUrl();
         }

         return parent::getRedirectUrl();
     }
     ```

---

## 6. Riesgos de Implementación
1. **Conflictos de Merge en `AdminPanelProvider.php`:**
   - Este provider es modificado frecuentemente. La eliminación de métodos y reordenamiento de arreglos requiere un merge cuidadoso o un rebase sobre `develop` para evitar sobrescribir configuraciones añadidas de forma paralela.
2. **Persistencia de Caché de Spatie:**
   - La suite de pruebas debe asegurar limpiar la caché de permisos (`app(PermissionRegistrar::class)->forgetCachedPermissions()`) al inicio y final del test, para evitar comportamientos inconsistentes debido al estado compartido.

---

## 7. Riesgos de Seguridad
1. **Persistencia de Sesiones Autenticadas en Usuarios Suspendidos:**
   - (Derivado del Hallazgo Crítico 1). Si un usuario es suspendido a mitad de jornada, su sesión no se cerrará al navegar en Filament y continuará válida a nivel de cookie y servidor, lo que representa un vector de ataque. La inversión del middleware mitiga este riesgo de inmediato.
2. **Anti-enumeración de Cuentas:**
   - Verificado. Al validarse las credenciales completas primero (vía `Auth::attempt`) antes de comprobar el estado de suspensión o los roles, un atacante no puede deducir la existencia de una cuenta simplemente enviando su correo (recibirá el mismo mensaje genérico de credenciales incorrectas si la contraseña no coincide).

---

## 8. Recomendaciones Obligatorias
1. **Reordenar Middleware:** Colocar `EnsureUserIsActive::class` antes de `Authenticate::class` en el array de `authMiddleware` de `AdminPanelProvider.php`.
2. **Implementar Redirección Dinámica para Agente:** Usar la firma activa con `class_exists` en `Login::getRedirectUrl()` para evitar código comentado.
3. **Proteger Dueños (`owner`):** Incluir la validación contra eliminación del rol `owner` en `UserPolicy::delete()`.
4. **Escribir Tests en PHPUnit 12:** Asegurar que `tests/Feature/Auth/AdminLoginTest.php` utilice la sintaxis nativa de clases y métodos de PHPUnit 12, coherente con el resto del proyecto.

---

## 9. Recomendaciones Opcionales
1. **Auditoría de Logins Bloqueados:** En el listener `UpdateUserLastLoginAt`, emitir una advertencia al log de Laravel cuando se detecte un evento exitoso de credenciales para un usuario que no tiene permisos de panel o no está activo, para propósitos de monitoreo.

---

## 10. Preguntas Abiertas
1. **Landing definitiva del agente:** El RFC asume que el agente redirigirá a un `AgentDashboard` en la Épica 3. ¿Está definida la UX/UI de esta página o se usará una vista simplificada del panel Filament?
2. **Notificación de suspensión:** ¿Se mantendrá un mensaje único de suspensión o se integrará en el futuro la razón almacenada en `user_status_logs`?

---

## 11. Checklist de Corrección (Para Claude - Agente de Implementación)
- [ ] Modificar el diseño de `AdminPanelProvider.php` para cambiar el orden de los middlewares en `authMiddleware`, dejando a `EnsureUserIsActive` primero.
- [ ] Modificar `app/Policies/UserPolicy.php` para impedir la eliminación de cualquier usuario con rol `owner`.
- [ ] Implementar `getRedirectUrl()` en `app/Filament/Pages/Auth/Login.php` usando la comprobación dinámica `class_exists` en lugar de código comentado.
- [ ] Adaptar la lista de pruebas en el diseño del RFC al formato convencional de PHPUnit 12.
- [ ] Asegurarse de añadir la validación de un usuario activo sin roles permitidos (`test_blocks_active_user_without_allowed_roles_from_admin_panel`).

---

## 12. Checklist de Implementación (Para Codex - Agente de Programación)
- [ ] Crear el test `tests/Feature/Auth/AdminLoginTest.php` con las 9 pruebas especificadas usando PHPUnit 12.
- [ ] Ejecutar la suite completa (`composer test`) y verificar que todos los tests pasen (incluyendo `UserResourceTest` que antes fallaba).
- [ ] Aplicar la reestructuración en `app/Providers/Filament/AdminPanelProvider.php` (importar el middleware, simplificar métodos, limpiar llamados redundantes a recursos).
- [ ] Aplicar la lógica dinámica en `app/Filament/Pages/Auth/Login.php`.
- [ ] Modificar el método `canAccessPanel()` en `app/Models/User.php` para delegar el control de acceso a los roles (`owner`, `admin`, `agente`).
- [ ] Ejecutar `php artisan filament:optimize-clear` y `php artisan config:clear` antes de realizar las pruebas manuales de QA.
- [ ] Validar manualmente los casos QA-038 a QA-046 sobre el navegador o consola con base de datos PostgreSQL.
