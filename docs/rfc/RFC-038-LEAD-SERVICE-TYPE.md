# RFC-038 — Catálogo `service_type` en Leads

**Proyecto:** NEW HAUZ  
**Épica de referencia:** EPICA-5 (Leads) + EPICA-6 (Frontend Público)  
**RFC:** RFC-038  
**Título:** Catálogo `service_type` en Leads: CMS + Formularios Públicos  
**Responsable Principal:** Edgar  
**QA:** Sebastián  
**Estado:** Implementado — Enmienda aplicada  
**Fecha de creación:** Junio 2026  
**Última revisión:** Junio 2026 — Enmienda post-cierre (ver `reports/AUDITORIA-RFC-038.md` y `reports/CIERRE-TECNICO-RFC-038.md`)  
**Tag objetivo:** v0.5.1

---

## 1. Contexto y Motivación

El módulo de Leads actualmente captura prospectos sin distinguir el tipo de servicio de interés del visitante. La plataforma New Hauz ofrece tres líneas de negocio diferenciadas — Comercialización inmobiliaria, Arquitectura y Construcción — y es necesario que cada lead quede etiquetado con el servicio al que corresponde para enrutar correctamente la atención comercial y producir métricas por línea de negocio.

### Superficies afectadas

| Superficie | Ruta | Comportamiento requerido |
|---|---|---|
| CMS Leads (Filament) | `/admin/leads` — `LeadResource` | Campo `service_type` con radio buttons inline; badge en listado; filtro por servicio |
| Formulario público `/contacto` | `resources/views/leads/create.blade.php` | Radio buttons visibles. El visitante elige su tipo de servicio de interés. Campo requerido. |
| Formulario en ficha de inmueble | `resources/views/inmuebles/show.blade.php` | `service_type` fijado en `comercializacion`, invisible. Locked por contexto. |
| Gestión del catálogo | `/admin/service-types` — `ServiceTypeResource` | Owner/Admin agrega, edita y activa/desactiva tipos de servicio |

> **Enmienda post-cierre:** `/contacto` muestra radio buttons y exige selección explícita del prospecto. Solo `/inmuebles/{slug}` queda locked en `comercializacion` por contexto. El componente Livewire maneja ambas superficies desde el mismo template mediante `$forced_service_type`.

---

## 2. Catálogo `service_type`

El catálogo vive en la tabla `service_types` de la base de datos. El Owner/Admin lo gestiona desde Filament. El código (`code`) es el identificador canónico que se almacena en `leads.service_type`.

| `code` | Label público | Descripción comercial |
|---|---|---|
| `comercializacion` | Comercialización | Compra, venta, renta, preventa e inmuebles. Único tipo que puede vincularse a un inmueble o zona. |
| `arquitectura` | Arquitectura | Diseño, proyecto ejecutivo, renders. Independiente de inmueble y zona. |
| `construccion` | Construcción | Construcción residencial, comercial y fraccionamientos. Independiente de inmueble y zona. |

> **Regla de negocio central:** Solo los leads de `comercializacion` pueden tener `property_id` y `zone_id`. Para `arquitectura` y `construccion` ambos campos son siempre `null`, forzado en el modelo, en el formulario Filament y en la validación.

---

## 3. Alcance Técnico

### 3.1 Migraciones (orden obligatorio)

El FK de `leads.service_type` referencia `service_types.code`. La tabla y los datos deben existir antes de agregar el FK.

**Migración A — Tabla `service_types`**

```php
// database/migrations/YYYY_MM_DD_create_service_types_table.php
public function up(): void
{
    Schema::create('service_types', function (Blueprint $table): void {
        $table->string('code', 30)->primary();
        $table->string('label', 100);
        $table->string('color', 30)->default('gray');
        $table->unsignedSmallInteger('sort_order')->default(0);
        $table->boolean('active')->default(true);
        $table->timestamps();
    });
}
```

**Migración B — Columna en `leads` (sin FK todavía)**

```php
// database/migrations/YYYY_MM_DD_add_service_type_to_leads_table.php
public function up(): void
{
    Schema::table('leads', function (Blueprint $table): void {
        $table->string('service_type', 30)
              ->default('comercializacion')
              ->after('message');
    });
}
```

**Migración C — FK (corre después del seeder)**

```php
// database/migrations/YYYY_MM_DD_add_service_type_fk_to_leads_table.php
public function up(): void
{
    Schema::table('leads', function (Blueprint $table): void {
        $table->foreign('service_type')
              ->references('code')
              ->on('service_types')
              ->restrictOnDelete();
    });
}

public function down(): void
{
    Schema::table('leads', function (Blueprint $table): void {
        $table->dropForeign(['service_type']);
    });
}
```

