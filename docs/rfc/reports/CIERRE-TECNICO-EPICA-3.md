# Cierre técnico — Épica 3 — Zonas Comerciales

**Proyecto:** New Hauz — Plataforma Inmobiliaria
**Rama:** `feature/epica-3-zonas-comerciales` (8 commits por delante de `origin/feature/epica-3-zonas-comerciales`, working tree limpio)
**Fecha de cierre:** 18 de Junio, 2026
**Arquitecto responsable:** Edgar
**QA:** Sebastián
**Revisión:** Kristian

---

## Estado final

> **✅ APROBADO PARA MERGE.**
>
> Las dos auditorías de Gemini (diseño e implementación) y la validación final registradas en engram concluyen veredicto favorable: diseño "Aprobado con observaciones" (corregidas), implementación "Aprobado" sin hallazgos críticos ni medios obligatorios. La validación final del 2026-06-18 reportó 51 tests / 332 aserciones en verde contra PostgreSQL+PostGIS real, sin regresión en Épicas 1 y 2. Re-ejecuté la suite en esta sesión con `.env.testing` provisto por el usuario: tras corregir un gap de configuración en `phpunit.xml` (ver §9.1), confirmé **51/51 tests, 332 aserciones en verde** y `./vendor/bin/pint --test` sin errores. La rama está lista para commit/push/PR; no se requiere tocar código de producto.

---

## 1. Alcance implementado (RFC-015 → RFC-018)

### RFC-015 — Modelo Zone

| Entregable | Estado | Nota |
| :--- | :---: | :--- |
| Migración `create_zones_table` (slug único, status, geometry columns, GIST, CHECK) | ✅ | `database/migrations/2026_06_17_200000_create_zones_table.php` |
| Enum `ZoneStatus` (activa/inactiva) | ✅ | |
| `Zone` con `SoftDeletes`, slug único (incluye soft-deleted), relación `agents()` | ✅ | Slug generado en `static::saving`, no en Observer separado — desviación menor de forma, mismo contrato |
| Relación `properties()` como contrato diferido (`whereRaw('1 = 0')`) | ✅ | Comentado explícitamente como activación futura Épica 4 |
| Scopes espaciales `containingPoint`, `containingPropertyPoint` | ✅ | El segundo es un contrato preparado para Épica 4, no usado aún |

### RFC-018 — Polígonos PostGIS

| Entregable | Estado | Nota |
| :--- | :---: | :--- |
| Columna `geometry(Polygon,4326)` + `geometry(Point,4326)`, ambas con índice GIST | ✅ | Confirmado en migración y en validación final (`zones_polygon_gist_idx`) |
| Center point auto-calculado vía `ST_Centroid` | ✅ | En `static::saved` del modelo (no en Observer dedicado), con `$zone->refresh()` tras el `DB::statement` — corrige el hallazgo crítico de desincronización de la auditoría de diseño |
| Validación topológica (`ST_IsValid`, anillo cerrado, SRID, tipo de geometría) | ✅ | `app/Services/Zones/ZoneGeometry.php` — corrige el hallazgo crítico R-2 de la auditoría de diseño |
| Estrategia PostGIS-en-Eloquent | **Cambiada respecto al diseño** | Diseño preveía `matanyadaev/laravel-eloquent-spatial`. Implementación final usa **SQL crudo encapsulado en `ZoneGeometry`** (servicio dedicado) sin casts espaciales de terceros. Auditoría de implementación clasifica esto como decisión de diseño aceptada, no como defecto — reduce una dependencia externa y centraliza la gramática PostGIS en un solo punto |

### RFC-016 — CRUD ZoneResource (Filament)

| Entregable | Estado | Nota |
| :--- | :---: | :--- |
| `ZoneResource` con form, table, filtros, acciones | ✅ | `app/Filament/Resources/ZoneResource.php` |
| Captura de polígono | **MVP reducido respecto al diseño** | El diseño preveía `LeafletPolygonInput` (Lote D, mapa interactivo). La implementación entregada usa **`Textarea` + regla `ValidZonePolygonGeoJson`** para pegar GeoJSON manualmente. Documentado en código y en la auditoría de implementación como decisión de alcance de MVP, no como omisión accidental |
| Validaciones de form (`name`, `municipality`, `status`, `polygon_geojson`) | ✅ | |

