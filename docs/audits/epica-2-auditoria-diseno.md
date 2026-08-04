# Auditoría de diseño — Épica 2 — Usuarios y Seguridad

**Proyecto:** New Hauz  
**Fecha:** 16 de Junio, 2026  
**Auditor:** Gemini CLI  
**Documento Auditado:** `docs/epicas/epica-2-usuarios-y-seguridad.md`

---

## 1. Veredicto
**Aprobado con observaciones**

El diseño es técnicamente sólido, respeta los contratos de la Épica 1 y sigue las mejores prácticas de Laravel 13 y Filament v3. La implementación de la máquina de estados para suspensiones y la protección del rol `owner` a nivel de Policy garantizan la integridad del sistema. Se han identificado detalles de usabilidad y consistencia técnica en la interfaz administrativa que deben corregirse antes de iniciar la implementación.

---

## 2. Hallazgos críticos
*No se encontraron hallazgos críticos que impidan el inicio de la implementación.*

---

## 3. Hallazgos medios

### 3.1 Bloqueo de UI para el rol Admin en UserResource
En el Lote C (Sección 7.2), los campos `roles` y `status` tienen una restricción de visibilidad:
```php
->visible(fn () => auth()->user()->hasRole('owner'))
```
Sin embargo, la matriz de permisos otorga a `admin` la capacidad de crear y actualizar usuarios (`users.create`, `users.update`). Dado que estos campos están marcados como `->required()`, cualquier usuario con rol `admin` fallará al intentar guardar un registro porque el campo es obligatorio pero invisible en su interfaz.
**Impacto:** El rol `admin` no podrá realizar las tareas de gestión de usuarios asignadas.

---

## 4. Hallazgos menores

### 4.1 Consistencia de Tipos en Auditoría de Suspensión
La tabla `user_suspensions` utiliza un campo `string` para `action`. Siguiendo la convención establecida con `UserStatus`, se recomienda utilizar un Enum (`SuspensionAction`) para los valores `suspendido` y `reactivado`.

### 4.2 Redundancia en el bloqueo de acceso
Se propone tanto un Middleware como un Listener de Login. El Middleware es suficiente y más robusto (cubre sesiones ya iniciadas que son suspendidas en tiempo real). El Listener es aceptable como defensa en profundidad pero añade una pequeña carga cognitiva al mantenimiento.

---

## 5. Sobreingeniería detectada
- **Contratos Diferidos (Sección 5.4):** La implementación de `1=0` para devolver relaciones vacías es ingeniosa para evitar errores de base de datos antes de que existan las tablas, pero podría considerarse una solución temporal que debe ser monitoreada para no dejar "código muerto" si las Épicas 3/4 cambian sus nombres de tabla.

---

## 6. Riesgos de implementación
- **Caché de Permisos:** Como se menciona en los riesgos (R-1), el seeder debe asegurar la limpieza de caché de Spatie para evitar falsos negativos en los tests de Policy.
- **Relaciones Cascade:** El uso de `cascadeOnDelete` en `user_suspensions` es correcto, pero se debe tener extrema precaución en el futuro con `properties` y `leads` para no perder datos históricos si un usuario es eliminado físicamente por error (aunque el diseño prioriza Soft Delete).

---

## 7. Riesgos de seguridad
- **Protección de Owner:** Verificado. La Policy impide que un Admin suspenda o modifique a un Owner. La lógica es correcta al estar en la capa de Policy y no solo en la UI.
- **Validación de Password:** Verificado. Se hashea correctamente en el Resource y no en el modelo, evitando doble hasheo o almacenamiento en plano.

---

## 8. Recomendaciones obligatorias
1.  **Corregir visibilidad en UserResource:** Cambiar `visible(fn () => auth()->user()->hasRole('owner'))` por una lógica que permita a `admin` gestionar roles (quizás restringiendo que no pueda asignar el rol `owner` a otros).
2.  **Implementar Enum para SuspensionAction:** Crear `app/Enums/SuspensionAction.php` para estandarizar el historial.
3.  **Asegurar validación de roles:** En el Resource, el Admin no debería poder asignar el rol `owner`. Esto debe validarse tanto en el componente `Select` (opciones filtradas) como en la `Policy`.

---

## 9. Recomendaciones opcionales
1.  **Refinar el Listener de Login:** Si se mantiene, asegurar que registre un log de seguridad cuando un usuario suspendido intente entrar, para detectar posibles intentos de intrusión o usuarios descontentos.

---

## 10. Preguntas abiertas
- ¿Se permitirá que un `admin` cree a otros `admin`? Según la Policy actual sí, pero el `owner` es el único protegido explícitamente.

---

## 11. Checklist de corrección para Claude (Agente de Implementación)
- [ ] Crear `app/Enums/SuspensionAction.php`.
- [ ] Ajustar `visible()` en `UserResource` para incluir al rol `admin`.
- [ ] Filtrar opciones del `Select::make('roles')` para que un `admin` no pueda ver/asignar el rol `owner`.
- [ ] Asegurar que `PermissionSeeder` llame a `forgetCachedPermissions()`.

---

## 12. Checklist de implementación para Codex (Agente de Programación)
- [ ] Implementar Lotes A → E siguiendo el orden definido.
- [ ] Priorizar la creación de la Policy antes que el Resource.
- [ ] Ejecutar la suite de tests tras cada Lote.
- [ ] Verificar compatibilidad con PostgreSQL en cada migración.

---
*Fin del reporte de auditoría.*