**Ejecución:** `php artisan migrate --seed` (el seeder debe correr entre B y C).

### 3.2 Seeder

```php
// database/seeders/ServiceTypeSeeder.php
public function run(): void
{
    $types = [
        ['code' => 'comercializacion', 'label' => 'Comercialización', 'color' => 'info',    'sort_order' => 1],
        ['code' => 'arquitectura',     'label' => 'Arquitectura',      'color' => 'warning', 'sort_order' => 2],
        ['code' => 'construccion',     'label' => 'Construcción',       'color' => 'success', 'sort_order' => 3],
    ];

    foreach ($types as $type) {
        ServiceType::firstOrCreate(['code' => $type['code']], $type);
    }
}
```

Registrar en `DatabaseSeeder` **antes** de cualquier seeder que cree Leads.

### 3.3 Modelo `ServiceType`

```php
// app/Models/ServiceType.php
class ServiceType extends Model
{
    protected $primaryKey = 'code';
    public $incrementing  = false;
    protected $keyType    = 'string';

    protected $fillable = ['code', 'label', 'color', 'sort_order', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    /** @return HasMany<Lead, $this> */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'service_type', 'code');
    }
}
```

### 3.4 Modelo `Lead`

Tres cambios en `app/Models/Lead.php`:

```php
// 1. Agregar a $fillable (después de 'message')
protected $fillable = [
    'name', 'email', 'phone', 'message',
    'service_type',  // ADD
    'source', 'status', 'property_id', 'agent_id', 'zone_id', 'assigned_at',
];

// 2. Default application-side
protected $attributes = [
    'source'       => LeadSource::Web->value,
    'status'       => LeadStatus::Nuevo->value,
    'service_type' => 'comercializacion',  // ADD
];

// 3. booted() — guardia de integridad relacional
protected static function booted(): void
{
    static::creating(function (Lead $lead): void {
        if ($lead->service_type !== 'comercializacion') {
            // arquitectura y construccion son independientes de inmueble y zona
            $lead->property_id = null;
            $lead->zone_id     = null;
            return;
        }

        // Herencia de zona desde el inmueble (solo para comercializacion)
        if ($lead->zone_id === null && $lead->property_id !== null) {
            $lead->zone_id = Property::query()
                ->whereKey($lead->property_id)
                ->value('zone_id');
        }
    });
}
```

> El `LeadServiceType` PHP enum queda **eliminado**: el catálogo es autoritativo en DB. Si hay lógica existente que referencia `LeadServiceType::Comercializacion`, reemplazar por el string `'comercializacion'`.

### 3.5 Filament — `ServiceTypeResource`

```php
// app/Filament/Resources/ServiceTypeResource.php
class ServiceTypeResource extends Resource
{
    protected static ?string $model           = ServiceType::class;
    protected static ?string $navigationGroup = 'Configuración';
    protected static ?string $navigationIcon  = 'heroicon-o-tag';
    protected static ?string $modelLabel      = 'tipo de servicio';
    protected static ?string $pluralModelLabel = 'tipos de servicio';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')
                ->label('Código')
                ->required()
                ->maxLength(30)
                ->unique(ignoreRecord: true)
                ->disabled(fn (string $operation): bool => $operation === 'edit')
                ->helperText('Identificador interno. No modificable después de creado.'),
            Forms\Components\TextInput::make('label')
                ->label('Etiqueta')
                ->required()
                ->maxLength(100),
            Forms\Components\Select::make('color')
                ->label('Color del badge')
                ->options([
                    'gray' => 'Gris', 'info' => 'Azul', 'success' => 'Verde',
                    'warning' => 'Amarillo', 'danger' => 'Rojo', 'primary' => 'Primario',
                ])
                ->required(),
            Forms\Components\TextInput::make('sort_order')->label('Orden')->numeric()->default(0),
            Forms\Components\Toggle::make('active')->label('Activo')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->label('Código')->searchable(),
                Tables\Columns\TextColumn::make('label')->label('Etiqueta'),
                Tables\Columns\TextColumn::make('color')->label('Color')->badge()
                    ->color(fn (string $state): string => $state),
                Tables\Columns\TextColumn::make('sort_order')->label('Orden')->sortable(),
                Tables\Columns\IconColumn::make('active')->label('Activo')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order');
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(['owner', 'admin']) ?? false;
    }
}
```

### 3.6 Filament — `LeadResource`

Cambios en `app/Filament/Resources/LeadResource.php`:

**Formulario — campo `service_type` con `->live()` para reactividad:**