### RFC-017 — Asignación de Agentes

| Entregable | Estado | Nota |
| :--- | :---: | :--- |
| Pivote `agent_zone` con PK compuesta | ✅ | `database/migrations/2026_06_18_000000_create_agent_zone_table.php` |
| `AgentsRelationManager` con `AttachAction` restringido | ✅ | Verificado por test: solo usuarios con rol `agente` y `status = activo` son asignables; owner/admin son rechazados explícitamente a nivel de backend (`test_backend_rejects_attaching_owner_or_admin_as_zone_agents`) |
| `User::zones()` (relación inversa, aditiva) | ✅ | |

---

## 2. Decisiones técnicas cerradas

- **Modelo `Zone` con slug único y `SoftDeletes`:** cerrado. Unicidad verificada contra registros con soft-delete (`withTrashed()`), generada en `static::saving`.
- **Columna geoespacial SRID 4326 + índice GIST + center point (`ST_Centroid`):** cerrado. El cálculo del centroide corre en `static::saved`, escribe vía SQL crudo y refresca el modelo en memoria — cierra el hallazgo crítico de la auditoría de diseño.
- **Estrategia PostGIS-en-Eloquent:** cerrada como **SQL crudo encapsulado en un servicio (`ZoneGeometry`)**, no el paquete `matanyadaev/laravel-eloquent-spatial` previsto en el diseño original. Decisión de implementación validada por la auditoría: reduce dependencias de terceros sin sacrificar seguridad (todas las validaciones topológicas siguen aplicándose).
- **Edición de polígono en Filament:** cerrada como **estrategia mínima viable** — `Textarea` + validación de GeoJSON en backend. El mapa interactivo Leaflet (Lote D del plan original) queda explícitamente fuera de este alcance, no como bug sino como recorte de producto documentado.
- **Asignación de agentes muchos-a-muchos:** cerrado. Pivote `agent_zone`, restringido por backend a rol `agente` + estado `activo`, no solo por UI.
- **Autorización con `zones.manage` en `ZonePolicy`:** cerrado. `viewAny/view/create/update` requieren `zones.manage`; `delete/restore` requieren además `hasRole('owner')`. Es la única fuente de verdad — Filament consume la Policy, no hay reglas duplicadas en la UI.
- **Relación Zona↔Inmuebles:** cerrada como contrato diferido (`properties()` con `whereRaw('1 = 0')`, comentado como reemplazo pendiente para Épica 4).

---

## 3. Validaciones realizadas (incluye geoespaciales)

- Estructura del GeoJSON (`ValidZonePolygonGeoJson`).
- Validez topológica vía `ST_IsValid` — rechaza polígonos auto-intersectantes.
- Anillo exterior cerrado vía `ST_IsClosed(ST_ExteriorRing(...))`.
- SRID forzado a 4326 (`ST_SRID`), rechazando geometrías con otro SRID o CRS legacy reconocido por PostGIS.
- Tipo de geometría forzado a `POLYGON` (`GeometryType`).
- Verificación manual en `psql`: índice `zones_polygon_gist_idx` usado en consultas point-in-polygon; centroide correcto tras guardar.
- Verificación de unicidad de slug contra soft-deleted.
- Verificación de `sync()` sin duplicados en pivote `agent_zone`.

---

## 4. Tests confirmados (mapeo QA-018 → QA-025)

| QA | Test | Archivo |
| :--- | :--- | :--- |
| QA-018 | `test_zone_generates_unique_slugs_including_soft_deleted_rows` | `ZoneCoreTest` |
| QA-019 | `test_admin_can_edit_zone_status_and_polygon_but_cannot_delete_or_restore` | `ZoneResourceTest` (Filament) |
| QA-020 | `test_owner_and_admin_can_access_zone_resource_pages_while_agente_cannot` / `test_owner_and_admin_can_manage_zones_while_agente_cannot` | `ZoneResourceTest`, `ZonePolicyTest` |
| QA-021 | `test_zone_calculates_center_point_from_polygon_on_create_and_update` | `ZoneGeospatialTest` |
| QA-022 | `test_scope_finds_zones_containing_a_point_with_index_friendly_bounding_box` | `ZoneGeospatialTest` |
| QA-023 | `test_owner_can_reassign_agent_between_zones_without_duplicate_assignments`, `test_only_active_agente_users_are_assignable_from_relation_manager_options` | `AgentZoneAssignmentTest` |
| QA-024 | `test_owner_can_soft_delete_and_restore_zone_from_resource_table` | `ZoneResourceTest` (Filament) |
| QA-025 | Suite completa (51 tests / 332 aserciones, validación final 2026-06-18) | Todo `tests/` |

