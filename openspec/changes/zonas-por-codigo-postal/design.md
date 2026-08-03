# Diseño técnico — Zonas por Código Postal

> Documento de handoff para implementación (dev: Edgar). Texto en español; todo el código, identificadores, nombres de tabla/columna, SQL y snippets en inglés (convención del proyecto). Lee la propuesta en `openspec/changes/zonas-por-codigo-postal/proposal.md` antes de empezar.
>
> Stack: Laravel 13 + Filament + PostgreSQL/PostGIS vía `clickbar/laravel-magellan`. DB de tests: pgsql real `inmo_test` (PostGIS testeable). **Strict TDD ACTIVO**: test primero, luego implementación.

---

## 0. Resumen arquitectónico

Se agrega un **catálogo de polígonos por código postal** (`postal_code_areas`) como tabla independiente y de solo lectura desde la UI. La zona NO cambia su modelo de datos: sigue siendo `zones.polygon geometry(Polygon,4326)`, un CP = un polígono. El catálogo guarda `geometry(MultiPolygon,4326)` para soportar CPs disjuntos.

El flujo nuevo es: el usuario selecciona Estado + Municipio, escribe el CP (5 dígitos), presiona **"Obtener"** en el mapa. Un método Livewire (`fetchPostalCodePolygon`) consulta el catálogo, hace la conversión **MultiPolygon → Polygon (largest ring) server-side** y devuelve un GeoJSON `Polygon` que el blade pinta con `renderExisting()`. El dibujo manual existente queda como fallback editable.

Decisiones de arquitectura clave (ADR al final):
- **ADR-1**: catálogo separado en `postal_code_areas`, no columnas en `zones`.
- **ADR-2**: conversión MultiPolygon→Polygon por *largest ring* y **siempre server-side** (limitación de `renderExisting`).
- **ADR-3**: fetch vía método Livewire en un **trait compartido**, no endpoint HTTP ni Filament Action.
- **ADR-4**: México fijo server-side; sin campo de país en la UI.

### Capas y límites

```
Filament UI (ZoneResource form)
   │  postal_code (disabled hasta state+muni), botón "Obtener" en blade
   ▼
Livewire page (CreateZone / EditZone)  ──uses──►  Concern ResolvesZonePostalCodePolygon
   │  fetchPostalCodePolygon(?string $cp): ?string
   ▼
Model PostalCodeArea  ──►  largestRingGeoJson(string $cp): ?string  (raw PostGIS)
   ▼
PostGIS (postal_code_areas)  ◄──seed──  geo:import-postal-codes (Artisan)
```

`ZoneGeometry` y `ValidZonePolygonGeoJson` se **reutilizan tal cual** para validar/convertir el GeoJSON resultante antes de persistir (mismo camino que el dibujo manual).

---

## 1. Migración: `create_postal_code_areas_table`

Archivo nuevo: `database/migrations/2026_06_23_000000_create_postal_code_areas_table.php` (ajustar timestamp para que ordene **después** de `2026_06_22_000003_add_geo_fields_to_zones_table` y de las migraciones de `municipalities`/`states`).

Mismo estilo que `2026_06_17_200000_create_zones_table` y `2026_06_22_000003`: columnas escalares con Blueprint, geometría + índice GIST con `DB::statement` **guardado por `DB::getDriverName() === 'pgsql'`**.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postal_code_areas', function (Blueprint $table): void {
            $table->id();
            $table->string('postal_code', 5);
            $table->foreignId('municipality_id')
                ->nullable()
                ->constrained('municipalities')
                ->nullOnDelete();
            $table->foreignId('state_id')
                ->nullable()
                ->constrained('states')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique('postal_code');
            $table->index('municipality_id');
            $table->index('state_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');
            DB::statement('ALTER TABLE postal_code_areas ADD COLUMN polygon geometry(MultiPolygon, 4326) NOT NULL');
            DB::statement('CREATE INDEX postal_code_areas_polygon_gist_idx ON postal_code_areas USING GIST (polygon)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('postal_code_areas');
    }
};
```

Notas:
- `polygon` es `NOT NULL` porque un registro de catálogo sin geometría no tiene sentido (a diferencia de `zones.center_point` que es derivado).
- No se agrega `country_id`: el país se deriva `state_id → states.country_id` (consistente con ADR-4 y con `zones`).
- `down()` con `dropIfExists` basta: PostGIS elimina la columna geometry y el índice GIST junto con la tabla.

---

## 2. Modelo: `app/Models/PostalCodeArea.php`

Espejo de `Zone` en lo relevante. Cast Magellan a `MultiPolygon`. Dos métodos de lectura GeoJSON: uno crudo (`polygonAsGeoJson`, todo el MultiPolygon, útil en tests/depuración) y `largestRingGeoJson` (la conversión que consume el fetch).

```php
<?php

namespace App\Models;

use Clickbar\Magellan\Data\Geometries\MultiPolygon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'postal_code',
    'municipality_id',
    'state_id',
    'polygon',
])]
class PostalCodeArea extends Model
{
    /**
     * @return BelongsTo<Municipality, $this>
     */
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    /**
     * @return BelongsTo<State, $this>
     */
    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    /**
     * Full MultiPolygon as GeoJSON. Mirror of Zone::polygonAsGeoJson().
     */
    public function polygonAsGeoJson(): ?string
    {
        if (! $this->exists) {
            return null;
        }

        return DB::table($this->getTable())
            ->where($this->getKeyName(), $this->getKey())
            ->selectRaw('ST_AsGeoJSON(polygon) as geojson')
            ->value('geojson');
    }

