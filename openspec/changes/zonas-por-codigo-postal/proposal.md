# Propuesta de cambio: Zonas por Código Postal (catálogo de polígonos)

**Change name:** `zonas-por-codigo-postal`
**Proyecto:** newhauz
**Stack:** Laravel 13 / PHP 8.3, PostgreSQL + PostGIS (clickbar/laravel-magellan), Filament v3, Alpine.js + Google Maps JS API.
**Estado del documento:** Propuesta lista para implementación. Este documento es AUTOCONTENIDO. Edgar puede implementar sin acceso a la conversación original.

> Nota de convención: el texto de esta propuesta está en español. Los identificadores de código, nombres de tablas/columnas y snippets permanecen en inglés según la convención del proyecto.

---

## 1. Problema y motivación

Hoy, al crear o editar una **Zona** (área de influencia de un agente) en el admin de Filament, el usuario tiene que **dibujar manualmente** el polígono sobre el mapa (`setupDrawing()` en el blade). Eso es lento, inconsistente entre agentes y propenso a error.

Una Zona en newhauz es **un código postal = un polígono**. La mayoría de las veces el agente solo quiere "el área del CP 76000", no un trazo artesanal. Google Maps **no** provee polígonos de CP mexicanos, así que necesitamos un **catálogo pre-cargado** de polígonos por CP que se pueda consultar y pintar automáticamente.

La precisión geográfica **no es crítica**: las zonas son áreas de influencia comercial, no límites catastrales. Esto permite usar datasets aproximados (los CP de SEPOMEX son rutas de reparto, no polígonos administrativos oficiales).

## 2. Objetivos (Goals)

1. Agregar una tabla catálogo `postal_code_areas` con el polígono de cada CP mexicano.
2. En el formulario de Zona: deshabilitar el campo `postal_code` hasta que **Estado y Municipio** estén seleccionados.
3. Agregar un botón **"Obtener"** que cargue el polígono del CP desde el catálogo y lo pinte en el mapa, sin que el usuario dibuje.
4. Mantener el dibujo manual existente (`setupDrawing`) como **fallback opcional** para ajustes finos.
5. Fijar **México** como país de forma **server-side** (lógica de backend), sin campo visible en el formulario.
6. Comando artisan para importar el dataset al catálogo, idempotente.

## 3. No-objetivos (Non-Goals) — explícito

- **NO** se crea una entidad de "grupos de CP" / "Zona Centro" nombrada. Zona = 1 CP = 1 polígono. Sin entidad de agrupación.
- **NO** se cambia la UI de asignación agente↔zona (ya existe vía `AgentsRelationManager`, pivot `agent_zone`, relación `Zone::agents()`).
- **NO** se reemplaza el mapa base de Google Maps.
- **NO** se agrega columna `country_id` a `zones` (no existe hoy y no se necesita: el país se deriva por `state_id → states → countries`).

## 4. Decisiones ya tomadas (no relitigar)

- Zona = 1 CP = 1 polígono. Agente (User) tiene muchas Zonas vía pivot existente `agent_zone` (`Zone::agents()`).
- `zones.polygon` es `geometry(Polygon,4326)`, tiene `center_point` (auto vía `ST_Centroid` en el hook `saved`), scopes `containingPoint`/`containingPropertyPoint`.
- Los polígonos de CP vienen de un dataset importado; Google Maps no los provee.
- País fijo = México: **solo lógica de backend, sin campo en la UI** (ni oculto ni deshabilitado visible).

## 5. Decisiones que esta propuesta resuelve

### Decisión 1 — Fuente del dataset de polígonos de CP

**Recomendación: Opción A — `open-mexico/mexico-geojson` (GitHub, licencia MIT).**

| Opción | Formato | Provenance | Esfuerzo | Licencia |
|--------|---------|------------|----------|----------|
| **A. open-mexico/mexico-geojson** | GeoJSON (32 archivos por estado) | Derivado de KML de SEPOMEX, mantenido por comunidad | **Bajo** — ya es GeoJSON, importable directo | MIT |
| B. datos.gob.mx (Correos de México) | Shapefiles (.shp), 32 archivos | **Oficial** (Servicio Postal Mexicano) | Alto — requiere `ogr2ogr`/GDAL para convertir a GeoJSON | Datos abiertos MX |
| C. shapesdemexico (agregación) | Shapefiles agregados | Re-empaqueta los oficiales de B | Medio | Datos abiertos MX |

