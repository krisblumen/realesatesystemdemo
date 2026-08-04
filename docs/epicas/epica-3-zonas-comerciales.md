# Épica 3 — Zonas Comerciales

**Proyecto:** New Hauz — Plataforma Inmobiliaria  
**Estado:** ✅ APROBADO PARA IMPLEMENTACIÓN  
**Rama base:** `develop`  
**Rama de trabajo:** `feature/epica-3-zonas-comerciales`  
**Arquitecto:** Edgar  
**QA:** Sebastián  
**Revisión:** Kristian  
**Diseño generado:** 17 de Junio, 2026  

---

## 1. Contexto y Dependencias

Esta épica consume los contratos entregados por las Épicas 1 y 2.

| Contrato consumido | RFC / Épica origen | Estado |
| :--- | :--- | :--- |
| Laravel 13.x + PHP 8.3 | RFC-001 / Épica 1 | ✅ Activo |
| PostgreSQL + PostGIS habilitado (`CREATE EXTENSION postgis`) | RFC-003 / Épica 1 | ✅ Activo |
| Filament v3 panel `/admin` | RFC-004 / Épica 1 | ✅ Activo |
| `spatie/laravel-permission` con roles `owner`, `admin`, `agente` | RFC-006 / Épica 1 | ✅ Activo |
| `App\Models\User` con `HasRoles`, `SoftDeletes`, `UserStatus` | RFC-011 / Épica 2 | ✅ Activo |
| `UserPolicy` como referencia de patrón de autorización | RFC-012 / Épica 2 | ✅ Activo |
| Permiso `zones.manage` sembrado en `PermissionSeeder` | RFC-014 / Épica 2 | ✅ Activo |

**No se toca ningún archivo de las Épicas 1 y 2.** Toda extensión es aditiva.

### Contratos diferidos que esta épica declara (sin implementar)

| Contrato | Épica futura | Declarado en |
| :--- | :--- | :--- |
| `Zone::properties()` — relación Zona ↔ Inmuebles | Épica 4 | `App\Models\Zone` (stub diferido) |

---

## 2. Objetivos

### Lo que esta épica entrega

- Enum `ZoneStatus` (`activa` / `inactiva`) como tipo de dominio.
- Modelo `Zone` con slug único, status, soft delete y relaciones.
- Polígono WGS84 (`geometry(Polygon,4326)`) y center point derivado (`geometry(Point,4326)`), ambos con índice GIST.
- Paquete `matanyadaev/laravel-eloquent-spatial` integrado para hidratación de geometrías en Eloquent.
- Scopes espaciales reutilizables: `containsPoint`, `intersectsZone`.
- `ZonePolicy` con backend-first authorization usando `zones.manage`.
- `ZoneResource` en Filament (form, table, filtros, acciones, permisos).
- Campo personalizado `LeafletPolygonInput` para captura y edición de polígonos en Filament.
- Tabla pivote `agent_zone` con `AgentRelationManager` para asignación de agentes.
- Suite de tests QA-018 → QA-025.

### Lo que esta épica NO entrega

- Tabla `properties` ni relación Zona ↔ Inmuebles real (solo contrato diferido).
- API REST de zonas (monolito; Filament es la interfaz).
- Importación masiva de zonas por CSV o GeoJSON externo.
- Frontend público de mapa (Épica 6).
- Validación topológica avanzada (solapamiento entre zonas) — riesgo conocido, documentado.
- Tiles de mapa de pago: se usan OpenStreetMap (sin clave API).

---

## 3. Alcance Funcional

| # | Funcionalidad | Actor |
| :--- | :--- | :--- |
| F-1 | Crear zona con nombre, municipio, descripción y polígono | owner, admin |
| F-2 | Editar zona (todos los campos, incluido polígono) | owner, admin |
| F-3 | Ver listado de zonas con filtros (status, municipio) | owner, admin |
| F-4 | Activar / inactivar zona | owner, admin |
| F-5 | Soft-delete de zona | owner |
| F-6 | Restaurar zona eliminada | owner |
| F-7 | Asignar agentes a una zona (uno o varios) | owner, admin |
| F-8 | Desasignar agentes de una zona | owner, admin |
| F-9 | Ver agentes asignados a una zona | owner, admin |
| F-10 | Consultar a qué zonas pertenece un agente | owner, admin |

---

## 4. Alcance Técnico

```
app/
  Enums/
    ZoneStatus.php           ← nuevo
  Models/
    Zone.php                 ← nuevo
  Observers/
    ZoneObserver.php         ← nuevo (ST_Centroid automático)
  Policies/
    ZonePolicy.php           ← nuevo
  Filament/
    Resources/
      ZoneResource.php       ← nuevo
      ZoneResource/
        Pages/
          CreateZone.php
          EditZone.php
          ListZones.php
        RelationManagers/
          AgentsRelationManager.php
    Forms/
      Components/
        LeafletPolygonInput.php  ← nuevo (campo personalizado)
resources/
  views/
    filament/
      forms/
        components/
          leaflet-polygon-input.blade.php  ← nuevo
database/
  migrations/
    xxxx_create_zones_table.php       ← nuevo
    xxxx_create_agent_zone_table.php  ← nuevo (pivote)
tests/
  Feature/
    ZoneResourceTest.php     ← nuevo
  Unit/
    ZoneModelTest.php        ← nuevo
    ZoneSpatialTest.php      ← nuevo
```

**Paquete a instalar:**
```bash
composer require matanyadaev/laravel-eloquent-spatial
```

---

## 5. RFC-015 — Modelo Zone

### 5.1 Campos

