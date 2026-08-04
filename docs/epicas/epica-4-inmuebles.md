# Épica 4 — Inmuebles

**Proyecto:** New Hauz — Plataforma Inmobiliaria\
**Estado:** ✅ APROBADO PARA IMPLEMENTACIÓN\
**Rama base:** `develop`\
**Rama de trabajo:** `feature/epica-4-inmuebles`\
**Responsable principal:** Kristian\
**Arquitecto:** Edgar\
**QA:** Sebastián\
**Revisión:** Kristian\
**Diseño generado:** 19 de Junio, 2026\
**Auditoría aplicada:** 19 de Junio, 2026 (Gemini CLI — veredicto original: Rechazado; correcciones aplicadas en §20)

---

## 1. Contexto y Dependencias

Esta épica consume los contratos cerrados por las Épicas 1, 2 y 3. **Es la primera épica que materializa las tablas reales detrás de los contratos diferidos `properties`** declarados en `User` (Épica 2) y `Zone` (Épica 3).

| Contrato consumido | RFC / Épica origen | Estado |
| :--- | :--- | :--- |
| Laravel 13.x + PHP 8.3 | RFC-001 / Épica 1 | ✅ Activo |
| PostgreSQL 18 + PostGIS 3.6 | RFC-002 / RFC-003 / Épica 1 | ✅ Activo |
| Filament v3.3 + panel `/admin` | RFC-004 / Épica 1 | ✅ Activo |
| Livewire 3.8 | RFC-005 / Épica 1 | ✅ Activo |
| `spatie/laravel-permission` con roles `owner`, `admin`, `agente` | RFC-006 / Épica 1 | ✅ Activo |
| `spatie/laravel-medialibrary` 11.x | RFC-007 / Épica 1 | ✅ Activo (sin consumidores aún) |
| `App\Models\User` con `HasRoles`, `SoftDeletes`, `UserStatus`, `isActive()`, `scopeActive()` | RFC-011 / Épica 2 | ✅ Activo |
| Permiso `properties.manage` sembrado (owner, admin, agente) | RFC-012 / Épica 2 | ✅ Activo |
| `UserPolicy` como patrón de autorización backend-first | RFC-012 / Épica 2 | ✅ Activo |
| `App\Models\Zone` (`name`, `slug`, `municipality`, `status`, `polygon`) + `ZoneStatus` | RFC-015 / Épica 3 | ✅ Activo |
| `User::zones()` ↔ `Zone::agents()` (pivote `agent_zone`) | RFC-017 / Épica 3 | ✅ Activo |
| `ZonePolicy` (`zones.manage`) | RFC-016 / Épica 3 | ✅ Activo |
| `PropertyObserver`/slug pattern de referencia (`ZoneObserver`) | RFC-015 / Épica 3 | ✅ Activo |

### Contratos diferidos que esta épica ACTIVA (regla de oro)

Toda extensión es **aditiva**. No se modifican migraciones existentes de `users`, `zones`, `agent_zone` ni `media`. Las dos relaciones diferidas se activan **reemplazando únicamente el cuerpo del método** — la firma pública ya es el contrato que el resto del sistema espera.

| Relación | Estado actual (verificado en código) | Acción en esta épica |
| :--- | :--- | :--- |
| `User::properties()` — `app/Models/User.php:84` | `return $this->hasMany(self::class, 'id', 'id')->whereRaw('1 = 0');` + PHPDoc `@return HasMany<User, $this>` | Reemplazar cuerpo por `hasMany(Property::class, 'agent_id')` **y** corregir PHPDoc/comentario a `@return HasMany<Property, $this>`. Ver §5.6. |
| `Zone::properties()` — `app/Models/Zone.php:160` | `return $this->hasMany(self::class, 'id', 'id')->whereRaw('1 = 0');` + PHPDoc `@return HasMany<Zone, $this>` | Reemplazar cuerpo por `hasMany(Property::class)` (FK `zone_id`) **y** corregir PHPDoc/comentario a `@return HasMany<Property, $this>`. Ver §5.6. |

> El comentario actual en `User::properties()` dice _"when Epic 3 creates the model/table"_ — es una etiqueta heredada imprecisa. La tabla `properties` nace en **esta** épica (Épica 4); ambos stubs se activan aquí.

**Alcance preciso de la excepción aditiva (corrección auditoría 8.6):** la activación toca **tres** elementos por método —cuerpo, comentario inline y PHPDoc `@return`—. Dejar el PHPDoc genérico (`HasMany<User…>` / `HasMany<Zone…>`) produciría tipos falsos que **Larastan** marcaría. Ningún otro elemento de `User`/`Zone` se modifica; tampoco se altera la columna `zones.polygon` (que es `NOT NULL` en producción — ver §8.3 y checklist auditoría 11.9).

**No se toca ningún archivo de las Épicas 1, 2 y 3 fuera de las dos activaciones documentadas en §5.6** (cuerpo + PHPDoc) **y el registro aditivo de eventos de `Zone` documentado en §8.4** (sin modificar `ZoneObserver` ni la tabla `zones`).

---

## 2. Objetivos

### Lo que esta épica entrega

- Enums de dominio `OperationType` (`venta`/`renta`), `PropertyType` (`casa`, `departamento`, `terreno`, `local`, `oficina`, `bodega`) y `PropertyStatus` (`borrador`, `publicado`, `pausado`, `vendido`, `rentado`).
- Modelo `Property` con slug único, soft delete, relaciones `zone()` y `agent()`, integración `HasMedia` y características N:N.
- Migración `create_properties_table` con todos los campos de RFC-019, FKs `zone_id`/`agent_id` (`nullOnDelete`), campos SEO y soft delete.
- Galería de imágenes con Media Library: colección `cover` (imagen principal, obligatoria para publicar) y `gallery` (múltiple, ordenable), con conversiones `thumb` y `web`.
- Características dinámicas modeladas como **catálogo `features` + pivote `property_feature`** (N:N), con seeder base y CRUD en Filament.
- Máquina de estados comerciales con transiciones validadas en capa de dominio (`PropertyStatusService`), no en la UI.
- Generación automática de slug (`zona-tipo-título`) con unicidad y actualización controlada.
- SEO básico: `meta_title`, `meta_description`, `canonical_url` y Open Graph, con fallbacks automáticos.
- `PropertyPolicy` backend-first sobre `properties.manage`, con scoping del agente a sus inmuebles/zonas.
- `PropertyResource` y `FeatureResource` en Filament con CRUD, filtros, badges y acciones controladas por Policy.
- Suite de tests QA-026 → QA-051, incluyendo seguridad del agente, invariante durable de publicación, regresión de Épicas 1/2/3 y activación de los contratos `properties()`.

### Lo que esta épica NO entrega

- **Frontend público** del catálogo (home, buscador, ficha de detalle) → Épica 6 (RFC-029, RFC-034, RFC-035).
- **Filtros avanzados** de búsqueda pública → Épica 6 (RFC-042).
- **Métricas comerciales** por inmueble/zona (leads, conversión, vistas) → Épica 7 (RFC-040, RFC-046).
- **Propiedades destacadas** / featured → Épica 7 (RFC-041).
- **Mapa interactivo / Google Maps** sobre el inmueble → Épica 7 (RFC-043). Esta épica consume `zone_id`; no renderiza geometría.
- **SEO avanzado** (schema.org / JSON-LD, sitemaps dinámicos) → Épica 7 (RFC-045). Aquí solo metadatos básicos y Open Graph.
- **Leads** y captura de contacto sobre el inmueble → Épica 5 (RFC-025 a RFC-028).
- API REST de inmuebles (monolito; Filament es la interfaz de gestión).

---

## 3. Alcance Funcional

| # | Funcionalidad | Actor |
| :--- | :--- | :--- |
| F-1 | Crear inmueble (queda en `borrador`) | owner, admin, agente |
| F-2 | Editar datos de un inmueble | owner, admin (todos); agente (solo los suyos / de sus zonas) |
| F-3 | Ver listado y detalle de inmuebles | owner, admin (todos); agente (solo los suyos / de sus zonas) |
| F-4 | Asignar zona y agente responsable | owner, admin |
| F-5 | Gestionar galería (subir, ordenar, eliminar, fijar principal) | owner, admin, agente (sobre los suyos) |
| F-6 | Asignar características desde catálogo | owner, admin, agente (sobre los suyos) |
| F-7 | Publicar inmueble (requiere imagen principal + zona) | owner, admin, agente (sobre los suyos) |
| F-8 | Pausar / republicar inmueble | owner, admin, agente (sobre los suyos) |
| F-9 | Marcar como vendido (operación `venta`) / rentado (operación `renta`) | owner, admin, agente (sobre los suyos) |
| F-10 | Editar SEO por inmueble (`meta_title`, `meta_description`, `canonical`) | owner, admin, agente (sobre los suyos) |
| F-11 | Soft-delete / restaurar inmueble | owner, admin |
| F-12 | Administrar catálogo de características (`features`) | owner, admin |

---

## 4. Alcance Técnico

```
app/
├── Enums/
│   ├── OperationType.php              ← Nuevo
│   ├── PropertyType.php               ← Nuevo
│   └── PropertyStatus.php             ← Nuevo
├── Models/
│   ├── Property.php                   ← Nuevo
│   ├── Feature.php                    ← Nuevo
│   ├── User.php                       ← ACTIVAR properties() (aditivo, solo cuerpo)
│   └── Zone.php                       ← ACTIVAR properties() (aditivo, solo cuerpo)
├── Observers/
│   └── PropertyObserver.php           ← Nuevo (slug en creating)
├── Policies/
│   ├── PropertyPolicy.php             ← Nuevo
│   └── FeaturePolicy.php              ← Nuevo
├── Services/
│   └── PropertyStatusService.php      ← Nuevo (transiciones de estado)
├── Filament/Resources/
│   ├── PropertyResource.php           ← Nuevo
│   ├── PropertyResource/Pages/
│   │   ├── ListProperties.php
│   │   ├── CreateProperty.php
│   │   └── EditProperty.php
│   ├── FeatureResource.php            ← Nuevo
│   └── FeatureResource/Pages/
│       ├── ListFeatures.php
│       ├── CreateFeature.php
│       └── EditFeature.php
database/
├── migrations/
│   ├── xxxx_create_properties_table.php          ← CREATE
│   ├── xxxx_create_features_table.php            ← CREATE
│   └── xxxx_create_property_feature_table.php    ← CREATE (pivote)
├── factories/
│   ├── PropertyFactory.php            ← Nuevo
│   └── FeatureFactory.php             ← Nuevo
└── seeders/
    └── FeatureSeeder.php              ← Nuevo
tests/
├── Unit/
│   ├── PropertyEnumsTest.php
│   ├── PropertySlugTest.php
│   ├── PropertyScopesTest.php
│   └── PropertyStatusServiceTest.php
└── Feature/
    ├── PropertyCrudTest.php
    ├── PropertyPublicationTest.php
    ├── PropertyGalleryTest.php
    ├── PropertyFeaturesTest.php
    ├── PropertySeoTest.php
    └── Regression/Epica123RegressionTest.php
```

### Archivos que NO se tocan

```
app/Models/User.php   ← SOLO el cuerpo de properties() (§5.6). Resto intacto.
app/Models/Zone.php   ← SOLO el cuerpo de properties() (§5.6). Resto intacto.
database/migrations/* de users, zones, agent_zone, media   ← intactas
app/Policies/UserPolicy.php, ZonePolicy.php                 ← intactas
database/seeders/PermissionSeeder.php                       ← properties.manage ya sembrado
```

---

## 5. RFC-019 — Modelo `Property`

### 5.1 Decisiones de arquitectura (CERRADAS)

