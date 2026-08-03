# Épica 3 — Corrección: Catálogo Geográfico y Polígonos Automáticos

**Proyecto:** New Hauz — Plataforma Inmobiliaria **Alcance:** RFC-015 → RFC-018 (Zonas Comerciales) — **ya implementada**, se aplica change request **Rama base:** `develop` **Rama de trabajo:** `feature/epica-3-geografia-poligonos` **Arquitecto:** Edgar / Kristian **QA:** Sebastián **Stack (contrato vigente):** Laravel 13.x · PHP 8.3 · PostgreSQL 18 \+ PostGIS 3.6 · Filament v3.3.54 · Livewire 3.8.1

---

## 1\. Objetivo del cambio

La Épica 3 está implementada con `municipality` como string libre y `status` etiquetado como "Estado" en la UI. Este change request profesionaliza la capa geográfica:

1. Renombrar en la vista la columna `status`: de **"Estado"** → **"Estatus"**.  
2. Crear catálogo geográfico real de México (relacional), con tres tablas: `countries`, `states`, `municipalities`. Solo México (descartar USA).  
3. Añadir a `Zone` la relación **State** (etiqueta UI "Estado"), la relación **Municipio** (uno-a-muchos: un estado tiene muchos municipios) y el campo **Código Postal**.  
4. Con `Estado + Municipio + C.P.` resueltos, **geocodificar y centrar** el componente Google Maps.  
5. Llenar el campo `polygon` **automáticamente**: el usuario dibuja el polígono en Google Maps y el sistema extrae el GeoJSON del polígono y lo persiste como geometría PostGIS.

---

## 2\. Decisiones de arquitectura (CERRADAS)

| Tema | Decisión |
| :---- | :---- |
| Tabla países | Se crea `countries` pero **solo se siembra México**. Filtro explícito sobre `paises.sql`. USA no se importa. |
| Estados | Tabla `states` con FK `country_id` → 32 estados de México. |
| Municipios | Tabla `municipalities` con FK `state_id`. Relación **State 1—N Municipality**. |
| `municipality` (string actual en Zone) | Se **deprecia** y migra a `municipality_id` (FK). El string libre desaparece. |
| Etiqueta de `status` | Solo cambia el **label** en la UI a "Estatus". La columna/lógica/enum NO se tocan. |
| Código Postal | Campo `postal_code` en `zones` (5 dígitos MX). Es **un solo** C.P. representativo de la zona. |
| Origen del polígono | El usuario lo dibuja con Google Maps **Drawing Manager**. No se captura a mano. |
| Formato de transporte del polígono | **GeoJSON** (`type: Polygon`, anillo cerrado, orden `[lng, lat]`) en la capa Livewire/form. |
| Formato de persistencia | **PostGIS `geometry(Polygon, 4326)`** (columna ya existente de RFC-018). Conversión GeoJSON ↔ geometry en el límite de persistencia. |
| Center point | Se deriva con `ST_Centroid(polygon)` al guardar (reutilizable para marcadores en listados). |
| Puente PostGIS ↔ Eloquent | Se recomienda `clickbar/laravel-magellan` (cast `Polygon`, helpers `ST_*`). Fallback: SQL crudo con binding parametrizado. |
| Idempotencia import | Seeder/comando con `upsert` por clave natural (clave INEGI / código), re-ejecutable sin duplicar. |

**Principio rector:** GeoJSON vive en la capa de UI/form; PostGIS vive en la base de datos. La conversión ocurre en un único punto (mutador del Resource / cast), nunca disperso.

---

## 3\. Modelo de datos

### 3.1 Tablas nuevas

```
countries
├── id              bigserial PK
├── name            varchar       -- 'México'
├── iso2            varchar(2)    -- 'MX'   (si el origen lo trae)
├── clave           varchar NULL  -- clave del dump origen
└── timestamps

states                              (32 filas)
├── id              bigserial PK
├── country_id      bigint FK → countries.id
├── name            varchar       -- 'Querétaro', 'Jalisco', ...
├── clave           varchar NULL  -- clave INEGI / clave del dump
├── source_id       bigint NULL   -- id original en estados.sql (mapeo)
└── timestamps
   idx (country_id), unique (country_id, name)

municipalities
├── id              bigserial PK
├── state_id        bigint FK → states.id   (1—N)
├── name            varchar
├── clave           varchar NULL
├── source_id       bigint NULL  -- id original en municipios.sql (mapeo)
└── timestamps
   idx (state_id), unique (state_id, name)
```