```php
// En Section 'Datos del prospecto', después del campo 'source':
Forms\Components\Radio::make('service_type')
    ->label('Tipo de servicio')
    ->options(
        ServiceType::query()
            ->where('active', true)
            ->orderBy('sort_order')
            ->pluck('label', 'code')
            ->all()
    )
    ->required()
    ->inline()
    ->live()           // dispara re-render para el campo property_id
    ->columnSpanFull(),
```

**Formulario — `property_id` condicional:**

```php
// En Section 'Gestión', campo property_id existente: agregar ->visible()
Forms\Components\Select::make('property_id')
    ->label('Inmueble')
    ->relationship('property', 'title', fn (Builder $query): Builder => ...)
    ->searchable()
    ->preload()
    ->visible(fn (Forms\Get $get): bool => $get('service_type') === 'comercializacion'),
```

**Columna en tabla:**

```php
Tables\Columns\TextColumn::make('service_type')
    ->label('Servicio')
    ->badge()
    ->formatStateUsing(fn (string $state): string =>
        ServiceType::find($state)?->label ?? $state
    )
    ->color(fn (string $state): string =>
        ServiceType::find($state)?->color ?? 'gray'
    ),
```

**Filtro:**

```php
SelectFilter::make('service_type')
    ->label('Servicio')
    ->options(
        ServiceType::query()
            ->where('active', true)
            ->orderBy('sort_order')
            ->pluck('label', 'code')
            ->all()
    ),
```

### 3.7 Componente Livewire — `LeadCaptureForm`

Archivo: `app/Livewire/Leads/LeadCaptureForm.php`

**Imports a agregar:**
```php
use Livewire\Attributes\Locked;
```

**Propiedades nuevas:**
```php
public string $service_type = '';  // vacío: fuerza selección explícita en /contacto

// #[Locked] impide que el cliente modifique esta propiedad vía Livewire.
// Es la guardia de seguridad real para el modo bloqueado.
#[Locked]
public ?string $forced_service_type = null;
```

**`mount()` actualizado:**
```php
public function mount(
    ?int    $propertyId  = null,
    ?string $source      = null,
    ?string $serviceType = null,
    bool    $locked      = false,
): void {
    $this->property_id = $propertyId;

    if ($source !== null) {
        $this->source = $source;
    }

    if ($serviceType !== null) {
        $this->service_type = $serviceType;
    }

    if ($locked && $serviceType !== null) {
        $this->forced_service_type = $serviceType;
    }
}
```

**`submit()` — guardia server-side antes de `validate()`:**
```php
public function submit(): void
{
    // ... rate limiter y honeypot existentes ...

    // Si el componente está bloqueado, imponer el valor del servidor
    // independientemente de lo que el cliente haya podido enviar.
    if ($this->forced_service_type !== null) {
        $this->service_type = $this->forced_service_type;
    }

    $validated = $this->validate();

    $lead = Lead::create([
        // ... campos existentes ...
        'service_type' => $validated['service_type'],  // ADD
    ]);
    // ...
}
```

**Reglas de validación actualizadas:**
```php
protected function rules(): array
{
    return [
        // ... reglas existentes ...
        'service_type' => ['required', Rule::exists('service_types', 'code')->where('active', true)],
        'property_id'  => $this->service_type === 'comercializacion'
            ? ['nullable', 'integer', Rule::exists('properties', 'id')
                ->where('status', PropertyStatus::Publicado->value)
                ->whereNotNull('agent_id')]
            : ['prohibited'],
    ];
}
```

### 3.8 Vista Blade del Componente

Archivo: `resources/views/livewire/leads/lead-capture-form.blade.php`

Agregar antes del botón de submit (después del campo `message`):

```blade
{{-- service_type: radio buttons cuando no está bloqueado (futuras superficies);
     campo hidden cuando sí (ambas superficies públicas actuales) --}}
@if ($forced_service_type === null)
    <div>
        <p class="mb-2 text-sm font-semibold text-graphite">Tipo de servicio</p>
        <div class="flex flex-wrap gap-3">
            @foreach (\App\Models\ServiceType::query()->where('active', true)->orderBy('sort_order')->get() as $type)
                <label class="flex cursor-pointer items-center gap-2">
                    <input
                        type="radio"
                        wire:model="service_type"
                        value="{{ $type->code }}"
                        class="h-4 w-4 accent-orange"
                    >
                    <span class="text-sm text-graphite">{{ $type->label }}</span>
                </label>
            @endforeach
        </div>
        @error('service_type')
            <p class="mt-1.5 text-xs text-danger">{{ $message }}</p>
        @enderror
    </div>
@else
    <input type="hidden" wire:model="service_type">
@endif
```

