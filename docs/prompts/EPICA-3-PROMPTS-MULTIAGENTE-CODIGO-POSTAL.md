# PRD: Épica 3 — Zonas por Código Postal Automáticas

**Proyecto:** New Hauz — Plataforma Inmobiliaria
**Deriva de:** Épica 3 — Corrección: Catálogo Geográfico y Polígonos Automáticos
**Base Técnica:** Laravel 13.x · PHP 8.3 · PostgreSQL + PostGIS · Filament v3 · Livewire 3 · `clickbar/laravel-magellan`
**Rama de trabajo:** `feature/epica-3-geografia-poligonos`
**Estado:** Planificado (SDD completo) — listo para implementación
**Modo de implementación:** Multiagente (TDD estricto sobre pgsql `inmo_test`)

---

## 1. Objetivo del cambio

La Épica 3 dejó el flujo de zonas con el polígono **dibujado a mano** por el usuario sobre Google Maps (Drawing Manager). Este cambio profesionaliza ese flujo y lo automatiza:

1. El usuario ya **no dibuja** la zona. El sistema obtiene el polígono del **código postal** desde un catálogo precargado y lo pinta en el mapa.
2. El catálogo de polígonos de CP de México se importa una sola vez a una tabla propia (`postal_code_areas`), porque **Google Maps NO provee polígonos de códigos postales** (solo punto + bounding box rectangular).
3. El país queda fijado a **México por lógica de fondo** (sin campo visible), derivado de `state → country`.
4. El **Código Postal** se habilita solo cuando ya hay Estado + Municipio seleccionados.
5. El dibujo manual nativo (ya migrado fuera del removido Drawing Manager) queda como **fallback** de edición para CP sin cobertura.

> **Justificación de negocio:** las zonas son **áreas de influencia comercial de agentes**, no perímetros catastrales. La precisión exacta no es crítica; un polígono aproximado por CP es suficiente.

---

## 2. Alcance

### 2.1 Dentro de alcance
- Tabla catálogo `postal_code_areas` (geometría `MultiPolygon`) + modelo `PostalCodeArea`.
- Comando de importación `geo:import-postal-codes` desde dataset GeoJSON de CP de México.
- Cambios en el formulario de Zona: CP deshabilitado hasta Estado+Municipio; aserción de México server-side.
- Botón **"Obtener"** que carga el polígono del CP y lo pinta (método Livewire + trait).
- Conversión server-side `MultiPolygon → Polygon` (anillo más grande).

### 2.2 Fuera de alcance
- Grupos nombrados de CP / "Zona Centro" como entidad. (Una zona = 1 CP; la agrupación emerge de asignar varios CP a un agente vía el pivote `agent_zone` ya existente.)
- Cambios en la UI de asignación agente↔zona (ya existe `AgentsRelationManager`).
- Reemplazo del mapa base de Google Maps.

---

## 3. Decisiones de arquitectura (CERRADAS)

| Tema | Decisión |
| :--- | :--- |
| Modelo de zona | Zona = **1 código postal = 1 polígono**. Agente (User) ↔ muchas Zonas vía pivote `agent_zone` existente. |
| País | **Lógica de fondo**, sin campo en el form. Se afirma server-side vía `state → country` (no hay `country_id` en `zones`). |
| Origen del polígono | Catálogo propio `postal_code_areas`, importado. **No** se obtiene de Google en runtime. |
| Dataset | `open-mexico/mexico-geojson` (MIT, ya en GeoJSON, 32 archivos por estado). Elegido por facilidad; precisión no crítica. |
| Geometría del catálogo | `geometry(MultiPolygon,4326)` — algunos CP son áreas disjuntas (rutas SEPOMEX). |
| Geometría de la zona | `geometry(Polygon,4326)` (ya existe). Conversión `MultiPolygon → Polygon` por **anillo más grande** (`ST_Dump` + `ORDER BY ST_Area DESC LIMIT 1`), server-side. |
| Flujo "Obtener" | Método Livewire `fetchPostalCodePolygon` en un **trait compartido** entre `CreateZone` y `EditZone` (espejo del patrón `resolveZoneMapAddressLabel`). |
| Render en mapa | Reusa `renderExisting()` del componente (solo maneja `type:'Polygon'` → por eso la conversión es server-side). |
| Importación | Comando idempotente (`upsert` por `postal_code`). Linkage `municipality_id` best-effort vía `municipalities.clave`. |
| Persistencia | GeoJSON en capa UI; PostGIS en BD. Conversión en un único punto. `center_point` se deriva con `ST_Centroid` (hook `saved()` existente). |

