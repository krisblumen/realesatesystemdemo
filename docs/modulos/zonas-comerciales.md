# Administración de zonas comerciales

Este módulo permite administrar zonas comerciales desde Filament, persistir polígonos PostGIS en SRID 4326 y asignar agentes activos a cada zona. La implementación actual cubre el MVP administrativo de la Épica 3; inmuebles y distribución de leads quedan diferidos para las épicas siguientes.

## Quick path

1. Entrar a `/admin` con un usuario `owner` o `admin`.
2. Abrir **Zonas comerciales**.
3. Crear o editar una zona con nombre, municipio, status y, opcionalmente, un polígono GeoJSON.
4. Desde la edición de la zona, usar **Agentes asignados** para adjuntar o quitar agentes activos.
5. Verificar con `php artisan test` antes de mover el cambio.

## Modelo y autorización

| Pieza | Implementación |
| --- | --- |
| Modelo | `App\Models\Zone` con `SoftDeletes`, `ZoneStatus`, slug único contra registros activos y borrados, y helpers `isActive()` / `isInactive()`. |
| Tabla | `zones` incluye `name`, `slug`, `description`, `municipality`, `status`, `polygon`, `center_point`, timestamps y `deleted_at`. |
| Geometría | `polygon geometry(Polygon, 4326)` y `center_point geometry(Point, 4326)`. La migración actual crea índice GIST sobre `polygon`; `center_point` queda como dato derivado sin índice propio. |
| Permiso | `zones.manage`, sembrado para `owner` y `admin`. |
| Policy | `ZonePolicy` permite ver/crear/editar a `owner` y `admin`; borrar/restaurar sólo a `owner`. |
| Agentes | `agent_zone` conecta zonas con usuarios de rol `agente`; sólo agentes activos son asignables desde el Relation Manager. |

## Estrategia PostGIS en Eloquent

No hay paquete geoespacial instalado. La integración real está encapsulada en `App\Services\Zones\ZoneGeometry` y en métodos del modelo:

- `setPolygonFromGeoJson()` convierte GeoJSON a EWKT validado.
- `setPolygonFromWkt()` normaliza WKT a `SRID=4326`.
- `polygonAsGeoJson()` lee con `ST_AsGeoJSON`.
- `scopeContainingPoint($longitude, $latitude)` usa bounding box `&&` y `ST_Contains`.
- `scopeContainingPropertyPoint()` deja preparada la consulta para una futura columna `properties.location`.

Al guardar una zona, el modelo valida que la geometría sea `POLYGON`, tenga SRID 4326, sea topológicamente válida y tenga anillo exterior cerrado. Después recalcula `center_point` con `ST_Centroid(polygon)` y refresca el modelo para evitar desincronización en memoria.

## Edición en Filament

`ZoneResource` usa un textarea `polygon_geojson` para el MVP. El formulario transforma ese GeoJSON mediante el modelo, por lo que la validación de UI y la persistencia comparten la misma fuente de verdad PostGIS.

El listado permite buscar y filtrar por municipio/status. Las acciones de borrado/restauración dependen de la policy, no de la visibilidad de botones como control primario.

## Asignación de agentes

`AgentsRelationManager` vive dentro de la edición de una zona:

- muestra agentes ya asignados;
- permite adjuntar agentes activos con rol `agente`;
- rechaza `owner`, `admin` y agentes suspendidos;
- evita duplicados por la clave primaria compuesta de `agent_zone`;
- permite quitar asignaciones sin borrar usuarios ni zonas.

La relación inversa `User::zones()` permite consultar las zonas asignadas a un agente.

## Integración con RFCs y épicas

| Referencia | Estado |
| --- | --- |
| RFC-003 | PostGIS se activa desde la migración `create_zones_table` con `CREATE EXTENSION IF NOT EXISTS postgis`. |
| RFC-004 | Base administrativa Filament y acceso `/admin` se conservan; se verifica por suite completa. |
| RFC-006 | Roles/permisos `owner`, `admin`, `agente` y permiso `zones.manage` gobiernan el acceso. |
| RFC-012 / Épica 2 | Roles, permisos, login, suspensión y `UserResource` siguen cubiertos por tests de regresión. |
| Épica 4 | `Zone::properties()` y `scopeContainingPropertyPoint()` son contratos diferidos hasta que exista `Property`. |
| Lead distribution | Diferido: esta épica sólo deja zonas y asignaciones, no reglas de distribución automática. |

## Mapeo QA-018 → QA-025

| QA | Criterio | Cobertura |
| --- | --- | --- |
| QA-018 | Crear zona con slug generado, status activo y campos base. | `ZoneCoreTest::test_zone_generates_unique_slugs_including_soft_deleted_rows`, `ZoneResourceTest::test_owner_can_create_zone_with_valid_polygon_geojson`. |
| QA-019 | Activar/inactivar zona y persistir status. | `ZoneCoreTest::test_zone_status_casts_helpers_and_soft_deletes`, `ZoneResourceTest::test_admin_can_edit_zone_status_and_polygon_but_cannot_delete_or_restore`. |
| QA-020 | Agente no puede gestionar zonas; backend exige `zones.manage`. | `ZonePolicyTest::test_owner_and_admin_can_manage_zones_while_agente_cannot`, `ZoneResourceTest::test_owner_and_admin_can_access_zone_resource_pages_while_agente_cannot`. |
| QA-021 | Guardar polígono y calcular `center_point`. | `ZoneGeospatialTest::test_zone_calculates_center_point_from_polygon_on_create_and_update`, `ZoneCoreTest::test_zone_factory_can_create_valid_polygon_fixture`. |
| QA-022 | Consultar zona que contiene un punto. | `ZoneGeospatialTest::test_scope_finds_zones_containing_a_point_with_index_friendly_bounding_box`. |
| QA-023 | Asignar, consultar, reasignar y quitar agentes sin duplicados. | `AgentZoneAssignmentTest::test_agent_zone_pivot_is_unique_and_exposes_bidirectional_relationships`, `test_owner_and_admin_can_attach_and_detach_agents_but_agent_cannot`, `test_owner_can_reassign_agent_between_zones_without_duplicate_assignments`, `test_only_active_agente_users_are_assignable_from_relation_manager_options`, `test_backend_rejects_attaching_owner_or_admin_as_zone_agents`. |
| QA-024 | Soft delete/restauración de zonas. | `ZoneResourceTest::test_owner_can_soft_delete_and_restore_zone_from_resource_table`, `ZonePolicyTest::test_only_owner_can_delete_and_restore_zones`. |
| QA-025 | Regresión Épicas 1/2: `/admin`, PostGIS, roles/login/User CRUD/suspensión siguen funcionando. | `php artisan test`, con énfasis en `PermissionSeederTest`, `EnsureUserIsActiveTest`, `UserCoreTest`, `UserPolicyTest`, `UserStatusServiceTest`, `UserResourceTest`, `ZoneCoreTest::test_zones_table_exposes_core_postgis_contract`. |

## Comandos de verificación

```bash
php artisan test
vendor\bin\pint --test
vendor\bin\phpstan analyse
npm run build
```

Si `vendor\bin\phpstan` o `node_modules` no existen en el entorno local, documentar el bloqueo real en el reporte de verificación en vez de simular cobertura.