**Rationale:** todas las fuentes son **aproximadas** (los CP son rutas de reparto de SEPOMEX, nunca se definieron como fronteras cerradas). Dado que la precisión no es crítica para áreas de influencia, prima la **facilidad de consumo**: la Opción A ya está en GeoJSON y se importa sin tooling externo (no GDAL, no `ogr2ogr`). La Opción B es más autoritativa pero agrega una dependencia de conversión y un paso de build manual.

**Mitigación de provenance:** documentar la fuente y fecha de descarga del dataset en el README del comando de import. Si en el futuro se requiere mayor exactitud, el mismo comando puede leer GeoJSON producido desde los shapefiles oficiales (Opción B) sin cambiar el esquema de tabla.

**ACCIÓN REQUERIDA ANTES DE CODEAR EL IMPORTER:** los nombres de las propiedades GeoJSON en `open-mexico/mexico-geojson` **no están documentados**. Edgar debe inspeccionar UN archivo de estado y confirmar el nombre exacto de la propiedad del CP (candidatos: `codigo_postal`, `cp`, `d_codigo`) antes de escribir el mapeo de campos del importador.

### Decisión 2 — Conversión MultiPolygon → Polygon

**Recomendación: almacenar `geometry(MultiPolygon,4326)` en el catálogo, y al cargar el polígono en una Zona convertir tomando el anillo más grande con `ST_GeometryN` sobre el resultado de `ST_Dump` ordenado por `ST_Area` desc (equivalente: "largest ring").**

**Por qué MultiPolygon en el catálogo:** algunos CP son **disjuntos** (rutas de reparto separadas). `MultiPolygon` los representa nativamente sin pérdida.

**Por qué "largest ring" en lugar de convex hull o union+hull:**
- `Zone.polygon` es `geometry(Polygon,4326)` (un solo polígono simple, anillo cerrado, validado por `ZoneGeometry`).
- **Largest ring** (`ST_GeometryN` del subpolígono de mayor área): preserva la forma real del componente principal del CP. Para áreas de influencia es lo más fiel y barato.
- `ST_ConvexHull`: deforma (envuelve, infla el área). Rechazado salvo fallback.
- `ST_Union + ST_ConvexHull`: pierde la forma real y une componentes disjuntos en un casco grande. Rechazado.

**Estrategia concreta** (server-side, en el método `fetchPostalCodePolygon`):

```sql
-- Devuelve el GeoJSON del subpolígono de mayor área de un CP.
-- ST_Dump expande el MultiPolygon en filas; tomamos el de mayor área.
SELECT ST_AsGeoJSON(geom) AS geojson
FROM (
    SELECT (ST_Dump(polygon)).geom AS geom
    FROM postal_code_areas
    WHERE postal_code = ?
) parts
ORDER BY ST_Area(geom) DESC
LIMIT 1;
```

- Si el catálogo guarda un MultiPolygon de 1 solo componente, esto devuelve ese Polygon tal cual.
- El GeoJSON resultante es `{ "type": "Polygon", ... }`, compatible con `renderExisting()`.

**CRÍTICO para el front:** `renderExisting()` (línea 284 del blade) **solo maneja `type:'Polygon'`**. Si se enviara MultiPolygon al cliente, el JS falla en silencio (return temprano). Por eso la conversión a Polygon **debe ocurrir server-side**, y el método de fetch debe devolver siempre GeoJSON tipo `Polygon`.

**Validación:** el GeoJSON devuelto pasa por el mismo flujo de guardado de Zona (`setPolygonFromGeoJson` → `ZoneGeometry::polygonEwktFromGeoJson`), que exige SRID 4326, tipo POLYGON, anillo cerrado y ≥4 puntos. El "largest ring" cumple estos requisitos.

### Decisión 3 — Flujo del botón "Obtener"

**Recomendación: Approach A — método Livewire en las page classes, espejando el patrón existente `resolveZoneMapAddressLabel`.**