    /**
     * Largest single polygon (by area) of the MultiPolygon for a postal code,
     * returned as a GeoJSON Polygon string, or null if the CP is not catalogued.
     *
     * Server-side conversion is MANDATORY: the blade renderExisting() only
     * handles GeoJSON type 'Polygon', never 'MultiPolygon'.
     */
    public static function largestRingGeoJson(string $postalCode): ?string
    {
        $row = DB::selectOne(
            <<<'SQL'
            SELECT ST_AsGeoJSON(geom) AS geojson
            FROM (
                SELECT (ST_Dump(polygon)).geom AS geom
                FROM postal_code_areas
                WHERE postal_code = ?
            ) parts
            ORDER BY ST_Area(geom) DESC
            LIMIT 1
            SQL,
            [$postalCode],
        );

        return $row?->geojson;
    }
}
```

Notas:
- `ST_Dump(polygon)` expande el MultiPolygon en sus N polígonos componentes; `ORDER BY ST_Area(geom) DESC LIMIT 1` toma el de mayor área. Esto **preserva la forma real** del componente dominante (a diferencia de `ST_ConvexHull`, que infla, o `ST_Union`+hull, que une componentes disjuntos en una mancha falsa).
- El GeoJSON devuelto es un `Polygon` con SRID implícito 4326 (los datos del catálogo están en 4326). Al persistirlo en la zona, `ZoneGeometry::polygonEwktFromGeoJson` valida SRID=4326, tipo POLYGON, anillo cerrado y ≥4 puntos. `ST_AsGeoJSON` cierra el anillo, así que esto pasa la validación.
- **Edge case (CP disjunto)**: si el CP tiene 3 islas, se devuelve solo la mayor. Es una pérdida de información aceptable (precisión no crítica, área de influencia comercial — ver propuesta). El usuario puede ajustar manualmente con el dibujo editable.

---

## 3. Comando Artisan: `geo:import-postal-codes`

Archivo nuevo: `app/Console/Commands/ImportPostalCodesCommand.php`. Patrón general inspirado en `GeoImportCommand` (transacción, resumen con `$this->info`, idempotencia con `updateOrCreate`/upsert), pero la **fuente es GeoJSON**, no dumps SQL.

### PASO 0 (BLOQUEANTE antes de codear el parser)

Fuente recomendada: **`open-mexico/mexico-geojson`** (GitHub, MIT, 32 archivos GeoJSON, uno por estado). **Antes de escribir el loop**, inspecciona un archivo real y confirma el nombre de la propiedad del CP. Candidatos: `codigo_postal`, `cp`, `d_codigo`. También confirma si la geometría por feature es `Polygon` o `MultiPolygon` (probablemente mezcla). Documenta la fuente, fecha de descarga y el nombre de propiedad confirmado en un comentario del comando y en el commit.

Define una constante con el nombre confirmado para no esparcir strings mágicos:

```php
// TODO(edgar): confirm against a real file from open-mexico/mexico-geojson before implementing.
private const CP_PROPERTY = 'd_codigo'; // candidates: codigo_postal | cp | d_codigo
```

### Firma y comportamiento

```php
protected $signature = 'geo:import-postal-codes {--state= : ISO/clave del estado para importar solo ese archivo} {--path= : directorio con los GeoJSON por estado}';