| Decisión | Resolución |
| :--- | :--- |
| Tipo de `operation_type`, `property_type`, `status` | **String con cast a enum + CHECK constraint.** Consistente con `UserStatus`/`ZoneStatus` (Épicas 2/3). Más flexible que `$table->enum()` ante migraciones futuras. |
| `price` | **`decimal(14,2)`** con cast `decimal:2`. Soporta valores en MXN sin pérdida de precisión flotante. |
| `bathrooms` | **`decimal(3,1)`.** Permite medios baños (`2.5`), convención común del mercado mexicano. |
| `bedrooms`, `parking_spaces` | **`unsignedSmallInteger` nullable.** Enteros simples. |
| `land_area`, `construction_area` | **`decimal(10,2)` nullable** (m²). |
| Relación con zona | **`zone_id` FK → `zones`, `nullOnDelete`.** Borrar una zona NO borra inmuebles; los deja sin zona (ver R-2). |
| Relación con agente | **`agent_id` FK → `users`, `nullOnDelete`.** Reasignar/eliminar un agente NO borra inmuebles (ver R-3). |
| Imágenes | **Spatie MediaLibrary** (`cover` single-file + `gallery` múltiple), no columnas de ruta. RFC-020. |
| Características | **Catálogo `features` + pivote `property_feature` (N:N)**, no JSON. Justificación en §7.1. |
| Slug | **Generado por `PropertySlugGenerator`** (invocado desde `PropertyObserver@creating`), `zona-tipo-título`, único contra `withTrashed()`, con reintento ante violación del índice único (concurrencia). No se regenera al editar salvo acción confirmada. RFC-023, §9. |
| Soft delete | **`SoftDeletes` trait** + `deleted_at`. |
| Estados | **Enum `PropertyStatus` + `PropertyStatusService`** (transacción + `lockForUpdate`) como única puerta de transición (RFC-022, §8). |
| `status` y `slug` en `$fillable` | **Excluidos de asignación masiva** (corrección auditoría 2.4 / 8.5). `status` solo cambia vía `PropertyStatusService`; `slug` solo vía generador/acción. `fill()`/`update()` sobre una instancia no pueden publicar ni cambiar el slug. Los updates masivos por query quedan prohibidos porque Laravel omite `$fillable` y eventos. |
| Invariante de publicación | **Durable, no solo precondición** (corrección auditoría 2.1 / 8.1). Un inmueble `publicado` no puede perder portada ni zona, y la baja/inactivación de su zona lo **pausa automáticamente** en transacción (§8.4). |
| Precedencia agente ↔ zona | **`agent_id` manda** (corrección auditoría 2.3 / 8.4). Si el inmueble tiene responsable, solo ese agente (más owner/admin) lo gestiona; la zona concede acceso únicamente a inmuebles **sin responsable** (§12). |
| Integridad numérica | **CHECK constraints + reglas backend** (corrección auditoría 3.4 / 8.7): `price > 0`, `bathrooms ≥ 0`, `bedrooms ≥ 0`, `parking_spaces ≥ 0`, `land_area ≥ 0`, `construction_area ≥ 0`. La UI (`minValue`) no es la garantía. |

### 5.2 Enum `OperationType`

```php
// app/Enums/OperationType.php
namespace App\Enums;

enum OperationType: string
{
    case Venta = 'venta';
    case Renta = 'renta';

    public function label(): string
    {
        return match ($this) {
            self::Venta => 'Venta',
            self::Renta => 'Renta',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Venta => 'success',
            self::Renta => 'info',
        };
    }
}
```

### 5.3 Enum `PropertyType`

```php
// app/Enums/PropertyType.php
namespace App\Enums;

enum PropertyType: string
{
    case Casa         = 'casa';
    case Departamento = 'departamento';
    case Terreno      = 'terreno';
    case Local        = 'local';
    case Oficina      = 'oficina';
    case Bodega       = 'bodega';

    public function label(): string
    {
        return match ($this) {
            self::Casa         => 'Casa',
            self::Departamento => 'Departamento',
            self::Terreno      => 'Terreno',
            self::Local        => 'Local comercial',
            self::Oficina      => 'Oficina',
            self::Bodega       => 'Bodega',
        };
    }
}
```

### 5.4 Enum `PropertyStatus` (estados comerciales — RFC-022)

```php
// app/Enums/PropertyStatus.php
namespace App\Enums;

enum PropertyStatus: string
{
    case Borrador  = 'borrador';
    case Publicado = 'publicado';
    case Pausado   = 'pausado';
    case Vendido   = 'vendido';
    case Rentado   = 'rentado';

    public function label(): string
    {
        return match ($this) {
            self::Borrador  => 'Borrador',
            self::Publicado => 'Publicado',
            self::Pausado   => 'Pausado',
            self::Vendido   => 'Vendido',
            self::Rentado   => 'Rentado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Borrador  => 'gray',
            self::Publicado => 'success',
            self::Pausado   => 'warning',
            self::Vendido   => 'info',
            self::Rentado   => 'info',
        };
    }

    /** Único estado visible públicamente (consumido por Épica 6). */
    public function isPublic(): bool
    {
        return $this === self::Publicado;
    }

    /** @return array<int, self> Transiciones permitidas desde este estado. */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Borrador  => [self::Publicado],
            self::Publicado => [self::Pausado, self::Vendido, self::Rentado],
            self::Pausado   => [self::Publicado, self::Vendido, self::Rentado],
            self::Vendido   => [self::Borrador],   // reapertura controlada
            self::Rentado   => [self::Borrador],   // reapertura controlada
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
```

### 5.5 Migración `create_properties_table`

```php
// database/migrations/xxxx_create_properties_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();

            // --- Datos descriptivos ---
            $table->string('title', 180);
            $table->string('slug', 200)->unique();
            $table->text('description')->nullable();

            // --- Clasificación (string + cast enum) ---
            $table->string('operation_type', 20);   // venta | renta
            $table->string('property_type', 30);     // casa | departamento | ...
            $table->string('status', 20)->default('borrador');

            // --- Atributos físicos ---
            $table->decimal('price', 14, 2);
            $table->unsignedSmallInteger('bedrooms')->nullable();
            $table->decimal('bathrooms', 3, 1)->nullable();
            $table->unsignedSmallInteger('parking_spaces')->nullable();
            $table->decimal('land_area', 10, 2)->nullable();
            $table->decimal('construction_area', 10, 2)->nullable();

            // --- Relaciones (nullOnDelete: el inmueble sobrevive) ---
            $table->foreignId('zone_id')->nullable()
                ->constrained('zones')->nullOnDelete();
            $table->foreignId('agent_id')->nullable()
                ->constrained('users')->nullOnDelete();

            // --- SEO básico (RFC-024) ---
            $table->string('meta_title', 180)->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->string('canonical_url', 255)->nullable();

            $table->timestamps();
            $table->softDeletes();

            // --- Índices de filtrado (Filament + futuro frontend) ---
            // (status, operation_type): su prefijo cubre las consultas por status solo
            // (scopePublished), por eso NO se duplica un índice simple de status.
            $table->index(['status', 'operation_type']);
            $table->index('property_type');
            $table->index('price');
            // FK: PostgreSQL NO indexa columnas referenciantes automáticamente.
            // El alcance del agente filtra por agent_id y zone_id (§13.1).
            $table->index('zone_id');
            $table->index('agent_id');
        });

        // CHECK constraints de enums — coherentes con el patrón de Épicas 2/3.
        DB::statement("ALTER TABLE properties ADD CONSTRAINT properties_operation_type_check
            CHECK (operation_type IN ('venta', 'renta'))");
        DB::statement("ALTER TABLE properties ADD CONSTRAINT properties_property_type_check
            CHECK (property_type IN ('casa', 'departamento', 'terreno', 'local', 'oficina', 'bodega'))");
        DB::statement("ALTER TABLE properties ADD CONSTRAINT properties_status_check
            CHECK (status IN ('borrador', 'publicado', 'pausado', 'vendido', 'rentado'))");

        // CHECK constraints numéricos (corrección auditoría 3.4 / 8.7).
        // La integridad NO se delega a Filament: la BD es la última garantía.
        DB::statement("ALTER TABLE properties ADD CONSTRAINT properties_price_positive
            CHECK (price > 0)");
        DB::statement("ALTER TABLE properties ADD CONSTRAINT properties_non_negative_metrics
            CHECK (
                (bedrooms IS NULL OR bedrooms >= 0) AND
                (bathrooms IS NULL OR bathrooms >= 0) AND
                (parking_spaces IS NULL OR parking_spaces >= 0) AND
                (land_area IS NULL OR land_area >= 0) AND
                (construction_area IS NULL OR construction_area >= 0)
            )");
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
```

### 5.6 Modelo `Property` y activación de contratos diferidos

```php
// app/Models/Property.php
namespace App\Models;

use App\Enums\OperationType;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\ZoneStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Property extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, SoftDeletes;

    // 'status' y 'slug' NO son mass-assignable (corrección auditoría 2.4 / 8.5):
    //   - status: solo cambia vía PropertyStatusService (transición validada).
    //   - slug:   solo lo escribe PropertySlugGenerator / acción confirmada.
    // fill()/update() sobre la instancia no pueden publicar ni reescribir el slug.
    protected $fillable = [
        'title', 'description',
        'operation_type', 'property_type',
        'price', 'bedrooms', 'bathrooms', 'parking_spaces',
        'land_area', 'construction_area',
        'zone_id', 'agent_id',
        'meta_title', 'meta_description', 'canonical_url',
    ];

    protected $attributes = [
        'status' => PropertyStatus::Borrador->value,
    ];

    protected function casts(): array
    {
        return [
            'operation_type'    => OperationType::class,
            'property_type'     => PropertyType::class,
            'status'            => PropertyStatus::class,
            'price'             => 'decimal:2',
            'bathrooms'         => 'decimal:1',
            'land_area'         => 'decimal:2',
            'construction_area' => 'decimal:2',
        ];
    }

    // --- Relaciones ---

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'property_feature')
            ->withTimestamps();
    }

    // --- Scopes ---

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', PropertyStatus::Publicado->value);
    }

    public function scopeByZone(Builder $query, int $zoneId): Builder
    {
        return $query->where('zone_id', $zoneId);
    }

    public function scopeByOperation(Builder $query, OperationType $operation): Builder
    {
        return $query->where('operation_type', $operation->value);
    }

    /**
     * Alcance de visibilidad/gestión de un usuario (corrección auditoría 2.3 / 8.4 / 7.3).
     * Reutilizable por Filament Y por cualquier consulta backend futura.
     *
     * Precedencia: el responsable manda. Un agente ve/gestiona:
     *   - los inmuebles asignados a él (agent_id = su id), y
     *   - los inmuebles SIN responsable (agent_id NULL) que caen en sus zonas.
     * Nunca los inmuebles asignados a OTRO agente, aunque compartan zona.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasAnyRole(['owner', 'admin'])) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where('agent_id', $user->id)
              ->orWhere(function (Builder $z) use ($user) {
                  $z->whereNull('agent_id')
                    ->whereIn('zone_id', $user->zones()->select('zones.id'));
              });
        });
    }

    // --- Helpers de estado ---

    public function isPublished(): bool
    {
        return $this->status === PropertyStatus::Publicado;
    }

    // --- SEO (RFC-024) — fallbacks automáticos ---

    public function seoTitle(): string
    {
        return $this->meta_title ?: $this->title;
    }

    public function seoDescription(): string
    {
        return $this->meta_description
            ?: Str::limit(strip_tags((string) $this->description), 160);
    }

    public function canonical(): string
    {
        return $this->canonical_url ?: url("/inmuebles/{$this->slug}");
    }

    // --- Media Library (RFC-020) ---

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);

        $this->addMediaCollection('gallery')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        // Ambas conversiones son síncronas (nonQueued) — corrección auditoría 4.1.
        // Garantiza que `web` (usada en og:image) exista inmediatamente tras subir,
        // sin depender de que un worker de cola esté corriendo en producción.
        // El costo de generar 2 thumbnails al guardar es aceptable para este volumen.
        $this->addMediaConversion('thumb')
            ->fit(Fit::Crop, 400, 300)
            ->nonQueued();

        $this->addMediaConversion('web')
            ->fit(Fit::Max, 1280, 1024)
            ->nonQueued();
    }

    public function hasCoverImage(): bool
    {
        return $this->hasMedia('cover');
    }
}
```

**Activación del contrato diferido en `Zone` (aditivo — cuerpo + PHPDoc):**

```php
// app/Models/Zone.php — método properties() (líneas 160-164)
// ANTES (cuerpo y PHPDoc):
//   /** @return HasMany<Zone, $this> */  ← genérico falso al devolver Property
//   return $this->hasMany(self::class, 'id', 'id')->whereRaw('1 = 0');
// DESPUÉS:

/**
 * Inmuebles ubicados en esta zona.
 *
 * @return HasMany<Property, $this>
 */
public function properties(): HasMany
{
    return $this->hasMany(Property::class);
}
```

**Activación del contrato diferido en `User` (aditivo — cuerpo + PHPDoc):**