- Agregar `fetchPostalCodePolygon(?string $cp): ?string` a `CreateZone` **y** `EditZone`.
- El JS lo invoca con `await this.$wire.call('fetchPostalCodePolygon', this.cpValue)` (idéntico mecanismo a `labelOf()` que ya llama `resolveZoneMapAddressLabel`).
- El método consulta el catálogo (SQL de la Decisión 2), devuelve string GeoJSON tipo Polygon o `null`.
- El JS recibe el resultado y hace: `this.value = result; this.$wire.set(cfg.statePath, result); this.renderExisting();`

**Por qué Approach A:**
- Cero infraestructura nueva (sin ruta, sin controlador, sin manejo de CSRF en Alpine).
- Consistente con el patrón ya probado en el proyecto.
- El resultado se serializa por Livewire (confiable).

**Anti-duplicación:** ambas pages necesitan el método. Para evitar copiar lógica, extraer un **trait `ResolvesZonePostalCodePolygon`** (en `app/Filament/Resources/ZoneResource/Concerns/`) que ambas pages usen. (Opcionalmente, mover también `resolveZoneMapAddressLabel` a un trait `ResolvesZoneMapLabels`, pero eso es refactor secundario, no bloqueante.)

Descartados: Approach B (endpoint API REST — over-engineered, CSRF, sin guard de Filament) y Approach C (Filament Action — coordina mal con el estado Alpine del componente custom).

## 6. Alcance (Scope) de alto nivel

### 6.1 Nueva tabla `postal_code_areas`

Migración nueva en `database/migrations/`. Esquema:

```sql
CREATE TABLE postal_code_areas (
    id              BIGSERIAL PRIMARY KEY,
    postal_code     VARCHAR(5)  NOT NULL,
    municipality_id BIGINT      NULL REFERENCES municipalities(id) ON DELETE SET NULL,
    state_id        BIGINT      NULL REFERENCES states(id)         ON DELETE SET NULL,
    polygon         GEOMETRY(MultiPolygon, 4326) NOT NULL,
    created_at      TIMESTAMPTZ NULL,
    updated_at      TIMESTAMPTZ NULL
);

CREATE UNIQUE INDEX postal_code_areas_postal_code_unique  ON postal_code_areas (postal_code);
CREATE INDEX        postal_code_areas_municipality_id_idx ON postal_code_areas (municipality_id);
CREATE INDEX        postal_code_areas_polygon_gist_idx    ON postal_code_areas USING GIST (polygon);
```

Notas para la migración Laravel:
- Usar `clickbar/laravel-magellan` para la columna geométrica: `$table->magellanMultiPolygon('polygon', 4326)` (verificar nombre del helper en la versión instalada; ver cómo lo hace la migración de `zones` `2026_06_22_000003` para `geometry(Polygon,4326)` y el índice GIST).
- FKs nullable con `nullOnDelete()` (consistente con `zones.state_id` / `zones.municipality_id`).
- `postal_code` VARCHAR(5) UNIQUE — es la clave de búsqueda natural.

### 6.2 Nuevo modelo `app/Models/PostalCodeArea.php`

- `$fillable`: `postal_code`, `municipality_id`, `state_id`, `polygon`.
- Casts: `polygon` → `Clickbar\Magellan\Data\Geometries\MultiPolygon::class` (espejar el cast de `Zone::polygon`).
- Relaciones: `municipality(): BelongsTo`, `state(): BelongsTo`.
- Método `polygonAsGeoJson(): ?string` espejando `Zone::polygonAsGeoJson()` (raw `ST_AsGeoJSON(polygon)` vía `DB::table`).
- Método `largestRingGeoJson(): ?string` (o static helper en una clase de servicio) que ejecuta el SQL de la Decisión 2 (ST_Dump + ORDER BY ST_Area DESC LIMIT 1) y devuelve GeoJSON tipo Polygon. Este es el que consume `fetchPostalCodePolygon`.

### 6.3 Nuevo comando artisan `geo:import-postal-codes`

```
php artisan geo:import-postal-codes {--state= : filtrar a un estado} {--path= : ruta a archivos GeoJSON}
```