`source_id` permite mapear correctamente municipios → estados durante el import aunque los PK finales difieran del dump.

### 3.2 Alteración de `zones`

```
zones (estado final post-corrección)
├── ... (campos previos de RFC-015)
├── municipality        ← SE ELIMINA (string libre)
├── state_id            bigint  FK → states.id        nullOnDelete   ← NUEVO  (UI: "Estado")
├── municipality_id     bigint  FK → municipalities.id nullOnDelete  ← NUEVO  (UI: "Municipio")
├── postal_code         varchar(10) NULL                             ← NUEVO  (UI: "Código Postal")
├── polygon             geometry(Polygon,4326) NULL   (RFC-018, ya existe)
├── center_point        geometry(Point,4326)   NULL   ← derivado de ST_Centroid (opcional)
└── status              ← SIN CAMBIO (solo cambia el label en UI a "Estatus")
   idx (state_id), idx (municipality_id)
```

### 3.3 Relaciones

```
Country ──hasMany──► State
State   ──belongsTo─► Country
State   ──hasMany──► Municipality
Municipality ─belongsTo─► State
Zone ──belongsTo──► State          (state_id)
Zone ──belongsTo──► Municipality   (municipality_id)
```

---

## 4\. Flujo funcional del formulario de Zona

```
1. Select "Estado" (state_id)  ──live──►  habilita y carga "Municipio"
2. Select "Municipio" (municipality_id, dependiente de state_id)
3. Input  "Código Postal" (postal_code, live debounce)
        │
        ▼  (los 3 valores se exponen al componente de mapa)
4. Google Maps geocodifica:  "{C.P.}, {Municipio}, {Estado}, México"
   → recentra + zoom a la zona
5. Usuario dibuja el polígono (Drawing Manager)
6. polygoncomplete / edición de vértices
   → path → GeoJSON {type:'Polygon', coordinates:[[ [lng,lat], ... , [lng,lat] ]]}  (anillo cerrado)
   → se escribe en el campo oculto `polygon` (estado del form)
7. Al guardar:
   GeoJSON → ST_SetSRID(ST_GeomFromGeoJSON(:geojson),4326)  → zones.polygon
   center_point = ST_Centroid(polygon)
8. Al editar:
   zones.polygon → ST_AsGeoJSON → se re-dibuja el polígono (editable) en el mapa
```

---

## 5\. Plan de implementación por Lotes (A → E)

```
Lote A → Lote B → Lote C → Lote D → Lote E
Catálogo  Modelo   Filament  Google   PostGIS
geográfico Zona    Resource  Maps     + Tests
(import)   (FKs)   (labels)  (draw)   (persist)
```

Cada lote cierra su DoD antes del siguiente. Los prompts de la Sección 7 están listos para pegar a cada agente.

---

## 6\. Criterios de aceptación (QA-022 → QA-030)

| ID | Caso | Verificación |
| :---- | :---- | :---- |
| QA-022 | Import solo México | `countries` tiene 1 fila (México). USA ausente. `states` \= 32\. |
| QA-023 | Integridad municipios | Cada municipio pertenece a un estado mexicano válido (sin huérfanos). |
| QA-024 | Label Estatus | Columna/campo `status` se muestra como **"Estatus"** en tabla y form. |
| QA-025 | Estado en UI | Select `state_id` se muestra como **"Estado"**; lista los 32 estados. |
| QA-026 | Municipio dependiente | Al elegir un estado, el Select de municipio carga **solo** los de ese estado. |
| QA-027 | Código Postal | Campo `postal_code` acepta 5 dígitos y persiste. |
| QA-028 | Geocodificación | Con Estado+Municipio+C.P. el mapa recentra a la ubicación correcta. |
| QA-029 | Polígono → GeoJSON | Dibujar polígono llena `polygon` con GeoJSON válido (anillo cerrado, SRID 4326). |
| QA-030 | Persistencia PostGIS | `ST_IsValid(polygon)` \= true; al editar, el polígono se re-dibuja. `center_point = ST_Centroid`. |

---

## 7\. Prompts para ejecución multimodelo

Convención: **Codex** \= agente de programación (escribe código por lote). **Gemini CLI** \= auditor. Pega cada bloque tal cual. Ejecuta `php artisan test` y verifica el DoD antes de avanzar al siguiente lote.

---

### 🟦 PROMPT — LOTE A · Catálogo geográfico (Codex)

