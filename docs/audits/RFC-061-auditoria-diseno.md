# Auditoría de Diseño: RFC-061 INTEGRACIÓN DE ZONAS COMERCIALES AL DASHBOARD

**Proyecto:** NEW HAUZ (Laravel 13 / Filament v3 / PostgreSQL + PostGIS)  
**Fecha:** 2026-06-18  
**Auditor:** Antigravity (Senior Technical Auditor)  
**Documento Auditado:** `docs/rfc/RFC-061-zonas-dashboard.md`  

---

## 1. Veredicto
**Resultado:** ⚠️ **APROBADO CON OBSERVACIONES** (Aprobación sujeta a la corrección de inconsistencias lógicas de enums y permisos de vista)

### Resumen del Veredicto
El diseño propuesto en el RFC-061 es modular y respeta correctamente el contrato dinámico de autodescubrimiento de Filament y el hook de redirección dinámico implementado en el RFC-060. No obstante, se detectó un **error lógico en las consultas de estado** (el enum de estado de la zona es femenino y la consulta usa masculino) y un **conflicto de seguridad en la navegación del agente** (se enlaza a un recurso que produce error 403 debido a la política restrictiva de la Épica 2). La corrección de estos puntos garantizará un despliegue sin fallas.

---

## 2. Hallazgos Críticos (Bloqueantes)
No se identificaron hallazgos críticos que impidan iniciar el desarrollo base, pero las inconsistencias detalladas a continuación impedirían que la funcionalidad de estadísticas y listas despliegue datos correctos o causará errores de acceso (403) a los agentes en producción.

---

## 3. Hallazgos Medios
1. **Inconsistencia de Valor en Consulta de Enum `ZoneStatus`:**
   - **Ubicación:** `app/Filament/Widgets/ZonesOverviewWidget.php` (línea 257) y `app/Filament/Widgets/AgentZonesWidget.php` (línea 199).
   - **Impacto:** Las consultas propuestas en el RFC realizan la búsqueda filtrando por `where('status', 'activo')`. Sin embargo, en el enum real `App\Enums\ZoneStatus` (definido en la Épica 3), el valor activo está definido como `'activa'` (femenino: `case Active = 'activa'`). Al realizar la consulta en PostgreSQL con un valor de cadena `'activo'` incorrecto, la base de datos retornará siempre un conteo de **0** zonas activas y dejará la lista de zonas asignadas al agente vacía, quebrando la lógica de negocio.
   - **Corrección:** Usar el valor tipado del enum en las consultas: `ZoneStatus::Active->value` (o `'activa'` literal si no se quiere importar el enum).
2. **Acceso Denegado (403) Asegurado en Enlace de Agente a `ZoneResource`:**
   - **Ubicación:** `resources/views/filament/widgets/agent-zones.blade.php` (línea 222).
   - **Impacto:** La vista del widget propone enlazar el nombre de la zona a `\App\Filament\Resources\ZoneResource::getUrl('view', ['record' => $zone])`. No obstante, de acuerdo con la política `App\Policies\ZonePolicy` y el seeder `PermissionSeeder.php` de la Épica 2, el rol `agente` **no** cuenta con el permiso `zones.manage` y por ende no tiene acceso a las acciones `view` o `viewAny` del recurso. Al dar clic en "Ver", el agente recibirá un error `403 Forbidden` de Laravel/Filament.
   - **Corrección:** Omitir el enlace a `ZoneResource` en la vista del widget de agentes y limitarse a renderizar los metadatos de la zona (nombre, municipalidad) como texto plano.

---

## 4. Hallazgos Menores
1. **Relación Inversa `User::zones()` Duplicada/Innecesaria:**
   - **Ubicación:** `app/Models/User.php` (Sección 5.5 del RFC).
   - **Impacto:** El RFC asume como tarea tentativa del Lote A la creación de la relación `User::zones()`. Sin embargo, tras validar contra el código del repositorio, la relación inversa ya existe en `app/Models/User.php` (líneas 95-99) de forma operativa.
   - **Corrección:** Declarar la relación como preexistente y eliminar la modificación aditiva del archivo `app/Models/User.php` del alcance del Lote A para evitar intervenciones redundantes.
2. **Sintaxis de Pest en Tests sobre Entorno PHPUnit 12:**
   - **Ubicación:** `docs/rfc/RFC-061-zonas-dashboard.md` (Sección 8. Criterios de Aceptación / Casos QA).
   - **Impacto:** El RFC propone tests en formato de Pest (`it(...)`), pero la configuración actual del repositorio en `composer.json` y el suite de pruebas corre bajo PHPUnit 12 nativo (`test_` prefix).
   - **Corrección:** Cambiar la nomenclatura de pruebas propuestas a sintaxis estándar de métodos de PHPUnit 12.