### 3.9 Invocación del Componente por Superficie

#### `/contacto` — `resources/views/leads/create.blade.php`

```blade
{{-- Radio buttons visibles. El prospecto elige su tipo de servicio. --}}
<livewire:leads.lead-capture-form source="web" />
```

> Sin `service-type` ni `:locked`. El componente arranca con `$service_type = ''` y la validación `required` obliga al prospecto a elegir explícitamente antes de enviar.

#### `/inmuebles/{slug}` — `resources/views/inmuebles/show.blade.php`

```blade
{{-- ANTES --}}
<livewire:leads.lead-capture-form :property-id="$property->id" source="web" />

{{-- DESPUÉS — mantener property_id, agregar lock --}}
<livewire:leads.lead-capture-form
    :property-id="$property->id"
    source="web"
    service-type="comercializacion"
    :locked="true"
/>
```

---

## 4. Reglas de Negocio

1. `service_type` es **obligatorio** en toda creación de Lead.
2. El valor default absoluto es `comercializacion`.
3. En `/contacto` el visitante elige el tipo de servicio mediante radio buttons visibles. El campo es requerido; sin selección el formulario no envía.
4. En `/inmuebles/{slug}` el `service_type` se fija en `comercializacion` de forma transparente (locked). El componente `LeadCaptureForm` usa `#[Locked] $forced_service_type` + guardia en `submit()`. La manipulación del payload Livewire no puede cambiar el valor.
5. Solo leads de `comercializacion` pueden tener `property_id` y `zone_id`. Para `arquitectura` y `construccion` ambos son siempre `null`. Esta regla se impone en el modelo (`booted()`), en Filament (campo condicional) y en la validación Livewire (`prohibited`).
6. El catálogo vive en la tabla `service_types`. El `code` es inmutable post-creación. Owner y Admin lo gestionan desde Filament.
7. La validación siempre usa `Rule::exists('service_types', 'code')->where('active', true)`. Si un tipo se desactiva, deja de ser válido para nuevos leads sin cambios de código.
8. En el CMS, el campo `service_type` es editable por Owner y Admin en la sección "Datos del prospecto" (hereda el `->disabled()` de la section). El Agente puede verlo pero no modificarlo.

---

## 5. Criterios de Aceptación

| ID | Criterio | Verificable por |
|---|---|---|
| CA-001 | Las tres migraciones ejecutan sin error; columna y FK existen en PostgreSQL | Sebastián QA |
| CA-002 | El seeder popula `service_types` con los tres tipos iniciales | Edgar |
| CA-003 | El modelo `Lead` acepta `service_type` en `$fillable` | Edgar / Codex |
| CA-004 | Filament `LeadResource` muestra radio buttons al crear/editar un Lead | Sebastián QA |
| CA-005 | El campo `property_id` desaparece en Filament al seleccionar `arquitectura` o `construccion` | Sebastián QA |
| CA-006 | El badge de `service_type` aparece correctamente coloreado en el listado | Sebastián QA |
| CA-007 | El filtro de tabla por `service_type` funciona correctamente | Sebastián QA |
| CA-008 | En `/contacto` se muestran radio buttons; enviar sin seleccionar muestra error de validación | Sebastián QA |
| CA-009 | En `/contacto` seleccionar "Arquitectura" crea lead con `service_type = arquitectura` y `property_id = null` | Sebastián QA |
| CA-010 | `/inmuebles/{slug}` envía siempre `comercializacion`; sin radio buttons visibles | Sebastián QA |
| CA-010b | Manipulación del payload Livewire en `/inmuebles/{slug}` no cambia `service_type` | Edgar |
| CA-011 | Lead de `arquitectura` creado desde CMS queda con `property_id = null` y `zone_id = null` | Sebastián QA |
| CA-012 | Los leads existentes tienen `service_type = comercializacion` después de la migración | Sebastián QA |
| CA-013 | Owner puede agregar un nuevo tipo desde `/admin/service-types` y queda disponible en LeadResource | Sebastián QA |

---

## 6. Casos QA