Lógica:
1. Leer los 32 archivos GeoJSON (FeatureCollection por estado) desde `--path`.
2. Por cada feature: extraer la propiedad del CP (confirmar nombre — ver Decisión 1) y la geometría.
3. Normalizar la geometría a MultiPolygon (si el feature es Polygon, envolver en MultiPolygon vía `ST_Multi`).
4. **Upsert** por `postal_code` (idempotente, `updateOrCreate` / `ON CONFLICT (postal_code)`), espejando la idempotencia del `GeoImportCommand` existente.
5. Linkage `municipality_id`/`state_id`:
   - Primero intentar `municipalities.clave = postal_code` (recordar: `municipalities.clave` guarda un CP representativo del municipio, según `GeoImportCommand` línea 78 — **nombre de columna engañoso**, no es un identificador estructurado y es nullable).
   - Si no hay match, dejar FK en `null` (son nullable). El linkage es informativo/filtrable, NO la clave de búsqueda.
6. Inserción por lotes para rendimiento (~100k features).

Patrón de referencia: el comando existente `GeoImportCommand` (lee dumps de `db_estados/`, idempotente vía `updateOrCreate`, invocado por seeder).

### 6.4 Cambios en Filament

**`ZoneResource.php` (`form()`):**
- En el `TextInput::make('postal_code')`, agregar:
  ```php
  ->disabled(fn (Get $get) => blank($get('municipality_id')))
  ```
  Así el CP queda deshabilitado hasta que haya municipio (que a su vez ya depende de estado). Mantener mask `99999`, regex `^\d{5}$`, `live(debounce: 600)`, `maxLength(5)`.
- País: **NO** agregar ningún campo. México se asume server-side; `zones` no tiene `country_id` y se deriva por `state_id → states → countries`. (Si en algún flujo se requiere validar que el estado pertenece a México, hacerlo en `handleRecordCreation`/`handleRecordUpdate`, no en el form.)

**`CreateZone.php` y `EditZone.php` (pages):**
- Agregar método `fetchPostalCodePolygon(?string $cp): ?string` (vía trait `ResolvesZonePostalCodePolygon`):
  ```php
  public function fetchPostalCodePolygon(?string $cp): ?string
  {
      if (blank($cp) || ! preg_match('/^\d{5}$/', $cp)) {
          return null;
      }

      return PostalCodeArea::query()
          ->where('postal_code', $cp)
          ->value(/* largest-ring GeoJSON, ver Decisión 2 */) ?? null;
  }
  ```
  (Implementación concreta: invocar el helper `largestRingGeoJson` del modelo/servicio.)

**`map-polygon-input.blade.php` (JS Alpine):**
- Agregar un botón **"Obtener"** en `setupDrawing()` junto a "Dibujar zona" / "Borrar zona".
- Handler del botón:
  ```js
  async fetchCpPolygon() {
      if (!this.cpValue) return;
      const geojson = await this.$wire.call('fetchPostalCodePolygon', this.cpValue);
      if (!geojson) {
          // notificar "CP sin polígono en catálogo"
          return;
      }
      this.value = geojson;
      this.$wire.set(cfg.statePath, geojson);
      this.renderExisting();
  }
  ```
- El botón "Obtener" se deshabilita en JS si `!this.cpValue` (espejo de la regla de Filament).
- Mantener intactos `setupDrawing`/`startDrawing`/`finishDrawing`/`syncGeoJSON` como **fallback de edición manual**. `renderExisting()` ya crea un polígono `editable: true`, así que tras "Obtener" el usuario puede ajustar vértices y `bindPolygonEvents` re-sincroniza vía `syncGeoJSON`.

**`MapPolygonInput.php`:** sin cambios estructurales necesarios (`cpField` ya está expuesto como `'postal_code'` y cableado por `dependsOn()`).

### 6.5 Testing (Strict TDD ACTIVO)

Los tests corren contra **PostgreSQL real** (`inmo_test`, ver `phpunit.xml`), NO SQLite. Por lo tanto **las features PostGIS SON testeables**. Cobertura requerida (escribir test primero, luego implementación):

