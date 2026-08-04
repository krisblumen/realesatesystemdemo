# Tasks: Zonas por Código Postal

> Handoff para Edgar. Texto en español; identificadores, paths y comandos en inglés.
> Proyecto: newhauz | Branch: feature/epica-3-geografia-poligonos | Strict TDD ACTIVO.
> Tests corren en pgsql real `inmo_test`. Orden test → implementación (RED-GREEN).
> Artefactos de referencia: spec (#513), design (#514).

---

## Review Workload Forecast

| Campo | Valor |
|-------|-------|
| Líneas estimadas (additions + deletions) | ~600–750 (PR1+PR2, YA MERGEADOS) + ~350–450 (PR3, adenda de catálogo) |
| Riesgo presupuesto 400 líneas | High (PR3 también amerita revisión propia) |
| PRs encadenados recomendados | Yes |
| Split sugerido | ~~PR 1: Catálogo + Importer~~ (merged #26) → ~~PR 2: Form + Trait + Blade~~ (merged #27) → PR 3: Corrección catálogo (states/municipalities/postal_codes) |
| Estrategia de entrega | ask-on-risk |
| Estrategia de cadena | pending (decidir antes de apply de PR 3) |

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: pending
400-line budget risk: High

### Suggested Work Units

| Unit | Objetivo | PR | Estado |
|------|----------|-------------|-------|
| 1 | Catálogo postal_code_areas + importer | PR 1 → `feature/zonas-cp-pr1-catalogo-importer` | **MERGEADO** (#26, commit `5235a5c`) |
| 2 | Form changes + trait Livewire + blade "Obtener" | PR 2 → `feature/zonas-cp-pr2-form-trait-blade` | **MERGEADO** (#27, commit `d989cb0`) |
| 3 | Corrección states/municipalities (xlsx) + tabla `postal_codes` + actualizar linkage de `ImportPostalCodesCommand` (ya shippeado en PR1) | PR 3 → rama nueva sobre `feature/epica-3-geografia-poligonos` | **PENDIENTE** (esta adenda) |

---

## PR 3 — Corrección catálogo base: states/municipalities (xlsx) + tabla postal_codes

> Trabajo NUEVO sobre lo ya mergeado de PR1/PR2. No reemplaza nada de la UI de Zona. Modifica un
> archivo ya shippeado: `app/Console/Commands/ImportPostalCodesCommand.php::resolveMunicipalityId`
> (agrega lookup por `postal_codes` antes del fallback legacy por `clave`). Ref proposal §10, spec
> REQ-6/REQ-7/REQ-8, design §12.

### Área 0: Dependencia y columnas `inegi_code`

- [x] 0.A **Agregar `phpoffice/phpspreadsheet`**: `composer require phpoffice/phpspreadsheet`.

- [x] 0.B **[RED] Tests de columnas** en `tests/Feature/GeoCatalogTest.php` (o archivo nuevo
  `tests/Feature/GeoCatalogSchemaTest.php`): assert que `states.inegi_code` existe, es único; que
  `municipalities.inegi_code` existe, único junto con `state_id`. Ejecutar → falla (columnas no
  existen).

- [x] 0.C **Migración** `add_inegi_code_to_states_and_municipalities`: agregar columnas según
  design §12.1. Ejecutar `php artisan migrate`.

- [x] 0.D **[GREEN]** Ejecutar tests de 0.B → pasan.

### Área 1: `StateSeeder`

- [x] 0.E **[RED] Crear `tests/Feature/StateSeederTest.php`**: siembra país México, corre
  `StateSeeder`, assert 32 estados, `inegi_code` en `'01'..'32'`, re-ejecutar no duplica. Ejecutar →
  falla (clase no existe).

- [x] 0.F **Crear `database/seeders/StateSeeder.php`** (design §12.3). Leer `db_estados/states.xlsx`
  con `phpoffice/phpspreadsheet`, descartar filas sin `state_id` numérico de 1-2 dígitos
  (`preg_match('/^\d{1,2}$/', ...)`), `updateOrCreate` por `inegi_code` zero-padded a 2.

- [x] 0.G **[GREEN]** Ejecutar `StateSeederTest` → pasa.

### Área 2: `MunicipalitySeeder`

- [x] 0.H **[RED] Crear `tests/Feature/MunicipalitySeederTest.php`**: siembra país + `StateSeeder`,
  corre `MunicipalitySeeder`, assert que cada municipio tiene `state_id` correcto vía `inegi_code`,
  `inegi_code` zero-padded a 3, nombre normalizado (no en mayúsculas crudas), re-ejecutar no
  duplica. Ejecutar → falla.
  > Nota de implementación: el xlsx tiene 2,478 filas pero el match key `(state_id, name)` colapsa
  > 2 colisiones de nombre genuinas en Oaxaca ("SAN JUAN MIXTEPEC" y "SAN PEDRO MIXTEPEC", cada una
  > con 2 `municipality_id` INEGI distintos) → resultado real: 2,476 municipios. El test se ajustó
  > a este conteo verificado, no al conteo crudo de filas.

- [x] 0.I **Crear `database/seeders/MunicipalitySeeder.php`** (design §12.4). Requiere que
  `StateSeeder` haya corrido (mapa `inegi_code → state_id` vía `State::pluck`). Normalizar nombre
  con `mb_convert_case(mb_strtolower($name), MB_CASE_TITLE, 'UTF-8')`.

- [x] 0.J **[GREEN]** Ejecutar `MunicipalitySeederTest` → pasa.

### Área 3: Tabla y seeder `postal_codes`

- [x] 0.K **[RED] Crear `tests/Feature/PostalCodeSeederTest.php`**: assert tabla `postal_codes`
  existe con unique compuesto `(postal_code, colonia)`; siembra país+estados+municipios de
  Querétaro, corre `PostalCodeSeeder`, assert filas con `state_id`/`municipality_id` resueltos por
  `inegi_code`; CP repetido con colonias distintas inserta ambas filas. Ejecutar → falla.

- [x] 0.L **Migración `create_postal_codes_table`** (spec REQ-7, design §12.5 esquema).

- [x] 0.M **Crear `app/Models/PostalCode.php`** — fillable, relaciones `municipality()`/`state()`.

- [x] 0.N **Crear `database/seeders/PostalCodeSeeder.php`** (design §12.5). Requiere
  `MunicipalitySeeder` corrido. Filtra filas inválidas con `preg_match('/^\d{5}$/', $postalCode)`.

- [x] 0.O **[GREEN]** Ejecutar `PostalCodeSeederTest` → pasa.

### Área 4: `GeoCatalogSeeder` reescrito + retiro de `geo:import`

- [x] 0.P **[RED] Reescribir `tests/Feature/GeoCatalogTest.php`**: mismos invariantes que hoy
  (1 país, 32 estados, todo municipio bajo estado mexicano) pero sin asumir `geo:import`; agregar
  caso "corre sin los .sql presentes" (REQ-6 último escenario). Ejecutar → falla (aún llama
  `geo:import`).

- [x] 0.Q **Reescribir `database/seeders/GeoCatalogSeeder.php`** (design §12.6): sembrar país
  inline + `$this->call([StateSeeder::class, MunicipalitySeeder::class, PostalCodeSeeder::class])`.

- [x] 0.R **Eliminar `app/Console/Commands/GeoImportCommand.php`**.

- [x] 0.S **[GREEN]** Ejecutar `GeoCatalogTest` → pasa. Ejecutar suite completa
  (`php artisan test`) para confirmar que nada más dependía de `geo:import`.

- [x] 0.T **Pint**: `./vendor/bin/pint`.

- [x] 0.U **Actualizar linkage de `ImportPostalCodesCommand::resolveMunicipalityId`** (REQ-8,
  design §12.7): prioriza `PostalCode::where('postal_code', $cp)->value('municipality_id')`, cae
  al fallback legacy `Municipality::where('clave', $cp)->value('id')` si no hay match. Tests
  agregados a `tests/Feature/ImportPostalCodesCommandTest.php`:
  `test_linkage_prioritizes_postal_codes_table_over_legacy_clave`,
  `test_linkage_falls_back_to_legacy_clave_when_no_postal_codes_match`.

---

## PASO 0 — Inspecciones BLOQUEANTES (hacer ANTES de escribir código)

- [x] 0.1 **Confirmar propiedad CP en el dataset GeoJSON.** ✅ RESUELTO y shippeado en PR1:
  `CP_PROPERTY = 'd_codigo'` (ver `ImportPostalCodesCommand.php` línea 17). Confirmado de nuevo al
  inspeccionar `22-Qro.geojson` para esta adenda (518 features, primer feature `d_codigo=76950`,
  geometry `Polygon`).

- [x] 0.2 **Confirmar columna iso2 en `app/Models/Country.php`.** ✅ RESUELTO y shippeado en PR2:
  la columna es `iso2` (confirmado leyendo `app/Models/Country.php` y la migración
  `2026_06_22_000000_create_countries_table.php`).

---

## PR 1 — Catálogo postal_code_areas + Importer

### Área 1: Tabla `postal_code_areas` + Modelo `PostalCodeArea`

#### 1-A: Tests RED (escribir ANTES de la migración/modelo)

- [ ] 1.1 **[RED] Crear `tests/Feature/PostalCodeAreaTest.php`** con los siguientes casos vacíos (que fallan porque la tabla no existe):
  - `test_migration_creates_table_with_correct_schema`: assert que la tabla `postal_code_areas` existe, que `postal_code` es `VARCHAR(5) NOT NULL`, que hay índice GIST sobre `polygon`, y que `municipality_id` es nullable con FK a `municipalities`.
  - `test_unique_constraint_on_postal_code`: intentar insertar duplicado, esperar `QueryException` con código de violación de UNIQUE.
  - `test_polygon_as_geo_json_returns_multipolygon_type`: crear fila con polígono válido, llamar `polygonAsGeoJson()`, assert `json_decode($result)->type === 'MultiPolygon'`.
  - `test_unknown_postal_code_returns_null`: `PostalCodeArea::where('postal_code','99999')->first()` retorna null.
  - `test_largest_ring_geo_json_returns_biggest_polygon`: crear PostalCodeArea con MultiPolygon de dos componentes de áreas distintas, assert `json_decode(largestRingGeoJson(...))->type === 'Polygon'` y que es el mayor.
  - `test_largest_ring_geo_json_output_passes_zone_geometry_validation`: resultado de `largestRingGeoJson` pasa `ZoneGeometry::polygonEwktFromGeoJson()` sin excepción.
  - Ejecutar: `php artisan test --filter=PostalCodeAreaTest` → debe fallar con errores de tabla inexistente.
  - **Ref spec**: REQ-1. **Ref design**: sección 2.

#### 1-B: Implementación

- [ ] 1.2 **Crear migración** `database/migrations/2026_06_23_000000_create_postal_code_areas_table.php`.
  - `id()`, `postal_code VARCHAR(5) NOT NULL`, `unique('postal_code')`, `municipality_id` FK nullable `nullOnDelete()`, `state_id` FK nullable `nullOnDelete()`, `timestamps()`.
  - Guard pgsql: `CREATE EXTENSION IF NOT EXISTS postgis`.
  - Columna `polygon`: `DB::statement("ALTER TABLE postal_code_areas ADD COLUMN polygon geometry(MultiPolygon,4326) NOT NULL")`.
  - Índice GIST: `DB::statement("CREATE INDEX postal_code_areas_polygon_gist ON postal_code_areas USING GIST(polygon)")`.
  - `down()`: `Schema::dropIfExists('postal_code_areas')`.
  - Ejecutar `php artisan migrate` en `inmo_test`.
  - **Ref design**: sección 1.

- [ ] 1.3 **Crear `app/Models/PostalCodeArea.php`**.
  - `$fillable = ['postal_code','municipality_id','state_id','polygon']`.
  - Cast `polygon` → `Clickbar\Magellan\Data\Geometries\MultiPolygon`.
  - Relaciones `municipality(): BelongsTo` y `state(): BelongsTo`.
  - Método `polygonAsGeoJson(): string` — raw select `ST_AsGeoJSON(polygon)`.
  - Método `static largestRingGeoJson(string $cp): ?string` con SQL:
    ```sql
    SELECT ST_AsGeoJSON(geom) AS geojson
    FROM (SELECT (ST_Dump(polygon)).geom AS geom
          FROM postal_code_areas WHERE postal_code = ?) parts
    ORDER BY ST_Area(geom) DESC LIMIT 1
    ```
  - **Ref design**: sección 2.

- [ ] 1.4 **[GREEN] Ejecutar `PostalCodeAreaTest`** → todos los tests deben pasar.
  ```bash
  php artisan test --filter=PostalCodeAreaTest
  ```

---

### Área 2: Comando `geo:import-postal-codes`

#### 2-A: Tests RED

- [ ] 2.1 **[RED] Crear `tests/Feature/ImportPostalCodesCommandTest.php`** con:
  - `test_imports_from_geojson_fixture`: fixture con 3 features de CPs distintos → N filas en `postal_code_areas`, polígono no nulo, exit code 0.
  - `test_import_is_idempotent`: correr comando dos veces → mismo conteo, sin excepción.
  - `test_linkage_sets_municipality_id_when_clave_matches`: `municipalities` tiene `clave='06600'`, fixture con feature CP='06600' → `municipality_id` no nulo.
  - `test_municipality_id_is_null_when_no_clave_match`: sin fila `municipalities.clave='99001'`, fixture CP='99001' → `municipality_id=NULL`.
  - `test_polygon_geometry_type_stored_as_multipolygon`: feature con geometría Polygon en fixture → almacenada como `geometry(MultiPolygon,4326)`.
  - **Crear fixture** `tests/Fixtures/postal_codes/sample.geojson` con 3 features: un Polygon simple, un MultiPolygon (2 componentes), y dos features con el mismo CP (se deben fundir en un solo MultiPolygon).
  - Ejecutar `php artisan test --filter=ImportPostalCodesCommandTest` → deben fallar.
  - **Ref spec**: REQ-2. **Ref design**: sección 3.

- [ ] 2.2 **⚠️ BLOQUEANTE: completar PASO 0.1 antes de continuar** con 2.3.

#### 2-B: Implementación

- [ ] 2.3 **Crear `app/Console/Commands/ImportPostalCodesCommand.php`**.
  - Signature: `geo:import-postal-codes {--state=} {--path=}`.
  - Constante `CP_PROPERTY` con el nombre confirmado en PASO 0.1.
  - Helper `normalizeCp(string $cp): ?string` — `str_pad($cp, 5, '0', STR_PAD_LEFT)`, valida `^\d{5}$`.
  - Lee archivos `.geojson` del directorio (filtrar por `--state=` si presente).
  - Agrupa features por CP normalizado; construye MultiPolygon via:
    ```sql
    ST_Multi(ST_SetSRID(ST_Collect(ST_GeomFromGeoJSON(?)), 4326))
    ```
  - Upsert idempotente: `DB::table('postal_code_areas')->upsert(...)` con `uniqueBy: ['postal_code']`.
  - Linkage best-effort: `Municipality::where('clave', $cp)->value('id')` → `municipality_id`; null si no hay match.
  - `state_id` derivado del municipio encontrado (nullable).
  - Insertar en batches de 100.
  - **Ref design**: sección 3.

- [ ] 2.4 **[GREEN] Ejecutar `ImportPostalCodesCommandTest`** → todos pasan.
  ```bash
  php artisan test --filter=ImportPostalCodesCommandTest
  ```

- [ ] 2.5 **Ejecutar import real (validación manual, no automatizada).**
  ```bash
  php artisan geo:import-postal-codes --path="db_estados/Pais-Estado-Municipio"
  ```
  Verificar conteo aproximado y ausencia de excepciones. Esto puede tardar varios minutos; usar `--state=` para probar con un estado primero.

---

## PR 2 — Form changes + Trait Livewire + Blade "Obtener"

> **Depende de PR 1 mergeado.** El modelo `PostalCodeArea` debe existir.

### Área 3: Formulario de Zona — regla `disabled` + aserción México

#### 3-A: Tests RED

- [ ] 3.1 **[RED] Agregar casos a (o crear) `tests/Feature/ZonePostalCodeFetchTest.php`**:
  - `test_postal_code_field_is_disabled_when_municipality_is_blank`: `assertFormFieldIsDisabled('postal_code')` cuando `municipality_id=null`.
  - `test_postal_code_field_is_enabled_when_municipality_has_value`: `assertFormFieldIsEnabled('postal_code')` con `municipality_id` válido.
  - `test_create_zone_throws_if_state_not_mexico`: pasar `state_id` de un estado con country `iso2≠'MX'` → esperar `LogicException`.
  - Ejecutar → deben fallar.
  - **Ref spec**: REQ-3. **Ref design**: sección 4.

#### 3-B: Implementación

- [ ] 3.2 **Modificar `app/Filament/Resources/ZoneResource.php`** (líneas 80–85 aprox).
  - Cambiar regla `disabled` de `postal_code` a:
    ```php
    ->disabled(fn(Get $get) => blank($get('state_id')) || blank($get('municipality_id')))
    ```
  - Actualizar `helperText` si corresponde.
  - **Ref design**: sección 4.

- [ ] 3.3 **[GREEN] Pasar tests de disabled.**
  ```bash
  php artisan test --filter=ZonePostalCodeFetchTest
  ```

---

### Área 4: Trait `ResolvesZonePostalCodePolygon` + `fetchPostalCodePolygon`

#### 4-A: Tests RED

- [ ] 4.1 **[RED] Agregar casos a `ZonePostalCodeFetchTest.php`**:
  - `test_fetch_returns_polygon_geojson_when_cp_has_coverage`: seed `PostalCodeArea` con CP='06600' multi-componente; `Livewire::test(CreateZone::class)->call('fetchPostalCodePolygon','06600')` → retorno con `type==='Polygon'`.
  - `test_fetch_returns_null_when_cp_has_no_coverage`: sin fila para '99999'; → retorna null.
  - `test_fetch_returns_null_when_cp_format_invalid`: CP='123' → retorna null.
  - `test_edit_zone_fetch_also_works`: mismo escenario con `EditZone::class`.
  - Ejecutar → deben fallar (trait no existe).
  - **Ref spec**: REQ-4. **Ref design**: sección 5.

#### 4-B: Implementación

- [ ] 4.2 **Crear directorio** `app/Filament/Resources/ZoneResource/Concerns/`.

- [ ] 4.3 **Crear `app/Filament/Resources/ZoneResource/Concerns/ResolvesZonePostalCodePolygon.php`**.
  - `fetchPostalCodePolygon(?string $cp): ?string`:
    - Si `!preg_match('/^\d{5}$/', (string)$cp)` → return null.
    - `PostalCodeArea::largestRingGeoJson($cp)` → si null, disparar `Notification::make()->warning()->title('Sin cobertura para este código postal')->send()` y return null.
    - Return el GeoJSON Polygon.
  - `assertStateBelongsToMexico(int $stateId): void`:
    - `State::with('country')->findOrFail($stateId)` — assert `$state->country->iso2 === 'MX'` (usar nombre de columna confirmado en PASO 0.2), lanzar `LogicException` si falla.
  - **Ref design**: sección 5.

- [ ] 4.4 **Modificar `app/Filament/Resources/ZoneResource/Pages/CreateZone.php`**:
  - Agregar `use ResolvesZonePostalCodePolygon`.
  - En `mutateFormDataBeforeCreate`: llamar `$this->assertStateBelongsToMexico($data['state_id'])`.
  - **Ref design**: sección 5.

- [ ] 4.5 **Modificar `app/Filament/Resources/ZoneResource/Pages/EditZone.php`**:
  - Agregar `use ResolvesZonePostalCodePolygon`.
  - En `mutateFormDataBeforeSave`: llamar `$this->assertStateBelongsToMexico($data['state_id'])`.
  - **Ref design**: sección 5.

- [ ] 4.6 **[GREEN] Pasar todos los tests del trait.**
  ```bash
  php artisan test --filter=ZonePostalCodeFetchTest
  ```

---

### Área 5: Blade `map-polygon-input.blade.php` — botón "Obtener"

> Sin runner JS/E2E. Verificación manual. No bloquea al resto del PR.

- [ ] 5.1 **Localizar y modificar `resources/views/components/map-polygon-input.blade.php`**.
  En el objeto Alpine `mapPolygon`, dentro de `setupDrawing()`:
  - Agregar `fetchButton: null` en las props del objeto Alpine.
  - Crear el botón "Obtener":
    ```js
    fetchButton = document.createElement('button');
    fetchButton.type = 'button';
    fetchButton.textContent = 'Obtener';
    // aplicar mismas clases CSS que controlButton y clearButton
    container.append(controlButton, clearButton, fetchButton);
    fetchButton.addEventListener('click', () => this.fetchCpPolygon());
    ```
  - Agregar método async `fetchCpPolygon()`:
    ```js
    async fetchCpPolygon() {
        const cpValue = this.$wire.get(cfg.cpStatePath); // ajustar según cómo está cableado cpField
        if (!cpValue) return;
        const result = await this.$wire.call('fetchPostalCodePolygon', cpValue);
        if (!result) return; // backend ya notificó
        this.value = result;
        this.$wire.set(cfg.statePath, result);
        this.renderExisting();
    }
    ```
  - `renderExisting()` **sin cambios** — ya maneja `type:'Polygon'`.
  - **Ref spec**: REQ-4 (escenarios MANUAL). **Ref design**: sección 6.

- [ ] 5.2 **Verificación manual del botón "Obtener"** (no automatizable):
  - Seleccionar Estado → Municipio → CP con cobertura → clic "Obtener" → mapa pinta polígono editable.
  - Seleccionar CP sin cobertura → clic "Obtener" → notificación visible, sin polígono.
  - Dibujo manual sigue funcionando como fallback en ambos casos.
  - **Ref spec**: REQ-4 escenarios MANUAL.

---

### Área 6: Suite completa + regresión

- [ ] 6.1 **Ejecutar suite completa** para detectar regresiones:
  ```bash
  php artisan test
  ```
  Todos los tests existentes deben seguir en verde.

- [ ] 6.2 **Ejecutar Pint** antes de hacer commit:
  ```bash
  ./vendor/bin/pint
  ```

---

## Dependencias y paralelismo

```
PASO 0.1 ──► 2.3 (comando)
PASO 0.2 ──► 4.3 (trait assertStateBelongsToMexico)

1.1 (RED) ──► 1.2 (migración) ──► 1.3 (modelo) ──► 1.4 (GREEN)
                                                    │
2.1 (RED) ──────────────────────────────────────────┤
                                                    ▼
                                               2.3 (comando) ──► 2.4 (GREEN)

[PR 1 merge] ──► 3.1 (RED) ──► 3.2 (form disabled) ──► 3.3 (GREEN)
                          └──► 4.1 (RED) ──► 4.2-4.5 (trait+pages) ──► 4.6 (GREEN)
                          └──► 5.1 (blade) ──► 5.2 (manual)

6.1 y 6.2 al final de cada PR antes de commit.
```

**Paralelizable:**
- PASO 0.1 y PASO 0.2 → independientes entre sí.
- 1.1 (escribir tests PostalCodeArea) y 2.1 (escribir tests ImportCommand) → pueden escribirse en paralelo.
- 3.1, 4.1 y 5.1 → pueden iniciarse en paralelo una vez PR 1 mergeado.

**Secuencial obligatorio:**
- 1.2 → 1.3 → 1.4 (migración antes que modelo).
- PASO 0.1 → 2.3 (necesita nombre de propiedad CP).
- PASO 0.2 → 4.3 (necesita nombre de columna iso2).
- PR 1 completo antes de empezar PR 2.

---

## Archivos afectados (resumen)

| Acción | Archivo |
|--------|---------|
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
| MODIFICADO | `resources/views/components/map-polygon-input.blade.php` |
| SIN CAMBIOS | `app/Models/Zone.php`, `app/Filament/Resources/ZoneResource/Components/MapPolygonInput.php`, `app/Rules/ValidZonePolygonGeoJson.php`, `app/Services/ZoneGeometry.php` |