```
ROL: Eres el agente de programación del proyecto New Hauz (Laravel 13.x, PostgreSQL 18 + PostGIS 3.6, Filament v3.3.54). Implementas el Lote A del change request "Épica 3 — Geografía y Polígonos". Trabajas en la rama feature/epica-3-geografia-poligonos.

CONTEXTO:
- Existen tres dumps SQL en /newhauz/db_estados/: paises.sql, estados.sql, municipios.sql. Son catálogos geográficos. Pueden venir en sintaxis MySQL.
- Solo nos interesa MÉXICO. NO se debe importar Estados Unidos (USA) ni ningún otro país.

OBJETIVO: Crear el catálogo geográfico relacional (countries, states, municipalities) e importar únicamente México, de forma idempotente.

PASO 0 — INSPECCIÓN OBLIGATORIA (no asumas nada):
- Abre y lee la cabecera de cada .sql. Identifica:
  · Nombres reales de tabla y columnas.
  · El identificador de México en paises (nombre 'México'/'Mexico' o iso/clave 'MX'). Anótalo.
  · La FK que liga estados→país (p.ej. pais_id / id_pais) y municipios→estado (estado_id / id_estado).
  · El PK original de cada fila (lo usaremos como source_id para mapear).
- Documenta lo encontrado en un comentario al inicio del seeder.

ARCHIVOS A CREAR:
1. database/migrations/xxxx_create_countries_table.php
   columns: id, name, iso2 nullable, clave nullable, timestamps.
2. database/migrations/xxxx_create_states_table.php
   columns: id, country_id (FK countries, cascadeOnDelete), name, clave nullable, source_id nullable(bigint), timestamps.
   índices: index(country_id); unique(country_id, name).
3. database/migrations/xxxx_create_municipalities_table.php
   columns: id, state_id (FK states, cascadeOnDelete), name, clave nullable, source_id nullable(bigint), timestamps.
   índices: index(state_id); unique(state_id, name).
4. app/Models/Country.php  (hasMany states)
5. app/Models/State.php    (belongsTo country, hasMany municipalities)
6. app/Models/Municipality.php (belongsTo state)
7. app/Console/Commands/GeoImportCommand.php  → signature: geo:import {--path=/newhauz/db_estados}
8. database/seeders/GeoCatalogSeeder.php  (envuelve la lógica o invoca el comando)

LÓGICA DE IMPORTACIÓN (GeoImportCommand):
- Recibe la ruta base por --path (default /newhauz/db_estados).
- Parsea cada .sql extrayendo las tuplas de VALUES (un parser de INSERT INTO ... VALUES (...),(...);). Mapea cada tupla a un array asociativo usando el orden de columnas detectado en el PASO 0. NO uses DB::unprepared sobre el dump MySQL directo (incompatibilidades con PostgreSQL).
- countries: inserta SOLO la fila de México (upsert por iso2 o name). Guarda su id.
- states: inserta SOLO los estados cuyo país = México. Mapea source_id = PK original. upsert por (country_id, name). Espera exactamente 32 estados.
- municipalities: inserta SOLO los municipios cuyo estado ∈ estados mexicanos importados. Resuelve state_id buscando por source_id del estado. upsert por (state_id, name).
- Todo dentro de transacción. Idempotente (re-ejecutable sin duplicar).
- Imprime resumen: países=1, estados=N, municipios=M.

VERIFICACIÓN (DoD):
  php artisan migrate
  php artisan geo:import
  php artisan tinker --execute="echo App\Models\Country::count();"        # => 1
  php artisan tinker --execute="echo App\Models\State::count();"          # => 32
  php artisan tinker --execute="echo App\Models\Municipality::whereDoesntHave('state')->count();"  # => 0
  php artisan tinker --execute="echo App\Models\State::where('name','like','Quer%')->first()->municipalities()->count();"  # > 0

RESTRICCIONES:
- USA NO se importa bajo ninguna circunstancia.
- Compatibilidad PostgreSQL en todas las migraciones.
- No toques tablas ni código de la Épica 1/2.

Entrega los archivos y el output de las verificaciones.
```

---

### 🟦 PROMPT — LOTE B · Modelo Zona extendido (Codex)