protected $description = 'Importa el catalogo de poligonos por codigo postal desde GeoJSON por estado.';
```

- `--path` resuelve igual que `GeoImportCommand::resolvePath` (default `base_path('db_estados/...')` o donde se versione el dataset; documentar la ruta elegida).
- `--state` permite importar un solo archivo (mitiga el riesgo de tamaño/timeout; ver Riesgos).
- **Idempotente**: `upsert` por `postal_code` (clave única). Re-correr no duplica ni rompe.

### Loop principal (núcleo)

```php
public function handle(): int
{
    $files = $this->resolveFiles(); // array<string> de rutas .geojson (todas, o solo --state)

    $totalUpserted = 0;
    $totalLinked = 0;

    foreach ($files as $file) {
        $fc = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);

        if (($fc['type'] ?? null) !== 'FeatureCollection') {
            throw new RuntimeException("Archivo {$file} no es un FeatureCollection.");
        }

        // Agrupar features por CP: un mismo CP puede venir en varias features (islas).
        // Se acumulan en un MultiPolygon por CP.
        $geomByCp = []; // [cp => list<geojson geometry array>]

        foreach ($fc['features'] as $feature) {
            $cp = $this->normalizeCp($feature['properties'][self::CP_PROPERTY] ?? null);

            if ($cp === null) {
                continue; // feature sin CP válido -> se ignora
            }

            $geomByCp[$cp][] = $feature['geometry'];
        }

        foreach ($geomByCp as $cp => $geometries) {
            $municipalityId = $this->resolveMunicipalityId($cp);
            $stateId = $this->resolveStateIdFromMunicipality($municipalityId); // best-effort

            // Construir MultiPolygon 4326 a partir de N geometrias (Polygon o MultiPolygon).
            // ST_Collect + ST_Multi normaliza todo a MULTIPOLYGON; ST_SetSRID fija 4326.
            $multiEwkt = DB::selectOne(
                <<<'SQL'
                SELECT ST_AsEWKT(
                    ST_Multi(
                        ST_SetSRID(
                            ST_Collect(geom),
                            4326
                        )
                    )
                ) AS ewkt
                FROM (
                    SELECT ST_GeomFromGeoJSON(value::text) AS geom
                    FROM json_array_elements(?::json) AS value
                ) g
                SQL,
                [json_encode(array_values($geometries), JSON_THROW_ON_ERROR)],
            )?->ewkt;

            if ($multiEwkt === null) {
                $this->warn("CP {$cp}: geometria vacia, se omite.");
                continue;
            }

            // upsert idempotente por postal_code; la geometria se setea por raw update
            // porque el value-list de upsert() de Eloquent no admite expresiones SQL.
            $id = DB::table('postal_code_areas')->upsert(
                [[
                    'postal_code' => $cp,
                    'municipality_id' => $municipalityId,
                    'state_id' => $stateId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]],
                ['postal_code'],          // unique by
                ['municipality_id', 'state_id', 'updated_at'], // update on conflict
            );

            DB::table('postal_code_areas')
                ->where('postal_code', $cp)
                ->update(['polygon' => DB::raw("ST_GeomFromEWKT('{$multiEwkt}')")]);

            $totalUpserted++;
            if ($municipalityId !== null) {
                $totalLinked++;
            }
        }
    }

    $this->info("postal_code_areas upserted={$totalUpserted}, linked_to_municipality={$totalLinked}");

    return self::SUCCESS;
}
```

> Nota de seguridad sobre `ST_GeomFromEWKT('{$multiEwkt}')`: el EWKT proviene de PostGIS (no del usuario) y solo contiene dígitos, signos, puntos, comas, paréntesis y la cabecera `SRID=4326;...`. Aun así, **prefiere binding parametrizado** si es viable en tu versión: `->update(['polygon' => DB::raw('ST_GeomFromEWKT(?)')], )` no acepta bindings directos en `update`; alternativa robusta: hacer el insert/update completo con `DB::statement('... ST_GeomFromEWKT(?) ...', [$multiEwkt])`. Documenta cuál usaste.

### Linkage CP → municipality_id (best-effort)

`municipalities.clave` guarda un CP **representativo** del municipio (ojo: nombre engañoso, ver `GeoImportCommand` línea 78 — `clave` se setea desde `codigo_postal`). No es único ni cubre todos los CPs. Por eso el linkage es best-effort:

```php
private function resolveMunicipalityId(string $cp): ?int
{
    // Coincidencia exacta por clave; si no hay match, FK queda null.
    return Municipality::query()->where('clave', $cp)->value('id');
}

private function resolveStateIdFromMunicipality(?int $municipalityId): ?int
{
    if ($municipalityId === null) {
        return null;
    }

    return Municipality::query()->whereKey($municipalityId)->value('state_id');
}
```

Si `clave` no matchea, `municipality_id` y `state_id` quedan `null`. **Esto no rompe el fetch**: `fetchPostalCodePolygon` busca por `postal_code`, no por la FK. Las FKs son metadata/filtrado, no la clave de consulta.

`normalizeCp`: castea a string, `str_pad` a 5 con ceros a la izquierda, valida `^\d{5}$`, devuelve `null` si no cumple.

---

## 4. Cambios en el formulario de Zona

### 4.1 `postal_code` deshabilitado hasta Estado + Municipio

En `ZoneResource::form()`, el campo `postal_code` (líneas 80-85) agrega `->disabled(...)`. La propuesta menciona solo `municipality_id`, pero como `municipality_id` ya está `disabled` hasta tener `state_id`, basta con condicionar sobre ambos para ser explícitos y robustos:

```php
Forms\Components\TextInput::make('postal_code')
    ->label('Código Postal')
    ->mask('99999')
    ->rule('regex:/^\d{5}$/')
    ->live(debounce: 600)
    ->maxLength(5)
    ->disabled(fn (Get $get): bool => blank($get('state_id')) || blank($get('municipality_id')))
    ->helperText('Selecciona primero estado y municipio.'),
```

`Get` ya está importado en `ZoneResource.php` (línea 14).

### 4.2 País México solo backend (sin campo UI)

No se agrega ningún campo de país al formulario. La aserción server-side de que el estado pertenece a México vive en las páginas Create/Edit, en los hooks que ya manipulan los datos antes de persistir.

En `CreateZone::mutateFormDataBeforeCreate` y `EditZone::mutateFormDataBeforeSave`, antes de `unset($data['polygon'])`, agregar la aserción:

```php
$this->assertStateBelongsToMexico($data['state_id'] ?? null);
```

Método compartido (lo natural es ponerlo en el mismo trait del fetch, sección 5, para no duplicarlo entre Create y Edit):

```php
protected function assertStateBelongsToMexico(mixed $stateId): void
{
    if (blank($stateId)) {
        return; // 'required' de Filament ya bloquea el guardado sin estado
    }

    $belongs = State::query()
        ->whereKey($stateId)
        ->whereHas('country', fn (Builder $q) => $q->where('iso2', 'MX'))
        ->exists();

    if (! $belongs) {
        throw new \LogicException('Zone state must belong to Mexico.');
    }
}
```

`Country` tiene `iso2 = 'MX'` (lo setea `GeoImportCommand`). Si el catálogo geográfico usa otra columna para el código, ajustar el `where` (verificar `app/Models/Country.php`).

---

## 5. Trait + método `fetchPostalCodePolygon` (Livewire)

Archivo nuevo: `app/Filament/Resources/ZoneResource/Concerns/ResolvesZonePostalCodePolygon.php`. Evita duplicar el método entre `CreateZone` y `EditZone` (mismo motivo por el que `resolveZoneMapAddressLabel` está duplicado hoy — este trait es la oportunidad de centralizar; opcionalmente mover también `resolveZoneMapAddressLabel` al trait, pero eso es scope extra, decisión de Edgar).

```php
<?php