| Columna | Tipo PostgreSQL | Tipo PHP / Cast | Requerido | Notas |
| :--- | :--- | :--- | :--- | :--- |
| `id` | `bigint GENERATED ALWAYS AS IDENTITY` | `int` | — | PK |
| `name` | `varchar(150)` | `string` | ✅ | Nombre legible |
| `slug` | `varchar(170) UNIQUE` | `string` | auto | Derivado de `name` |
| `description` | `text NULLABLE` | `?string` | — | |
| `municipality` | `varchar(100)` | `string` | ✅ | Enum de municipios de Querétaro |
| `status` | `varchar(20) CHECK(status IN ('activa','inactiva'))` | `ZoneStatus` | ✅ | Default: `activa` |
| `polygon` | `geometry(Polygon,4326) NULLABLE` | `\MatanYadaev\EloquentSpatial\Objects\Polygon` | — | Dibujado en Filament |
| `center_point` | `geometry(Point,4326) NULLABLE` | `\MatanYadaev\EloquentSpatial\Objects\Point` | auto | ST_Centroid del polígono |
| `created_at` | `timestamptz` | `datetime` | auto | |
| `updated_at` | `timestamptz` | `datetime` | auto | |
| `deleted_at` | `timestamptz NULLABLE` | `datetime` | auto | Soft delete |

### 5.2 Municipios de Querétaro (lista cerrada)

```php
// app/Enums/ZoneStatus.php
enum ZoneStatus: string
{
    case Active   = 'activa';
    case Inactive = 'inactiva';

    public function label(): string
    {
        return match($this) {
            self::Active   => 'Activa',
            self::Inactive => 'Inactiva',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Active   => 'success',
            self::Inactive => 'warning',
        };
    }
}
```

```php
// Constante en Zone o en clase de soporte
const QUERÉTARO_MUNICIPALITIES = [
    'Querétaro', 'Corregidora', 'El Marqués', 'San Juan del Río',
    'Huimilpan', 'Pedro Escobedo', 'Tequisquiapan', 'Ezequiel Montes',
    'Cadereyta de Montes', 'Colón', 'Amealco de Bonfil', 'Jalpan de Serra',
    'Arroyo Seco', 'Landa de Matamoros', 'Peñamiller', 'Pinal de Amoles',
    'San Joaquín', 'Tolimán',
];
```

### 5.3 Slug

Generado automáticamente a partir de `name` usando `Str::slug()`. Persiste aunque el nombre cambie (no se regenera al editar). Si hay colisión, se agrega sufijo numérico: `querétaro → queretaro-2`.

```php
// En ZoneObserver@creating
$zone->slug = Str::unique()->slug($zone->name, '-', Zone::withTrashed());
```

### 5.4 Modelo Zone (esqueleto)

```php
namespace App\Models;

use App\Enums\ZoneStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Objects\Polygon;
use MatanYadaev\EloquentSpatial\Traits\HasSpatial;

#[Fillable([
    'name', 'slug', 'description', 'municipality',
    'status', 'polygon', 'center_point',
])]
class Zone extends Model
{
    use HasFactory, HasSpatial, SoftDeletes;

    protected $attributes = [
        'status' => ZoneStatus::Active->value,
    ];

    protected function casts(): array
    {
        return [
            'status'       => ZoneStatus::class,
            'polygon'      => Polygon::class,
            'center_point' => Point::class,
        ];
    }

    // --- Relaciones ---

    /** Agentes asignados a la zona (muchos-a-muchos) */
    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'agent_zone', 'zone_id', 'agent_id')
                    ->withTimestamps();
    }

    /**
     * Contrato diferido — Épica 4.
     * Reemplazar con $this->hasMany(Property::class) cuando la tabla exista.
     *
     * @return HasMany<Zone, $this>
     */
    public function properties(): HasMany
    {
        return $this->hasMany(self::class, 'id', 'id')->whereRaw('1 = 0');
    }

    // --- Scopes espaciales ---

    public function scopeContainsPoint($query, float $lat, float $lng): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereRaw(
            "ST_Contains(polygon, ST_SetSRID(ST_MakePoint(?, ?), 4326))",
            [$lng, $lat]
        );
    }

    public function scopeIntersectsZone($query, int $zoneId): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereRaw(
            "ST_Intersects(polygon, (SELECT polygon FROM zones WHERE id = ?))",
            [$zoneId]
        );
    }
}
```

---

## 6. RFC-018 — Polígonos PostGIS

### 6.1 Decisión: `geometry` vs `geography`

**Elección: `geometry(Polygon, 4326)` y `geometry(Point, 4326)`.**

| Criterio | `geometry` | `geography` |
| :--- | :--- | :--- |
| Cálculo | Plano (Euclidiano) | Esférico (geodésico) |
| Precisión en área pequeña | Suficiente para municipios (< 4 000 km²) | Innecesaria a esa escala |
| Funciones PostGIS disponibles | Completo (ST_Contains, ST_Centroid, ST_Area…) | Subconjunto más pequeño |
| Rendimiento en GIST | Mejor | Marginal |
| Soporte en `laravel-eloquent-spatial` | Nativo | Limitado |

Querétaro opera a escala municipal. La diferencia de precisión entre `geometry` y `geography` se manifiesta por encima de los 100 km; dentro de un municipio es sub-métrica y no tiene impacto de negocio. `geometry` es la elección correcta aquí.

### 6.2 SRID

`4326` (WGS84) — coordenadas en grados decimales, estándar de GPS y GeoJSON. Leaflet.js usa WGS84 por defecto.