```php
// app/Models/User.php — método properties() (líneas 84-88)
// ANTES (cuerpo y PHPDoc):
//   /** @return HasMany<User, $this> */  ← genérico falso al devolver Property
//   return $this->hasMany(self::class, 'id', 'id')->whereRaw('1 = 0');
// DESPUÉS:

/**
 * Inmuebles de los que este usuario es agente responsable.
 *
 * @return HasMany<Property, $this>
 */
public function properties(): HasMany
{
    // El inmueble referencia al agente vía agent_id.
    return $this->hasMany(Property::class, 'agent_id');
}
```

> Ambos cambios son **estrictamente aditivos**: la firma pública no cambia. Se actualiza cuerpo **y** PHPDoc `@return` (corrección auditoría 8.6) para que Larastan conserve tipos correctos. No requieren migración sobre `users` ni `zones`. El test de regresión (QA-040) verifica que `$zone->properties()` y `$user->properties()` resuelven contra la tabla real sin romper consumidores existentes.

---

## 6. RFC-020 — Galería de Imágenes

### 6.1 Decisiones (CERRADAS)

| Decisión | Resolución |
| :--- | :--- |
| Almacenamiento | **Spatie MediaLibrary** (RFC-007, ya instalado). Sin columnas de ruta en `properties`. |
| Imagen principal | **Colección `cover`** con `singleFile()`. Subir una nueva reemplaza la anterior automáticamente. |
| Galería | **Colección `gallery`** múltiple, ordenable (`order_column` nativo de Media Library). |
| Conversiones | **`thumb`** (400×300, recorte) y **`web`** (máx. 1280×1024). **Ambas `nonQueued`** (corrección auditoría 4.1): garantizan `og:image` inmediato sin depender de un worker de cola. |
| Mime types | `image/jpeg`, `image/png`, `image/webp`. Rechazo en `acceptsMimeTypes()` y en el `FileUpload` de Filament. |
| Tamaño máximo | 5 MB por archivo (validación en el componente Filament). |
| Regla de publicación | **No se puede publicar sin imagen principal** (`cover`). Validación cruzada con RFC-022, aplicada en `PropertyStatusService`. |

### 6.2 Campo Filament (galería)

```php
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;

// Imagen principal — obligatoria a nivel de UI, reforzada en publicación
SpatieMediaLibraryFileUpload::make('cover')
    ->collection('cover')
    ->image()
    ->maxSize(5120)
    ->imageEditor()
    ->helperText('Imagen principal. Obligatoria para publicar el inmueble.'),

// Galería — múltiple y reordenable
SpatieMediaLibraryFileUpload::make('gallery')
    ->collection('gallery')
    ->multiple()
    ->reorderable()
    ->appendFiles()
    ->image()
    ->maxSize(5120)
    ->maxFiles(30)
    ->helperText('Arrastra para reordenar. Hasta 30 imágenes.'),
```

> La obligatoriedad de la imagen principal **no** se confía solo al `->required()` del form: la verdad vive en `PropertyStatusService::publish()`, que rechaza la transición si `hasCoverImage()` es falso. Así, publicar por Artisan, test o futura API también queda protegido.

---

## 7. RFC-021 — Características Dinámicas

### 7.1 Decisión de modelado: catálogo + pivote (vs JSON) — CERRADA

| Criterio | Catálogo `features` + pivote (elegido) | Columna JSON en `properties` |
| :--- | :--- | :--- |
| Filtrado en Épica 6/7 (`WHERE feature_id IN (...)`) | Índice nativo, `JOIN` simple | `jsonb` con operadores `@>`, más frágil |
| Integridad referencial | FK garantiza valores válidos | Texto libre, propenso a typos (`alberca` vs `Alberca`) |
| CRUD de catálogo por admin | Tabla real editable en Filament | Requiere editar código/seed |
| Reutilización entre inmuebles | Una fila `feature`, N relaciones | Valor duplicado por inmueble |
| Conteos / analítica (Épica 7) | `GROUP BY feature_id` trivial | Agregación sobre JSON costosa |

**Elección: catálogo `features` + pivote `property_feature` (N:N).** El frontend público (Épica 6) y las métricas (Épica 7) van a filtrar y agregar por característica; JSON convertiría esos filtros en escaneos costosos y abriría la puerta a inconsistencias de texto. El costo extra (una tabla + un pivote) es mínimo frente al beneficio.

### 7.2 Modelo `Feature` y migraciones

```php
// app/Models/Feature.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Feature extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'icon'];

    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'property_feature')
            ->withTimestamps();
    }
}
```

```php
// database/migrations/xxxx_create_features_table.php
Schema::create('features', function (Blueprint $table) {
    $table->id();
    $table->string('name', 80);
    $table->string('slug', 90)->unique();
    $table->string('icon', 60)->nullable();   // heroicon opcional para UI
    $table->timestamps();
});

// database/migrations/xxxx_create_property_feature_table.php
Schema::create('property_feature', function (Blueprint $table) {
    $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
    $table->foreignId('feature_id')->constrained('features')->cascadeOnDelete();
    $table->timestamps();

    $table->primary(['property_id', 'feature_id']);

    // Índice inverso (corrección auditoría 3.2 / 8.7): la PK (property_id, feature_id)
    // NO sirve a consultas cuyo primer criterio es feature_id — el patrón de filtrado
    // "inmuebles que tienen la característica X" de Épicas 6/7.
    $table->index(['feature_id', 'property_id'], 'property_feature_feature_property_index');
});
```

> `cascadeOnDelete` en el pivote es seguro: borra solo la **asociación**, nunca el inmueble ni la característica catalogada.

### 7.3 Seeder base

```php
// database/seeders/FeatureSeeder.php
use App\Models\Feature;
use Illuminate\Support\Str;

class FeatureSeeder extends Seeder
{
    public function run(): void
    {
        $features = [
            'Alberca', 'Jardín', 'Roof garden', 'Seguridad 24/7',
            'Elevador', 'Estacionamiento techado', 'Cocina integral',
            'Cuarto de servicio', 'Bodega', 'Amueblado',
            'Aire acondicionado', 'Calentador solar', 'Cisterna',
            'Acceso controlado', 'Área de juegos', 'Gimnasio',
        ];

        foreach ($features as $name) {
            // updateOrCreate (corrección auditoría 4.2): además de idempotente,
            // es CONVERGENTE — si el catálogo cambia el `name`, el seeder lo actualiza.
            Feature::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name],
            );
        }
    }
}
```

> `updateOrCreate` por `slug` → seeder **idempotente y convergente**: re-ejecutar refleja el estado deseado del catálogo sin duplicar.

### 7.4 Campo Filament (asignación) y catálogo

```php
// En PropertyResource form:
Forms\Components\Select::make('features')
    ->relationship('features', 'name')
    ->multiple()
    ->preload()
    ->searchable(),
```

El CRUD del catálogo vive en `FeatureResource` (§13.4), gateado por `properties.manage` a owner/admin (ver §12).

---

## 8. RFC-022 — Estados Comerciales

### 8.1 Máquina de estados (CERRADA)

```
                ┌─────────────────────────────────────────────┐
                │                                             reapertura
                ▼                                                  │
           ┌──────────┐   publish()   ┌───────────┐               │
           │ borrador │ ────────────► │ publicado │               │
           └──────────┘               └───────────┘               │
                ▲                       │   ▲   │                  │
                │                pause()│   │   │ sell()/rent()    │
                │                       ▼   │republish()           │
                │                   ┌─────────┐                    │
                │                   │ pausado │                    │
                │                   └─────────┘                    │
                │                       │                          │
                │                sell()/rent()                     │
                │                       ▼                          │
                │              ┌──────────────────┐                │
                └──────────────│ vendido / rentado│────────────────┘
                               └──────────────────┘

Transiciones permitidas (PropertyStatus::allowedTransitions):
  borrador  → publicado
  publicado → pausado, vendido, rentado
  pausado   → publicado, vendido, rentado
  vendido   → borrador        (reapertura controlada)
  rentado   → borrador        (reapertura controlada)

Reglas duras (validadas en PropertyStatusService, no en la UI):
  • Publicar exige: imagen principal (cover) + zona EXISTENTE, ACTIVA y con polígono.
  • "vendido" solo es válido si operation_type = venta.
  • "rentado" solo es válido si operation_type = renta.
  • Solo "publicado" es visible públicamente (scope published()).

Invariante DURABLE (no solo precondición — auditoría 2.1):
  • Un inmueble publicado NO puede perder portada ni zona (§8.3).
  • Inactivar o dar de baja lógica una zona PAUSA sus inmuebles publicados (§8.4).
  • Restaurar un inmueble lo devuelve a borrador (revalida invariantes — §13.3).
```

### 8.2 `PropertyStatusService`

```php
// app/Services/PropertyStatusService.php
namespace App\Services;

use App\Enums\OperationType;
use App\Enums\PropertyStatus;
use App\Enums\ZoneStatus;
use App\Models\Property;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PropertyStatusService
{
    /**
     * Única puerta de transición de estado. Transaccional y con lock de fila
     * para serializar transiciones concurrentes (corrección auditoría 2.4 / 8.5).
     */
    public function transition(Property $property, PropertyStatus $target): void
    {
        DB::transaction(function () use ($property, $target) {
            // Re-lee la fila bajo FOR UPDATE: el estado evaluado es el real, no uno
            // potencialmente obsoleto en memoria. Evita doble publicación en carrera.
            $fresh   = Property::whereKey($property->getKey())->lockForUpdate()->firstOrFail();
            $current = $fresh->status;

            if (! $current->canTransitionTo($target)) {
                throw ValidationException::withMessages([
                    'status' => "Transición no permitida: {$current->label()} → {$target->label()}.",
                ]);
            }

            match ($target) {
                PropertyStatus::Publicado => $this->guardPublish($fresh),
                PropertyStatus::Vendido   => $this->guardOperation($fresh, OperationType::Venta, 'vendido'),
                PropertyStatus::Rentado   => $this->guardOperation($fresh, OperationType::Renta, 'rentado'),
                default                   => null, // pausar / reabrir a borrador: sin guard extra
            };

            // status NO es mass-assignable: se asigna directo (bypassa $fillable a propósito).
            $fresh->status = $target;
            $fresh->save();

            $property->setRawAttributes($fresh->getAttributes(), true);
        });
    }

    /**
     * Invariante de publicación (corrección auditoría 2.1 / 8.1):
     * zona EXISTENTE, ACTIVA y con polígono, + imagen principal.
     */
    private function guardPublish(Property $property): void
    {
        // $property->zone aplica el global scope SoftDeletes de Zone:
        // si la zona fue dada de baja lógica, devuelve null aunque zone_id apunte a ella.
        $zone = $property->zone;

        if ($zone === null) {
            throw ValidationException::withMessages([
                'status' => 'No se puede publicar: el inmueble no tiene una zona vigente asignada.',
            ]);
        }

        if ($zone->status !== ZoneStatus::Active) {
            throw ValidationException::withMessages([
                'status' => 'No se puede publicar en una zona inactiva.',
            ]);
        }

        if ($zone->polygon === null) {
            throw ValidationException::withMessages([
                'status' => 'No se puede publicar: la zona no tiene polígono definido.',
            ]);
        }

        if (! $property->hasCoverImage()) {
            throw ValidationException::withMessages([
                'status' => 'No se puede publicar un inmueble sin imagen principal.',
            ]);
        }
    }

    private function guardOperation(Property $property, OperationType $required, string $label): void
    {
        if ($property->operation_type !== $required) {
            throw ValidationException::withMessages([
                'status' => "Solo un inmueble en {$required->label()} puede marcarse como {$label}.",
            ]);
        }
    }
}
```

> El servicio es la **única** puerta de cambio de estado: transacción + `lockForUpdate`, y `status` se asigna directo porque fue removido de `$fillable` (§5.6). Las acciones de Filament (§13.3) lo invocan; nadie hace `update(['status' => ...])`. Como `status` está fuera de `$fillable`, `fill()`/`update()` sobre una instancia no pueden colar una publicación (QA-043). **No se permite** `Property::query()->update()` para estado/slug: Laravel lo delega al query builder y omite `$fillable`, casts y eventos.

### 8.3 Durabilidad del invariante en edición (corrección auditoría 2.1 / 8.1)

Publicar deja de ser un evento aislado: mientras el inmueble esté `publicado`, **no puede perder los atributos que lo hicieron publicable**. Se valida al guardar, antes de persistir:

```php
// app/Models/Property.php — guard de edición invocado en saving()
public function assertPublishedInvariant(): void
{
    if ($this->status !== PropertyStatus::Publicado) {
        return; // borrador/pausado/vendido/rentado no exigen el invariante
    }

    // Consultar la relación evita usar una zona cacheada si zone_id cambió.
    $zone = $this->zone()->first();

    if ($zone === null) {
        throw ValidationException::withMessages([
            'zone_id' => 'Un inmueble publicado requiere una zona vigente. Pausa primero.',
        ]);
    }

    if ($zone->status !== ZoneStatus::Active || $zone->polygon === null) {
        throw ValidationException::withMessages([
            'zone_id' => 'Un inmueble publicado requiere una zona activa con polígono. Pausa primero.',
        ]);
    }

    if (! $this->hasCoverImage()) {
        throw ValidationException::withMessages([
            'cover' => 'Un inmueble publicado no puede quedarse sin imagen principal. Pausa primero.',
        ]);
    }
}
```

Registro en `PropertyObserver@saving` (corre dentro de la transacción del save de Filament):

```php
public function saving(Property $property): void
{
    $property->assertPublishedInvariant();
}
```

La portada vive en `media`, por lo que borrarla **no** dispara `PropertyObserver@saving`. Se registra además un guard sobre `Media::deleting`: si es la última portada de un inmueble publicado, el borrado se rechaza. Si ya existe otra portada, se permite eliminar la anterior para que `singleFile()` pueda reemplazarla.

```php
Media::deleting(function (Media $media): void {
    if ($media->model_type !== Property::class || $media->collection_name !== 'cover') {
        return;
    }

    $property = $media->model;

    if (! $property instanceof Property || ! $property->isPublished()) {
        return;
    }

    $hasReplacement = Media::query()
        ->where('model_type', Property::class)
        ->where('model_id', $property->getKey())
        ->where('collection_name', 'cover')
        ->whereKeyNot($media->getKey())
        ->exists();

    if (! $hasReplacement) {
        throw ValidationException::withMessages([
            'cover' => 'Un inmueble publicado no puede quedarse sin imagen principal. Pausa primero.',
        ]);
    }
});
```

> Para **quitar** la portada o cambiar a una zona inválida, el flujo correcto es: pausar → editar → republicar (que vuelve a pasar por `guardPublish`). El reemplazo de portada sí permanece permitido porque la nueva media existe antes de eliminar la anterior.

### 8.4 Propagación al inactivar o dar de baja una zona (corrección auditoría 2.1 / 8.2)

`zone_id` usa `nullOnDelete`, pero **`Zone` usa `SoftDeletes`**: una baja lógica NO dispara `ON DELETE SET NULL` ni pone `zone_id` en `NULL`. Un inmueble publicado quedaría apuntando a una zona excluida por su global scope y seguiría siendo devuelto por `scopePublished()`. Hay que reaccionar al evento de la zona.

**Mecanismo aditivo (no modifica `ZoneObserver` ni la tabla `zones`):** Épica 4 registra escuchas a los eventos del modelo `Zone` en `AppServiceProvider::boot()`. Si una zona se inactiva o se da de baja lógica, sus inmuebles publicados pasan a `pausado` en una transacción.

```php
// app/Providers/AppServiceProvider.php — registro aditivo (Épica 4)
use App\Enums\PropertyStatus;
use App\Enums\ZoneStatus;
use App\Models\Zone;
use Illuminate\Support\Facades\DB;

Zone::updated(function (Zone $zone): void {
    if ($zone->wasChanged('status') && $zone->status === ZoneStatus::Inactive) {
        self::pausePublishedProperties($zone);
    }
});

Zone::deleted(function (Zone $zone): void {
    // SoftDeletes dispara `deleted` en la baja lógica.
    self::pausePublishedProperties($zone);
});
```

```php
// helper invocado por ambos listeners
protected static function pausePublishedProperties(Zone $zone): void
{
    DB::transaction(function () use ($zone) {
        $zone->properties()
            ->where('status', PropertyStatus::Publicado->value)
            ->lockForUpdate()
            ->get()
            ->each(function ($property) {
                $property->status = PropertyStatus::Pausado;
                $property->save();
            });
    });
}
```

> No se asigna `zone_id = null`: el vínculo se conserva para que owner/admin puedan reactivar la zona y republicar. El efecto es **pausar**, que es la mitigación menos destructiva y reversible. Cubierto por QA-046 y QA-047.

---

## 9. RFC-023 — Generación de Slug

### 9.1 Estrategia (CERRADA)

- **Patrón:** `zona-tipo-título` → ej. `juriquilla-casa-con-alberca`.
- **Generador desacoplado:** la lógica vive en `App\Support\PropertySlugGenerator` (corrección sobreingeniería auditoría 5), no en el Observer. El Observer solo orquesta el ciclo de vida; el generador es reutilizable por la acción `regenerateSlug`.
- **Momento:** `PropertyObserver@creating`. Se genera una sola vez al crear.
- **Unicidad:** sufijo incremental contra `withTrashed()`, **excluyendo el `id` actual** al regenerar (corrección auditoría 3.3) — un inmueble no colisiona consigo mismo.
- **Concurrencia:** el índice único es la garantía final. El generador **reintenta** ante `QueryException` de violación única (dos altas simultáneas que eligieron el mismo slug) — corrección auditoría 3.3.
- **Actualización controlada:** editar el título **no** regenera el slug por defecto, para no romper URLs indexadas (RFC-024 / Épica 6). La regeneración es una acción explícita y confirmada en Filament (§13.3, `regenerateSlug`).
- **Índice:** `unique` en `slug` (migración §5.5).
- **Caso borde de relación:** el slug usa `zone?->slug`; se prueba con `zone_id` presente, ausente y soft-deleted (riesgo de impl. auditoría 6).

### 9.2 `PropertySlugGenerator`

```php
// app/Support/PropertySlugGenerator.php
namespace App\Support;

use App\Models\Property;
use Illuminate\Support\Str;

class PropertySlugGenerator
{
    /** Slug base único. Excluye $ignoreId (el propio inmueble) al regenerar. */
    public function generate(Property $property, ?int $ignoreId = null): string
    {
        $base = Str::slug(implode(' ', array_filter([
            $property->zone?->slug,
            $property->property_type?->value,
            $property->title,
        ]))) ?: 'inmueble';

        $slug = $base;
        $n    = 2;

        while ($this->exists($slug, $ignoreId)) {
            $slug = "{$base}-{$n}";
            $n++;
        }

        return $slug;
    }

    private function exists(string $slug, ?int $ignoreId): bool
    {
        return Property::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();
    }
}
```

### 9.3 `PropertyObserver` (orquestación del ciclo de vida)

```php
// app/Observers/PropertyObserver.php
namespace App\Observers;

use App\Models\Property;
use App\Support\PropertySlugGenerator;

class PropertyObserver
{
    public function __construct(private PropertySlugGenerator $slugs) {}

    public function creating(Property $property): void
    {
        if (blank($property->slug)) {
            // slug no es mass-assignable: se asigna directo (§5.6).
            $property->slug = $this->slugs->generate($property);
        }
    }

    public function saving(Property $property): void
    {
        // Invariante durable de publicación (§8.3).
        $property->assertPublishedInvariant();
    }
}
```

**Reintento ante carrera (corrección auditoría 3.3).** El `INSERT`/`UPDATE` se envuelve en un reintento acotado: si el índice único rechaza el slug (otra transacción lo tomó entre el `exists()` y el `save()`), se regenera y reintenta hasta 3 veces. Se encapsula en un pequeño helper reutilizado por la creación y por `regenerateSlug`:

```php
// Patrón usado en CreateProperty::handleRecordCreation y en la acción regenerateSlug
retry(3, function () use ($property) {
    $property->slug = app(PropertySlugGenerator::class)->generate($property, $property->id);
    $property->save();
}, 0, fn (\Throwable $e): bool => $e instanceof \Illuminate\Database\UniqueConstraintViolationException);
```

**Registro en `AppServiceProvider::boot()`:**

```php
use App\Models\Property;
use App\Observers\PropertyObserver;

Property::observe(PropertyObserver::class);
```

---

## 10. RFC-024 — SEO Básico

### 10.1 Campos y fallbacks (CERRADOS)

| Campo | Persistido | Fallback si vacío |
| :--- | :--- | :--- |
| `meta_title` | `properties.meta_title` | `title` del inmueble |
| `meta_description` | `properties.meta_description` | `Str::limit(strip_tags(description), 160)` |
| `canonical_url` | `properties.canonical_url` | `url("/inmuebles/{slug}")` |
| Open Graph (`og:title`, `og:description`, `og:image`) | Derivado | `seoTitle()`, `seoDescription()`, primera imagen `cover`/`gallery` |

Los fallbacks viven en los helpers del modelo (`seoTitle()`, `seoDescription()`, `canonical()` — §5.6). El frontend (Épica 6) nunca decide el fallback: consume los helpers.

### 10.2 Contrato hacia Épica 6 (Frontend Público)

La ficha de detalle pública (RFC-035) construirá las etiquetas `<meta>` y Open Graph llamando exclusivamente a:

```php
$property->seoTitle();        // <title> y og:title
$property->seoDescription();  // <meta name="description"> y og:description
$property->canonical();       // <link rel="canonical">

// og:image — conversión 'web' (síncrona, §6) con degradado al original.
// getFirstMediaUrl devuelve '' si la conversión no existe, por eso el encadenado.
$property->getFirstMediaUrl('cover', 'web')
    ?: $property->getFirstMediaUrl('cover')
    ?: $property->getFirstMediaUrl('gallery', 'web')
    ?: $property->getFirstMediaUrl('gallery');
```

> SEO avanzado (JSON-LD / schema.org `RealEstateListing`, sitemap dinámico) queda **fuera de alcance** — Épica 7 (RFC-045). Esta épica garantiza que los metadatos básicos existen y tienen fallback seguro.

---

## 11. Modelo de Datos

### 11.1 Tabla `properties` (estado final)

```
properties
├── id                  bigserial PK
├── title               varchar(180) NOT NULL
├── slug                varchar(200) UNIQUE NOT NULL
├── description         text NULL
├── operation_type      varchar(20)  NOT NULL  [CHECK venta|renta]
├── property_type       varchar(30)  NOT NULL  [CHECK casa|departamento|terreno|local|oficina|bodega]
├── status              varchar(20)  DEFAULT 'borrador' [CHECK borrador|publicado|pausado|vendido|rentado]
├── price               decimal(14,2) NOT NULL
├── bedrooms            smallint NULL
├── bathrooms           decimal(3,1) NULL
├── parking_spaces      smallint NULL
├── land_area           decimal(10,2) NULL
├── construction_area   decimal(10,2) NULL
├── zone_id             bigint FK → zones.id   NULL ON DELETE SET NULL
├── agent_id            bigint FK → users.id   NULL ON DELETE SET NULL
├── meta_title          varchar(180) NULL
├── meta_description    varchar(320) NULL
├── canonical_url       varchar(255) NULL
├── created_at          timestamp
├── updated_at          timestamp
└── deleted_at          timestamp NULL (soft delete)

Índices:
  properties_slug_unique            ON (slug)
  properties_status_index           ON (status)
  properties_operation_type_index   ON (operation_type)
  properties_property_type_index    ON (property_type)
  properties_status_operation_index ON (status, operation_type)
  properties_price_index            ON (price)
```

### 11.2 Tablas `features` y `property_feature`

```
features
├── id          bigserial PK
├── name        varchar(80) NOT NULL
├── slug        varchar(90) UNIQUE NOT NULL
├── icon        varchar(60) NULL
└── created_at, updated_at

property_feature
├── property_id bigint FK → properties.id CASCADE
├── feature_id  bigint FK → features.id   CASCADE
├── created_at, updated_at
└── PK(property_id, feature_id)
```

### 11.3 Relaciones

```
Property ──(belongsTo)──► Zone           [zone_id, nullOnDelete]
         ──(belongsTo)──► User (agent)   [agent_id, nullOnDelete]
         ──(belongsToMany)──► Feature     [property_feature]
         ──(HasMedia)──► media            [cover | gallery]

Zone ──(hasMany)──► Property              [ACTIVADO en esta épica]
User ──(hasMany)──► Property (agent_id)   [ACTIVADO en esta épica]
```

---

## 12. Seguridad — Mapa de controles

`PropertyPolicy` es la **única fuente de verdad** de autorización. Filament la consume; no la reemplaza. El permiso base es `properties.manage` (sembrado en Épica 2 para owner, admin y agente).

**Regla diferencial del agente (precedencia cerrada — corrección auditoría 2.3 / 8.4):** un `agente` tiene `properties.manage`, pero **el responsable manda**:

- Si el inmueble tiene `agent_id`, **solo ese agente** (más owner/admin) lo gestiona.
- La zona concede acceso **únicamente a inmuebles sin responsable** (`agent_id NULL`) que caen en sus zonas.
- Nunca un agente gestiona el inmueble asignado a **otro** agente, aunque compartan zona. Esto alinea la Policy con QA-039 y con el scope `visibleTo()` (§5.6): **una sola definición de alcance** para Policy y query.

```php
// app/Policies/PropertyPolicy.php
namespace App\Policies;

use App\Models\Property;
use App\Models\User;

class PropertyPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->can('properties.manage');
    }

    public function view(User $auth, Property $property): bool
    {
        return $auth->can('properties.manage') && $this->canManage($auth, $property);
    }

    public function create(User $auth): bool
    {
        return $auth->can('properties.manage');
    }

    public function update(User $auth, Property $property): bool
    {
        return $auth->can('properties.manage') && $this->canManage($auth, $property);
    }

    public function delete(User $auth, Property $property): bool
    {
        // Soft-delete reservado a owner/admin. Se exige también properties.manage
        // para mantener coherencia con el contrato de Épica 2 (corrección auditoría 7.4).
        return $auth->can('properties.manage') && $auth->hasAnyRole(['owner', 'admin']);
    }

    public function restore(User $auth, Property $property): bool
    {
        return $auth->can('properties.manage') && $auth->hasAnyRole(['owner', 'admin']);
    }

    public function forceDelete(User $auth, Property $property): bool
    {
        return false;
    }

    /**
     * Precedencia: agent_id manda; la zona solo da acceso a inmuebles sin responsable.
     * Coincide EXACTAMENTE con el predicado de Property::scopeVisibleTo() (§5.6),
     * para que Policy y query autoricen lo mismo (corrección auditoría 7.3).
     */
    private function canManage(User $auth, Property $property): bool
    {
        if ($auth->hasAnyRole(['owner', 'admin'])) {
            return true;
        }

        if ($property->agent_id !== null) {
            return $property->agent_id === $auth->id;
        }

        // Sin responsable: accesible si la zona pertenece al agente.
        return $property->zone_id !== null
            && $auth->zones()->whereKey($property->zone_id)->exists();
    }
}
```

> **Paridad Policy ↔ query (corrección auditoría 7.3):** `canManage()` y `scopeVisibleTo()` codifican el mismo predicado. QA-048 los prueba **en conjunto** (un registro de otro agente en mi zona no aparece en mi query Y la Policy lo rechaza).

**Registro en `AppServiceProvider::boot()`:**

```php
Gate::policy(Property::class, PropertyPolicy::class);
Gate::policy(Feature::class, FeaturePolicy::class);
```

`FeaturePolicy` restringe el catálogo a owner/admin (el agente consume features pero no administra el catálogo):

```php
// app/Policies/FeaturePolicy.php — todas las acciones:
public function viewAny(User $auth): bool { return $auth->hasAnyRole(['owner', 'admin']); }
public function create(User $auth): bool  { return $auth->hasAnyRole(['owner', 'admin']); }
public function update(User $auth, Feature $f): bool { return $auth->hasAnyRole(['owner', 'admin']); }
public function delete(User $auth, Feature $f): bool { return $auth->hasRole('owner'); }
```

### 12.1 Mapa de controles

| Control | Capa | Mecanismo |
| :--- | :--- | :--- |
| Ver listado de inmuebles | Policy + Resource | `viewAny()` → `properties.manage` |
| Agente ve solo lo suyo | Policy + scope único | `canManage()` ≡ `scopeVisibleTo()` (§5.6); query del Resource usa el scope (§13.1) |
| Crear inmueble | Policy + mutación backend | `create()` → `properties.manage`; el agente NO elige `agent_id` (§13.2 fuerza el suyo) |
| Forzar responsable y validar zona del agente | Backend (no UI) | `mutateFormDataBeforeCreate/Save` (§13.2) — ocultar el campo no es autorización |
| Editar inmueble | Policy | `update()` + precedencia `agent_id` |
| Publicar / pausar / vender / rentar / reabrir | Service + Policy | `PropertyStatusService` (tx+lock) + `update` policy en la acción |
| Soft-delete / restaurar | Policy | `delete()`/`restore()` → `properties.manage` + owner/admin |
| Administrar catálogo `features` | Policy | `FeaturePolicy` → owner/admin (delete: owner) |
| Invariante durable de publicación | Service + Observer | `guardPublish()` (cover + zona activa con polígono) + `assertPublishedInvariant()` en `saving` |

**Principio rector:** la autorización vive en la Policy; las reglas de transición de estado en el Service. La UI de Filament las consume, nunca las define.

---

## 13. `PropertyResource` en Filament

### 13.1 Estructura y scoping del agente

```
app/Filament/Resources/PropertyResource/
├── PropertyResource.php
└── Pages/
    ├── ListProperties.php
    ├── CreateProperty.php
    └── EditProperty.php
```

El agente solo debe **ver** sus inmuebles en la tabla. La query base del Resource delega en el scope **único** `visibleTo()` (§5.6) — no se reimplementa el predicado, para no divergir de la Policy:

```php
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()->visibleTo(auth()->user());
}
```

> Reusar `visibleTo()` cierra el hueco de la auditoría 7.3: Policy (`canManage`) y query (`visibleTo`) comparten una sola definición de "lo mío".

### 13.2 Forzado de responsable y validación de zona en backend (corrección auditoría 2.2 / 8.3)

Ocultar el campo `agent_id` en la UI **no** es autorización: un agente podría enviar `agent_id`/`zone_id` por payload directo (riesgo de seguridad auditoría 7). El backend fuerza el responsable y valida la zona en las Pages, no en el form:

```php
// CreateProperty.php
protected function mutateFormDataBeforeCreate(array $data): array
{
    return $this->enforceAgentOwnership($data);
}

// EditProperty.php
protected function mutateFormDataBeforeSave(array $data): array
{
    return $this->enforceAgentOwnership($data);
}

// Trait/método compartido por ambas Pages
protected function enforceAgentOwnership(array $data): array
{
    $user = auth()->user();

    if ($user->hasAnyRole(['owner', 'admin'])) {
        return $data; // owner/admin asignan libremente
    }

    // El agente SIEMPRE es el responsable; ignora cualquier agent_id entrante.
    $data['agent_id'] = $user->id;

    // La zona, si se envía, debe pertenecer al agente. Si no, se rechaza.
    if (! empty($data['zone_id']) && ! $user->zones()->whereKey($data['zone_id'])->exists()) {
        throw ValidationException::withMessages([
            'zone_id' => 'Solo puedes asignar inmuebles a tus zonas.',
        ]);
    }

    return $data;
}
```

> El `Select` de `zone_id` además filtra opciones para el agente (`->relationship('zone', 'name', fn ($q) => /* zonas del agente */)`), pero esa restricción de UI es **complemento**, no reemplazo, de la validación de servidor.

### 13.3 Form (secciones)

```php
Forms\Components\Section::make('Datos del inmueble')->schema([
    Forms\Components\TextInput::make('title')->required()->maxLength(180)->live(onBlur: true),
    Forms\Components\Select::make('operation_type')->options(OperationType::class)->required(),
    Forms\Components\Select::make('property_type')->options(PropertyType::class)->required(),
    Forms\Components\Textarea::make('description')->rows(4)->nullable(),
])->columns(2),

Forms\Components\Section::make('Ubicación y responsable')->schema([
    // Zona: para el agente, el Select solo ofrece SUS zonas (complemento de la
    // validación backend de §13.2; la UI no es la autorización).
    Forms\Components\Select::make('zone_id')
        ->relationship('zone', 'name', function ($query) {
            $user = auth()->user();
            return $user->hasAnyRole(['owner', 'admin'])
                ? $query
                : $query->whereIn('zones.id', $user->zones()->select('zones.id'));
        })
        ->searchable()->preload(),
    Forms\Components\Select::make('agent_id')
        ->relationship('agent', 'name', fn ($query) => $query->role('agente')->active())
        ->searchable()->preload()
        ->visible(fn () => auth()->user()->hasAnyRole(['owner', 'admin'])),
])->columns(2),

Forms\Components\Section::make('Precio y dimensiones')->schema([
    Forms\Components\TextInput::make('price')->numeric()->prefix('$')->required(),
    Forms\Components\TextInput::make('bedrooms')->numeric()->minValue(0),
    Forms\Components\TextInput::make('bathrooms')->numeric()->step(0.5)->minValue(0),
    Forms\Components\TextInput::make('parking_spaces')->numeric()->minValue(0),
    Forms\Components\TextInput::make('land_area')->numeric()->suffix('m²'),
    Forms\Components\TextInput::make('construction_area')->numeric()->suffix('m²'),
])->columns(3),

Forms\Components\Section::make('Características')->schema([
    Forms\Components\Select::make('features')->relationship('features', 'name')
        ->multiple()->preload()->searchable(),
]),

Forms\Components\Section::make('Galería')->schema([
    // SpatieMediaLibraryFileUpload cover + gallery — ver §6.2
]),

Forms\Components\Section::make('SEO')->schema([
    Forms\Components\TextInput::make('meta_title')->maxLength(180)
        ->placeholder(fn ($record) => $record?->title),
    Forms\Components\Textarea::make('meta_description')->maxLength(320)->rows(2),
    Forms\Components\TextInput::make('canonical_url')->url()->maxLength(255),
])->collapsed(),

Forms\Components\Section::make('Estado')->schema([
    Forms\Components\Placeholder::make('status_display')
        ->label('Estado actual')
        ->content(fn ($record) => $record?->status?->label() ?? 'Borrador'),
])->visible(fn ($operation) => $operation === 'edit'),
```

> El campo `status` **no** es editable directamente en el form. El cambio de estado pasa siempre por las acciones de tabla/página que invocan `PropertyStatusService` (§13.4). Esto evita saltar las reglas duras desde un `Select`.

### 13.4 Table — columnas, badges y acciones

```php
// Columnas
Tables\Columns\SpatieMediaLibraryImageColumn::make('cover')->collection('cover')->circular(),
Tables\Columns\TextColumn::make('title')->searchable()->sortable(),
Tables\Columns\TextColumn::make('zone.name')->badge()->toggleable(),
Tables\Columns\TextColumn::make('operation_type')->badge()
    ->formatStateUsing(fn ($state) => $state->label())
    ->color(fn ($state) => $state->color()),
Tables\Columns\TextColumn::make('property_type')->formatStateUsing(fn ($state) => $state->label()),
Tables\Columns\TextColumn::make('price')->money('MXN')->sortable(),
Tables\Columns\TextColumn::make('status')->badge()
    ->formatStateUsing(fn ($state) => $state->label())
    ->color(fn ($state) => $state->color()),

// Filtros
Tables\Filters\SelectFilter::make('zone')->relationship('zone', 'name'),
Tables\Filters\SelectFilter::make('operation_type')->options(OperationType::class),
Tables\Filters\SelectFilter::make('property_type')->options(PropertyType::class),
Tables\Filters\SelectFilter::make('status')->options(PropertyStatus::class),
Tables\Filters\TrashedFilter::make()->visible(fn () => auth()->user()->hasAnyRole(['owner', 'admin'])),

// Acciones de estado (cada una llama al Service y respeta la Policy 'update')
Tables\Actions\Action::make('publish')
    ->label('Publicar')->icon('heroicon-o-globe-alt')->color('success')
    ->visible(fn ($record) => auth()->user()->can('update', $record)
        && $record->status->canTransitionTo(PropertyStatus::Publicado))
    ->requiresConfirmation()
    ->action(fn ($record) => app(PropertyStatusService::class)
        ->transition($record, PropertyStatus::Publicado)),

Tables\Actions\Action::make('pause')
    ->label('Pausar')->icon('heroicon-o-pause')->color('warning')
    ->visible(fn ($record) => auth()->user()->can('update', $record)
        && $record->status->canTransitionTo(PropertyStatus::Pausado))
    ->action(fn ($record) => app(PropertyStatusService::class)
        ->transition($record, PropertyStatus::Pausado)),

Tables\Actions\Action::make('markSold')
    ->label('Marcar vendido')->icon('heroicon-o-check-badge')->color('info')
    ->visible(fn ($record) => auth()->user()->can('update', $record)
        && $record->operation_type === OperationType::Venta
        && $record->status->canTransitionTo(PropertyStatus::Vendido))
    ->requiresConfirmation()
    ->action(fn ($record) => app(PropertyStatusService::class)
        ->transition($record, PropertyStatus::Vendido)),

Tables\Actions\Action::make('markRented')
    ->label('Marcar rentado')->icon('heroicon-o-key')->color('info')
    ->visible(fn ($record) => auth()->user()->can('update', $record)
        && $record->operation_type === OperationType::Renta
        && $record->status->canTransitionTo(PropertyStatus::Rentado))
    ->requiresConfirmation()
    ->action(fn ($record) => app(PropertyStatusService::class)
        ->transition($record, PropertyStatus::Rentado)),

// Reabrir un inmueble vendido/rentado → vuelve a borrador (corrección auditoría 3.5).
Tables\Actions\Action::make('reopen')
    ->label('Reabrir')->icon('heroicon-o-arrow-uturn-left')->color('gray')
    ->visible(fn ($record) => auth()->user()->can('update', $record)
        && $record->status->canTransitionTo(PropertyStatus::Borrador))
    ->requiresConfirmation()
    ->modalDescription('El inmueble volverá a borrador y deberá publicarse de nuevo.')
    ->action(fn ($record) => app(PropertyStatusService::class)
        ->transition($record, PropertyStatus::Borrador)),

Tables\Actions\Action::make('regenerateSlug')
    ->label('Regenerar slug')->icon('heroicon-o-link')->color('gray')
    ->visible(fn ($record) => auth()->user()->can('update', $record))
    ->requiresConfirmation()
    ->modalDescription('Regenerar el slug puede romper enlaces indexados. ¿Continuar?')
    ->action(function ($record) {
        // slug no es mass-assignable: asignación directa + reintento ante carrera (§9.3).
        retry(3, function () use ($record) {
            $record->slug = app(\App\Support\PropertySlugGenerator::class)
                ->generate($record, $record->id);
            $record->save();
        }, 0, fn (\Throwable $e): bool => $e instanceof \Illuminate\Database\UniqueConstraintViolationException);
    }),

Tables\Actions\EditAction::make()->visible(fn ($record) => auth()->user()->can('update', $record)),
Tables\Actions\DeleteAction::make()->visible(fn ($record) => auth()->user()->can('delete', $record)),

// Restaurar (corrección auditoría 3.5): al restaurar, el inmueble vuelve a BORRADOR
// para revalidar invariantes — nunca reaparece publicado sin re-pasar guardPublish.
Tables\Actions\RestoreAction::make()
    ->visible(fn ($record) => auth()->user()->can('restore', $record))
    ->after(function ($record) {
        if ($record->status === PropertyStatus::Publicado) {
            $record->status = PropertyStatus::Borrador;
            $record->save();
        }
    }),
```