```
ROL: Agente de programación, New Hauz. Lote B del change request "Épica 3 — Geografía y Polígonos". El Lote A (countries/states/municipalities) ya está en verde.

OBJETIVO: Extender la entidad Zone con state_id, municipality_id, postal_code y consolidar el campo polygon (PostGIS). Migrar fuera el string libre 'municipality'.

ARCHIVOS:
1. database/migrations/xxxx_add_geo_fields_to_zones_table.php  (ALTER):
   - $table->foreignId('state_id')->nullable()->after('description')->constrained('states')->nullOnDelete();
   - $table->foreignId('municipality_id')->nullable()->after('state_id')->constrained('municipalities')->nullOnDelete();
   - $table->string('postal_code', 10)->nullable()->after('municipality_id');
   - Eliminar la columna string 'municipality' (RFC-015) — DROP COLUMN. Si hubiera datos de prueba, ignóralos (Épica recién implementada).
   - índices: index('state_id'), index('municipality_id').
   - Si la columna polygon NO existe aún como geometry(Polygon,4326), créala vía DB::statement:
       ALTER TABLE zones ADD COLUMN polygon geometry(Polygon,4326);
     y añade center_point geometry(Point,4326) NULL.
   - down(): revertir (dropColumn de las nuevas; restaurar 'municipality' string como nullable).

2. app/Models/Zone.php — agregar:
   - fillable: state_id, municipality_id, postal_code (+ los previos).
   - relaciones:
       public function state(): BelongsTo { return $this->belongsTo(State::class); }
       public function municipality(): BelongsTo { return $this->belongsTo(Municipality::class); }
   - Casteo/manejo de polygon: instalar y usar clickbar/laravel-magellan
       composer require clickbar/laravel-magellan
     Castear polygon a \Clickbar\Magellan\Data\Geometries\Polygon. Si magellan no es compatible con la versión de Laravel/PG instalada, NO lo fuerces: deja polygon sin cast en el modelo y maneja la conversión GeoJSON↔geometry vía SQL crudo parametrizado en el Resource (Lote D/E). Documenta la decisión tomada.

VERIFICACIÓN (DoD):
  php artisan migrate
  php artisan tinker --execute="echo Schema::hasColumn('zones','state_id') ? 'ok' : 'fail';"
  php artisan tinker --execute="echo Schema::hasColumn('zones','municipality') ? 'STILL THERE(fail)' : 'dropped(ok)';"
  php artisan tinker --execute="\$z=App\Models\Zone::factory()->create(['state_id'=>App\Models\State::first()->id]); echo \$z->state->name;"

RESTRICCIONES:
- nullOnDelete en las FK de geografía (no perder zonas si se borra un catálogo).
- Compatibilidad PostgreSQL/PostGIS.
- Actualiza ZoneFactory para incluir state_id/municipality_id válidos.

Entrega archivos + output de verificación.
```

---

### 🟦 PROMPT — LOTE C · ZoneResource en Filament (Codex)

```
ROL: Agente de programación, New Hauz. Lote C del change request "Épica 3 — Geografía y Polígonos". Lotes A y B en verde.

OBJETIVO: Actualizar app/Filament/Resources/ZoneResource.php para: (1) renombrar el label de status a "Estatus", (2) Select "Estado" (state_id), (3) Select "Municipio" dependiente del estado, (4) input "Código Postal". Usa Filament v3.

IMPORTS:
use Filament\Forms\Get;
use Filament\Forms\Set;
use App\Models\Municipality;

FORM — añade/ajusta:
  Forms\Components\Select::make('state_id')
      ->label('Estado')
      ->relationship('state', 'name')
      ->searchable()->preload()->required()
      ->live()
      ->afterStateUpdated(fn (Set $set) => $set('municipality_id', null)),

  Forms\Components\Select::make('municipality_id')
      ->label('Municipio')
      ->options(fn (Get $get) => filled($get('state_id'))
          ? Municipality::where('state_id', $get('state_id'))->orderBy('name')->pluck('name', 'id')
          : [])
      ->searchable()->required()->live()
      ->disabled(fn (Get $get) => blank($get('state_id')))
      ->helperText('Selecciona primero el estado.'),

  Forms\Components\TextInput::make('postal_code')
      ->label('Código Postal')
      ->mask('99999')
      ->rule('regex:/^\d{5}$/')
      ->live(debounce: 600)
      ->maxLength(5),

  // status: SOLO cambiar el label (no tocar lógica/enum)
  Forms\Components\Select::make('status')
      ->label('Estatus')           // <— antes "Estado"
      /* resto de la config previa intacta */ ,

TABLE — columnas:
  Tables\Columns\TextColumn::make('state.name')->label('Estado')->sortable()->searchable(),
  Tables\Columns\TextColumn::make('municipality.name')->label('Municipio')->sortable()->searchable(),
  Tables\Columns\TextColumn::make('postal_code')->label('C.P.')->searchable(),
  Tables\Columns\TextColumn::make('status')->label('Estatus')->badge() /* color previo intacto */,

FILTERS (opcional, recomendado):
  Tables\Filters\SelectFilter::make('state_id')->label('Estado')->relationship('state','name'),

VERIFICACIÓN (DoD):
- /admin/zones muestra columnas Estado, Municipio, C.P., Estatus.
- En el form, al elegir un Estado, el Select Municipio carga solo municipios de ese estado y antes está deshabilitado.
- 'status' aparece como "Estatus" en tabla y form.
- C.P. valida 5 dígitos.

RESTRICCIONES:
- No alterar la lógica del enum de status; SOLO el label.
- No romper acciones/permisos existentes del ZoneResource.

Entrega ZoneResource.php modificado + checklist de verificación manual.
```