---

## 4. Modelo de datos

### 4.1 Tabla nueva: `postal_code_areas`

```
postal_code_areas
├── id              bigserial PK
├── postal_code     varchar(5)  NOT NULL  UNIQUE
├── municipality_id bigint FK → municipalities.id  NULL  nullOnDelete
├── state_id        bigint FK → states.id          NULL  nullOnDelete
├── polygon         geometry(MultiPolygon,4326)     NOT NULL
└── timestamps
   unique (postal_code) · GIST (polygon) · idx (municipality_id)
```

### 4.2 Relación con el modelo existente

```
PostalCodeArea ─belongsTo─► Municipality   (best-effort, nullable)
PostalCodeArea ─belongsTo─► State          (derivado, nullable)
Zone           ─belongsTo─► State / Municipality   (sin cambios)
Zone.polygon   ◄── largest-ring de PostalCodeArea.polygon (al pulsar "Obtener")
```

---

## 5. Flujo funcional del formulario de Zona

```
1. Select "Estado" (state_id)        ──live──► habilita y carga "Municipio"
2. Select "Municipio"                 ──live──► habilita "Código Postal"
3. Input "Código Postal" (5 dígitos)  (disabled hasta state_id + municipality_id)
4. Botón "Obtener"
        │
        ▼  $wire.call('fetchPostalCodePolygon', cp)
5. Backend: PostalCodeArea.largestRingGeoJson(cp)
        │   ST_Dump(polygon) → ORDER BY ST_Area DESC LIMIT 1 → ST_AsGeoJSON
        ├── con cobertura  → devuelve Polygon GeoJSON → renderExisting() lo pinta (editable)
        └── sin cobertura  → Notification warning → el usuario puede dibujar manual (fallback)
6. Al guardar:
   - assertStateBelongsToMexico(state_id)   (LogicException si el país ≠ MX)
   - GeoJSON Polygon → zones.polygon (regla ValidZonePolygonGeoJson sigue aplicando)
   - center_point = ST_Centroid(polygon)    (hook saved() existente)
```

---

## 6. Reglas críticas y criterios de aceptación

1. **CP deshabilitado** mientras falte Estado o Municipio.
2. **México obligatorio**: guardar una zona cuyo estado pertenezca a un país con `iso2 ≠ 'MX'` lanza `LogicException`.
3. **Cobertura ausente**: si el CP no existe en el catálogo, el botón notifica y NO rompe; el dibujo manual sigue disponible.
4. **El polígono guardado en `zones.polygon` siempre es un `Polygon` válido** (nunca MultiPolygon).
5. **Importación idempotente**: re-ejecutar el comando no duplica filas (upsert por `postal_code`).
6. **TDD estricto**: cada unidad testeable lleva su test RED antes de la implementación. La capa JS/Blade se verifica manualmente (no hay runner JS).

---

## 7. Plan de entrega

Estimado ~600–750 líneas → **supera el presupuesto de 400**. Se entrega en **2 PRs encadenados**:

| PR | Contenido | Base |
| :-- | :-- | :-- |
| **PR 1** | Catálogo `postal_code_areas` + modelo + comando de importación + tests | `feature/epica-3-geografia-poligonos` |
| **PR 2** | Form (CP disabled + aserción MX) + trait Livewire + botón "Obtener" en blade + tests | rama de PR 1 |