1. **Migración + modelo:** `PostalCodeArea` se persiste con `polygon` MultiPolygon y `polygonAsGeoJson()` devuelve GeoJSON válido (`ST_AsGeoJSON`).
2. **Largest-ring conversion:** dado un CP con MultiPolygon de varios componentes, `largestRingGeoJson()` devuelve un `type:'Polygon'` correspondiente al componente de mayor área.
3. **`fetchPostalCodePolygon`:** vía `Livewire::test(CreateZone::class)` y `Livewire::test(EditZone::class)` — con una fila sembrada en `postal_code_areas`, el método devuelve GeoJSON Polygon; con CP inexistente o inválido devuelve `null`.
4. **Regla disabled:** el `postal_code` está disabled cuando `municipality_id` está vacío (test de schema del form / interacción Livewire).
5. **Comando import:** `geo:import-postal-codes --path=<fixture>` con un GeoJSON fixture pequeño (1-2 CP) inserta filas, es idempotente (correr 2 veces no duplica), y enlaza `municipality_id` cuando `municipalities.clave` coincide.

Patrón de referencia: `ZoneResourceTest` siembra `GeoCatalogSeeder` en `setUp()` y usa `Livewire::test(CreateZone::class)`.

## 7. Archivos afectados / nuevos

**Nuevos:**
- `database/migrations/XXXX_XX_XX_create_postal_code_areas_table.php`
- `app/Models/PostalCodeArea.php`
- `app/Console/Commands/ImportPostalCodesCommand.php` (signature `geo:import-postal-codes`)
- `app/Filament/Resources/ZoneResource/Concerns/ResolvesZonePostalCodePolygon.php` (trait)
- Dataset importable (GeoJSON) — ubicación a definir (p.ej. `db_estados/postal-codes/` o `storage/`), documentar fuente y fecha.
- Tests: `tests/Feature/PostalCodeAreaTest.php`, `tests/Feature/ZonePostalCodeFetchTest.php`, `tests/Feature/ImportPostalCodesCommandTest.php` (+ fixtures GeoJSON en `tests/Fixtures/`).

**Modificados:**
- `app/Filament/Resources/ZoneResource.php` — regla `disabled` en `postal_code`.
- `app/Filament/Resources/ZoneResource/Pages/CreateZone.php` — usar trait, método fetch.
- `app/Filament/Resources/ZoneResource/Pages/EditZone.php` — usar trait, método fetch.
- `resources/views/filament/forms/components/map-polygon-input.blade.php` — botón "Obtener" + handler `fetchCpPolygon`.

**Referencia (no se modifican):**
- `app/Models/Zone.php` — espejar `polygonAsGeoJson()`, casts.
- `app/Services/Zones/ZoneGeometry.php` — valida el GeoJSON al guardar la Zona.
- `app/Filament/Forms/Components/MapPolygonInput.php` — `cpField` ya cableado.
- `app/Console/Commands/GeoImportCommand.php` — patrón de import idempotente y `municipalities.clave`.

## 8. Riesgos y mitigaciones

| # | Riesgo | Mitigación |
|---|--------|------------|
| 1 | Nombre de la propiedad del CP en el GeoJSON desconocido | Edgar inspecciona 1 archivo del dataset y confirma el campo ANTES de codear el importer (acción bloqueante de Decisión 1). |
| 2 | CP disjuntos (MultiPolygon) incompatibles con `Zone.polygon` (Polygon) | Catálogo guarda MultiPolygon; conversión a "largest ring" server-side en el fetch (Decisión 2). |
| 3 | `municipalities.clave` nullable y no único por municipio | Linkage de FK best-effort; FKs nullable; la búsqueda real es por `postal_code` string, no por FK. |
| 4 | Cobertura incompleta del dataset (CP rurales ausentes) | El método fetch devuelve `null` y el JS notifica "CP sin polígono"; el dibujo manual sigue disponible como fallback. |
| 5 | `renderExisting()` solo maneja `type:'Polygon'`; MultiPolygon falla en silencio | Conversión a Polygon SIEMPRE server-side antes de devolver GeoJSON al cliente (Decisión 2/3). |
| 6 | Tamaño/tiempo de import (~100k features) | Inserción por lotes; comando idempotente con `--state=` para importar incremental. |
| 7 | GeoJSON inválido del dataset rechazado por `ZoneGeometry` al guardar | El polígono fetchado pasa por el mismo validador; el "largest ring" cumple SRID/tipo/cierre/≥4 puntos. Cubrir con test. |

## 9. Secuencia de implementación (orden sugerido para Edgar)