**Nota de precisión (hallazgo menor de auditoría):** con `geometry` + SRID 4326, funciones como `ST_Centroid` y `ST_Area` operan en plano cartesiano sobre grados decimales, no en geodesia esférica. Documentado y aceptado: la distorsión es despreciable a escala municipal (< 4 000 km², ver tabla en 6.1) y se vuelve relevante solo por encima de ~100 km, fuera del alcance de Querétaro. Si una épica futura requiere zonas a escala estatal o nacional, esta decisión debe revisarse.

### 6.3 Índice GIST

```sql
CREATE INDEX zones_polygon_gist     ON zones USING GIST (polygon);
CREATE INDEX zones_center_point_gist ON zones USING GIST (center_point);
```

Los índices GIST aceleran `ST_Contains`, `ST_Intersects`, y consultas KNN (`<->`). Son obligatorios; sin ellos las consultas espaciales hacen full scan.

### 6.4 Center point — cálculo automático

**Estrategia: `ZoneObserver@saved`** — después de cada guardado con polígono, ejecuta:

```php
// app/Observers/ZoneObserver.php
class ZoneObserver
{
    public function creating(Zone $zone): void
    {
        $zone->slug = $this->uniqueSlug($zone->name);
    }

    public function saved(Zone $zone): void
    {
        if ($zone->polygon !== null && $zone->wasChanged('polygon')) {
            DB::statement(
                "UPDATE zones SET center_point = ST_Centroid(polygon) WHERE id = ?",
                [$zone->id]
            );

            // Evita desincronización: el DB::statement actualiza la fila pero
            // deja $zone en memoria con el center_point previo (o null).
            $zone->refresh();
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $n    = 2;

        while (Zone::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$n}";
            $n++;
        }

        return $slug;
    }
}
```

**¿Por qué `saved` y no un trigger de PostgreSQL?**
- Los triggers de BD son invisibles para PHPUnit y requieren un proceso de deploy adicional.
- `saved` se dispara dentro de la misma transacción HTTP que el save de Filament.
- El raw `DB::statement` es más simple que componer un objeto `Point` desde `ST_Centroid` y pasarlo por el paquete espacial.

**¿Cuándo se recalcula?** Solo si `polygon` cambió (`wasChanged('polygon')`). Un edit que solo toque `name` o `status` no recalcula.

### 6.5 Decisión: paquete Eloquent espacial

**Elección: `matanyadaev/laravel-eloquent-spatial` v5.x.**

| Criterio | `laravel-eloquent-spatial` | `laravel-magellan` | SQL crudo |
| :--- | :--- | :--- | :--- |
| Mantenimiento | Activo, Laravel 11/12/13 | Activo, más pesado | N/A |
| Hidratación de geometry | Value objects (`Polygon`, `Point`) | Value objects | Manual |
| API | `$zone->polygon->getCoordinates()` | Similar | `json_decode($raw)` |
| GeoJSON in/out | Nativo | Nativo | Manual |
| Peso | Mínimo | Mayor (más funciones) | Cero |
| Justificación | Cubre 100% de nuestros casos | Sobredimenionado | Verboso y propenso a errores |

El único motivo para elegir SQL crudo sería no querer dependencias. Pero los scopes espaciales ya requieren SQL raw de todas formas; el paquete solo agrega hidratación limpia. Instalar `laravel-magellan` sería sobreingeniería para polígono + punto.

---

## 7. RFC-016 — CRUD ZoneResource (Filament)

### 7.1 Form

```php
Forms\Components\TextInput::make('name')
    ->required()->maxLength(150)
    ->live(onBlur: true),

Forms\Components\TextInput::make('slug')
    ->disabled()->dehydrated(false),  // solo display; el observer lo genera

Forms\Components\Textarea::make('description')
    ->rows(3)->nullable(),

Forms\Components\Select::make('municipality')
    ->options(array_combine(
        Zone::QUERÉTARO_MUNICIPALITIES,
        Zone::QUERÉTARO_MUNICIPALITIES,
    ))
    ->required()->searchable(),

Forms\Components\Select::make('status')
    ->options(collect(ZoneStatus::cases())->pluck('value', 'value'))
    ->required()
    ->default(ZoneStatus::Active->value),

LeafletPolygonInput::make('polygon_geojson')
    ->label('Delimitación del polígono')
    ->columnSpanFull()
    ->hint('Dibuja el polígono sobre el mapa. Centrar en Querétaro.'),
```

### 7.2 Captura de polígono — `LeafletPolygonInput` (campo personalizado)

**Estrategia MVA:** Campo personalizado Filament que extiende `Field`. Renderiza un `<div>` con Leaflet.js (CDN gratuito) y el plugin Leaflet.draw. El polígono dibujado se convierte a GeoJSON y se almacena en un atributo virtual `polygon_geojson` del modelo.

```php
// app/Filament/Forms/Components/LeafletPolygonInput.php
namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Field;

class LeafletPolygonInput extends Field
{
    protected string $view = 'filament.forms.components.leaflet-polygon-input';
}
```