> Estrategia de cadena (`stacked-to-main` vs `feature-branch-chain`): **a decidir por Edgar** antes de empezar.

---

## 8. Prompts Multiagente

Prompts autocontenidos, listos para lanzar un agente por cada uno. Cada agente trabaja con TDD estricto contra pgsql `inmo_test`, ejecuta `./vendor/bin/pint` antes de cerrar, y NO inventa APIs (lee los archivos reales referenciados). Respetar el orden de dependencias del orquestador (§8.0).

> **Convención de todos los prompts:** Stack Laravel 13 + Filament + PostgreSQL/PostGIS (Magellan). Branch `feature/epica-3-geografia-poligonos`. Texto y comentarios de negocio en español; identificadores, nombres de tabla/columna y código en inglés. Tests en `pgsql inmo_test`. Referencia completa: `openspec/changes/zonas-por-codigo-postal/{spec,design,tasks}.md`.

### 8.0 — Agente Orquestador (coordina; no escribe código)

```
Coordina la implementación de "Zonas por Código Postal" en el proyecto newhauz.
NO escribas código: lanzá y secuenciá a los agentes especialistas y verificá sus entregas.

Orden y dependencias OBLIGATORIO:
1. Agente A (Inspecciones bloqueantes) PRIMERO. Sin sus dos resultados, los agentes
   C y E quedan bloqueados.
2. PR 1 — en paralelo se pueden ESCRIBIR los tests de B y C; implementación: B antes que C
   solo si comparten fixtures; el comando (C) necesita el resultado de A.1.
3. Merge de PR 1 antes de arrancar PR 2.
4. PR 2 — tras PR 1: D, E y F pueden empezar en paralelo (D=form, E=trait, F=blade).
5. Agente G (regresión + Pint) al final de cada PR antes del commit.

Criterio de aceptación global: §6 del PRD. Forecast: 2 PRs encadenados (§7).
Verificá que cada agente entregue sus tests en verde antes de aprobar su parte.
```

### 8.1 — Agente A: Inspecciones bloqueantes (PASO 0)

```
Tarea de investigación PREVIA a cualquier código. Proyecto newhauz.

A.1 — Confirmar el nombre de la propiedad del Código Postal en el dataset GeoJSON.
Abrí un archivo de db_estados/Pais-Estado-Municipio/ (untracked en la raíz) e inspeccioná
las claves de properties del primer feature:
  head -c 2000 "db_estados/Pais-Estado-Municipio/<estado>.geojson" \
    | python3 -c "import sys,json; d=json.load(sys.stdin); print(list(d['features'][0]['properties'].keys()))"
Candidatos esperados: codigo_postal | cp | d_codigo. Reportá el nombre EXACTO: será la
constante CP_PROPERTY del comando importer.

A.2 — Confirmar la columna que identifica a México en countries.
Leé app/Models/Country.php y la migración que crea la tabla countries. Confirmá si la
columna es iso2 (valor 'MX'), code o name. Reportá el nombre exacto: lo usa
assertStateBelongsToMexico.

Entregá ambos nombres confirmados. No escribas código de implementación.
```

### 8.2 — Agente B: Catálogo `postal_code_areas` + modelo `PostalCodeArea` (PR 1)