namespace App\Filament\Resources\ZoneResource\Concerns;

use App\Models\PostalCodeArea;
use App\Models\State;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

trait ResolvesZonePostalCodePolygon
{
    /**
     * Livewire-callable. Returns a GeoJSON Polygon string (largest ring of the
     * catalogued MultiPolygon) for the given postal code, or null if not found.
     * Called from the blade "Obtener" button via $wire.call(...).
     */
    public function fetchPostalCodePolygon(?string $postalCode): ?string
    {
        $postalCode = trim((string) $postalCode);

        if (! preg_match('/^\d{5}$/', $postalCode)) {
            return null;
        }

        $geoJson = PostalCodeArea::largestRingGeoJson($postalCode);

        if ($geoJson === null) {
            Notification::make()
                ->title('Sin polígono para ese código postal')
                ->body('No hay un polígono catalogado para el C.P. '.$postalCode.'. Puedes dibujarlo manualmente.')
                ->warning()
                ->send();

            return null;
        }

        return $geoJson;
    }

    protected function assertStateBelongsToMexico(mixed $stateId): void
    {
        if (blank($stateId)) {
            return;
        }

        $belongs = State::query()
            ->whereKey($stateId)
            ->whereHas('country', fn (Builder $q) => $q->where('iso2', 'MX'))
            ->exists();

        if (! $belongs) {
            throw new \LogicException('Zone state must belong to Mexico.');
        }
    }
}
```

Uso en las páginas:

```php
// CreateZone.php y EditZone.php
use App\Filament\Resources\ZoneResource\Concerns\ResolvesZonePostalCodePolygon;

class CreateZone extends CreateRecord
{
    use ResolvesZonePostalCodePolygon;
    // ...
}
```

`MapPolygonInput.php` **no cambia**: `cpField` ya está cableado y el blade ya recibe `cpValue` por entangle.

---

## 6. Cambios en el blade `map-polygon-input.blade.php`

Tres cambios mínimos, todos dentro del componente Alpine `mapPolygon`:

### 6.1 Botón "Obtener" en `setupDrawing()`

Junto a "Dibujar zona" y "Borrar zona" (después de crear `this.clearButton`, antes de `container.append(...)`):

```js
this.fetchButton = document.createElement('button');
this.fetchButton.type = 'button';
this.fetchButton.className = buttonClass;
this.fetchButton.textContent = 'Obtener';
this.fetchButton.addEventListener('click', () => this.fetchCpPolygon());

container.append(this.controlButton, this.clearButton, this.fetchButton);
```

Agregar `fetchButton: null,` en el bloque de propiedades del objeto (junto a `clearButton`).

### 6.2 Handler `fetchCpPolygon()`

Nuevo método dentro del objeto Alpine. Llama al método Livewire, setea el valor, repinta. Mantiene el dibujo manual intacto como fallback (el `Polygon` resultante de `renderExisting` es `editable: true`, así que el usuario puede ajustarlo).

```js
async fetchCpPolygon() {
    if (!this.cpValue) {
        return;
    }

    const result = await this.$wire.call('fetchPostalCodePolygon', this.cpValue);

    if (!result) {
        // La notificación de "no encontrado" la dispara el backend (Filament Notification).
        return;
    }

    this.value = result;
    this.$wire.set(cfg.statePath, result);
    this.renderExisting();
},
```

### 6.3 Sin cambios en `renderExisting()`

`renderExisting()` (líneas 276-312) ya pinta cualquier GeoJSON `type: 'Polygon'`. Como el fetch SIEMPRE devuelve `Polygon` (largest ring server-side), no necesita tocarse. **No agregar soporte de MultiPolygon en el cliente** — la conversión es responsabilidad del servidor (ADR-2). El `$watch('value', () => this.renderExisting())` existente (línea 108) también lo repintaría, pero llamar `renderExisting()` explícito en `fetchCpPolygon` garantiza el repintado inmediato sin depender del timing del entangle.

---

## 7. Diagrama de flujo de datos (textual)

```
1. Usuario: selecciona Estado  -> state_id (live)
2. Usuario: selecciona Municipio -> municipality_id (live) ; habilita postal_code
3. Usuario: escribe CP (5 dígitos) -> cpValue (entangle .live)
4. Usuario: clic "Obtener" (blade)
        │
        ▼
5. Alpine fetchCpPolygon(): this.$wire.call('fetchPostalCodePolygon', cpValue)
        │
        ▼