Cobertura adicional no exigida explícitamente por QA-018→025 pero presente: rechazo de SRID incorrecto, rechazo de anillo no cerrado, rechazo de owner/admin como agente asignado, filtros de tabla por municipio/status.

26 métodos de test cubren específicamente la Épica 3, repartidos en `ZoneCoreTest`, `ZoneGeospatialTest`, `ZonePolicyTest` (Unit/Feature en `tests/Feature/Zones`) y `ZoneResourceTest`, `AgentZoneAssignmentTest` (`tests/Feature/Filament`).

---

## 5. Integración con Épicas previas

| Contrato consumido | Origen | Verificado |
| :--- | :--- | :--- |
| PostgreSQL + extensión PostGIS habilitada | RFC-003 / Épica 1 | ✅ — `CREATE EXTENSION postgis` confirmada en validación final, PostGIS 3.6 disponible |
| Panel Filament `/admin`, `FilamentUser` | RFC-004 / Épica 1 | ✅ — `ZoneResource` registrado en el panel existente, sin tocar el panel base |
| `zones.manage` permiso sembrado en `PermissionSeeder` | RFC-014 / Épica 2 | ✅ — consumido directamente por `ZonePolicy`, sin modificar el seeder |
| `User::hasRole()`, rol `agente`, `UserStatus` activo | RFC-012 / Épica 2 | ✅ — usado en `AgentsRelationManager` para filtrar candidatos asignables |
| `UserPolicy` como patrón de referencia de autorización | RFC-012 / Épica 2 | ✅ — `ZonePolicy` replica el mismo patrón backend-first |

No se modificó ningún archivo de las Épicas 1 o 2; las únicas adiciones al modelo `User` (relación `zones()`, scope `active()`) son aditivas.

---

## 6. Seguimiento de fase — regresión Épicas 1 y 2

Confirmado sin regresión por dos fuentes independientes:

1. Auditoría de implementación (Gemini, `docs/audits/epica-3-auditoria-implementacion.md`): "Ninguna" regresión detectada.
2. Validación final registrada en engram (`epica-3-final-validation`, 2026-06-18): suite completa de 51 tests / 332 aserciones en verde, que incluye los suites de Épicas 1 y 2 (51 es el conteo total de métodos `test_*` en todo el repo, no solo de zonas).

---

## 7. Deuda técnica aceptada

| # | Ítem | Justificación |
| :--- | :--- | :--- |
| 1 | Captura de polígono vía `Textarea` + GeoJSON pegado a mano, en vez de mapa Leaflet interactivo | Decisión de alcance MVP documentada en código y en auditoría; la validación de backend (`ST_IsValid`, SRID, anillo cerrado) compensa el riesgo de error de copiado |
| 2 | Sin paquete espacial externo (`matanyadaev/laravel-eloquent-spatial`); hidratación manual vía `ZoneGeometry` + SQL crudo | Decisión de implementación aprobada por auditoría — evita una dependencia de terceros a cambio de mantener la gramática PostGIS centralizada en un servicio. Revisitar si se agregan más de 2 modelos espaciales (recomendación de la auditoría) |
| 3 | Sin análisis estático (PHPStan) configurado como gate de CI para este módulo | Reportado en la validación final como binario ausente localmente; no bloqueante, pendiente de decisión del equipo sobre exigirlo |

---

## 8. Pendientes fuera de alcance