```
Implementá el catálogo de polígonos de CP. TDD estricto: tests RED antes de implementar.
Proyecto newhauz, branch feature/epica-3-geografia-poligonos. Ref: design.md §1-2, spec REQ-1.

1. [RED] Creá tests/Feature/PostalCodeAreaTest.php con:
   - test_migration_creates_table_with_correct_schema (tabla existe, postal_code VARCHAR(5) NOT NULL,
     índice GIST sobre polygon, municipality_id nullable FK).
   - test_unique_constraint_on_postal_code (duplicado → QueryException).
   - test_polygon_as_geo_json_returns_multipolygon_type.
   - test_unknown_postal_code_returns_null.
   - test_largest_ring_geo_json_returns_biggest_polygon (MultiPolygon de 2 componentes → Polygon mayor).
   - test_largest_ring_geo_json_output_passes_zone_geometry_validation
     (resultado pasa ZoneGeometry::polygonEwktFromGeoJson sin excepción).
   Corré php artisan test --filter=PostalCodeAreaTest → debe fallar.

2. Creá la migración database/migrations/2026_06_23_000000_create_postal_code_areas_table.php:
   id(); postal_code VARCHAR(5) NOT NULL + unique('postal_code'); municipality_id y state_id FK
   nullable nullOnDelete(); timestamps(). Guard pgsql: CREATE EXTENSION IF NOT EXISTS postgis.
   Columna geométrica vía DB::statement:
     ALTER TABLE postal_code_areas ADD COLUMN polygon geometry(MultiPolygon,4326) NOT NULL
   Índice: CREATE INDEX postal_code_areas_polygon_gist ON postal_code_areas USING GIST(polygon).
   down(): Schema::dropIfExists('postal_code_areas'). Espejá el estilo de la migración de zones.

3. Creá app/Models/PostalCodeArea.php:
   $fillable = ['postal_code','municipality_id','state_id','polygon'].
   Cast polygon → Clickbar\Magellan\Data\Geometries\MultiPolygon.
   municipality(): BelongsTo, state(): BelongsTo.
   polygonAsGeoJson(): string  → raw select ST_AsGeoJSON(polygon) (espejá Zone::polygonAsGeoJson).
   static largestRingGeoJson(string $cp): ?string con:
     SELECT ST_AsGeoJSON(geom) AS geojson
     FROM (SELECT (ST_Dump(polygon)).geom AS geom
           FROM postal_code_areas WHERE postal_code = ?) parts
     ORDER BY ST_Area(geom) DESC LIMIT 1

4. [GREEN] php artisan test --filter=PostalCodeAreaTest → todo verde. Corré ./vendor/bin/pint.
```

### 8.3 — Agente C: Comando `geo:import-postal-codes` (PR 1)

```
Implementá el comando de importación del dataset de CP. REQUIERE el resultado de Agente A.1
(nombre de la propiedad CP). TDD estricto. Proyecto newhauz. Ref: design.md §3, spec REQ-2.

1. [RED] Creá tests/Feature/ImportPostalCodesCommandTest.php con:
   - test_imports_from_geojson_fixture (3 features → N filas, polígono no nulo, exit 0).
   - test_import_is_idempotent (correr 2x → mismo conteo, sin excepción).
   - test_linkage_sets_municipality_id_when_clave_matches (municipalities.clave='06600' + feature CP='06600'
     → municipality_id no nulo).
   - test_municipality_id_is_null_when_no_clave_match.
   - test_polygon_geometry_type_stored_as_multipolygon.
   Creá fixture tests/Fixtures/postal_codes/sample.geojson con 3 features: un Polygon simple,
   un MultiPolygon (2 componentes) y dos features con el MISMO CP (se funden en un MultiPolygon).
   Corré php artisan test --filter=ImportPostalCodesCommandTest → debe fallar.

2. Creá app/Console/Commands/ImportPostalCodesCommand.php:
   Signature: geo:import-postal-codes {--state=} {--path=}.
   Constante CP_PROPERTY = <nombre confirmado por Agente A.1>.
   normalizeCp(string $cp): ?string → str_pad($cp,5,'0',STR_PAD_LEFT); valida ^\d{5}$.
   Lee .geojson del directorio (filtra por --state= si se pasa).
   Agrupa features por CP normalizado y construye MultiPolygon:
     ST_Multi(ST_SetSRID(ST_Collect(ST_GeomFromGeoJSON(?)), 4326))
   Upsert idempotente: DB::table('postal_code_areas')->upsert(..., uniqueBy: ['postal_code']).
   Linkage best-effort: Municipality::where('clave',$cp)->value('id') → municipality_id (null si no hay match);
   state_id derivado del municipio. Insert en batches de 100.

3. [GREEN] php artisan test --filter=ImportPostalCodesCommandTest → verde. ./vendor/bin/pint.

4. Validación manual (no automatizada): php artisan geo:import-postal-codes --path="db_estados/Pais-Estado-Municipio" --state=<uno>
   Verificá conteo razonable y ausencia de excepciones.
```