| ID QA | Descripción | Resultado esperado |
|---|---|---|
| QA-038-01 | Ejecutar migraciones A, B (seeder), C en orden | Éxito; `service_types` con 3 filas; FK activo |
| QA-038-02 | Crear Lead desde CMS sin seleccionar `service_type` | Validación impide guardar |
| QA-038-03 | Crear Lead desde CMS seleccionando "Arquitectura" | Lead con `service_type = arquitectura`, `property_id = null`, `zone_id = null` |
| QA-038-04 | Ver listado de Leads en CMS | Badge de color correcto por servicio |
| QA-038-05 | Filtrar leads por "Construcción" en CMS | Solo aparecen leads de construcción |
| QA-038-06 | Enviar formulario en `/contacto` sin seleccionar tipo de servicio | Error de validación visible: "El tipo de servicio es obligatorio" |
| QA-038-07 | En `/contacto` seleccionar "Arquitectura" y enviar | Lead con `service_type = arquitectura`, `property_id = null`, `zone_id = null` |
| QA-038-07b | En `/contacto` seleccionar "Comercialización" y enviar | Lead con `service_type = comercializacion` |
| QA-038-08 | Enviar formulario en `/inmuebles/{slug}` | Lead con `service_type = comercializacion` y `property_id` del inmueble correcto |
| QA-038-09 | Inspeccionar DOM en `/contacto` | Existen radio buttons visibles y ninguno pre-seleccionado |
| QA-038-09b | Inspeccionar DOM en `/inmuebles/{slug}` | No existen radio buttons; existe `<input type="hidden">` con `comercializacion` |
| QA-038-10 | Manipular payload Livewire desde `/inmuebles/{slug}` para setear `service_type = arquitectura` | Lead guardado con `comercializacion` |
| QA-038-10 | Revisar leads pre-existentes después de migración | Todos tienen `service_type = comercializacion` |
| QA-038-11 | Crear Lead de Arquitectura desde CMS; verificar en DB | `property_id IS NULL`, `zone_id IS NULL` |
| QA-038-12 | Owner agrega tipo "Asesoría" desde ServiceTypeResource | Tipo disponible en radio buttons de LeadResource |
| QA-038-13 | Owner desactiva tipo existente | Deja de aparecer en opciones; leads existentes sin cambios |
| QA-038-14 | Agente intenta editar `service_type` en un Lead | Campo deshabilitado (readonly); no puede modificar |
| QA-038-15 | Responsive en móvil para radio buttons (modo no-locked) | Legibles y táctiles |

---

## 7. Archivos Modificados

| Archivo | Tipo de cambio |
|---|---|
| `database/migrations/..._create_service_types_table.php` | Nuevo |
| `database/migrations/..._add_service_type_to_leads_table.php` | Nuevo |
| `database/migrations/..._add_service_type_fk_to_leads_table.php` | Nuevo |
| `database/seeders/ServiceTypeSeeder.php` | Nuevo |
| `database/seeders/DatabaseSeeder.php` | Modificación (registrar seeder) |
| `app/Models/ServiceType.php` | Nuevo |
| `app/Filament/Resources/ServiceTypeResource.php` | Nuevo |
| `app/Enums/LeadServiceType.php` | Eliminar |
| `app/Models/Lead.php` | Modificación (`$fillable`, `$attributes`, `booted()`) |
| `app/Filament/Resources/LeadResource.php` | Modificación (form, table, filters) |
| `app/Livewire/Leads/LeadCaptureForm.php` | Modificación (props, mount, submit, rules) |
| `resources/views/livewire/leads/lead-capture-form.blade.php` | Modificación (bloque service_type) |
| `resources/views/leads/create.blade.php` | Modificación (parámetros de invocación) |
| `resources/views/inmuebles/show.blade.php` | Modificación (parámetros de invocación) |

---

## 8. Dependencias

- RFC-025 Modelo Lead ✅
- RFC-026 Captura de Leads ✅
- RFC-035 Detalle Inmueble ✅
- RFC-036 Contacto ✅

---

## 9. Definition of Done

- [ ] Tres migraciones ejecutadas; tabla `service_types` con FK activo.
- [ ] `ServiceTypeSeeder` corre sin error; tres tipos iniciales presentes.
- [ ] Modelo `Lead` actualizado: `$fillable`, `$attributes`, `booted()` con guardia.
- [ ] `ServiceType` model y `ServiceTypeResource` creados.
- [ ] `LeadResource` muestra radio desde DB, `property_id` reactivo, badge en listado, filtro.
- [ ] `LeadCaptureForm` usa `#[Locked] $forced_service_type`; guardia en `submit()`; validación condicional de `property_id`.
- [ ] Ambas superficies públicas invocan el componente con `service-type="comercializacion" :locked="true"`.
- [ ] Vista Blade usa `$forced_service_type !== null` como discriminador.
- [ ] `app/Enums/LeadServiceType.php` eliminado; ninguna referencia queda en el código.
- [ ] CA-001 a CA-013 verificados.
- [ ] QA-038-01 a QA-038-15 aprobados por Sebastián.
- [ ] Sin regresiones en leads existentes.