6. Livewire (CreateZone/EditZone via trait): fetchPostalCodePolygon(cp)
        │  valida regex ^\d{5}$
        ▼
7. PostalCodeArea::largestRingGeoJson(cp):
        ST_Dump(polygon) -> componentes -> ORDER BY ST_Area DESC LIMIT 1 -> ST_AsGeoJSON
        │
        ├─ null  -> Filament Notification (warning) -> return null -> fallback dibujo manual
        ▼
8. GeoJSON Polygon string (4326) -> return al cliente
        │
        ▼
9. Alpine: this.value = result ; $wire.set(statePath, result) ; renderExisting()
        │  pinta google.maps.Polygon editable (ajustable a mano)
        ▼
10. Submit del form:
        mutateFormDataBefore{Create|Save}: assertStateBelongsToMexico(state_id)
        polygon GeoJSON -> Zone::setPolygonFromGeoJson -> ZoneGeometry::polygonEwktFromGeoJson
        (valida SRID 4326, POLYGON, anillo cerrado, >=4 pts)
        │
        ▼
11. saving() hook: ZoneGeometry::validatePolygonEwkt
    saved() hook: center_point = ST_Centroid(polygon)  (sin cambios)
```

---

## 8. Estrategia de testing (Strict TDD, pgsql `inmo_test`)

Test primero en cada bloque. Como `postal_code_areas` usa PostGIS real, los tests corren contra `inmo_test` (no `:memory:`). Sembrar geometrías de prueba con WKT/EWKT vía `DB::statement` o factory dedicada.

### Tests automatizados (escribir ANTES de implementar)

1. **`PostalCodeAreaTest`** (Feature):
   - `it persists a MultiPolygon and reads it back via polygonAsGeoJson` — inserta un MultiPolygon EWKT, asserts `ST_AsGeoJSON` devuelve `type: MultiPolygon`.
   - `largestRingGeoJson returns the largest component as a Polygon` — siembra un MultiPolygon con 2 componentes de áreas distintas; assert que devuelve un `Polygon` cuyo área coincide con el mayor. Cubre el **edge case de CP disjunto**.
   - `largestRingGeoJson returns null for an unknown postal code`.
   - `largestRingGeoJson output passes ZoneGeometry::polygonEwktFromGeoJson` — assert que el GeoJSON devuelto es persistible como `zones.polygon` (cierra el contrato con ADR-2).

2. **`ImportPostalCodesCommandTest`** (Feature):
   - `it imports postal code areas from a GeoJSON fixture` — fixture pequeño (2-3 CPs, uno con feature MultiPolygon, uno con 2 features mismo CP = islas). Assert conteo y que un CP quedó como MultiPolygon con 2 componentes.
   - `it is idempotent` — correr el comando 2 veces; assert que el conteo de filas no cambia.
   - `it links municipality_id by municipalities.clave when present` — sembrar municipio con `clave` = CP; assert FK seteada. Y un CP sin match -> FK null.
   - **Fixture**: `tests/Fixtures/postal_codes/sample.geojson` (documentar nombre de propiedad CP usado, debe coincidir con `CP_PROPERTY`).

3. **`ZonePostalCodeFetchTest`** (Feature, `Livewire::test`):
   - Sobre `CreateZone` y `EditZone`: `fetchPostalCodePolygon returns a Polygon GeoJSON for a catalogued CP`.
   - `fetchPostalCodePolygon returns null and notifies for an unknown CP` — assert `Notification::assertNotified(...)` o equivalente.
   - `fetchPostalCodePolygon returns null for an invalid CP` (no 5 dígitos), sin tocar la DB.

4. **Regla disabled de `postal_code`** (Feature, Livewire form):
   - `postal_code is disabled until state and municipality are set` — `Livewire::test(CreateZone)->assertFormFieldIsDisabled('postal_code')`, luego setear state+muni y `assertFormFieldIsEnabled('postal_code')`.

5. **Aserción México server-side**:
   - `creating a zone with a state outside Mexico throws` — sembrar un país no-MX, un estado bajo él; assert que el guardado lanza `LogicException`. (Caso defensivo; en prod el catálogo es solo MX, pero el contrato debe estar testeado.)

Patrón de setUp: igual que `ZoneResourceTest`, sembrar el catálogo geográfico (`GeoCatalogSeeder` o seed mínimo de país MX + estado + municipio) en `setUp()`, más las filas de `postal_code_areas` que cada test necesite.

### Partes manuales (NO automatizables)

- Render del `google.maps.Polygon` y el botón "Obtener" en el mapa (JS/Google Maps API).
- Verificación visual e2e: seleccionar estado/muni/CP real, "Obtener", confirmar que el polígono se pinta y es editable, guardar, reabrir en Edit y confirmar que persiste.
- Import del dataset real completo (32 archivos) — verificación de volumen/tiempo en local, no en CI.

---

## 9. Edge cases y limitaciones (consolidado)

1. **CPs disjuntos (MultiPolygon con N componentes)**: el catálogo los guarda completos; el fetch devuelve solo el componente de mayor área (largest ring). Pérdida de información aceptable; el usuario ajusta a mano si hace falta.
2. **`renderExisting()` solo soporta `type: 'Polygon'`**: por eso la conversión MultiPolygon→Polygon es SIEMPRE server-side. Nunca devolver un MultiPolygon al cliente.
3. **`municipalities.clave` no único / no cubre todos los CPs**: linkage FK best-effort; el fetch consulta por `postal_code`, no por FK, así que CPs sin municipio asociado funcionan igual.
4. **CP sin polígono en catálogo**: `largestRingGeoJson` devuelve null → Notification warning → fallback a dibujo manual.
5. **Nombre de propiedad del CP en el dataset desconocido**: BLOQUEANTE, inspeccionar archivo real antes de codear el parser (PASO 0).
6. **Tamaño del import**: opción `--state` para importar por estado y evitar timeouts; upsert por lotes por CP dentro de cada archivo.
7. **GeoJSON inválido al persistir**: mismo validador `ZoneGeometry` que el dibujo manual; cubierto por test del punto 1.4.
8. **Anillo cerrado**: `ST_AsGeoJSON` cierra el anillo exterior, así que el output del fetch cumple `ST_IsClosed` y `ST_NPoints >= 4`.

---

## 10. Decisiones de arquitectura (ADR)

### ADR-1 — Catálogo en tabla separada `postal_code_areas`
- **Decisión**: tabla independiente, no columnas en `zones`.
- **Rationale**: el catálogo es un dataset compartido, reimportable e idempotente; las zonas lo consumen pero no lo poseen. Mezclarlo en `zones` acoplaría datos maestros con datos transaccionales.
- **Rechazado**: agregar `multipolygon` a `zones` (rompe el invariante "zona = 1 Polygon" y duplicaría datos por cada zona del mismo CP).

### ADR-2 — Conversión MultiPolygon→Polygon por *largest ring*, server-side
- **Decisión**: `ST_Dump` + `ORDER BY ST_Area DESC LIMIT 1`, siempre en el servidor.
- **Rationale**: preserva la forma real del componente dominante; el cliente (`renderExisting`) solo entiende `Polygon`.
- **Rechazado**: `ST_ConvexHull` (infla el área), `ST_Union`+hull (fusiona disjuntos en una mancha falsa), conversión en el cliente (renderExisting no soporta MultiPolygon).

### ADR-3 — Fetch vía método Livewire en trait compartido
- **Decisión**: `fetchPostalCodePolygon` en `ResolvesZonePostalCodePolygon`, usado por Create y Edit.
- **Rationale**: espeja `resolveZoneMapAddressLabel`, reusa la sesión/CSRF de Livewire, sin nuevo endpoint. El trait evita duplicación.
- **Rechazado**: endpoint HTTP (CSRF + over-engineering), Filament Action (coordina mal con el ciclo Alpine del mapa).

### ADR-4 — México fijo server-side, sin campo UI
- **Decisión**: aserción `assertStateBelongsToMexico` en los hooks de Create/Edit; el país se deriva `state_id → states.country_id`.
- **Rationale**: el negocio opera solo en MX; un campo de país sería ruido. Mantiene `zones` sin `country_id`.
- **Rechazado**: agregar `country_id` a `zones` o un Select de país en el form.

---

## 11. Archivos (resumen para Edgar)

**Nuevos**:
- `database/migrations/2026_06_23_000000_create_postal_code_areas_table.php`
- `app/Models/PostalCodeArea.php`
- `app/Console/Commands/ImportPostalCodesCommand.php`
- `app/Filament/Resources/ZoneResource/Concerns/ResolvesZonePostalCodePolygon.php`
- Dataset GeoJSON (versionar bajo `db_estados/` o similar; documentar fuente, fecha, propiedad CP)
- Tests: `tests/Feature/PostalCodeAreaTest.php`, `tests/Feature/ImportPostalCodesCommandTest.php`, `tests/Feature/ZonePostalCodeFetchTest.php` (+ regla disabled y aserción MX, pueden ir en `ZoneResourceTest` o tests dedicados)
- Fixture: `tests/Fixtures/postal_codes/sample.geojson`

**Modificados**:
- `app/Filament/Resources/ZoneResource.php` (regla `disabled` en `postal_code`)
- `app/Filament/Resources/ZoneResource/Pages/CreateZone.php` (`use` trait + aserción MX en `mutateFormDataBeforeCreate`)
- `app/Filament/Resources/ZoneResource/Pages/EditZone.php` (`use` trait + aserción MX en `mutateFormDataBeforeSave`)
- `resources/views/filament/forms/components/map-polygon-input.blade.php` (botón "Obtener" + `fetchCpPolygon`)

**Sin cambios**: `app/Models/Zone.php`, `app/Filament/Forms/Components/MapPolygonInput.php`, `app/Services/Zones/ZoneGeometry.php`, `app/Rules/ValidZonePolygonGeoJson.php` (todos reutilizados).

### Secuencia sugerida
1. PASO 0: inspeccionar dataset, confirmar `CP_PROPERTY` (BLOQUEANTE).
2. Test + migración `postal_code_areas`.
3. Test + modelo `PostalCodeArea` (`largestRingGeoJson`).
4. Test + comando `geo:import-postal-codes` (con fixture).
5. Test + trait `ResolvesZonePostalCodePolygon` (`fetchPostalCodePolygon` + aserción MX) en Create/Edit.
6. Test + regla `disabled` en `postal_code`.
7. Blade: botón "Obtener" + `fetchCpPolygon`.
8. Import real del dataset completo.
9. Verificación e2e manual.

---

## 12. Adenda — Diseño de la corrección de catálogo (states/municipalities/postal_codes)

> PASO 0.1 de la sección 3 queda RESUELTO: `CP_PROPERTY = 'd_codigo'` (confirmado inspeccionando
> `db_estados/Pais-Estado-Municipio/22-Qro.geojson`, 518 features, primer feature geometry type
> `Polygon`).

### 12.1 Migración: `inegi_code` en `states` y `municipalities`

```php
Schema::table('states', function (Blueprint $table): void {
    $table->string('inegi_code', 2)->nullable()->unique()->after('clave');
});