### 8.4 — Agente D: Form — regla `disabled` + aserción México (PR 2)

```
Depende de PR 1 mergeado. TDD estricto. Proyecto newhauz. Ref: design.md §4, spec REQ-3.

1. [RED] Creá/extendé tests/Feature/ZonePostalCodeFetchTest.php con:
   - test_postal_code_field_is_disabled_when_municipality_is_blank (assertFormFieldIsDisabled('postal_code')).
   - test_postal_code_field_is_enabled_when_municipality_has_value.
   - test_create_zone_throws_if_state_not_mexico (state con country iso2≠'MX' → LogicException).
   Corré → deben fallar.

2. En app/Filament/Resources/ZoneResource.php (campo postal_code, ~líneas 80-85) cambiá la regla disabled a:
     ->disabled(fn (Get $get) => blank($get('state_id')) || blank($get('municipality_id')))
   Ajustá helperText si corresponde.

3. [GREEN] php artisan test --filter=ZonePostalCodeFetchTest (casos de disabled). ./vendor/bin/pint.

Nota: la aserción de México la implementa el Agente E en el trait; coordiná para no duplicar.
```

### 8.5 — Agente E: Trait `ResolvesZonePostalCodePolygon` + `fetchPostalCodePolygon` (PR 2)

```
Depende de PR 1 mergeado y del resultado de Agente A.2 (columna iso2). TDD estricto.
Proyecto newhauz. Ref: design.md §5, spec REQ-4.

1. [RED] Agregá a tests/Feature/ZonePostalCodeFetchTest.php:
   - test_fetch_returns_polygon_geojson_when_cp_has_coverage (seed PostalCodeArea CP='06600' multi-componente;
     Livewire::test(CreateZone::class)->call('fetchPostalCodePolygon','06600') → type==='Polygon').
   - test_fetch_returns_null_when_cp_has_no_coverage.
   - test_fetch_returns_null_when_cp_format_invalid (CP='123').
   - test_edit_zone_fetch_also_works (EditZone::class).
   Corré → deben fallar.

2. Creá app/Filament/Resources/ZoneResource/Concerns/ResolvesZonePostalCodePolygon.php:
   fetchPostalCodePolygon(?string $cp): ?string
     - si !preg_match('/^\d{5}$/',(string)$cp) → return null.
     - PostalCodeArea::largestRingGeoJson($cp); si null → Notification::make()->warning()
       ->title('Sin cobertura para este código postal')->send() y return null.
     - si no, return el GeoJSON Polygon.
   assertStateBelongsToMexico(int $stateId): void
     - State::with('country')->findOrFail($stateId); assert $state->country-><col confirmada A.2> === 'MX',
       si no LogicException.

3. Modificá app/Filament/Resources/ZoneResource/Pages/CreateZone.php: use ResolvesZonePostalCodePolygon;
   en mutateFormDataBeforeCreate llamá $this->assertStateBelongsToMexico($data['state_id']).
   Modificá EditZone.php igual en mutateFormDataBeforeSave.

4. [GREEN] php artisan test --filter=ZonePostalCodeFetchTest → todo verde. ./vendor/bin/pint.
```

### 8.6 — Agente F: Blade botón "Obtener" (PR 2, verificación manual)