- **Mapa interactivo Leaflet (Lote D original):** próxima iteración de UX, no bloquea esta épica.
- **Validación de no-solapamiento entre zonas (D-3 / R-5):** requiere decisión de negocio (error vs advertencia). Diferido a Épica 4/5.
- **Módulo de Inmuebles / `Property`:** la relación `Zone::properties()` queda como contrato diferido (`whereRaw('1=0')`), a activar cuando exista la tabla. — **Épica 4**.
- **Distribución de leads entre agentes según zona asignada:** no existe aún ningún consumidor de `agent_zone` más allá de la asignación administrativa. — **Épica 5**.
- **Acceso de agentes a su propio panel de zonas (D-1):** depende del diseño del panel de agente. — **Épica 5**.
- **Tiles de mapa de pago vs. OSM gratuito (D-4):** decisión de producto para el frontend público. — **Épica 6**.
- **Límite de cardinalidad agente↔zona (D-2):** sin regla de negocio definida; no bloquea el CRUD actual.

---

## 9. Riesgos residuales

| # | Riesgo | Severidad | Mitigación / Estado |
| :--- | :--- | :--- | :--- |
| 1 | **Resuelto.** `phpunit.xml` forzaba `DB_PASSWORD=""` como variable de entorno, lo cual pisa cualquier valor de `.env.testing` (Laravel no sobreescribe variables de entorno ya definidas por el proceso). El usuario agregó `.env.testing` con la contraseña correcta (`hobbit`), pero la suite seguía fallando con `fe_sendauth: no password supplied` hasta remover la línea `<env name="DB_PASSWORD" value=""/>` de `phpunit.xml`. Tras el fix: `php artisan test` → 51/51 verde, 332 aserciones; `./vendor/bin/pint --test` → sin errores | Resuelta — cambio de 1 línea en `phpunit.xml`, pendiente de commit | Confirmar que el runner de CI no dependía de ese `DB_PASSWORD=""` explícito (p. ej. Postgres con `trust`/`peer` auth sin contraseña). Si el CI usa auth por contraseña, este fix lo alinea; si usa `trust`, sigue funcionando porque ausencia de la variable deja que Postgres no la exija |
| 2 | `npm run build` falló en la validación final por `vite` no disponible en `node_modules`/PATH | Baja — no afecta el backend de esta épica (Filament es server-rendered) | Ejecutar `npm install` / `npm ci` antes de dar por cerrado el frontend build, documentado como next step en la validación final |
| 3 | Sin constraint de no-solapamiento entre zonas | Alta a mediano plazo (R-5) | Diferido a Épica 4/5 por decisión de negocio pendiente (D-3) |
| 4 | Dependencia de internet para drawing UI si se reactiva Leaflet a futuro (R-3) | Baja en este alcance (Leaflet no está activo en el MVP entregado) | Reevaluar al implementar el mapa interactivo |

---

## 10. Recomendación final

**Aprobar para merge.** No hay hallazgos de código pendientes: las dos auditorías de Gemini y la validación final coinciden en que la implementación cierra correctamente los contratos de RFC-015 a RFC-018, corrige los hallazgos críticos de la auditoría de diseño (validación topológica, desincronización del centroide en memoria) y no introduce regresión en Épicas 1 y 2. Las desviaciones respecto al diseño original (sin Leaflet, sin paquete espacial externo) están documentadas, justificadas y clasificadas como decisiones de alcance/implementación aceptables, no como defectos. La suite completa (51 tests / 332 aserciones) y Pint fueron re-verificados en esta sesión contra PostgreSQL+PostGIS real, confirmando los resultados de la validación final del 18/06.

---

## 11. Checklist para Kristian / Edgar

- [x] Confirmar `DB_PASSWORD` correcto contra el PostgreSQL del entorno y re-ejecutar `php artisan test` completo — **51/51 tests, 332 aserciones en verde** (esta sesión, 2026-06-18)
- [x] Ejecutar `./vendor/bin/pint --test` → 0 errores (esta sesión)
- [ ] Revisar diff completo de la rama (`git diff develop...feature/epica-3-zonas-comerciales`)
- [ ] Commit del fix de `phpunit.xml` (remueve `DB_PASSWORD=""` hardcodeado que pisaba `.env.testing`) y de este informe de cierre
- [ ] Push de la rama (`git push -u origin feature/epica-3-zonas-comerciales`)
- [ ] Abrir PR contra `develop`
- [ ] Revisión de QA (Sebastián) contra QA-018 → QA-025
- [ ] Merge a `develop`
- [ ] Tag `v0.3.0-zonas-comerciales`

---

*Documento generado el 18 de Junio, 2026*
*Rama de origen: `feature/epica-3-zonas-comerciales` → destino `develop`*