---

---

# PROMPTS MULTIMODELO — RFC-038 (revisados post-auditoría)

---

## PROMPT 1 — CLAUDE (Arquitecto)

```
Eres el arquitecto técnico del proyecto NEW HAUZ, una plataforma inmobiliaria en Laravel 13 + Filament v4 + Livewire v3 + Tailwind CSS v4 + PostgreSQL + PostGIS.

Tu tarea es producir el diseño técnico completo para el RFC-038: "Catálogo service_type en Leads".

### Stack
- Laravel 13, PHP 8.3+, Livewire v3, Filament v4 (/admin únicamente), Tailwind v4, PostgreSQL
- Sin enums nativos de PostgreSQL
- Filament v4: Radio::make()->inline(), TextColumn->badge()->color(), SelectFilter

### Requerimiento

El módulo de Leads debe incorporar un campo `service_type` que clasifica cada prospecto según la línea de negocio de interés. El catálogo vive en una tabla `service_types` en DB (el catálogo evolucionará; Owner/Admin lo gestiona desde Filament).

### Catálogo inicial

| code | label | descripción |
|------|-------|-------------|
| comercializacion | Comercialización | Compra, venta, renta. Único tipo vinculado a inmueble y zona. |
| arquitectura | Arquitectura | Diseño y proyecto. Independiente de inmueble y zona. |
| construccion | Construcción | Obra residencial y comercial. Independiente de inmueble y zona. |

### Superficies involucradas

1. **CMS Filament** (`LeadResource`): radio buttons inline con opciones desde DB. Badge en listado con color desde DB. Filtro por servicio. El campo `property_id` es reactivo: visible solo cuando `service_type = 'comercializacion'`.

2. **Formulario público `/contacto`** y **ficha `/inmuebles/{slug}`**: ambas superficies fijan `service_type = 'comercializacion'` de forma transparente (campo oculto, usuario no elige). El componente Livewire `LeadCaptureForm` es el mismo para ambas superficies, parametrizado.

3. **ServiceTypeResource**: Owner/Admin crea y edita tipos. El `code` es inmutable post-creación.

### Reglas de negocio críticas

- Solo `service_type = 'comercializacion'` puede tener `property_id` y `zone_id`. Para `arquitectura` y `construccion` ambos son siempre null. Esta regla se impone en el modelo (`booted()`), en Filament (`->visible()` reactivo) y en la validación Livewire (`'prohibited'`).
- Seguridad Livewire: usar `#[Locked] public ?string $forced_service_type = null` como guardia. En `submit()` sobreescribir `$service_type` si `$forced_service_type !== null` antes de `validate()`. NO usar `public bool $locked` sin `#[Locked]`.
- La validación usa `Rule::exists('service_types', 'code')->where('active', true)`, nunca hardcoded.

### Entregables

1. Diseño de las tres migraciones (create_service_types, add_col_sin_fk, add_fk) y seeder.
2. Modelo ServiceType (code como PK string).
3. Modelo Lead: $fillable, $attributes default, booted() con guardia de service_type.
4. ServiceTypeResource en Filament (code inmutable en edit).
5. LeadResource: Radio con live(), property_id con visible() reactivo, badge desde DB, filtro desde DB.
6. LeadCaptureForm: #[Locked] forced_service_type, mount() params, submit() guard, validación condicional property_id.
7. Vista Blade: discriminador $forced_service_type !== null.
8. Invocaciones correctas en create.blade.php y show.blade.php (ambas con locked=true).
9. Matriz de archivos modificados.

Produce pseudocódigo PHP/Blade real listo para que el implementador lo tome como referencia directa.
```

---

## PROMPT 2 — CODEX (Developer Senior)

```
Eres un developer senior de Laravel especializado en el stack TALL (Laravel 13, Livewire v3, Alpine.js, Tailwind CSS v4) y Filament v4.

Tu tarea es implementar el RFC-038 "Catálogo service_type en Leads" para el proyecto NEW HAUZ.

### Stack y contexto

- Laravel 13, PHP 8.3+, Livewire v3, Filament v4 (admin únicamente), Tailwind v4, PostgreSQL + PostGIS
- El módulo de Leads ya existe: modelo `Lead`, componente `LeadCaptureForm`, recurso `LeadResource`
- El componente está embebido en `resources/views/leads/create.blade.php` y `resources/views/inmuebles/show.blade.php`

### Implementación (en este orden exacto)

---

#### PASO 1 — Migración A: tabla service_types

```bash
php artisan make:migration create_service_types_table
```