3. **Omisión de Caso QA para Usuario Activo sin Rol Permitido en Redirección:**
   - **Ubicación:** `docs/rfc/RFC-061-zonas-dashboard.md` (Sección 8. Criterios de Aceptación).
   - **Impacto:** Aunque se valida que un no-agente no pueda acceder a `/admin/mi-zona` (QA-052), no se valida explícitamente en los casos QA qué sucede con un usuario sin roles que intenta ingresar.
   - **Corrección:** Añadir un caso de prueba (`test_blocks_active_user_without_allowed_roles_from_accessing_any_dashboard`) para robustecer el alcance de la integración.

---

## 5. Sobreingeniería Detectada
* No se detecta sobreingeniería. La decisión de apoyarse en la funcionalidad de auto-descubrimiento de Filament v3 para registrar las páginas y los widgets en lugar de manipular `AdminPanelProvider.php` de forma manual es una excelente decisión que previene conflictos de fusionado (merge conflicts).

---

## 6. Riesgos de Implementación
1. **Desviación del Contrato de Redirección (RFC-060):**
   - El redirect post-login del agente en `Login::getRedirectUrl()` es resuelto por `class_exists(AgentDashboard::class)`. Si el namespace de la clase o su ruta no coincide exactamente con `App\Filament\Pages\AgentDashboard`, el hook fallará de forma silenciosa y los agentes aterrizarán en el dashboard ordinario. Se debe corroborar esto inmediatamente en el Lote A.

---

## 7. Riesgos de Seguridad
1. **Exposición Accidental de Widgets por Falla en `canView()`:**
   - Al usar el auto-descubrimiento general en `app/Filament/Widgets`, si se olvida implementar o falla el método `canView()` en `AgentZonesWidget` o `ZonesOverviewWidget`, los paneles se cruzarán, exponiendo estadísticas privadas de administración a los agentes. Se deben implementar pruebas estrictas para validar la visibilidad.

---

## 8. Recomendaciones Obligatorias
1. **Corregir Filtros de SQL:** Usar `'activa'` o la referencia del enum `ZoneStatus::Active->value` en las consultas de base de datos de los widgets.
2. **Remover el Enlace Interrumpido (403):** Eliminar el tag `<a>` en `agent-zones.blade.php` para evitar que el agente reciba errores de acceso.
3. **Utilizar PHPUnit 12 para la Suite de Pruebas:** Formatear los nombres de test propuestos en el RFC a sintaxis nativa de clases de PHPUnit.

---

## 9. Recomendaciones Opcionales
1. **Diseñar una página de visualización exclusiva para Agentes (Futura iteración):** Si se desea que el agente vea el detalle completo de la zona, considerar crear una página específica dentro de su flujo de dashboard en lugar de intentar darle lectura a `ZoneResource`.

---

## 10. Preguntas Abiertas
1. **Refinamiento de UX/UI para Agentes:** Dado que `AgentDashboard` es temporalmente un contenedor simple de un widget, ¿cuál será la UX/UI final de esta landing en futuras épicas? (¿Se integrarán accesos rápidos a leads, etc.?).

---

## 11. Checklist de Corrección (Para Claude - Agente de Implementación)
- [ ] Modificar las consultas en `app/Filament/Widgets/ZonesOverviewWidget.php` y `app/Filament/Widgets/AgentZonesWidget.php` para usar `'activa'` o `ZoneStatus::Active->value` como valor de filtro.
- [ ] Editar `resources/views/filament/widgets/agent-zones.blade.php` para remover el tag de enlace `<a href="...">Ver</a>` y mostrar únicamente el texto plano de la zona.
- [ ] Adaptar la lista de pruebas de referencia del RFC al formato nativo de PHPUnit 12.
- [ ] Actualizar el listado del alcance técnico indicando que `app/Models/User.php` no requiere modificaciones ya que la relación `zones()` ya está implementada.

---

## 12. Checklist de Implementación (Para Codex - Agente de Programación)
- [ ] Crear la página `App\Filament\Pages\AgentDashboard` y validar que el hook de `Login.php` lo detecte (redirigiendo a `/admin/mi-zona`).
- [ ] Implementar `App\Filament\Widgets\AgentZonesWidget` con `canView()` restringido a `agente` y su vista en `resources/views/filament/widgets/agent-zones.blade.php` sin enlaces rotos.
- [ ] Implementar `App\Filament\Widgets\ZonesOverviewWidget` con `canView()` restringido a `owner` y `admin`.
- [ ] Crear las suites de pruebas en `tests/Feature/Dashboard/AgentDashboardTest.php` y `tests/Feature/Dashboard/ZonesWidgetsTest.php` en base a PHPUnit 12.
- [ ] Confirmar que la suite de pruebas completa (`composer test`) pase correctamente en entorno PostgreSQL.
- [ ] Verificar QA-047 a QA-054 manualmente.