> Nota sobre `status` directo en acciones: las acciones asignan `status`/`slug` por atributo directo (no `update([...])`), porque ambos están fuera de `$fillable` (§5.6). El cambio de estado de negocio siempre pasa por `PropertyStatusService`; el `after` de Restore es la única excepción y solo **degrada** a borrador.

### 13.5 `FeatureResource`

CRUD simple del catálogo (name, slug auto, icon), gateado por `FeaturePolicy` (owner/admin). `canViewAny()` → `auth()->user()->hasAnyRole(['owner','admin'])`. Sin acciones de estado ni media.

---

## 14. Estrategia de Testing

Tests de Feature usan `RefreshDatabase` sobre **PostgreSQL `inmo_test`** (igual que Épicas 2/3), nunca SQLite. `setUp()` limpia caché de permisos y siembra `PermissionSeeder` + `FeatureSeeder`.

### 14.1 Unit

```php
// tests/Unit/PropertyEnumsTest.php
test_operation_type_has_venta_and_renta_cases()
test_property_status_is_public_only_for_publicado()
test_property_status_allowed_transitions_match_spec()
test_property_status_cannot_transition_to_disallowed_state()

// tests/Unit/PropertySlugTest.php
test_slug_is_generated_from_zone_type_and_title_on_create()
test_slug_collision_appends_incremental_suffix()
test_slug_uniqueness_considers_soft_deleted_properties()
test_editing_title_does_not_regenerate_slug()
test_regenerate_excludes_current_id_so_property_does_not_collide_with_itself()  // auditoría 3.3
test_slug_generation_handles_missing_and_soft_deleted_zone()                    // auditoría 6

// tests/Unit/PropertyScopesTest.php
test_published_scope_returns_only_publicado()
test_by_zone_scope_filters_by_zone_id()
test_by_operation_scope_filters_by_operation_type()
test_visible_to_owner_admin_returns_all()                                       // auditoría 7.3
test_visible_to_agent_excludes_properties_assigned_to_other_agent_in_same_zone() // auditoría 2.3

// tests/Unit/PropertyStatusServiceTest.php
test_publish_requires_cover_image()
test_publish_requires_assigned_zone()
test_publish_requires_active_zone()                                             // auditoría 2.1
test_publish_requires_zone_with_polygon()                                       // auditoría 2.1
test_sold_only_allowed_for_venta()
test_rented_only_allowed_for_renta()
test_invalid_transition_throws_validation_exception()
test_reopen_from_sold_returns_to_borrador()                                     // auditoría 3.5
```

### 14.2 Feature

```php
// tests/Feature/PropertyCrudTest.php
test_owner_can_create_property()
test_admin_can_create_property()
test_agente_can_create_property_assigned_to_self()
test_agente_only_sees_own_or_unassigned_zone_properties()
test_agente_cannot_edit_property_of_another_agent()
test_owner_can_soft_delete_and_restore_property()
test_agente_cannot_soft_delete_property()
// Escenarios de seguridad añadidos por la auditoría (3.6):
test_agente_cannot_force_agent_id_via_payload()                 // 2.2 — backend fuerza el suyo
test_agente_cannot_assign_property_to_foreign_zone_via_payload() // 2.2 — validación backend
test_two_agents_same_zone_cannot_edit_each_others_assigned_properties() // 2.3
test_restoring_a_published_property_returns_it_to_borrador()    // 3.5

// tests/Feature/PropertyPublicationTest.php
test_cannot_publish_without_cover_image()
test_cannot_publish_without_zone()
test_cannot_publish_in_inactive_zone()                          // 2.1
test_cannot_publish_when_zone_has_no_polygon()                  // 2.1
test_publish_makes_property_visible_in_published_scope()
test_pausing_removes_property_from_published_scope()
test_mark_sold_only_for_sale_properties()
test_mark_rented_only_for_rent_properties()
test_reopen_sold_property_returns_to_borrador()                 // 3.5
// Durabilidad del invariante (2.1):
test_published_property_cannot_lose_cover_on_edit()
test_published_property_cannot_lose_zone_on_edit()
test_soft_deleting_zone_pauses_its_published_properties()       // 8.4
test_inactivating_zone_pauses_its_published_properties()        // 8.4
// La única puerta de status (2.4 / 8.5):
test_ordinary_update_cannot_change_status_to_publicado()
test_ordinary_update_cannot_change_slug()
test_concurrent_publish_is_serialized_by_lock()                 // 3.3 / 8.5

// tests/Feature/PropertyGalleryTest.php
test_uploads_multiple_gallery_images()
test_cover_collection_keeps_single_file()
test_gallery_images_can_be_reordered()

// tests/Feature/PropertyFeaturesTest.php
test_assigns_features_from_catalog_via_pivot()
test_detaching_feature_keeps_catalog_intact()

// tests/Feature/PropertySeoTest.php
test_seo_title_falls_back_to_title()
test_seo_description_falls_back_to_description_excerpt()
test_canonical_falls_back_to_slug_url()
```

### 14.3 Regresión Épicas 1/2/3

```php
// tests/Feature/Regression/Epica123RegressionTest.php
test_filament_panel_loads_at_admin()
test_roles_and_properties_manage_permission_exist()
test_user_resource_still_operational()          // Épica 2
test_zone_resource_still_operational()           // Épica 3
test_zone_properties_relation_now_resolves_to_real_table()   // contrato activado
test_user_properties_relation_now_resolves_to_real_table()   // contrato activado
```

### 14.4 Factories

```php
// database/factories/PropertyFactory.php
public function definition(): array
{
    return [
        'title'          => fake()->streetName().' '.fake()->randomNumber(3),
        'description'    => fake()->paragraph(),
        'operation_type' => fake()->randomElement(OperationType::cases()),
        'property_type'  => fake()->randomElement(PropertyType::cases()),
        'status'         => PropertyStatus::Borrador,
        'price'          => fake()->numberBetween(800_000, 12_000_000), // > 0 (CHECK)
        'bedrooms'       => fake()->numberBetween(1, 5),
        'bathrooms'      => fake()->randomElement([1, 1.5, 2, 2.5, 3]),
        'parking_spaces' => fake()->numberBetween(0, 3),
        'zone_id'        => null,
        'agent_id'       => null,
    ];
}

// Estado VÁLIDO y publicable: crea zona activa con polígono, agente y portada.
// (corrección auditoría riesgo 6: published() no debe fabricar estados imposibles)
public function published(): static
{
    return $this
        ->for(Zone::factory()->withPolygon()->state(['status' => ZoneStatus::Active]))
        ->for(User::factory()->withRole('agente')->active(), 'agent')
        ->afterCreating(function (Property $property) {
            $property->addMediaFromString('fake-cover-bytes')
                ->usingFileName('cover.jpg')
                ->toMediaCollection('cover');

            // El modelo debe existir antes de adjuntar media. Solo después de crear
            // la portada se publica mediante la única puerta de estado (§8.2).
            app(PropertyStatusService::class)
                ->transition($property, PropertyStatus::Publicado);
        });
}

// Estado DELIBERADAMENTE inválido, nombrado como tal, para probar guardPublish.
public function draftWithoutCover(): static
{
    return $this->state(['status' => PropertyStatus::Borrador]); // sin media cover
}

public function forAgent(User $agent): static
{
    return $this->state(['agent_id' => $agent->id]);
}
```

> `published()` produce un fixture **íntegro** (zona activa con polígono + portada + agente). Primero persiste el borrador, luego adjunta la portada —Media Library requiere un `id`— y finalmente transiciona mediante `PropertyStatusService`. Este estado queda operativo al cerrar el Lote C. Los tests de bloqueo usan estados nombrados como inválidos (`draftWithoutCover()`), no `published()` mutilado.

---

## 15. Riesgos Técnicos

| # | Riesgo | Prob. | Impacto | Mitigación |
| :--- | :--- | :---: | :---: | :--- |
| R-1 | Colisión de slug (incl. concurrencia) | Media | Medio | `PropertySlugGenerator` con sufijo incremental contra `withTrashed()`, exclusión del `id` propio y **reintento** ante violación única; el índice `unique` es la garantía final (§9, QA-028). |
| R-2 | Baja/inactivación de zona deja inmueble publicado inconsistente | Media | Alto | `nullOnDelete` **no** cubre `SoftDeletes`. Mitigado por la propagación de §8.4 (inactivar/eliminar zona → pausa sus inmuebles publicados, transaccional) + invariante durable §8.3. QA-046/047. |
| R-3 | Agente reasignado o eliminado deja inmuebles colgados | Media | Medio | `agent_id` con `nullOnDelete`. Tras quedar sin responsable, el inmueble vuelve a ser accesible por zona (precedencia §12). owner/admin reasignan. |
| R-4 | Archivos de media tras soft-delete | Baja | Bajo | Mientras el inmueble esté soft-deleted los archivos **no están huérfanos**: son recuperables al restaurar. Solo se limpian en `forceDelete` (deshabilitado por Policy). No se presenta como "media huérfana" (corrección auditoría 5). |
| R-5 | Filtros sin índice → full scan | Media | Medio | Índices `(status, operation_type)` (su prefijo cubre status solo), `property_type`, `price`, `zone_id`, `agent_id` (§5.5) y `(feature_id, property_id)` en el pivote (§7.2). Se validan con `EXPLAIN` ante volumen real (auditoría 4.3). |
| R-6 | Publicar saltando reglas duras vía API/Artisan/seeder | Baja | Alto | `status` fuera de `$fillable`; única puerta `PropertyStatusService` (tx + lock). `fill()`/`update()` de instancia no publican (QA-043); updates masivos por query quedan prohibidos porque omiten el modelo. |
| R-7 | `bathrooms` con medios baños + integridad numérica | Baja | Bajo | `decimal(3,1)` + cast `decimal:1`; CHECK constraints `price > 0` y métricas `≥ 0` (§5.5). Tests cubren `2.5` (QA-026). |
| R-8 | Contratos `properties()` activados rompen Épicas 2/3 | Baja | Alto | Cambio de cuerpo **+ PHPDoc**; firma intacta. Regresión QA-040 + Larastan verifican tipos y resolución real. |
| R-9 | Acceso horizontal: agente afecta inmueble de otro agente en zona compartida | Media | Alto | Precedencia `agent_id` manda: zona solo da acceso a inmuebles sin responsable. Policy `canManage()` ≡ scope `visibleTo()` (§12). QA-044/048. |
| R-10 | Agente fuerza `agent_id`/`zone_id` por payload directo | Media | Alto | Forzado/validación en backend (`mutateFormDataBeforeCreate/Save`, §13.2); la UI no es autorización. QA-041/042. |
| R-11 | Conversión `web` ausente al momento del `og:image` | Baja | Bajo | `web` es `nonQueued` (§6) + fallback al original en el contrato SEO (§10.2). |
| R-12 | Reglas que cruzan `Property`/`Zone`/Media implementadas solo en callbacks del Resource | Media | Alto | Las reglas viven en Service/Observer/eventos de modelo (transaccionales), no en el Resource — robustas ante Artisan/seeder/futura API (auditoría 6). |