---

### 🟦 PROMPT — LOTE D · Componente Google Maps con dibujo y extracción GeoJSON (Codex)

```
ROL: Agente de programación, New Hauz. Lote D del change request "Épica 3 — Geografía y Polígonos". Lotes A–C en verde.

OBJETIVO: Crear un campo Filament personalizado de mapa que (a) reciba Estado+Municipio+C.P. del form, (b) geocodifique y recentre Google Maps, (c) permita dibujar un polígono con Drawing Manager, (d) convierta el polígono dibujado a GeoJSON (Polygon, anillo cerrado, orden [lng,lat]) y lo escriba en el estado del campo, (e) al editar, re-dibuje el polígono almacenado.

CONFIG:
- config/services.php: 'google_maps' => ['key' => env('GOOGLE_MAPS_API_KEY')]
- Carga del SDK con libraries=places,drawing,geometry.

ARCHIVOS:
1. app/Filament/Forms/Components/MapPolygonInput.php
   namespace App\Filament\Forms\Components;
   use Filament\Forms\Components\Field;
   class MapPolygonInput extends Field {
       protected string $view = 'filament.forms.components.map-polygon-input';
       // Permite declarar de qué campos lee la dirección:
       public ?string $stateField='state_id'; public ?string $muniField='municipality_id'; public ?string $cpField='postal_code';
       public function dependsOn(string $state,string $muni,string $cp): static { $this->stateField=$state; $this->muniField=$muni; $this->cpField=$cp; return $this; }
   }

2. resources/views/filament/forms/components/map-polygon-input.blade.php
   - Envuelve en <x-dynamic-component :component="$getFieldWrapperView()" :field="$field">.
   - Contenedor <div x-data="mapPolygon({...})"> con:
       · statePath del campo (Alpine entangle con $wire para leer/escribir el GeoJSON).
       · nombres de los campos hermanos (state/municipio/cp) para leerlos vía $wire.get().
       · apiKey de Google Maps.
   - El polígono se guarda en la propiedad enlazada como STRING GeoJSON.

3. Alpine component (en el mismo blade o resources/js):
   Lógica clave:

   function mapPolygon(cfg){ return {
     map:null, geocoder:null, drawingManager:null, polygon:null,
     value: cfg.value,  // entangle con el estado Livewire (GeoJSON string)
     init(){
        const loader = ... // carga Google Maps JS API con cfg.apiKey y libraries=places,drawing,geometry
        loader.then(()=>{
           this.map = new google.maps.Map(this.$refs.map,{center:{lat:20.5888,lng:-100.3899},zoom:12}); // QRO por defecto
           this.geocoder = new google.maps.Geocoder();
           this.setupDrawing();
           this.renderExisting();          // si value trae GeoJSON, dibujarlo
           this.watchAddressFields();       // re-geocodificar al cambiar estado/muni/cp
        });
     },
     async recenter(){
        const estado = await this.labelOf(cfg.stateField);     // resolver nombre del estado por id
        const muni   = await this.labelOf(cfg.muniField);
        const cp     = this.$wire.get(cfg.cpField);
        if(!estado && !cp) return;
        const address = [cp, muni, estado, 'México'].filter(Boolean).join(', ');
        this.geocoder.geocode({address}, (res,status)=>{
           if(status==='OK' && res[0]){ this.map.setCenter(res[0].geometry.location); this.map.setZoom(14); }
        });
     },
     setupDrawing(){
        this.drawingManager = new google.maps.drawing.DrawingManager({
           drawingMode: google.maps.drawing.OverlayType.POLYGON,
           drawingControl:true,
           drawingControlOptions:{ drawingModes:[google.maps.drawing.OverlayType.POLYGON] },
           polygonOptions:{ editable:true, draggable:false }
        });
        this.drawingManager.setMap(this.map);
        google.maps.event.addListener(this.drawingManager,'polygoncomplete',(poly)=>{
           if(this.polygon) this.polygon.setMap(null);   // un solo polígono por zona
           this.polygon = poly;
           this.drawingManager.setDrawingMode(null);
           this.bindPolygonEvents();
           this.syncGeoJSON();
        });
     },
     bindPolygonEvents(){
        const path=this.polygon.getPath();
        ['set_at','insert_at','remove_at'].forEach(ev=>google.maps.event.addListener(path,ev,()=>this.syncGeoJSON()));
     },
     syncGeoJSON(){
        const coords=this.polygon.getPath().getArray().map(ll=>[ll.lng(), ll.lat()]);
        if(coords.length){
           const [fx,fy]=coords[0], [lx,ly]=coords[coords.length-1];
           if(fx!==lx||fy!==ly) coords.push(coords[0]);   // cerrar el anillo (requisito GeoJSON)
        }
        this.value = JSON.stringify({ type:'Polygon', coordinates:[coords] });
        this.$wire.set(cfg.statePath, this.value);          // persistir en el form state
     },
     renderExisting(){
        if(!this.value) return;
        try{
           const gj=JSON.parse(this.value);
           const ring=gj.coordinates[0].map(([lng,lat])=>({lat,lng}));
           this.polygon=new google.maps.Polygon({paths:ring, editable:true, map:this.map});
           this.bindPolygonEvents();
           const b=new google.maps.LatLngBounds(); ring.forEach(p=>b.extend(p)); this.map.fitBounds(b);
        }catch(e){ console.error('GeoJSON inválido', e); }
     },
     // ...watchAddressFields() usa $watch sobre los campos hermanos vía Livewire para llamar recenter()
   }}

4. ZoneResource (form): añade el campo
   App\Filament\Forms\Components\MapPolygonInput::make('polygon')
      ->label('Delimitación de la zona')
      ->dependsOn('state_id','municipality_id','postal_code')
      ->columnSpanFull(),

DETALLES OBLIGATORIOS:
- GeoJSON: orden [lng,lat] (NO [lat,lng]); anillo CERRADO (primer = último vértice).
- Un solo polígono por zona (al dibujar uno nuevo se reemplaza el anterior).
- El polígono debe ser editable: arrastrar vértices re-sincroniza el GeoJSON.
- Resolver el NOMBRE del estado/municipio a partir del id seleccionado para la geocodificación (expón un método pequeño en la página Livewire o consulta vía $wire si ya tienes los labels).

VERIFICACIÓN (DoD):
- El mapa carga en el form de Zona.
- Cambiar Estado+Municipio+C.P. recentra el mapa a la ubicación correcta.
- Dibujar un polígono llena el estado 'polygon' con GeoJSON válido (revisa en el inspector/Network o con un dump temporal).
- Editar una zona existente re-dibuja su polígono.

RESTRICCIONES:
- API key SOLO desde env/config, nunca hardcodeada.
- No envíes datos sensibles en query strings.

Entrega: MapPolygonInput.php, el blade, ajuste al ZoneResource, y nota sobre cómo resolviste los nombres estado/municipio para geocodificar.
```