```blade
{{-- resources/views/filament/forms/components/leaflet-polygon-input.blade.php --}}
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="leafletPolygonInput(@entangle($getStatePath()))"
        wire:ignore
        class="rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700"
        style="height: 420px"
    ></div>
</x-dynamic-component>

@push('scripts')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js"></script>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('leafletPolygonInput', (state) => ({
        map: null, drawLayer: null,
        init() {
            try {
                this.map = L.map(this.$el).setView([20.5888, -100.3899], 12); // Querétaro
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap',
                }).addTo(this.map);

                this.drawLayer = new L.FeatureGroup().addTo(this.map);

                const drawControl = new L.Control.Draw({
                    draw: { polygon: true, polyline: false, rectangle: false, circle: false, marker: false, circlemarker: false },
                    edit: { featureGroup: this.drawLayer },
                });
                this.map.addControl(drawControl);

                // Cargar polígono existente
                if (state.get()) {
                    try {
                        const geojson = JSON.parse(state.get());
                        const layer = L.geoJSON(geojson);
                        layer.eachLayer(l => this.drawLayer.addLayer(l));
                        this.map.fitBounds(layer.getBounds());
                    } catch (e) {
                        console.error('LeafletPolygonInput: GeoJSON inicial inválido', e);
                    }
                }

                // Emitir GeoJSON al modelo al dibujar/editar/borrar
                this.map.on(L.Draw.Event.CREATED, (e) => {
                    this.drawLayer.clearLayers();
                    this.drawLayer.addLayer(e.layer);
                    state.set(JSON.stringify(this.drawLayer.toGeoJSON()));
                });
                this.map.on(L.Draw.Event.EDITED, () => {
                    state.set(JSON.stringify(this.drawLayer.toGeoJSON()));
                });
                this.map.on(L.Draw.Event.DELETED, () => {
                    state.set(null);
                });
            } catch (e) {
                // Aislamiento de fallos del CDN/Leaflet: el form sigue usable
                // sin mapa (riesgo R-3) en vez de romper la página completa.
                console.error('LeafletPolygonInput: error inicializando el mapa', e);
            }
        },
    }));
});
</script>
@endpush
```

**Flujo de datos:**

```
Usuario dibuja → GeoJSON string → polygon_geojson (atributo virtual)
   ↓ (en ZoneResource::mutateFormDataBeforeCreate / BeforeSave)
GeoJSON → Polygon object (via Polygon::fromJson()) → $zone->polygon
   ↓ (ZoneObserver@saved)
ST_Centroid(polygon) → center_point
```

```php
// En ZoneResource (CreateZone y EditZone Pages):
protected function mutateFormDataBeforeSave(array $data): array
{
    if (! empty($data['polygon_geojson'])) {
        $data['polygon'] = Polygon::fromJson($data['polygon_geojson']);
    }
    unset($data['polygon_geojson']);

    return $data;
}
```

El atributo virtual `polygon_geojson` se rellena en el form con el GeoJSON del polígono existente al editar:

```php
// En EditZone Page:
protected function mutateFormDataBeforeFill(array $data): array
{
    /** @var Zone $zone */
    $zone = $this->getRecord();

    if ($zone->polygon !== null) {
        $data['polygon_geojson'] = json_encode($zone->polygon->toArray());
    }

    return $data;
}
```

### 7.3 Table

```
Columnas: name, municipality, status (badge), agents_count, created_at
Filtros: status (select), municipality (select)
Acciones: edit, delete (soft), restore (trashed), activate/deactivate (toggle)
Grupos: toggle "Ver papelera" con withTrashed()
```

### 7.4 Validaciones del form

| Campo | Regla |
| :--- | :--- |
| `name` | required, string, max:150 |
| `municipality` | required, in:QUERÉTARO_MUNICIPALITIES |
| `status` | required, in:activa,inactiva |
| `polygon_geojson` | nullable, json, custom rule `ValidGeoJsonPolygon` |
| `description` | nullable, string, max:2000 |

**Regla personalizada `ValidGeoJsonPolygon`:**  
Verifica que el JSON sea `{ "type": "FeatureCollection", "features": [{ "geometry": { "type": "Polygon" } }] }` o equivalente. Además, ejecuta una verificación topológica contra PostGIS antes de aceptar el valor:

```php
// app/Rules/ValidGeoJsonPolygon.php
public function validate(string $attribute, mixed $value, Closure $fail): void
{
    // ... validación estructural del GeoJSON (sin cambios) ...

    $isValid = DB::selectOne(
        'SELECT ST_IsValid(ST_GeomFromGeoJSON(?)) AS valid',
        [$geometryJson]
    );

    if (! $isValid?->valid) {
        $fail('El polígono dibujado es inválido (auto-intersección o geometría mal cerrada). Vuelve a dibujarlo.');
    }
}
```

**Cierre R-2 (hallazgo crítico de auditoría):** la validación estructural por sí sola no detecta polígonos auto-intersectantes. `ST_IsValid(ST_GeomFromGeoJSON(...))` corre en el momento de validación del form, antes de llegar a `ZoneObserver@saved`, evitando que PostGIS lance excepciones en `ST_Centroid` / `ST_Contains` con geometría corrupta.

---

## 8. RFC-017 — Asignación de Agentes

### 8.1 Pivote `agent_zone`

| Columna | Tipo | Notas |
| :--- | :--- | :--- |
| `agent_id` | `bigint FK → users` | `onDelete('cascade')` |
| `zone_id` | `bigint FK → zones` | `onDelete('cascade')` |
| `created_at` | `timestamptz` | `withTimestamps()` |
| `updated_at` | `timestamptz` | |

PK compuesta `(agent_id, zone_id)`. Un agente puede estar en múltiples zonas. Una zona puede tener múltiples agentes. No hay límite de cardinalidad en la capa de datos; las reglas de negocio futuras se aplican en Policy o Service.

### 8.2 Relaciones

```php
// Zone::agents() — ya declarado en RFC-015
$zone->agents()->sync($agentIds);

// User::zones() — se agrega al User SOLO la relación inversa, sin tocar lógica existente:
public function zones(): BelongsToMany
{
    return $this->belongsToMany(Zone::class, 'agent_zone', 'agent_id', 'zone_id')
                ->withTimestamps();
}
```

### 8.3 UI — `AgentsRelationManager`