---

## 16. Criterios de Aceptación (QA-026 → QA-051)

| ID | Caso | Verificación |
| :--- | :--- | :--- |
| QA-026 | Crear inmueble | Owner/admin crea inmueble → queda en `borrador` con datos correctos (incluye `bathrooms = 2.5`). |
| QA-027 | Agente crea su inmueble | Agente crea inmueble → `agent_id` = su id; aparece en su listado. |
| QA-028 | Slug autogenerado | Crear título "Con alberca" en zona Juriquilla, tipo casa → `slug = juriquilla-casa-con-alberca` (RFC-023). Si colisiona, sufijo incremental. Único contra `withTrashed()`. |
| QA-029 | Publicar sin imagen principal | Intentar publicar sin `cover` → `ValidationException`, estado sigue en `borrador`. |
| QA-030 | Publicar sin zona | Intentar publicar con `zone_id = null` → `ValidationException`, no publica. |
| QA-031 | Publicar válido | Inmueble con cover + zona → `publish()` → `status = publicado`, aparece en `published()`. |
| QA-032 | Pausar | Inmueble publicado → `pause()` → sale de `published()`; no visible públicamente. |
| QA-033 | Marcar vendido | Solo inmueble en `operation_type = venta` puede marcarse `vendido`; en `renta` → rechazo. |
| QA-034 | Marcar rentado | Solo inmueble en `operation_type = renta` puede marcarse `rentado`; en `venta` → rechazo. |
| QA-035 | Galería múltiple | Subir varias imágenes a `gallery`, reordenar y eliminar → persiste el orden y la eliminación. |
| QA-036 | Imagen principal única | Subir nuevo `cover` reemplaza el anterior (single-file). |
| QA-037 | Características | Asignar features desde catálogo → filas en `property_feature`; quitar una no borra el catálogo. |
| QA-038 | SEO fallbacks | Sin `meta_title` → `seoTitle()` = `title`; sin `meta_description` → resumen de `description`. |
| QA-039 | Aislamiento del agente | Agente A no ve ni edita inmuebles asignados a otro agente, **aunque compartan zona**; sí gestiona los sin responsable de sus zonas; owner/admin ven todos. |
| QA-040 | Regresión Épicas 1/2/3 + contratos | `UserResource`/`ZoneResource` operativos; `$zone->properties()` y `$user->properties()` resuelven contra la tabla real; Larastan en verde con PHPDoc corregido. |

### Criterios añadidos tras la auditoría (QA-041 → QA-051)

| ID | Caso | Verificación | Hallazgo |
| :--- | :--- | :--- | :--- |
| QA-041 | Agente no fuerza responsable | Agente envía `agent_id` de otro por payload → backend lo sobreescribe con el suyo. | 2.2 |
| QA-042 | Agente no asigna a zona ajena | Agente envía `zone_id` fuera de sus zonas → `ValidationException`. | 2.2 |
| QA-043 | `status`/`slug` no mass-assignable | `$property->update(['status'=>'publicado'])` y `$property->fill(['slug'=>...])` no cambian estado ni slug. El test no usa `Property::query()->update()`, que por contrato de Laravel omite `$fillable` y eventos y queda prohibido para estos campos. | 2.4 |
| QA-044 | Dos agentes, misma zona | Agente B no puede ver ni editar el inmueble asignado al agente A en su zona común. | 2.3 |
| QA-045 | Zona/portada inválidas | Publicar o reasignar un publicado a zona inactiva/sin polígono → `ValidationException`; eliminar su última portada también se rechaza. Reemplazar portada permanece permitido. | 2.1 |
| QA-046 | Soft-delete de zona | Dar de baja lógica una zona con inmuebles publicados → quedan en `pausado`; salen de `published()`. | 2.1/8.2 |
| QA-047 | Inactivar zona | Cambiar zona a `inactiva` → sus inmuebles publicados pasan a `pausado`. | 2.1/8.2 |
| QA-048 | Paridad Policy ↔ query | Un inmueble de otro agente en mi zona: ausente de mi query (`visibleTo`) Y rechazado por la Policy (`canManage`). | 7.3 |
| QA-049 | Restore y reapertura | Restaurar un publicado → vuelve a `borrador`; reabrir vendido/rentado → `borrador`. | 3.5 |
| QA-050 | Slug: id propio y concurrencia | Regenerar slug de un inmueble no colisiona consigo mismo; dos altas concurrentes no rompen por el índice único (reintento). | 3.3 |
| QA-051 | Integridad numérica en BD | `INSERT` con `price <= 0` o área negativa → rechazado por CHECK constraint, no solo por la UI. | 3.4 |

---

## 17. Plan de Implementación por Lotes (A → E)

Lotes **estrictamente incrementales**: cada uno con su DoD y verificación antes del siguiente.

```
Lote A → Lote B → Lote C → Lote D → Lote E
Modelo+   Caracte-  Estados+  Galería+   Tests+
contratos rísticas  slug      SEO+UI     regresión
```

> **Orden impuesto por la auditoría (§12):** dentro de cada lote, los tests de seguridad/dominio se escriben **antes** del Resource de Filament. El Resource es superficie de UI; la verdad (Policy, Service, scope, constraints) se prueba primero.

### Lote A — Enums, Migración, Modelo, contratos y alcance

**Archivos:**
1. `app/Enums/OperationType.php`, `PropertyType.php`, `PropertyStatus.php` (con `allowedTransitions()`)
2. `database/migrations/xxxx_create_properties_table.php` (CHECKs de enum **y numéricos**; índices `(status, operation_type)`, `property_type`, `price`, `zone_id`, `agent_id`)
3. `app/Models/Property.php` — `$fillable` **sin** `status` ni `slug`; casts, relaciones, scopes (incluido `visibleTo()`), helpers SEO, `assertPublishedInvariant()`
4. `app/Models/Zone.php` — activar `properties()` (cuerpo **+ PHPDoc** `HasMany<Property,$this>`)
5. `app/Models/User.php` — activar `properties()` (cuerpo **+ PHPDoc** `HasMany<Property,$this>`)
6. `app/Support/PropertySlugGenerator.php` (excluye id propio + reintento)
7. `app/Observers/PropertyObserver.php` (`creating` slug, `saving` invariante) + registro en `AppServiceProvider`
8. `app/Policies/PropertyPolicy.php` (precedencia `agent_id`; `delete`/`restore` exigen `properties.manage`) + registro
9. `database/factories/PropertyFactory.php` (estados válidos `published()` / inválidos nombrados)

**Verificación:**
```bash
php artisan migrate
php artisan test --filter='PropertySlugTest|PropertyScopesTest'
php artisan tinker --execute="var_dump(App\Models\Zone::first()?->properties()->getModel()::class);"  # App\Models\Property
./vendor/bin/phpstan analyse app/Models/User.php app/Models/Zone.php   # PHPDoc correcto
```

**DoD:** Migración corre en `inmo_test` con todos los CHECK constraints. `status`/`slug` NO mass-assignable (test). `Zone::properties()`/`User::properties()` resuelven a `Property` con Larastan en verde. `visibleTo()` aísla por precedencia. `PropertySlugTest` (incl. id propio) y `PropertyScopesTest` en verde.

---

### Lote B — Características dinámicas

**Archivos:**
1. `app/Models/Feature.php`
2. `database/migrations/xxxx_create_features_table.php`, `xxxx_create_property_feature_table.php`
3. `database/seeders/FeatureSeeder.php` + registro en `DatabaseSeeder`
4. `database/factories/FeatureFactory.php`
5. `Property::features()` (ya declarado en §5.6 — verificar pivote)
6. `app/Policies/FeaturePolicy.php` + registro

**Verificación:**
```bash
php artisan migrate
php artisan db:seed --class=FeatureSeeder
php artisan tinker --execute="echo App\Models\Feature::count();"   # 16
```

**DoD:** Catálogo sembrado idempotente. `PropertyFeaturesTest` en verde (asignar/quitar vía pivote).

---

### Lote C — Estados comerciales, invariante durable y slug

**Archivos:**
1. `app/Services/PropertyStatusService.php` (transacción + `lockForUpdate`; `guardPublish` exige zona activa con polígono + cover; `guardOperation`)
2. `Property::assertPublishedInvariant()` invocado en `PropertyObserver@saving`, validando zona vigente/activa/con polígono + cover (§8.3)
3. Guard `Media::deleting` para impedir borrar la última portada de un publicado, permitiendo reemplazo `singleFile` (§8.3)
4. Listeners aditivos `Zone::updated`/`Zone::deleted` en `AppServiceProvider` → pausan publicados (§8.4)
5. `PropertySlugGenerator` + reintento ante carrera (§9.3)

**Verificación:**
```bash
php artisan test --filter='PropertyStatusServiceTest|PropertyPublicationTest'
```

**DoD:** Publicación bloqueada sin cover o con zona inactiva/sin polígono. Un publicado no puede reasignarse a zona inválida ni perder su última portada; reemplazarla sí funciona. Inactivar/eliminar zona pausa sus publicados (transaccional). `status`/`slug` no cambian por asignación masiva sobre una instancia. Transición concurrente serializada por lock. Reapertura vendido/rentado → borrador.

---

### Lote D — Galería, SEO y `PropertyResource`