1. **Inspeccionar el dataset** (Opción A): descargar 1-2 archivos de `open-mexico/mexico-geojson`, confirmar el nombre de la propiedad del CP y el tipo de geometría. (Bloqueante.)
2. **Migración** `create_postal_code_areas_table` (test primero: migración corre y la tabla tiene columna geométrica + índices).
3. **Modelo** `PostalCodeArea` con casts, relaciones, `polygonAsGeoJson()` y `largestRingGeoJson()` (test: persistencia + ST_AsGeoJSON + largest ring).
4. **Comando** `geo:import-postal-codes` con fixture pequeño (test: inserta, idempotente, linkage por `clave`).
5. **Trait** `ResolvesZonePostalCodePolygon` + método `fetchPostalCodePolygon` en `CreateZone`/`EditZone` (test Livewire: CP válido → GeoJSON Polygon; inválido → null).
6. **Regla disabled** en `ZoneResource::form()` para `postal_code` (test de form/interacción).
7. **Blade**: botón "Obtener" + handler `fetchCpPolygon`, manteniendo el dibujo manual como fallback.
8. **Import real**: correr `geo:import-postal-codes` con el dataset completo en el entorno destino (documentar fuente/fecha en README del comando).
9. Verificar end-to-end en el admin: seleccionar Estado → Municipio (habilita CP) → escribir CP → "Obtener" pinta el polígono → opcionalmente ajustar vértices → guardar.

---

**Siguiente fase recomendada:** `sdd-spec` y `sdd-design` (pueden correr en paralelo).

---

## 10. Adenda — Corrección del catálogo geográfico base (states/municipalities) + tabla `postal_codes`

**Fecha:** 2026-06-23. **Motivación:** Edgar agregó tres datasets nuevos en `db_estados/` (`states.xlsx`, `municipalities.xlsx`, `cp_queretaro.xlsx`) con las claves **oficiales INEGI** (estado 2 dígitos, municipio 3 dígitos). El catálogo actual (`states`, `municipalities`) se llena vía `geo:import` parseando `db_estados/{paises,estados,municipios}.sql`, cuyo campo `clave` no es la clave INEGI (es un código ISO inventado para estados y el CP representativo para municipios — ver `GeoImportCommand`). Sin una clave INEGI real no se puede enlazar `cp_queretaro.xlsx` (que usa `state_id`/`imunicipality_id` INEGI) a `states`/`municipalities` de forma confiable.

### Decisión 5 — Reemplazar `geo:import` (SQL dumps) por Seeders basados en xlsx

**Decisión:** retirar `app/Console/Commands/GeoImportCommand.php` y los dumps `paises.sql`/`estados.sql`/`municipios.sql` como fuente de `states`/`municipalities`. Reemplazo:
- País México se siembra inline en `GeoCatalogSeeder` (`Country::updateOrCreate(['name' => 'México'], ['iso2' => 'MX', 'clave' => 'MEX'])`) — ya no depende de `paises.sql`.
- `StateSeeder` (nuevo, `database/seeders/StateSeeder.php`) lee `db_estados/states.xlsx` y siembra los 32 estados.
- `MunicipalitySeeder` (nuevo, `database/seeders/MunicipalitySeeder.php`) lee `db_estados/municipalities.xlsx` y siembra los municipios.

**Rationale:** mantener ambas fuentes (SQL dump + xlsx) duplicaría la fuente de verdad y arriesga estados duplicados si los nombres no calzan exacto entre `estados.sql` y `states.xlsx` (p.ej. "Coahuila" vs "Coahuila de Zaragoza"). El dataset INEGI de los xlsx es más autoritativo y es el que se necesita para enlazar `cp_queretaro.xlsx`. **Confirmado con Edgar** (no relitigar).

**Migración de columnas:** agregar `inegi_code` a `states` (`CHAR(2)`, único) y a `municipalities` (`CHAR(3)`, único junto con `state_id`). Estas son las claves de unión reales del catálogo INEGI; `clave` (legacy) se mantiene sin cambios para no romper consumidores existentes, pero deja de ser la clave de unión.

**Lectura de xlsx:** se agrega la dependencia `phpoffice/phpspreadsheet` (composer) — es la librería estándar del ecosistema Laravel para leer `.xlsx` sin pasos de conversión manual.