---

### 🟦 PROMPT — LOTE E · Persistencia PostGIS \+ Tests (Codex)

```
ROL: Agente de programación, New Hauz. Lote E (cierre) del change request "Épica 3 — Geografía y Polígonos". Lotes A–D en verde.

OBJETIVO: Persistir el GeoJSON del form como geometry(Polygon,4326) en zones.polygon, derivar center_point con ST_Centroid, leer de vuelta como GeoJSON para edición, y cubrir todo con tests.

PERSISTENCIA (elige UNA vía según lo decidido en Lote B):

VÍA 1 — magellan (si quedó instalado y compatible):
- Cast en Zone: 'polygon' => Polygon::class. Magellan serializa/deserializa contra PostGIS.
- Para entrada GeoJSON desde el form, convierte el string a Polygon de magellan en mutateFormDataBeforeSave / mutateFormDataBeforeCreate del Resource.

VÍA 2 — SQL crudo parametrizado (fallback robusto):
- En CreateZone/EditZone (Pages), sobreescribe el guardado del campo polygon:
    protected function handleRecordCreation(array $data): Model { ... }  / mutateFormDataBeforeSave
  Extrae $geojson = $data['polygon']; quítalo de $data; guarda el registro; luego:
    DB::statement(
      'UPDATE zones SET polygon = ST_SetSRID(ST_GeomFromGeoJSON(?),4326),
                        center_point = ST_Centroid(ST_SetSRID(ST_GeomFromGeoJSON(?),4326))
       WHERE id = ?',
      [$geojson, $geojson, $record->id]
    );
- Lectura para el form (edit): en mutateFormDataBeforeFill o en un accessor:
    $row = DB::selectOne('SELECT ST_AsGeoJSON(polygon) AS g FROM zones WHERE id = ?', [$record->id]);
    $data['polygon'] = $row?->g;
- NUNCA concatenes el GeoJSON en el SQL: siempre binding parametrizado (anti-inyección).

VALIDACIÓN DE GEOMETRÍA:
- Antes de guardar, valida que el GeoJSON sea Polygon y tenga ≥4 vértices (anillo cerrado).
- Opcional: rechazar si ST_IsValid = false, o auto-corregir con ST_MakeValid.

TESTS (PostgreSQL, RefreshDatabase, sin SQLite):
tests/Feature/GeoCatalogTest.php
  it('imports only mexico (1 country, 32 states)')
  it('every municipality belongs to a mexican state')
tests/Feature/ZoneGeoFieldsTest.php
  it('zone belongs to state and municipality')
  it('municipality options are scoped to selected state')
  it('postal_code accepts 5 digits and rejects otherwise')
tests/Feature/ZonePolygonTest.php
  it('saving a zone with geojson stores a valid postgis polygon')      // ST_IsValid = true
  it('center_point equals ST_Centroid of polygon')
  it('editing returns polygon as geojson')
  it('rejects malformed geojson')
tests/Feature/Regression/Epica3RegressionTest.php
  it('zone crud still works')   // no romper RFC-016/017
  it('postgis extension is active')

VERIFICACIÓN (DoD):
  php artisan test --testsuite=Feature
  # SQL manual:
  php artisan tinker --execute="
    \$id=App\Models\Zone::first()->id;
    echo DB::selectOne('SELECT ST_IsValid(polygon) v FROM zones WHERE id=?',[\$id])->v ? 'valid' : 'invalid';
  "

RESTRICCIONES:
- Binding parametrizado siempre.
- SRID 4326 explícito.
- No regresiones en RFC-015/016/017.

Entrega: archivos de persistencia, tests, y output de la suite en verde.
```