```php
// app/Filament/Resources/ZoneResource/RelationManagers/AgentsRelationManager.php

class AgentsRelationManager extends RelationManager
{
    protected static string $relationship = 'agents';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('email'),
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(fn ($query) => $query->role('agente')->active()),
            ])
            ->actions([
                Tables\Actions\DetachAction::make(),
            ]);
    }
}
```

`active()` scope en `User` — agrega condición `status = 'activo'` para no asignar agentes suspendidos. Este scope se agrega al modelo `User` de forma aditiva (no rompe Épica 2).

---

## 9. Modelo de Datos

### 9.1 Migración `create_zones_table`

```php
Schema::create('zones', function (Blueprint $table) {
    $table->id();
    $table->string('name', 150);
    $table->string('slug', 170)->unique();
    $table->text('description')->nullable();
    $table->string('municipality', 100);
    $table->string('status', 20)->default('activa');
    $table->geometry('polygon', 'polygon', 4326)->nullable();
    $table->geometry('center_point', 'point', 4326)->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->rawIndex('polygon USING GIST', 'zones_polygon_gist');
    $table->rawIndex('center_point USING GIST', 'zones_center_point_gist');
    $table->index('municipality');
    $table->index('status');
});

// CHECK constraint de status
DB::statement("ALTER TABLE zones ADD CONSTRAINT zones_status_check
    CHECK (status IN ('activa', 'inactiva'))");
```

> **Nota:** `$table->geometry()` con parámetros de tipo y SRID requiere que `laravel-eloquent-spatial` esté instalado; registra un tipo de columna en la gramática de Blueprint.

### 9.2 Migración `create_agent_zone_table`

```php
Schema::create('agent_zone', function (Blueprint $table) {
    $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();
    $table->foreignId('zone_id')->constrained('zones')->cascadeOnDelete();
    $table->timestamps();

    $table->primary(['agent_id', 'zone_id']);
});
```

### 9.3 Diagrama de tablas nuevas

```
zones
├── id (PK)
├── name
├── slug (UNIQUE)
├── description
├── municipality
├── status  [CHECK activa|inactiva]
├── polygon  [geometry(Polygon,4326), GIST]
├── center_point  [geometry(Point,4326), GIST]
├── created_at, updated_at, deleted_at

agent_zone
├── agent_id (FK → users, CASCADE)
├── zone_id  (FK → zones, CASCADE)
├── created_at, updated_at
└── PK(agent_id, zone_id)
```

---

## 10. Seguridad — ZonePolicy

La autorización **no depende de la UI de Filament**. `ZonePolicy` es la única fuente de verdad. Filament llama a la policy automáticamente mediante `$record->can()`.

```php
namespace App\Policies;

use App\Models\User;
use App\Models\Zone;

class ZonePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('zones.manage');
    }

    public function view(User $user, Zone $zone): bool
    {
        return $user->can('zones.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('zones.manage');
    }

    public function update(User $user, Zone $zone): bool
    {
        return $user->can('zones.manage');
    }

    public function delete(User $user, Zone $zone): bool
    {
        return $user->hasRole('owner');
    }

    public function restore(User $user, Zone $zone): bool
    {
        return $user->hasRole('owner');
    }

    public function forceDelete(User $user, Zone $zone): bool
    {
        return false;
    }
}
```

**Matriz de permisos de zonas:**

| Acción | owner | admin | agente |
| :--- | :---: | :---: | :---: |
| Ver listado | ✅ | ✅ | ❌ |
| Ver detalle | ✅ | ✅ | ❌ |
| Crear | ✅ | ✅ | ❌ |
| Editar | ✅ | ✅ | ❌ |
| Activar / inactivar | ✅ | ✅ | ❌ |
| Soft delete | ✅ | ❌ | ❌ |
| Restaurar | ✅ | ❌ | ❌ |
| Force delete | ❌ | ❌ | ❌ |

`zones.manage` está asignado a `owner` y `admin` en `PermissionSeeder` (Épica 2, ya sembrado). No se necesita modificar el seeder.

**Registro del observer:**

```php
// app/Providers/AppServiceProvider.php
use App\Models\Zone;
use App\Observers\ZoneObserver;

public function boot(): void
{
    Zone::observe(ZoneObserver::class);
}
```

**Registro de la policy:**

```php
// app/Providers/AuthServiceProvider.php (o Gate en AppServiceProvider)
Gate::policy(Zone::class, ZonePolicy::class);
```

---

## 11. Estrategia de Testing

### 11.1 Suite

| Archivo | Tipo | Qué verifica |
| :--- | :--- | :--- |
| `ZoneModelTest.php` | Unit | Slug auto, status default, soft delete, relación agents() |
| `ZoneSpatialTest.php` | Unit | ST_Centroid calculado, scopeContainsPoint, scopeIntersectsZone |
| `ZoneResourceTest.php` | Feature | CRUD completo en Filament, validaciones, autorización, asignación agentes |

### 11.2 Criterios de test por QA

| QA | Descripción | Test |
| :--- | :--- | :--- |
| QA-018 | Crear zona → slug generado, status=activa, sin polígono inicial | `ZoneModelTest::test_creating_zone_generates_slug` |
| QA-019 | Activar/inactivar zona → status persiste | `ZoneResourceTest::test_admin_can_toggle_zone_status` |
| QA-020 | Agente no puede crear/editar zonas | `ZoneResourceTest::test_agent_cannot_manage_zones` |
| QA-021 | Guardar polígono → center_point calculado | `ZoneSpatialTest::test_center_point_calculated_on_polygon_save` |
| QA-022 | `scopeContainsPoint` retorna zonas que contienen el punto | `ZoneSpatialTest::test_contains_point_scope` |
| QA-023 | Asignar agente → pivote correcto; desasignar → limpia pivote | `ZoneResourceTest::test_agent_assignment_and_detachment` |
| QA-024 | Soft delete → zona no aparece en listado; restore → reaparece | `ZoneResourceTest::test_soft_delete_and_restore` |
| QA-025 | Tests de Épica 2 siguen en verde tras los cambios aditivos | `php artisan test --filter=UserResource` |