```php
Schema::create('service_types', function (Blueprint $table): void {
    $table->string('code', 30)->primary();
    $table->string('label', 100);
    $table->string('color', 30)->default('gray');
    $table->unsignedSmallInteger('sort_order')->default(0);
    $table->boolean('active')->default(true);
    $table->timestamps();
});
```

#### PASO 2 — Migración B: columna en leads (sin FK)

```bash
php artisan make:migration add_service_type_to_leads_table
```

```php
Schema::table('leads', function (Blueprint $table): void {
    $table->string('service_type', 30)->default('comercializacion')->after('message');
});
```

#### PASO 3 — Seeder ServiceTypeSeeder

```php
// database/seeders/ServiceTypeSeeder.php
public function run(): void
{
    $types = [
        ['code' => 'comercializacion', 'label' => 'Comercialización', 'color' => 'info',    'sort_order' => 1],
        ['code' => 'arquitectura',     'label' => 'Arquitectura',      'color' => 'warning', 'sort_order' => 2],
        ['code' => 'construccion',     'label' => 'Construcción',       'color' => 'success', 'sort_order' => 3],
    ];
    foreach ($types as $type) {
        \App\Models\ServiceType::firstOrCreate(['code' => $type['code']], $type);
    }
}
```

Registrar en DatabaseSeeder ANTES de cualquier seeder que cree Leads.

Ejecutar: `php artisan db:seed --class=ServiceTypeSeeder`

#### PASO 4 — Migración C: FK

```bash
php artisan make:migration add_service_type_fk_to_leads_table
```

```php
Schema::table('leads', function (Blueprint $table): void {
    $table->foreign('service_type')->references('code')->on('service_types')->restrictOnDelete();
});
```

Ejecutar: `php artisan migrate`

#### PASO 5 — Modelo ServiceType

Crear `app/Models/ServiceType.php`:

```php
class ServiceType extends Model
{
    protected $primaryKey = 'code';
    public $incrementing  = false;
    protected $keyType    = 'string';
    protected $fillable   = ['code', 'label', 'color', 'sort_order', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
```

#### PASO 6 — Modelo Lead

En `app/Models/Lead.php`:

1. Agregar `'service_type'` a `$fillable` después de `'message'`.
2. Agregar a `$attributes`: `'service_type' => 'comercializacion'`.
3. Actualizar `booted()`:

```php
protected static function booted(): void
{
    static::creating(function (Lead $lead): void {
        if ($lead->service_type !== 'comercializacion') {
            $lead->property_id = null;
            $lead->zone_id     = null;
            return;
        }
        if ($lead->zone_id === null && $lead->property_id !== null) {
            $lead->zone_id = Property::query()->whereKey($lead->property_id)->value('zone_id');
        }
    });
}
```

4. Eliminar `app/Enums/LeadServiceType.php` y remover sus imports si existen.

#### PASO 7 — ServiceTypeResource

Crear `app/Filament/Resources/ServiceTypeResource.php` con:
- `code` deshabilitado en operación 'edit' (`->disabled(fn(string $op) => $op === 'edit')`)
- `->unique(ignoreRecord: true)` en creación
- Opciones de color: gray, info, success, warning, danger, primary
- Toggle `active`
- Tabla con `->reorderable('sort_order')`
- `canViewAny()` solo owner/admin

#### PASO 8 — LeadResource

En `app/Filament/Resources/LeadResource.php`:

**Formulario — Section 'Datos del prospecto', después de 'source':**
```php
Forms\Components\Radio::make('service_type')
    ->label('Tipo de servicio')
    ->options(
        \App\Models\ServiceType::query()
            ->where('active', true)->orderBy('sort_order')
            ->pluck('label', 'code')->all()
    )
    ->required()->inline()->live()->columnSpanFull(),
```

**Formulario — Section 'Gestión', campo property_id existente, agregar:**
```php
->visible(fn (Forms\Get $get): bool => $get('service_type') === 'comercializacion')
```

**Tabla — columna después de 'source':**
```php
Tables\Columns\TextColumn::make('service_type')
    ->label('Servicio')->badge()
    ->formatStateUsing(fn (string $state): string =>
        \App\Models\ServiceType::find($state)?->label ?? $state)
    ->color(fn (string $state): string =>
        \App\Models\ServiceType::find($state)?->color ?? 'gray'),
```

**Filtros — agregar junto a los existentes:**
```php
SelectFilter::make('service_type')
    ->label('Servicio')
    ->options(\App\Models\ServiceType::query()
        ->where('active', true)->orderBy('sort_order')
        ->pluck('label', 'code')->all()),
```

#### PASO 9 — LeadCaptureForm