---

### 🟨 PROMPT — Auditoría de DISEÑO (Gemini CLI)

```
ROL: Eres Gemini CLI, auditor técnico de New Hauz. Audita el DISEÑO del change request "Épica 3 — Corrección: Catálogo Geográfico y Polígonos Automáticos" (este documento) ANTES de implementar.

Evalúa y reporta con el formato estándar (Veredicto / Hallazgos críticos / medios / menores / Sobreingeniería / Riesgos de implementación / Riesgos de seguridad / Recomendaciones obligatorias / opcionales / Preguntas abiertas / Checklists para Codex):

PUNTOS A REVISAR:
1. Catálogo geográfico: ¿countries solo-México es la decisión correcta vs. mantener multipaís? ¿El uso de source_id para mapear municipios→estados es robusto frente a PKs que cambian en el destino?
2. Migración de Zone: dropear el string 'municipality' vs. preservarlo — ¿riesgo de pérdida de datos si la épica ya tiene zonas reales en algún ambiente?
3. FKs geografía con nullOnDelete: ¿correcto para no perder zonas? ¿Debería ser restrictOnDelete en municipios?
4. Polígono: el contrato GeoJSON (orden [lng,lat], anillo cerrado, SRID 4326) y la conversión ST_GeomFromGeoJSON/ST_AsGeoJSON — ¿válido para PostGIS 3.6? ¿ST_MakeValid necesario?
5. Google Maps: ¿exponer Estado/Municipio/C.P. al cliente para geocodificar implica fuga de datos? ¿La API key está correctamente confinada a env/config y restringida por dominio?
6. Dependencia clickbar/laravel-magellan: ¿compatibilidad con Laravel 13.x? ¿El fallback SQL crudo parametrizado es suficiente y seguro?
7. center_point derivado con ST_Centroid: ¿adecuado o se requiere ST_PointOnSurface para polígonos cóncavos?
8. Idempotencia del import y del seeder.

Entrega el reporte en docs/audits/epica-3-auditoria-diseno.md.
```

