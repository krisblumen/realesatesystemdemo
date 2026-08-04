# Cierre Técnico — Zona: Polígono Obligatorio (NOT NULL)

**Proyecto:** NEW HAUZ
**RFC:** RFC-061 (Zonas → Dashboard) · base geoespacial RFC-015 / RFC-018
**Rama:** `feature/rfc-061-zonas-dashboard`
**Fecha de cierre:** 2026-06-19
**Cerrado por:** Claude (Arquitecto)

---

## Veredicto

> **✅ APROBADO**

La geometría PostGIS de las zonas **ya estaba implementada y testeada** antes de esta sesión. El único hueco respecto a la regla de negocio ("toda zona nace con su polígono") era que la columna `polygon` admitía `NULL`. Esta entrega cierra ese hueco: la geometría es obligatoria a nivel de base de datos y de formulario, sigue siendo editable, y se conserva la implementación raw-SQL existente (sin agregar dependencias). Suite completa **76/76 verde** sobre PostgreSQL real y verificación en vivo del pipeline geoespacial contra `inmo_db`.

---

## Contexto — qué ya existía (verificado, no reconstruido)

La búsqueda previa a implementar confirmó que el stack geoespacial estaba completo:

| Componente | Estado previo |
|---|---|
| Migración `zones` | `polygon geometry(Polygon, 4326)`, `center_point geometry(Point, 4326)`, `CREATE EXTENSION postgis`, índice GIST, check de status |
| `App\Services\Zones\ZoneGeometry` | Conversión GeoJSON/WKT/EWKT + validación PostGIS (SRID 4326, tipo POLYGON, `ST_IsValid`, anillo cerrado) |
| `App\Models\Zone` | `setPolygonFromGeoJson`, `polygonAsGeoJson`, auto-`ST_Centroid`, scope `containingPoint` (`&&` + `ST_Contains`) |
| Form `ZoneResource` | Campo `polygon_geojson` con regla `ValidZonePolygonGeoJson` |
| Tests | `ZoneCoreTest`, `ZoneGeospatialTest`, `ZonePolicyTest`, `ZoneResourceTest`, `AgentZoneAssignmentTest` |

**Decisión:** NO instalar `clickbar/laravel-magellan`. La implementación raw-SQL ya cumple y está testeada; introducir el paquete sería refactor sin ganancia funcional. **PostGIS 3.6.4 ya está habilitado en `inmo_db`.**

---

## Cambio entregado

Regla de negocio: la zona **nace con un polígono obligatorio** pero **se puede modificar después** (la columna sigue siendo mutable; solo se prohíbe el `NULL`).

| # | Cambio | Archivo |
|---|---|---|
| 1 | `ALTER COLUMN polygon SET NOT NULL` (guard pgsql, `down` revierte) | `database/migrations/2026_06_19_120000_make_zones_polygon_not_null.php` |
| 2 | Factory deja de generar zonas sin polígono — polígono válido por defecto | `database/factories/ZoneFactory.php` |
| 3 | Campo `polygon_geojson` ahora `->required()` | `app/Filament/Resources/ZoneResource.php` |
| 4 | `EditZone` ya no permite anular el polígono a `NULL` (rama muerta bajo `required` + incompatible con `NOT NULL`) | `app/Filament/Resources/ZoneResource/Pages/EditZone.php` |

---

## Cobertura de Tests

| Caso | Test | Estado |
|---|---|---|
| Columna `polygon` es `NOT NULL` (schema) | `ZonePolygonRequiredTest::test_polygon_column_is_not_nullable` | ✅ |
| La DB rechaza una zona sin polígono | `ZonePolygonRequiredTest::test_database_rejects_a_zone_without_polygon` | ✅ |
| El form exige `polygon_geojson` (required) | `ZoneResourceTest::test_zone_form_requires_polygon_geojson` | ✅ |
| Regresión — geometría, centroide, scope, CRUD, policy | suite previa de zonas | ✅ |

**Suite completa: 76/76 (431 assertions).** Antes: 73 → +3 nuevos. Cero regresiones.

---

## Verificación en vivo (`inmo_db`, PostgreSQL real)

1. Migración aplicada: `polygon` → `is_nullable = NO`.
2. Form de creación **bloquea** el submit sin polígono (validación `required` activa).
3. Pipeline geoespacial end-to-end:
   - Alta con GeoJSON → `polygonAsGeoJson()` relee sin corrupción.
   - `center_point` auto-calculado por `ST_Centroid` = `(-100.35, 20.65)` (centro exacto).
   - `containingPoint(-100.35, 20.65)` → **dentro = SÍ**; `containingPoint(-90, 10)` → **fuera = NO**.

---

## Contratos para épicas siguientes

| Contrato | Archivo | Descripción |
|---|---|---|
| `zones.polygon` es `NOT NULL` | migración | Toda zona persistida tiene geometría. Épica 4 (inmuebles) puede asumir polígono presente al resolver "¿en qué zona cae esta propiedad?". |
| `Zone::scopeContainingPoint(lng, lat)` | `app/Models/Zone.php` | Consulta index-friendly (`&&` + `ST_Contains`). Orden de coordenadas **[lng, lat]**. |
| `Zone::scopeContainingPropertyPoint(col)` | `app/Models/Zone.php` | Preparado para cuando `properties` exponga columna PostGIS de punto (Épica 4). |
| Validación de polígono | `ValidZonePolygonGeoJson` + `ZoneGeometry` | Rechaza SRID ≠ 4326, geometría inválida y anillos abiertos. |

---

## Archivos — Inventario final

| Archivo | Operación |
|---|---|
| `database/migrations/2026_06_19_120000_make_zones_polygon_not_null.php` | Creado |
| `database/factories/ZoneFactory.php` | Modificado — polígono por defecto |
| `app/Filament/Resources/ZoneResource.php` | Modificado — `polygon_geojson` required |
| `app/Filament/Resources/ZoneResource/Pages/EditZone.php` | Modificado — sin anulación de polígono |
| `tests/Feature/Zones/ZonePolygonRequiredTest.php` | Creado — 2 tests |
| `tests/Feature/Filament/ZoneResourceTest.php` | Modificado — +1 test required |

---

## Checklist

- [x] `polygon` `NOT NULL` en migración (con `down` reversible y guard pgsql)
- [x] Factory genera polígono válido por defecto
- [x] Form de creación y edición exige polígono
- [x] `EditZone` no puede anular la geometría
- [x] 76/76 tests en verde sobre PostgreSQL
- [x] Migración aplicada y verificada en `inmo_db`
- [x] Pipeline geoespacial verificado en vivo (centroide + `ST_Contains`)
- [x] Sin dependencias nuevas (se conserva raw-SQL `ZoneGeometry`)

---

## Deuda técnica

| ID | Deuda | Motivo | Estado |
|---|---|---|---|
| DT-1 | Editor de mapa (Leaflet) para dibujar el polígono | Hoy el polígono se pega como GeoJSON crudo en un textarea — áspero para un usuario de negocio. El backend ya valida y persiste la geometría correctamente; solo falta la capa de dibujo en el form. | ⏳ Diferido — aceptado para este MVP |