Schema::table('municipalities', function (Blueprint $table): void {
    $table->string('inegi_code', 3)->nullable()->after('clave');
    $table->unique(['state_id', 'inegi_code']);
});
```

`nullable()` porque la columna se backfillea por el seeder, no por la migración; una vez sembrado
el catálogo completo, en la práctica nunca queda null.

### 12.2 Lectura de xlsx: `phpoffice/phpspreadsheet`

```bash
composer require phpoffice/phpspreadsheet
```

Patrón de lectura común a los 3 seeders (helper privado por seeder, sin abstracción compartida —
solo 3 usos, no amerita una clase de servicio):

```php
$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load(base_path('db_estados/states.xlsx'));
$rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
$header = array_shift($rows); // ['state_id', 'state']
```

### 12.3 `StateSeeder`

```php
class StateSeeder extends Seeder
{
    public function run(): void
    {
        $mexico = Country::query()->where('iso2', 'MX')->firstOrFail();
        $rows = $this->readXlsx(base_path('db_estados/states.xlsx'));

        foreach ($rows as [$inegiCode, $name]) {
            if ($inegiCode === null || $inegiCode === 'state_id') {
                continue; // header o fila de nota final
            }

            State::query()->updateOrCreate(
                ['country_id' => $mexico->id, 'name' => trim((string) $name)],
                ['inegi_code' => str_pad((string) $inegiCode, 2, '0', STR_PAD_LEFT)],
            );
        }
    }
}
```

Nota: la fila 33 del xlsx ("NOTA: La Encuesta...") no tiene `state_id` numérico de 2 dígitos — se
descarta validando `preg_match('/^\d{1,2}$/', (string) $inegiCode)` en vez de comparar contra el
literal `'state_id'` (más robusto que el ejemplo arriba; usar el regex en la implementación real).

**IMPORTANTE — la clave de match del `updateOrCreate` es `(country_id, name)`, NO `inegi_code`.**
`states` ya tiene `unique(['country_id','name'])` (migración `2026_06_22_000001`). Si se matcheara
por `inegi_code` en una base que ya tiene filas de `geo:import` (con `inegi_code` NULL), el upsert
INSERTARÍA una fila nueva con el mismo nombre y violaría ese unique. Matchear por `(country_id,
name)` corrige la fila existente in-place (agrega `inegi_code` sin tocar el `id`, preservando FKs
de `municipalities`/`zones` que ya apunten a ese estado) y sigue siendo válido en una base vacía.
**Edge case:** si el nombre del xlsx difiere del nombre viejo para el mismo estado (no se detectó
ningún caso en la inspección de `states.xlsx` — los 32 nombres calzan con los de `estados.sql`),
quedaría una fila duplicada sin `inegi_code`. Mitigación operativa: correr esta corrección con
`php artisan migrate:fresh --seed` en entornos de desarrollo (sin datos reales aún) en vez de un
`db:seed` incremental, para garantizar catálogo limpio. Documentar esto en el README/PR.

### 12.4 `MunicipalitySeeder`

```php
class MunicipalitySeeder extends Seeder
{
    public function run(): void
    {
        $statesByInegi = State::query()->pluck('id', 'inegi_code');
        $rows = $this->readXlsx(base_path('db_estados/municipalities.xlsx'));

        foreach ($rows as [$stateInegi, $muniInegi, $name]) {
            if (! preg_match('/^\d{1,2}$/', (string) $stateInegi)) {
                continue;
            }

            $stateInegi = str_pad((string) $stateInegi, 2, '0', STR_PAD_LEFT);
            $muniInegi = str_pad((string) $muniInegi, 3, '0', STR_PAD_LEFT);
            $stateId = $statesByInegi[$stateInegi] ?? null;

            if ($stateId === null) {
                continue; // estado no sembrado; no debería pasar si StateSeeder corrió antes
            }

            $name = mb_convert_case(mb_strtolower((string) $name), MB_CASE_TITLE, 'UTF-8');

            Municipality::query()->updateOrCreate(
                ['state_id' => $stateId, 'name' => $name],
                ['inegi_code' => $muniInegi],
            );
        }
    }
}
```

`mb_convert_case(..., MB_CASE_TITLE, 'UTF-8')` normaliza `'JESÚS MARÍA'` → `'Jesús María'`
preservando acentos (mbstring ya es dependencia de Laravel, sin paquete nuevo). Misma razón que
en 12.3: el match es `(state_id, name)` (clave existente, `unique(['state_id','name'])`), no
`inegi_code` — evita duplicar filas de municipios ya sembrados por `geo:import`. Con 2,478
municipios, es esperable que algunos nombres no calcen exacto contra el dump viejo (mayúsculas
parciales, acentos); en esos casos se crea una fila nueva correcta y el operador debe limpiar la
vieja — de nuevo, `migrate:fresh --seed` en dev evita el problema de raíz.

### 12.5 `PostalCodeSeeder` + modelo `PostalCode`

```php
class PostalCodeSeeder extends Seeder
{
    public function run(): void
    {
        $municipalitiesByInegi = Municipality::query()
            ->whereHas('state', fn ($q) => $q->where('inegi_code', '22'))
            ->get(['id', 'inegi_code', 'state_id'])
            ->keyBy('inegi_code');

        $rows = $this->readXlsx(base_path('db_estados/cp_queretaro.xlsx'));

        foreach ($rows as [$postalCode, $colonia, $stateInegi, $muniInegi]) {
            if (! preg_match('/^\d{5}$/', (string) $postalCode)) {
                continue;
            }

            $muniInegi = str_pad((string) $muniInegi, 3, '0', STR_PAD_LEFT);
            $municipality = $municipalitiesByInegi->get($muniInegi);

            PostalCode::query()->updateOrCreate(
                ['postal_code' => (string) $postalCode, 'colonia' => trim((string) $colonia)],
                [
                    'municipality_id' => $municipality?->id,
                    'state_id' => $municipality?->state_id,
                ],
            );
        }
    }
}
```

`app/Models/PostalCode.php` — `$fillable`: `postal_code`, `colonia`, `municipality_id`,
`state_id`; relaciones `municipality(): BelongsTo`, `state(): BelongsTo`. Sin campo geométrico
(eso vive en `PostalCodeArea`, modelo distinto).

### 12.6 `GeoCatalogSeeder` reescrito

```php
class GeoCatalogSeeder extends Seeder
{
    public function run(): void
    {
        Country::query()->updateOrCreate(
            ['name' => 'México'],
            ['iso2' => 'MX', 'clave' => 'MEX'],
        );

        $this->call([
            StateSeeder::class,
            MunicipalitySeeder::class,
            PostalCodeSeeder::class,
        ]);
    }
}
```

`GeoImportCommand.php` y el `protected $signature = 'geo:import ...'` se ELIMINAN (ya no hay
llamador). Los dumps `paises.sql`/`estados.sql`/`municipios.sql` quedan sin uso en `db_estados/`
(no se borran del filesystem en este cambio, solo dejan de ser leídos).

### 12.7 Linkage actualizado en `geo:import-postal-codes` (REQ-8)

Reemplaza el método `resolveMunicipalityId` de la sección 3 (Decisión 2 de la propuesta original)
por:

```php
private function resolveMunicipalityId(string $cp): ?int
{
    $viaPostalCode = PostalCode::query()->where('postal_code', $cp)->value('municipality_id');

    if ($viaPostalCode !== null) {
        return $viaPostalCode;
    }

    return Municipality::query()->where('clave', $cp)->value('id'); // fallback legacy
}
```

### ADR-5 — `inegi_code` como clave de unión real, `clave` legacy intacta

- **Decisión:** agregar `inegi_code` a `states`/`municipalities`; no tocar ni reinterpretar `clave`.
- **Rationale:** `clave` ya tiene semántica fijada por `GeoImportCommand` (código ISO inventado en
  states, CP representativo en municipalities) y algún consumidor podría depender de ese valor.
  Mezclar semánticas en una sola columna genera ambigüedad; una columna nueva con nombre explícito
  es más barato que una migración de datos riesgosa.
- **Rechazado:** reescribir `clave` para que sea el código INEGI (rompe cualquier lectura existente
  de `clave` con la semántica vieja, sin ganancia real).

### ADR-6 — `postal_codes` separada de `postal_code_areas`

- **Decisión:** dos tablas con propósitos distintos: `postal_codes` (administrativa, CP↔colonia,
  sin geometría) y `postal_code_areas` (espacial, CP↔polígono). `postal_codes` además SIRVE como
  fuente de linkage confiable para `postal_code_areas`.
- **Rationale:** un CP tiene 1 polígono pero N colonias — fusionarlas en una tabla forzaría a elegir
  entre duplicar el polígono por colonia (desperdicio) o perder la relación colonia (pérdida de
  información). Mantenerlas separadas y relacionadas por `postal_code` (string) es más simple que
  una FK directa entre ellas, porque `postal_codes` no necesariamente tiene cobertura para todos los
  CPs del polígono (y viceversa).
- **Rechazado:** agregar `colonia` como columna repetida en `postal_code_areas` (rompe el unique de
  `postal_code` ahí).

### ADR-7 — Import de `postal_code_areas` acotado a Querétaro

- **Decisión:** primera corrida real de `geo:import-postal-codes` solo con `22-Qro.geojson`
  (`--state=22-Qro`), no los 32 archivos.
- **Rationale:** es el único estado con `postal_codes` poblado hoy (desde `cp_queretaro.xlsx`); 
  correr los 32 sin tener `postal_codes` de los otros 31 estados solo ejercitaría el fallback legacy
  por `clave`, sin validar el camino nuevo de linkage. Ampliar a más estados es trabajo futuro
  cuando existan sus respectivos xlsx de CP↔colonia (o se decida importar `postal_codes` desde otra
  fuente).
- **Rechazado:** import nacional completo en este cambio (fuera de alcance, sin datasets de
  `postal_codes` para validarlo en los otros estados).