---

### 🟨 PROMPT — Auditoría de IMPLEMENTACIÓN (Gemini CLI)

```
ROL: Gemini CLI, auditor técnico de New Hauz. Audita la IMPLEMENTACIÓN de los Lotes A–E del change request "Épica 3 — Geografía y Polígonos" ya programados por Codex.

VERIFICA CONTRA EL DISEÑO:
- countries=1 (México), states=32, municipios sin huérfanos; USA ausente (QA-022/023).
- Label 'status' = "Estatus"; Select 'Estado' (state_id) y 'Municipio' dependiente funcionando (QA-024/025/026).
- postal_code 5 dígitos (QA-027).
- Geocodificación recentra el mapa con Estado+Municipio+C.P. (QA-028).
- Polígono dibujado → GeoJSON válido (anillo cerrado, [lng,lat], SRID 4326) → persiste como geometry válida; center_point = ST_Centroid; edición re-dibuja (QA-029/030).
- Sin regresiones en RFC-015/016/017.

REVISA ESPECÍFICAMENTE:
- Seguridad: API key no hardcodeada y restringida; SQL del polígono con binding parametrizado (sin concatenación de GeoJSON).
- ST_IsValid sobre los polígonos guardados.
- Idempotencia del import.
- Cobertura de tests (catálogo, campos geo de zona, polígono, regresión).

Reporta en el formato estándar (Veredicto / críticos / medios / menores / regresiones / riesgos seguridad / tests faltantes / correcciones obligatorias / checklist antes de merge) en docs/audits/epica-3-auditoria-implementacion.md.
```

---

## 8\. Checklist de cierre técnico

| Elemento | Estado |
| :---- | :---- |
| Tablas `countries` / `states` / `municipalities` creadas | Pendiente |
| Import solo-México (1 país, 32 estados, municipios sin huérfanos) | Pendiente |
| `GeoImportCommand` idempotente y verificado | Pendiente |
| `zones`: `state_id`, `municipality_id`, `postal_code` añadidos | Pendiente |
| `zones`: string `municipality` eliminado | Pendiente |
| `zones.polygon` \= `geometry(Polygon,4326)`; `center_point` añadido | Pendiente |
| Relaciones `Zone→State`, `Zone→Municipality` operativas | Pendiente |
| Label `status` → "Estatus" en tabla y form | Pendiente |
| Select "Estado" \+ Select "Municipio" dependiente | Pendiente |
| Campo "Código Postal" (5 dígitos) | Pendiente |
| Componente Google Maps geocodifica con Estado+Municipio+C.P. | Pendiente |
| Drawing Manager → GeoJSON (anillo cerrado, \[lng,lat\], 4326\) | Pendiente |
| Persistencia GeoJSON → PostGIS \+ `ST_Centroid` | Pendiente |
| Edición re-dibuja el polígono almacenado | Pendiente |
| API key confinada a env/config y restringida por dominio | Pendiente |
| SQL del polígono con binding parametrizado | Pendiente |
| Suite de tests QA-022 → QA-030 en verde | Pendiente |
| Sin regresiones RFC-015/016/017 | Pendiente |
| Auditoría de diseño (Gemini) aprobada | Pendiente |
| Auditoría de implementación (Gemini) aprobada | Pendiente |

---

## 9\. Decisiones diferidas / Notas

| \# | Tema | Estado |
| :---- | :---- | :---- |
| D-1 | Zona con múltiples C.P. (rango) en lugar de uno representativo | Diferido — hoy 1 C.P. por zona |
| D-2 | Validación de que el polígono caiga dentro del municipio (ST\_Within) | Diferido — posible mejora QA |
| D-3 | `ST_PointOnSurface` vs `ST_Centroid` para etiquetar zonas cóncavas | A confirmar en auditoría de diseño |
| D-4 | Migrar avatar/imagenes a Media Library (no aplica a esta corrección) | Épica 4 |
| D-5 | Restricción por dominio/HTTP-referrer de la Google Maps API key en producción | Obligatorio antes de deploy |

---

*Documento de arquitectura — Change Request Épica 3\. Listo para auditoría de diseño (Gemini) e implementación por lotes (Codex).*  