En `app/Livewire/Leads/LeadCaptureForm.php`:

1. Agregar import: `use Livewire\Attributes\Locked;`

2. Agregar propiedades:
```php
public string $service_type = 'comercializacion';

#[Locked]
public ?string $forced_service_type = null;
```

3. Actualizar `mount()` — agregar parámetros al final:
```php
public function mount(
    ?int    $propertyId  = null,
    ?string $source      = null,
    ?string $serviceType = null,
    bool    $locked      = false,
): void {
    $this->property_id = $propertyId;
    if ($source !== null) { $this->source = $source; }
    if ($serviceType !== null) { $this->service_type = $serviceType; }
    if ($locked && $serviceType !== null) { $this->forced_service_type = $serviceType; }
}
```

4. En `submit()`, ANTES de `$this->validate()`:
```php
if ($this->forced_service_type !== null) {
    $this->service_type = $this->forced_service_type;
}
```

5. En `submit()`, incluir en `Lead::create()`:
```php
'service_type' => $validated['service_type'],
```

6. En `rules()`, agregar:
```php
'service_type' => ['required', Rule::exists('service_types', 'code')->where('active', true)],
'property_id'  => $this->service_type === 'comercializacion'
    ? ['nullable', 'integer', Rule::exists('properties', 'id')
        ->where('status', PropertyStatus::Publicado->value)->whereNotNull('agent_id')]
    : ['prohibited'],
```

(Eliminar la regla `property_id` existente y reemplazarla por la condicional)

#### PASO 10 — Vista Blade del componente

En `resources/views/livewire/leads/lead-capture-form.blade.php`, agregar antes del botón de submit:

```blade
@if ($forced_service_type === null)
    <div>
        <p class="mb-2 text-sm font-semibold text-graphite">Tipo de servicio</p>
        <div class="flex flex-wrap gap-3">
            @foreach (\App\Models\ServiceType::query()->where('active', true)->orderBy('sort_order')->get() as $type)
                <label class="flex cursor-pointer items-center gap-2">
                    <input type="radio" wire:model="service_type" value="{{ $type->code }}" class="h-4 w-4 accent-orange">
                    <span class="text-sm text-graphite">{{ $type->label }}</span>
                </label>
            @endforeach
        </div>
        @error('service_type') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
    </div>
@else
    <input type="hidden" wire:model="service_type">
@endif
```

#### PASO 11 — Vistas de invocación

**`resources/views/leads/create.blade.php`:**
```blade
{{-- Reemplazar la invocación existente --}}
<livewire:leads.lead-capture-form
    source="web"
    service-type="comercializacion"
    :locked="true"
/>
```

**`resources/views/inmuebles/show.blade.php`:**
```blade
{{-- Reemplazar la invocación existente --}}
<livewire:leads.lead-capture-form
    :property-id="$property->id"
    source="web"
    service-type="comercializacion"
    :locked="true"
/>
```

---

### Verificación final

```bash
php artisan migrate:status
php artisan config:clear && php artisan view:clear
composer test
```

Validar manualmente:
- `/admin/service-types` → CRUD funcional; code deshabilitado en edit
- `/admin/leads/create` → radio buttons visibles; `property_id` desaparece con arquitectura/construccion
- `/contacto` → sin radio buttons; lead guardado con `comercializacion`
- `/inmuebles/{slug}` → sin radio buttons; lead con `service_type = comercializacion` y `property_id` correcto
- Manipular payload Livewire en superficies públicas → no cambia `service_type`

### Reglas que no debes romper

- NO usar `public bool $service_type_locked` sin `#[Locked]`.
- NO hardcodear los valores del catálogo; siempre desde `service_types` en DB.
- El `booted()` de Lead debe mantenerse como única fuente de verdad para la herencia de zona.
- No duplicar el componente `LeadCaptureForm`.
- Respetar el orden de migrations: A → seeder → B → C.
- Correr Pint antes del commit: `./vendor/bin/pint`
```

---

## 10. Notas de Implementación

- **Orden de ejecución:** Migraciones A+B → Seeder → Migración C → Modelos → Filament → Livewire → Vistas.
- **Testing local:** Probar los cuatro flujos: CMS, `/contacto`, `/inmuebles/{slug}`, ServiceTypeResource.
- **Branch:** `feature/leads-contact-public` (rama actual del proyecto)
- **Commit de cierre:** `feat(leads): agrega catálogo service_type con tabla service_types (RFC-038)`
- **Auditoría:** Ver `docs/rfc/reports/AUDITORIA-RFC-038.md` para el detalle de hallazgos y resoluciones.