### 11.3 Configuración de test espacial

Los tests de PostGIS requieren que la extensión esté habilitada en `inmo_test`. Verificar en setup o en un `TestCase` base:

```php
// tests/Unit/ZoneSpatialTest.php
protected function setUp(): void
{
    parent::setUp();

    // Verificar que PostGIS esté disponible
    $hasPostgis = DB::select("SELECT COUNT(*) as c FROM pg_extension WHERE extname = 'postgis'");

    if (! $hasPostgis[0]->c) {
        $this->markTestSkipped('PostGIS no está instalado en la base de datos de test.');
    }
}
```

### 11.4 Factory

```php
// database/factories/ZoneFactory.php
Zone::factory()->create([
    'name'         => 'Centro Querétaro',
    'municipality' => 'Querétaro',
    'status'       => ZoneStatus::Active,
]);

// Con polígono (fixture GeoJSON):
Zone::factory()->withPolygon()->create();
// → el state 'withPolygon' carga un GeoJSON de Querétaro desde tests/fixtures/queretaro-centro.geojson
```

---

## 12. Riesgos Técnicos y Decisiones Abiertas

### Riesgos

| ID | Riesgo | Probabilidad | Impacto | Mitigación |
| :--- | :--- | :--- | :--- | :--- |
| R-1 | `php artisan migrate:fresh` falla si PostGIS no está habilitado en `inmo_test` | Alta | Bloquea CI | Documentar en README: `CREATE EXTENSION postgis;` en `inmo_test` antes de migrar |
| R-2 | Polígono auto-intersectante (inválido) guardado sin validación topológica | Media | Inconsistencia de datos | `ValidGeoJsonPolygon` verifica estructura pero no topología. PostGIS puede rechazar con `ERROR: polygon self-intersects`. Atrapar con `DB::beginTransaction` y rollback en `ZoneObserver` |
| R-3 | Leaflet CDN no disponible en entorno sin internet | Baja | UI de mapa sin carga | Documentar en `.env.example`: `LEAFLET_CDN_URL` — futura mejora para usar assets locales |
| R-4 | `laravel-eloquent-spatial` incompatible con futura versión de Laravel | Baja | Deuda de actualización | Paquete activamente mantenido. Alternativa siempre es SQL crudo (los scopes lo permiten) |
| R-5 | Solapamiento de zonas no controlado en Épica 3 | Alta | Confusión operacional | Diferido. En Épica 4/5 añadir constraint de no-solapamiento vía regla de negocio + `ST_Overlaps` |

### Decisiones abiertas

| ID | Decisión | Bloquea | Dueño |
| :--- | :--- | :--- | :--- |
| D-1 | ¿Los agentes pueden verse a sí mismos y a sus zonas desde el panel? | Épica 5 (panel agente) | Kristian |
| D-2 | ¿Límite de agentes por zona o de zonas por agente? | No bloquea Épica 3 | Negocio |
| D-3 | ¿Validar solapamiento entre zonas como error o como advertencia? | No bloquea Épica 3 | Kristian |
| D-4 | ¿Tiles de mapa de pago (Mapbox) o OSM gratuito en producción? | Épica 6 (frontend público) | Kristian |

---

## 13. Criterios de Aceptación (QA-018 → QA-025)

| ID | Escenario | Resultado esperado | Verificado por |
| :--- | :--- | :--- | :--- |
| QA-018 | Owner crea zona "Centro Histórico" en municipio "Querétaro" | Zona guardada, `slug = 'centro-historico'`, `status = activa` | Test + manual |
| QA-019 | Admin cambia status de zona activa a inactiva | Badge cambia a "Inactiva" en tabla; `status = 'inactiva'` en BD | Test + manual |
| QA-020 | Usuario con rol `agente` intenta acceder a ZoneResource | Recibe 403 / redireccionado; no ve el recurso en sidebar | Test |
| QA-021 | Admin guarda zona con polígono dibujado | `polygon IS NOT NULL` y `center_point = ST_Centroid(polygon)` en BD | Test + `psql` |
| QA-022 | Consulta `Zone::containsPoint(lat, lng)` con punto dentro de zona | Retorna la zona correcta | Test |
| QA-023 | Admin asigna agente A y agente B a zona | `agent_zone` tiene dos filas; `zone->agents()->count() === 2` | Test + manual |
| QA-024 | Owner soft-deletes zona → listado normal no la muestra; Owner restaura → vuelve | `deleted_at` poblado / nulo | Test + manual |
| QA-025 | Suite completa de Épica 2 (`UserResourceTest`) sin cambios | 25 tests / 144 assertions en verde | `php artisan test` |

---

## 14. Plan de Implementación por Lotes (Codex)

### Lote A — Fundación del modelo (no-espacial)

**Objetivo:** El modelo `Zone` con todos los campos no-espaciales es creatable y testeable. La migración corre en `inmo_test`.