```
Depende de Agente E (método fetchPostalCodePolygon disponible). Sin runner JS → verificación manual.
Proyecto newhauz. Ref: design.md §6, spec REQ-4 (escenarios MANUAL).

ARCHIVO REAL: resources/views/filament/forms/components/map-polygon-input.blade.php
(NO el path que aparece en tasks.md; usar este, verificado).

En el objeto Alpine mapPolygon, dentro de setupDrawing():
  - Agregá fetchButton a las props del objeto.
  - Creá el botón "Obtener" con las MISMAS clases CSS que controlButton/clearButton y agregalo al container:
      fetchButton.addEventListener('click', () => this.fetchCpPolygon());
  - Agregá método async fetchCpPolygon():
      const cpValue = this.$wire.get(cfg.cpStatePath); // ajustar al cableado real de cpField
      if (!cpValue) return;
      const result = await this.$wire.call('fetchPostalCodePolygon', cpValue);
      if (!result) return;            // backend ya notificó si no hay cobertura
      this.value = result;
      this.$wire.set(cfg.statePath, result);
      this.renderExisting();
  - renderExisting() SIN cambios (ya maneja type:'Polygon').
  - El dibujo manual (setupDrawing actual) permanece como fallback.

Verificación manual (documentá resultados):
  1. Estado→Municipio→CP con cobertura → "Obtener" → mapa pinta polígono editable.
  2. CP sin cobertura → "Obtener" → notificación visible, sin polígono, dibujo manual disponible.
```

### 8.7 — Agente G: Regresión + estilo (cierre de cada PR)

```
Cierre de PR. Proyecto newhauz.
1. Corré la suite completa: php artisan test → todo en verde, sin regresiones.
2. Corré ./vendor/bin/pint → sin diffs pendientes.
3. Reportá cobertura de los nuevos tests y cualquier test existente afectado.
No hagas commit ni push salvo instrucción explícita del orquestador.
```

---

## 9. Archivos afectados

| Acción | Archivo |
| :-- | :-- |
| NUEVO | `database/migrations/2026_06_23_000000_create_postal_code_areas_table.php` |
| NUEVO | `app/Models/PostalCodeArea.php` |
| NUEVO | `app/Console/Commands/ImportPostalCodesCommand.php` |
| NUEVO | `app/Filament/Resources/ZoneResource/Concerns/ResolvesZonePostalCodePolygon.php` |
| NUEVO | `tests/Feature/PostalCodeAreaTest.php` |
| NUEVO | `tests/Feature/ImportPostalCodesCommandTest.php` |
| NUEVO | `tests/Feature/ZonePostalCodeFetchTest.php` |
| NUEVO | `tests/Fixtures/postal_codes/sample.geojson` |
| MODIFICADO | `app/Filament/Resources/ZoneResource.php` |
| MODIFICADO | `app/Filament/Resources/ZoneResource/Pages/CreateZone.php` |
| MODIFICADO | `app/Filament/Resources/ZoneResource/Pages/EditZone.php` |
| MODIFICADO | `resources/views/filament/forms/components/map-polygon-input.blade.php` |
| SIN CAMBIOS | `app/Models/Zone.php`, `app/Filament/Forms/Components/MapPolygonInput.php`, `app/Rules/ValidZonePolygonGeoJson.php`, `app/Services/Zones/ZoneGeometry.php` |

---

## 10. Riesgos

| Riesgo | Mitigación |
| :-- | :-- |
| Nombre de propiedad CP en el dataset desconocido | **Bloqueante** — Agente A.1 lo confirma antes de codear el importer. |
| `municipalities.clave` nullable / no único (guarda un CP representativo) | Linkage best-effort; `municipality_id` nullable; el fetch consulta por `postal_code`, no por FK. |
| CP disjuntos pierden componentes menores al pasar a Polygon | Aceptable para área de influencia; se conserva el anillo mayor. |
| `renderExisting()` solo pinta `type:'Polygon'` | Conversión MultiPolygon→Polygon server-side antes de enviar al cliente. |
| Cobertura rural incompleta del dataset | Notificación + dibujo manual como fallback. |

---

**Generado a partir del ciclo SDD:** `openspec/changes/zonas-por-codigo-postal/` (proposal · spec · design · tasks)
**Fecha:** 22 de Junio, 2026