**Archivos:**
1. `Property` — `registerMediaCollections()` + `registerMediaConversions()` (ambas `nonQueued`) + `hasCoverImage()`
2. `app/Filament/Resources/PropertyResource.php` + Pages — `getEloquentQuery()` usa `visibleTo()`; `mutateFormDataBeforeCreate/Save` fuerza `agent_id` y valida zona del agente (§13.2)
3. Acciones: publish/pause/markSold/markRented/**reopen**/regenerateSlug/**Restore** (Restore degrada a borrador)
4. `app/Filament/Resources/FeatureResource.php` + Pages
5. Campos SEO en el form con placeholders de fallback; zona filtrada para el agente

**Verificación:**
```bash
php artisan serve
# owner/admin: crear, subir cover+galería, asignar features, publicar, pausar, vender, reabrir, restaurar
# agente: solo ve sus inmuebles + sin-responsable de su zona; intento de payload a zona/agente ajeno → rechazo
```

**DoD:** CRUD completo. Galería sube/reordena/elimina. Acciones de estado pasan por el Service. `getEloquentQuery()` usa `visibleTo()`. Forzado backend de `agent_id`/zona verificado. Restore y Reopen operativos. Badges con color por enum.

---

### Lote E — Tests, regresión y cierre

**Archivos:**
1. `tests/Unit/*` y `tests/Feature/*` (§14)
2. `tests/Feature/Regression/Epica123RegressionTest.php`

**Verificación:**
```bash
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
./vendor/bin/pint --test
./vendor/bin/phpstan analyse   # Larastan: tipos de los contratos activados
```

**DoD:** Suite completa en verde. QA-026 → **QA-051** cubiertos (incluye seguridad de agente, invariante durable, bypass de status/slug, propagación de zona, concurrencia de slug, integridad numérica). Regresión Épicas 1/2/3 sin fallos. Pint y Larastan sin errores. Sebastián valida los casos manuales.

---

## 18. Checklist de Cierre Técnico

| Elemento | Estado |
| :--- | :--- |
| Enums `OperationType`, `PropertyType`, `PropertyStatus` con `label()`/`color()` | ✅ |
| Migración `create_properties_table` con FKs `nullOnDelete`, SEO, soft delete, CHECKs, índices | ✅ |
| Modelo `Property` (casts, relaciones, scopes, helpers SEO, `HasMedia`) | ✅ |
| `status` y `slug` **fuera de `$fillable`** (no mass-assignable) | ✅ |
| CHECK constraints numéricos (`price > 0`, métricas `≥ 0`) en migración | ✅ |
| Índice inverso `(feature_id, property_id)` en el pivote | ✅ |
| Índices `zone_id`/`agent_id` en `properties` | ✅ |
| `Zone::properties()` activado (cuerpo **+ PHPDoc** `HasMany<Property,$this>`) | ✅ |
| `User::properties()` activado (cuerpo **+ PHPDoc** `HasMany<Property,$this>`) | ✅ |
| `PropertySlugGenerator` (excluye id propio + reintento concurrencia) | ✅ |
| `PropertyObserver` (`creating` slug + `saving` `assertPublishedInvariant`) registrado | ✅ |
| Catálogo `features` + pivote + `FeatureSeeder` (`updateOrCreate`, convergente) | ✅ |
| `PropertyStatusService` (transacción + `lockForUpdate`; zona activa con polígono + cover) | ✅ |
| Invariante durable: publicado no pierde cover ni se reasigna a zona inválida; reemplazo de cover permitido (§8.3) | ✅ |
| Propagación `Zone::updated`/`deleted` → pausa publicados (§8.4) | ✅ |
| Galería Media Library (`cover` single + `gallery`) con `thumb`/`web` **ambas `nonQueued`** | ✅ |
| Helpers SEO con fallbacks + `og:image` con degradado al original | ✅ |
| `PropertyPolicy`: precedencia `agent_id`; `delete`/`restore` exigen `properties.manage` | ✅ |
| `Property::scopeVisibleTo()` único, reutilizado por Policy y query | ✅ |
| Forzado backend de `agent_id` + validación de zona del agente (§13.2) | ✅ |
| Acciones Reopen y Restore (Restore degrada a borrador) | ✅ |
| `FeaturePolicy` (owner/admin; delete owner) | ✅ |
| `PropertyResource`/`FeatureResource` + Pages | ✅ |
| Factories: `published()` válido + estados inválidos nombrados | ✅ |
| Suite QA-026 → **QA-051** en verde | ✅ — 132 tests, 671 assertions |
| Regresión Épicas 1/2/3 en verde + Larastan (PHPDoc contratos) | Tests ✅; Larastan bloqueado por 19 errores preexistentes fuera de Épica 4 |
| `./vendor/bin/pint --test` y `phpstan analyse` → 0 errores | ❌ Deuda preexistente: Pint no parsea `docs/files-login-design/AdminPanelProvider.snippet.php`; PHPStan reporta 19 errores fuera de Épica 4 |

---

## 19. Decisiones Diferidas / Fuera de Alcance

| # | Tema | Estado | Épica / Destino |
| :--- | :--- | :--- | :--- |
| D-1 | Widget "conteo de inmuebles por zona" en dashboard | Diferido — `Zone::properties()` ya queda real tras esta épica; el widget es trabajo de dashboard | Épica 3 (RFC-061) / dashboard |
| D-2 | ¿El agente puede hacer soft-delete de sus propios inmuebles? | **Diferido — decisión de negocio.** Hoy `delete`/`restore` = owner/admin (pregunta abierta auditoría 10). | Revisión de negocio (Kristian) |
| D-2b | ¿Colaboración por zona o solo por asignación? | **Cerrado: solo por asignación** (precedencia `agent_id`). Reabrir requiere cambiar QA-039 + tests. | Cerrado en esta épica |
| D-2c | ¿Reapertura desde vendido/rentado requiere motivo/auditoría? | Diferido — hoy es transición simple a borrador. Auditoría de motivos es Épica 8. | Épica 8 |
| D-3 | Buscador y filtros avanzados públicos | Fuera de alcance | Épica 6 (RFC-034, RFC-042) |
| D-4 | Ficha de detalle pública + render de metadatos | Fuera de alcance | Épica 6 (RFC-035) |
| D-5 | Mapa / Google Maps sobre el inmueble | Fuera de alcance | Épica 7 (RFC-043) |
| D-6 | SEO avanzado (schema.org / JSON-LD, sitemap dinámico) | Fuera de alcance | Épica 7 (RFC-045) |
| D-7 | Propiedades destacadas (featured) | Fuera de alcance | Épica 7 (RFC-041) |
| D-8 | Métricas comerciales por inmueble (leads, conversión, vistas) | Fuera de alcance | Épica 7 (RFC-040, RFC-046) |
| D-9 | Comando de limpieza de media en `forceDelete` masivo | Diferido — los archivos de un inmueble soft-deleted **no** son huérfanos (recuperables). Solo aplica a purga definitiva. | Épica 8 (mantenimiento) |
| D-10 | Validación de no-solapamiento de zona al asignar inmueble | Diferido — heredado de R-5 Épica 3 | Épica 5/7 |

---

## 20. Registro de Cambios desde la Auditoría

Auditoría de diseño por **Gemini CLI** (`docs/audits/epica-4-auditoria-diseno.md`, registro en engram `auditoria-diseno-epica-4-inmuebles`). Veredicto original: **Rechazado** (4 hallazgos críticos). Todas las recomendaciones obligatorias (§8) y la checklist de corrección (§11) fueron aplicadas.

### 20.1 Hallazgos aplicados

| # | Hallazgo (auditoría) | Severidad | Cambio aplicado |
| :--- | :--- | :--- | :--- |
| 2.1 / 8.1 / 8.2 | Invariante de publicación no durable; `nullOnDelete` no cubre `SoftDeletes` | Crítico | `guardPublish` exige zona **existente, activa y con polígono** + cover (§8.2). Invariante durable en `saving` (§8.3). Inactivar/eliminar zona **pausa** sus publicados, transaccional (§8.4). Tests QA-045/046/047. |
| 2.2 / 8.3 | Creación/edición del agente sin forzar `agent_id` ni validar zona | Crítico | `mutateFormDataBeforeCreate/Save` fuerza `agent_id` del agente y valida que la zona sea suya en backend (§13.2). QA-041/042. |
| 2.3 / 8.4 | Acceso "asignado O por zona" deja editar inmuebles de otro agente | Crítico | Precedencia cerrada: `agent_id` manda; la zona solo da acceso a inmuebles **sin responsable**. `canManage()` ≡ `scopeVisibleTo()` (§5.6, §12). QA-039/044/048. |
| 2.4 / 8.5 | `status`/`slug` en `$fillable` → el Service no era la única puerta | Crítico | `status` y `slug` removidos de `$fillable` (§5.6). Transiciones con transacción + `lockForUpdate` (§8.2). QA-043/050. |
| 3.1 / 8.6 | PHPDoc genérico falso al activar contratos | Medio | Activación documentada como cuerpo **+ PHPDoc** `HasMany<Property,$this>` (§1, §5.6). Larastan en DoD. |
| 3.2 / 8.7 | Falta índice inverso del pivote | Medio | Índice `(feature_id, property_id)` añadido (§7.2). |
| 3.3 / 8.5 | Carrera de slug; regeneración no excluye id propio | Medio | `PropertySlugGenerator` excluye id propio y reintenta ante violación única (§9). QA-050. |
| 3.4 / 8.7 | Integridad numérica confiada a Filament | Medio | CHECK constraints `price > 0` y métricas `≥ 0` (§5.5). QA-051. |
| 3.5 / 8.8 | Restore/reapertura sin acción completa | Medio | Acciones `reopen` y `Restore` (degrada a borrador) especificadas (§13.4). QA-049. |
| 3.6 / 8.8 | QA no cubre los riesgos | Medio | QA-041 → QA-051 añadidos; tests de seguridad ampliados (§14, §16). |
| 4.1 | Conversión `web` dependiente de cola | Menor | `web` ahora `nonQueued` + degradado del `og:image` al original (§6, §10.2). R-11. |
| 4.2 | Seeder idempotente pero no convergente | Menor | `FeatureSeeder` usa `updateOrCreate` (§7.3). |
| 4.3 | Índices potencialmente redundantes | Menor | Eliminado el índice simple de `status` (cubierto por prefijo del compuesto); resto justificado y validable con `EXPLAIN` (§5.5, R-5). |
| 5 (sobreing.) | Observer como servicio invocable | — | Extraído `PropertySlugGenerator` reutilizable; el Observer solo orquesta ciclo de vida (§9). |
| 5 (sobreing.) | "media huérfana" | — | Reformulado: archivos de soft-deleted son recuperables, no huérfanos (R-4, D-9). |
| 7.4 (seguridad) | `delete`/`restore` solo por rol | — | Ahora exigen `properties.manage` **+** rol, coherente con Épica 2 (§12). |
| 7.3 (seguridad) | Alcance solo en `getEloquentQuery` | — | Encapsulado en scope reutilizable `visibleTo(User)`, probado junto a la Policy (§5.6, §12). QA-048. |

### 20.2 Hallazgos no aplicados (con justificación)

| # | Hallazgo | Tipo | Razón |
| :--- | :--- | :--- | :--- |
| 5 | Usar policy auto-discovery en lugar de `Gate::policy()` manual | Opcional | **Rechazado por consistencia.** Épicas 2 y 3 registran sus policies con `Gate::policy()` explícito; mantener el mismo patrón es más legible que mezclar dos convenciones. La auditoría lo marca como "no es un bloqueo". |
| 9.4 | Medir índices con `EXPLAIN (ANALYZE, BUFFERS)` | Opcional | **Diferido a implementación con volumen real.** No hay datos representativos en fase de diseño; el plan ya reduce a índices justificados y lo deja como verificación de Codex (§5.5). |
| 9.5 | Historial de slugs/redirects al regenerar | Opcional | **Diferido a Épica 6.** Los redirects 301 pertenecen al frontend público; aquí el slug ya no se regenera salvo acción confirmada. |
| 10 (preg. abierta) | ¿Owner/admin puede publicar en zona inactiva? | Pregunta | **Resuelto: NO.** `guardPublish` exige zona activa (§8.2), siguiendo la recomendación de la auditoría. |

---

## 21. Cierre Técnico del Diseño

**Fecha de cierre:** 19 de Junio, 2026\
**Basado en auditoría:** `docs/audits/epica-4-auditoria-diseno.md` — Gemini CLI

### 21.1 Confirmaciones de arquitectura

| Punto | Confirmado |
| :--- | :--- |
| Activación aditiva de `User::properties()`/`Zone::properties()` (cuerpo + PHPDoc), sin tocar tablas ni `zones.polygon` (`NOT NULL`) | ✅ |
| Invariante de publicación **durable**: cover + zona activa con polígono, sostenido en edición y ante baja/inactivación de zona | ✅ |
| `PropertyStatusService` única puerta de estado, transaccional con `lockForUpdate`; `status`/`slug` fuera de `$fillable` | ✅ |
| Precedencia agente↔zona cerrada (`agent_id` manda); Policy `canManage()` ≡ scope `visibleTo()` | ✅ |
| Forzado backend de `agent_id` y validación de zona del agente (no solo UI) | ✅ |
| Integridad numérica por CHECK constraints + índice inverso del pivote | ✅ |
| Slug desacoplado (`PropertySlugGenerator`), resistente a concurrencia y a id propio | ✅ |
| Galería con `cover`/`gallery`, conversiones síncronas, SEO con fallbacks seguros | ✅ |
| Acciones Restore (degrada a borrador) y Reopen especificadas | ✅ |
| Cobertura QA-026 → QA-051 mapeada a tests; regresión Épicas 1/2/3 + Larastan | ✅ |
| Plan por lotes A→E incremental, con tests de seguridad antes del Resource | ✅ |

### 21.2 Veredicto

> **✅ APROBADO PARA IMPLEMENTACIÓN**
>
> Los cuatro hallazgos críticos de la auditoría están resueltos en el diseño: el invariante de publicación es durable y transaccional, la autorización del agente se fuerza y valida en backend con precedencia cerrada, y `PropertyStatusService` es realmente la única puerta de estado al sacar `status`/`slug` de la asignación masiva. Los hallazgos medios y menores (PHPDoc, índices, concurrencia de slug, integridad numérica, restore/reapertura, QA) también fueron aplicados; las recomendaciones opcionales rechazadas están justificadas. No se introducen regresiones sobre los contratos de Épicas 1/2/3.
>
> **Codex puede iniciar el Lote A.**

---

*Documento de diseño — Épica 4 Inmuebles — 19 de Junio, 2026 (auditoría aplicada)*\
*Rama de destino: `feature/epica-4-inmuebles` desde `develop`*