1. Instalar paquete: `composer require matanyadaev/laravel-eloquent-spatial`
2. Crear `app/Enums/ZoneStatus.php`
3. Crear migración `create_zones_table` (campos básicos + geometry columns + índices GIST + CHECK constraint)
4. Crear `App\Models\Zone` con casts, fillable, relaciones (`agents()`, `properties()` diferida)
5. Crear `database/factories/ZoneFactory.php`
6. Crear `app/Observers/ZoneObserver.php` (solo slug en `creating`; body de `saved` vacío aún)
7. Registrar observer en `AppServiceProvider`
8. Escribir `ZoneModelTest`: slug, status default, soft delete, relación agents

**QA gate:** `php artisan test --filter=ZoneModelTest` → verde.

---

### Lote B — Polígono y scopes espaciales

**Objetivo:** El polígono se guarda, el center_point se calcula y los scopes espaciales funcionan.

1. Completar `ZoneObserver@saved` con `DB::statement(ST_Centroid)` 
2. Agregar `scopeContainsPoint` y `scopeIntersectsZone` a `Zone`
3. Crear `tests/fixtures/queretaro-centro.geojson` (polígono real de prueba)
4. Agregar state `withPolygon()` a `ZoneFactory`
5. Escribir `ZoneSpatialTest`: center_point calculado, scopes retornan resultados correctos

**QA gate:** `php artisan test --filter=ZoneSpatialTest` → verde; verificar en `psql` que `center_point IS NOT NULL`.

---

### Lote C — ZoneResource Filament (CRUD sin mapa)

**Objetivo:** CRUD completo con autorización, validaciones y filtros. El polígono se captura como campo `polygon_geojson` textarea JSON (MVP mínimo antes de agregar mapa).

1. Crear `App\Policies\ZonePolicy` y registrarla
2. Crear `ZoneResource` con form (sin `LeafletPolygonInput` aún — usar `Textarea` para GeoJSON), table, filtros, acciones
3. Implementar `mutateFormDataBeforeSave` y `mutateFormDataBeforeFill` para conversión GeoJSON ↔ Polygon
4. Implementar `ValidGeoJsonPolygon` rule
5. Agregar `active()` scope a `User` (aditivo)
6. Escribir `ZoneResourceTest` (create, edit, delete, restore, status toggle, auth 403 para agente)

**QA gate:** `php artisan test --filter=ZoneResourceTest` → verde para QA-018, QA-019, QA-020, QA-024.

---

### Lote D — Mapa Leaflet + Asignación de Agentes

**Objetivo:** El polígono se dibuja en mapa interactivo. Los agentes se asignan desde la UI.

1. Crear `LeafletPolygonInput` (clase PHP) en `app/Filament/Forms/Components/`
2. Crear blade `resources/views/filament/forms/components/leaflet-polygon-input.blade.php`
3. Reemplazar `Textarea` de GeoJSON en el form de `ZoneResource` por `LeafletPolygonInput`
4. Crear migración `create_agent_zone_table`
5. Agregar `zones()` BelongsToMany a `User` (aditivo)
6. Crear `AgentsRelationManager`
7. Registrar `AgentsRelationManager` en `ZoneResource::$relationManagers`
8. Ampliar `ZoneResourceTest`: test de asignación/desasignación de agentes (QA-023)

**QA gate:** Test QA-021, QA-023 en verde. Verificación manual: abrir Filament, dibujar polígono, guardar, confirmar en BD.

---

### Lote E — Tests completos, regresión y cleanup

**Objetivo:** Suite completa pasa. Épica 2 sin regresiones. Rama lista para PR.

1. Revisar cobertura: QA-018 → QA-025 todos en verde
2. Ejecutar suite completa: `php artisan test` — confirmar QA-025 (Épica 2 sin cambios)
3. Ejecutar Pint: `./vendor/bin/pint`
4. Revisar comentarios en `ZonePolicy` y `ZoneObserver`
5. Verificar `.env.example` incluye `LEAFLET_CDN_URL=` como placeholder documentado
6. Commit y push

**QA gate:** `php artisan test` → todos los tests en verde. `./vendor/bin/pint --test` → 0 errores.

---

## 15. Checklist de Cierre Técnico

### Pre-commit (Edgar)

- [ ] `./vendor/bin/pint` → 0 errores
- [ ] `php artisan test` → todos en verde (incluye Épicas 1 y 2)
- [ ] `php artisan migrate:fresh --seed` en `inmo_db` → sin errores
- [ ] Verificar en psql: `SELECT name, center_point FROM zones;` → `center_point` no nulo para zonas con polígono
- [ ] Revisión manual en Filament: crear zona, dibujar polígono, asignar agente, inactivar, soft-delete, restaurar

### Pre-merge (Sebastián — QA)

- [ ] QA-018: Crear zona → slug generado ✓
- [ ] QA-019: Toggle status → badge actualizado ✓
- [ ] QA-020: Agente → 403 en ZoneResource ✓
- [ ] QA-021: Polígono guardado → center_point calculado ✓
- [ ] QA-022: Scope containsPoint → retorna zona correcta ✓
- [ ] QA-023: Asignar / desasignar agentes ✓
- [ ] QA-024: Soft delete y restore ✓
- [ ] QA-025: Suite Épica 2 sin regresiones ✓

### Post-merge (Kristian)

- [ ] Merge `feature/epica-3-zonas-comerciales` → `develop`
- [ ] Crear tag: `v0.3.0-zonas-comerciales`
- [ ] Actualizar estado de este documento a `✅ IMPLEMENTADO`
- [ ] Documentar resolución de D-1 (acceso panel agente) antes de iniciar Épica 4

---

## 16. Cierre Técnico del Diseño