**Impacto en tests:** `tests/Feature/GeoCatalogTest.php` deja de validar el camino de `geo:import`; se reescribe para validar `StateSeeder`/`MunicipalitySeeder` (mismos invariantes: 1 país, 32 estados, todo municipio bajo un estado mexicano).

### Decisión 6 — Tabla nueva `postal_codes` (catálogo CP → colonia)

**Decisión:** agregar tabla `postal_codes` (columnas: `postal_code` VARCHAR(5), `colonia` VARCHAR(255), `municipality_id` FK nullable, `state_id` FK nullable, unique compuesto `(postal_code, colonia)`), poblada desde `cp_queretaro.xlsx` vía `PostalCodeSeeder` nuevo. Un CP tiene múltiples colonias (confirmado en el xlsx: CP `76000` repite con distintas colonias), por eso el unique es compuesto y no solo `postal_code`.

**Rationale — por qué no es lo mismo que `postal_code_areas`:** `postal_code_areas` (ya existente, Decisión 1-3 de este documento) es el catálogo **espacial** (1 polígono por CP, para pintar en el mapa de Zona). `postal_codes` es el catálogo **administrativo** (CP ↔ colonia ↔ municipio/estado, sin geometría), usado como tabla de enlace confiable: en vez de adivinar `municipality_id` por coincidencia de `municipalities.clave` (best-effort, ver Decisión 1, Riesgo 3 original), el importer de `postal_code_areas` ahora puede resolver `municipality_id`/`state_id` consultando `postal_codes.where('postal_code', $cp)`.

### Decisión 7 — Alcance del import de `postal_code_areas`: solo Querétaro por ahora

**Decisión:** el primer import real de `geo:import-postal-codes` (Decisión 2-3 de este documento, comando ya especificado) se limita al archivo `db_estados/Pais-Estado-Municipio/22-Qro.geojson` (Querétaro), no a los 32 estados. **Bloqueante de la Decisión 1 RESUELTO:** se inspeccionó el dataset real — la propiedad del CP es `d_codigo` (no `codigo_postal`/`cp`). La geometría por feature puede ser `Polygon` (confirmado en el primer feature de Qro); se mantiene la conversión `ST_Multi` ya diseñada para normalizar a `MultiPolygon`.

**Linkage actualizado:** el importer intenta `postal_codes.where('postal_code', $cp)->first()` primero (más confiable, viene de `cp_queretaro.xlsx`); si no hay fila, cae al fallback best-effort `municipalities.clave = $cp` ya diseñado. Ambos pueden devolver `null` — las FKs siguen siendo nullable.

### Archivos nuevos/modificados de esta adenda

**Nuevos:**
- `database/migrations/2026_06_23_0000XX_add_inegi_code_to_states_and_municipalities.php`
- `database/migrations/2026_06_23_0000XX_create_postal_codes_table.php`
- `app/Models/PostalCode.php`
- `database/seeders/StateSeeder.php`, `database/seeders/MunicipalitySeeder.php`, `database/seeders/PostalCodeSeeder.php`
- `tests/Feature/StateSeederTest.php`, `tests/Feature/MunicipalitySeederTest.php`, `tests/Feature/PostalCodeSeederTest.php`

**Modificados:**
- `database/seeders/GeoCatalogSeeder.php` — ya no llama `Artisan::call('geo:import')`; siembra país inline + llama `StateSeeder`, `MunicipalitySeeder`, `PostalCodeSeeder`.
- `tests/Feature/GeoCatalogTest.php` — reescrito contra el nuevo flujo.
- `app/Console/Commands/ImportPostalCodesCommand.php` (Decisión 2-3) — `CP_PROPERTY = 'd_codigo'` ya no es TODO; linkage usa `postal_codes` primero.
- `composer.json` — agrega `phpoffice/phpspreadsheet`.

**Retirados (sin uso tras esta adenda, no se borran los .sql físicamente salvo que Edgar lo pida):**
- `app/Console/Commands/GeoImportCommand.php`
- `tests/Feature/GeoCatalogTest.php` (versión vieja, reemplazada)

**Siguiente fase:** continuar directo a `sdd-tasks` para esta adenda (spec y design ya fueron actualizados en el mismo paso dado que las decisiones eran concretas, no exploratorias).