Auditoría de diseño realizada por Gemini (`docs/audits/epica-3-auditoria-diseno.md`, espejo en engram `audit:epica-3:diseno`). Veredicto original: **Aprobado con observaciones**. Las observaciones obligatorias fueron aplicadas a este documento; las opcionales y las preguntas abiertas sin impacto en el alcance de esta épica quedan diferidas explícitamente.

### 16.1 Decisiones cerradas

| # | Decisión | Cierre |
| :--- | :--- | :--- |
| 1 | `Zone` con `slug` único (con sufijo numérico ante colisión, incluso contra soft-deleted) y `SoftDeletes` | Cerrado — §5.3, §5.4 |
| 2 | Columnas geoespaciales `geometry(Polygon,4326)` y `geometry(Point,4326)`, ambas con índice GIST | Cerrado — §6.2, §6.3, §9.1 |
| 3 | Estrategia PostGIS-en-Eloquent: paquete `matanyadaev/laravel-eloquent-spatial` v5.x (vs. `laravel-magellan` o SQL crudo) | Cerrado — §6.5 |
| 4 | Estrategia de edición de polígono en Filament: campo personalizado `LeafletPolygonInput` (Leaflet.js + Leaflet.draw vía Alpine, atributo virtual `polygon_geojson`) con manejo de excepciones JS aislado | Cerrado — §7.2 (actualizado tras auditoría) |
| 5 | Pivote Zona ↔ Agentes: tabla `agent_zone`, PK compuesta, `BelongsToMany` en ambos sentidos | Cerrado — §8 |
| 6 | Relación Zona ↔ Inmuebles: contrato diferido (stub `properties()` sin tabla real) hasta Épica 4 | Cerrado como diferido — §1, §5.4 |
| 7 | Autorización: `ZonePolicy` como única fuente de verdad (`zones.manage` para CRUD, `owner` exclusivo para delete/restore) | Cerrado — §10 |

### 16.2 Cambios aplicados desde la auditoría

1. **R-2 (crítico):** `ValidGeoJsonPolygon` ahora ejecuta `ST_IsValid(ST_GeomFromGeoJSON(...))` contra PostGIS además de la validación estructural del JSON, rechazando polígonos auto-intersectantes antes de persistir (§7.4).
2. **Desincronización de modelo (crítico):** `ZoneObserver@saved` ahora llama a `$zone->refresh()` después del `DB::statement` que calcula `center_point`, evitando que el objeto en memoria quede con el valor previo o `null` (§6.4).
3. **Manejo de excepciones JS (recomendación obligatoria):** la inicialización de Leaflet en `leaflet-polygon-input.blade.php` está envuelta en `try/catch`, de forma que un fallo de CDN o de parsing no rompe la página completa del formulario (§7.2).
4. **Precisión SRID 4326 (hallazgo menor):** se documentó explícitamente que los cálculos son planos y la precisión decae fuera de escala municipal (§6.2).

### 16.3 Puntos diferidos / fuera de alcance

| # | Punto | Razón | Dueño / Épica |
| :--- | :--- | :--- | :--- |
| 1 | Validación de no-solapamiento entre zonas (D-3, R-5) | Requiere definición de negocio sobre si es error o advertencia | Kristian — Épica 4/5 |
| 2 | Subida de archivo `.geojson` como alternativa a dibujar | Mejora opcional de UX, no bloquea CRUD funcional | Backlog |
| 3 | Campo `color` hexadecimal por zona | Mejora visual opcional, sin consumidor en esta épica (frontend de mapa es Épica 6) | Backlog |
| 4 | Descarga local de assets Leaflet (independencia de CDN, R-3) | Mitigación ya documentada como aceptable para este alcance; se revisita si el entorno productivo restringe internet | Backlog |
| 5 | Acceso de agentes a su propio panel de zonas (D-1) | Depende del diseño del panel de agente | Kristian — Épica 5 |
| 6 | Límite de cardinalidad agente↔zona o zona↔agente (D-2) | Sin regla de negocio definida; no bloquea el CRUD actual | Negocio |
| 7 | Tiles de mapa de pago vs. OSM gratuito (D-4) | Decisión de producto para el frontend público, no aplica al panel Filament | Kristian — Épica 6 |
| 8 | Compatibilidad exacta de `matanyadaev/laravel-eloquent-spatial` v5.x con el sistema de tipos de Laravel 13 | Riesgo ya cubierto por R-4 (alternativa: SQL crudo); se confirma empíricamente al ejecutar Lote A, no requiere decisión de diseño previa | Lote A (Codex) |

### 16.4 Verificación de criterios de aceptación

Los 8 criterios (QA-018 → QA-025, §13) están redactados como escenario + resultado verificable en BD o UI, cada uno mapeado 1:1 a un test concreto en §11.2. No se identificaron criterios ambiguos o no verificables. QA-025 actúa como gate de regresión sobre la Épica 2.

### 16.5 Verificación del plan de lotes

El plan de lotes A→E (§14) es incremental y cada lote tiene su propio QA gate ejecutable antes de avanzar:

- **A** (fundación no-espacial) no depende de PostGIS para pasar su gate.
- **B** (polígono + scopes) depende solo de A.
- **C** (CRUD Filament sin mapa) depende de A y B, pero deliberadamente evita el mapa para aislar fallos de autorización/validación de fallos de UI geoespacial.
- **D** (mapa + agentes) depende de C.
- **E** (regresión + cleanup) depende de todos los anteriores y verifica explícitamente que Épica 2 no se rompe (QA-025).

No hay dependencias circulares ni lotes que requieran adelantar trabajo de un lote posterior.

### 16.6 Estado final

**✅ Aprobado para implementación.**

---

*Fin del documento de diseño — Épica 3 Zonas Comerciales*
