# Especificación: Zonas por Código Postal (catálogo de polígonos)

> Handoff autocontenido — el dev (Edgar) implementa desde este documento SIN la conversación original.
> Texto de requisitos en español. Identificadores de código, columnas y tablas en inglés.
> Proyecto: newhauz | Branch: feature/epica-3-geografia-poligonos | TDD estricto activo.

---

## Propósito

Agregar un catálogo pre-cargado de polígonos por código postal (`postal_code_areas`), integrar un
botón "Obtener" en el formulario de Zona que pinta el polígono desde el catálogo, y mantener el
dibujo manual como fallback cuando el CP no tiene cobertura.

---

## Dominios cubiertos

1. `postal-code-areas-catalog` — Tabla, modelo y utilidades PostGIS
2. `geo-import-command` — Comando artisan `geo:import-postal-codes`
3. `zone-form-behavior` — Reglas de habilitación/deshabilitación y validación del formulario
4. `fetch-polygon-flow` — Método Livewire `fetchPostalCodePolygon` y renderizado en mapa
5. `zone-persistence` — Guardado de polígono en `zones.polygon` y cómputo de `center_point`

---

## REQ-1 — Catálogo `postal_code_areas`: esquema y modelo

### Requisito: Tabla postal_code_areas

La tabla `postal_code_areas` DEBE existir en la base de datos con exactamente las siguientes
columnas, tipos e índices. No se DEBE agregar columna `country_id` — el país se deriva via
`state_id → states → countries`.

| Columna            | Tipo                             | Restricciones                        |
|--------------------|----------------------------------|--------------------------------------|
| `id`               | BIGSERIAL                        | PK                                   |
| `postal_code`      | VARCHAR(5)                       | NOT NULL, UNIQUE                     |
| `municipality_id`  | BIGINT                           | NULL, FK → municipalities ON DELETE SET NULL |
| `state_id`         | BIGINT                           | NULL, FK → states ON DELETE SET NULL |
| `polygon`          | GEOMETRY(MultiPolygon, 4326)     | NOT NULL                             |
| `created_at`       | TIMESTAMP                        |                                      |
| `updated_at`       | TIMESTAMP                        |                                      |

Índices adicionales OBLIGATORIOS: GIST sobre `polygon`, índice simple sobre `municipality_id`.

#### Escenario: Migración crea la tabla con el esquema correcto

- DADO que se ejecuta `php artisan migrate` en la base de datos `inmo_test`
- CUANDO se inspecciona el schema de `postal_code_areas`
- ENTONCES la columna `postal_code` es VARCHAR(5) NOT NULL con restricción UNIQUE
- Y la columna `polygon` es de tipo `geometry(MultiPolygon,4326)`
- Y existe un índice GIST sobre `polygon`
- Y `municipality_id` admite NULL y tiene FK a `municipalities`

#### Escenario: Restricción UNIQUE sobre postal_code

- DADO que `postal_code_areas` ya contiene una fila con `postal_code = '06600'`
- CUANDO se intenta insertar otra fila con `postal_code = '06600'`
- ENTONCES la base de datos lanza una excepción de violación de UNIQUE constraint

---

### Requisito: Modelo PostalCodeArea

El modelo `app/Models/PostalCodeArea.php` DEBE:
- Declarar `$fillable` con todos los campos editables.
- Castear `polygon` al tipo Magellan `MultiPolygon` (consistente con el patrón de `Zone`).
- Exponer relaciones `municipality(): BelongsTo` y `state(): BelongsTo`.
- Exponer el método `polygonAsGeoJson(): string` que retorna el polígono como GeoJSON válido.

#### Escenario: polygonAsGeoJson retorna GeoJSON tipo MultiPolygon

- DADO que existe una `PostalCodeArea` con `postal_code = '11000'` y un polígono válido
- CUANDO se llama `$area->polygonAsGeoJson()`
- ENTONCES el resultado es un string JSON decodificable
- Y `json_decode($result)->type` es `"MultiPolygon"`

#### Escenario: CP no encontrado retorna null

- DADO que no existe ninguna `PostalCodeArea` con `postal_code = '99999'`
- CUANDO se llama `PostalCodeArea::where('postal_code','99999')->first()`
- ENTONCES el resultado es `null`

---

### Requisito: Método largestRingGeoJson

El modelo DEBE exponer `largestRingGeoJson(): ?string` que ejecuta `ST_Dump` sobre el `polygon`
propio, ordena por `ST_Area` descendente y retorna el anillo más grande como GeoJSON de tipo
`Polygon`. Si el registro no existe o la geometría es inválida, DEBE retornar `null`.

#### Escenario: largestRingGeoJson retorna el componente más grande como Polygon

- DADO que existe una `PostalCodeArea` con un MultiPolygon de dos componentes de distinto tamaño
- CUANDO se llama `$area->largestRingGeoJson()`
- ENTONCES el resultado es un string JSON con `type = "Polygon"`
- Y el área del polígono retornado es mayor o igual a la del segundo componente

---

## REQ-2 — Comando `geo:import-postal-codes`

### Requisito: Importación desde dataset open-mexico/mexico-geojson

El comando artisan `geo:import-postal-codes` DEBE:
- Aceptar las opciones `{--state=}` (código de estado, para importar un solo estado) y
  `{--path=}` (directorio raíz del dataset clonado).
- Leer archivos GeoJSON con geometrías de tipo `MultiPolygon` (o `Polygon`, convirtiendo con
  `ST_Multi`).
- Extraer el código postal del feature property correcto (a determinar al inspeccionar el dataset:
  puede ser `codigo_postal`, `cp` o `d_codigo`).
- Realizar un `UPSERT` idempotente usando `postal_code` como clave de conflicto.
- Intentar vincular `municipality_id` buscando en `municipalities.clave = postal_code`
  (best-effort: si no hay match, deja `municipality_id = NULL`).
- Insertar en batches para no saturar la memoria.

#### Escenario: Importación exitosa desde fixture

- DADO que existe un fixture GeoJSON válido con al menos 3 features con CPs distintos
- CUANDO se ejecuta `php artisan geo:import-postal-codes --path={fixture_dir}`
- ENTONCES se crean exactamente N filas en `postal_code_areas` (una por CP)
- Y cada fila tiene `polygon` no nulo con tipo `MultiPolygon`
- Y el comando termina con código de salida 0

#### Escenario: Ejecución idempotente (re-run)

- DADO que el comando ya se ejecutó una vez y hay N filas en `postal_code_areas`
- CUANDO se ejecuta el mismo comando nuevamente con los mismos datos
- ENTONCES el conteo de filas en `postal_code_areas` NO aumenta
- Y no se lanza ninguna excepción
- Y los datos existentes son actualizados (upsert por `postal_code`)

#### Escenario: Linkage municipality_id cuando existe clave coincidente

- DADO que en `municipalities` existe una fila con `clave = '06600'`
- Y el fixture tiene un feature con CP `'06600'`
- CUANDO se ejecuta el comando
- ENTONCES la fila `postal_code_areas` con `postal_code = '06600'` tiene `municipality_id` no nulo
  apuntando al municipio correcto

#### Escenario: municipality_id queda NULL cuando no hay clave coincidente

- DADO que ninguna fila de `municipalities` tiene `clave = '99001'`
- Y el fixture tiene un feature con CP `'99001'`
- CUANDO se ejecuta el comando
- ENTONCES la fila `postal_code_areas` con `postal_code = '99001'` tiene `municipality_id = NULL`
- Y el comando NO lanza excepción (la vinculación es best-effort)

#### Escenario: Geometría Polygon se convierte a MultiPolygon al importar

- DADO que un feature del GeoJSON tiene geometría de tipo `Polygon` (no `MultiPolygon`)
- CUANDO se importa ese feature
- ENTONCES la columna `polygon` en `postal_code_areas` almacena un `MultiPolygon` (via `ST_Multi`)
- Y la fila es válida según el constraint `geometry(MultiPolygon,4326)`

---

## REQ-3 — Comportamiento del formulario de Zona

### Requisito: País México fijo como lógica backend

El campo país MUST NOT aparecer en el formulario de Zona (`ZoneResource`). El sistema DEBE
derivar el país exclusivamente desde `state_id → states → countries` en backend. No se agrega
columna `country_id` a la tabla `zones`.

#### Escenario: El formulario no contiene campo de país (verificación manual)

- DADO que el usuario navega al formulario de crear/editar Zona en el panel admin
- CUANDO inspecciona los campos del formulario
- ENTONCES no hay ningún campo de selección de país visible
- Y el país se refleja implícitamente al elegir un estado

> Nota: sin runner JS automatizado — verificación manual.

---

### Requisito: Campo postal_code deshabilitado hasta seleccionar Estado y Municipio

El campo `postal_code` en el formulario de Zona DEBE estar deshabilitado (`disabled`) mientras
`municipality_id` esté en blanco (`blank`). En cuanto `municipality_id` tenga valor, el campo
DEBE habilitarse.

#### Escenario: postal_code está deshabilitado cuando municipality_id es null

- DADO que se renderiza el formulario de crear Zona
- Y `municipality_id` tiene valor `null` (recién cargado, sin selección)
- CUANDO el sistema evalúa la regla `->disabled(fn(Get $get) => blank($get('municipality_id')))`
- ENTONCES el campo `postal_code` se renderiza como deshabilitado

#### Escenario: postal_code se habilita cuando municipality_id tiene valor

- DADO que el usuario ha seleccionado un estado y luego un municipio
- Y `municipality_id` tiene un valor entero no nulo
- CUANDO el sistema evalúa la regla de `disabled`
- ENTONCES la función retorna `false` y el campo `postal_code` es editable

---

### Requisito: Validación formato código postal

El campo `postal_code` DEBE aceptar únicamente valores que coincidan con la expresión regular
`^\d{5}$` (exactamente 5 dígitos). El sistema DEBE rechazar valores de 4, 6 o más dígitos,
y valores que contengan letras.

#### Escenario: CP válido de 5 dígitos pasa validación

- DADO que `municipality_id` tiene valor y el usuario ingresa `postal_code = '06600'`
- CUANDO se dispara la validación del formulario
- ENTONCES no se genera error de validación para `postal_code`

#### Escenario: CP inválido (4 dígitos) falla validación

- DADO que el usuario ingresa `postal_code = '0660'`
- CUANDO se dispara la validación del formulario
- ENTONCES se genera un error de validación indicando formato incorrecto

---

## REQ-4 — Flujo del botón "Obtener"

### Requisito: Método fetchPostalCodePolygon en páginas Create y Edit

Los componentes Livewire `CreateZone` y `EditZone` DEBEN exponer el método
`fetchPostalCodePolygon(?string $cp): ?string` mediante el trait
`ResolvesZonePostalCodePolygon` ubicado en
`app/Filament/Resources/ZoneResource/Concerns/`.

El método DEBE:
1. Validar que `$cp` coincide con `^\d{5}$`; si no, retornar `null`.
2. Buscar la `PostalCodeArea` con ese `postal_code`.
3. Si no existe, retornar `null`.
4. Si existe, llamar `largestRingGeoJson()` y retornar el GeoJSON resultante (tipo `Polygon`).

#### Escenario: CP con cobertura retorna Polygon GeoJSON

- DADO que existe `PostalCodeArea` con `postal_code = '06600'` y polígono multi-componente
- Y `CreateZone` usa el trait `ResolvesZonePostalCodePolygon`
- CUANDO se llama `Livewire::test(CreateZone::class)->call('fetchPostalCodePolygon', '06600')`
- ENTONCES el valor retornado es un string con `json_decode($result)->type === 'Polygon'`

#### Escenario: CP sin cobertura retorna null

- DADO que NO existe `PostalCodeArea` con `postal_code = '99999'`
- CUANDO se llama `->call('fetchPostalCodePolygon', '99999')`
- ENTONCES el valor retornado es `null`

#### Escenario: CP con formato inválido retorna null

- DADO un CP con valor `'123'` (menos de 5 dígitos)
- CUANDO se llama `->call('fetchPostalCodePolygon', '123')`
- ENTONCES el valor retornado es `null`

---

### Requisito: Botón "Obtener" en el blade de mapa

El componente `map-polygon-input.blade.php` DEBE incluir un botón "Obtener" visible junto a los
botones "Dibujar" y "Borrar" en la barra de controles del mapa. El handler JS DEBE:
1. Leer el valor actual del campo CP.
2. Llamar `await this.$wire.call('fetchPostalCodePolygon', cpValue)`.
3. Si el resultado es no-nulo: asignar el valor al estado Alpine del campo, sincronizar con Livewire
   via `this.$wire.set(cfg.statePath, result)`, y llamar `this.renderExisting()`.
4. Si el resultado es `null`: mostrar una notificación al usuario indicando que no hay cobertura
   y el polígono deberá dibujarse manualmente.

El dibujo manual DEBE mantenerse funcional como fallback en todo momento.

#### Escenario: Botón "Obtener" pinta polígono cuando CP tiene cobertura (verificación manual)

- DADO que el usuario seleccionó Estado, Municipio, e ingresó CP `06600` con cobertura
- CUANDO hace clic en el botón "Obtener"
- ENTONCES el mapa muestra el polígono del CP sin necesidad de dibujar
- Y el polígono es editable (puede ajustar vértices)

> Nota: sin runner JS automatizado — verificación manual.

#### Escenario: "Obtener" sin cobertura muestra notificación y habilita dibujo manual (verificación manual)

- DADO que el CP ingresado no tiene cobertura en el catálogo
- CUANDO el usuario hace clic en "Obtener"
- ENTONCES aparece una notificación indicando ausencia de cobertura
- Y el modo de dibujo manual permanece disponible

> Nota: sin runner JS automatizado — verificación manual.

---

## REQ-5 — Persistencia de la Zona

### Requisito: Zone.polygon almacena el polígono obtenido como Polygon válido

Cuando el usuario guarda una Zona cuyo polígono fue obtenido via "Obtener" (no dibujado), el
campo `zones.polygon` DEBE contener una geometría `Polygon` con SRID 4326, con al menos 4
puntos, y con el anillo cerrado. La regla de validación `ValidZonePolygonGeoJson` existente
DEBE seguir pasando sin modificación.

#### Escenario: Guardado de Zona con polígono obtenido del catálogo

- DADO que `fetchPostalCodePolygon('06600')` retorna un GeoJSON de tipo `Polygon`
- Y ese GeoJSON es asignado al campo `polygon` del formulario
- CUANDO el usuario guarda la Zona
- ENTONCES `zones.polygon` contiene una geometría `Polygon` con SRID 4326
- Y la regla `ValidZonePolygonGeoJson` valida sin error

#### Escenario: center_point se calcula automáticamente via hook saved()

- DADO que se guarda una Zona con el polígono obtenido del catálogo
- CUANDO el hook `saved()` del modelo `Zone` se ejecuta
- ENTONCES `zones.center_point` tiene un valor no nulo calculado via `ST_Centroid`
- Y no se requiere ningún cambio en el hook existente

---

## REQ-6 — Corrección del catálogo base: `states` y `municipalities` desde xlsx

### Requisito: Columna `inegi_code` en `states` y `municipalities`

`states` DEBE tener columna `inegi_code` CHAR(2) UNIQUE. `municipalities` DEBE tener columna
`inegi_code` CHAR(3), UNIQUE en conjunto con `state_id`. Estas son las claves del catálogo oficial
INEGI (no confundir con la columna legacy `clave`, que no se modifica).

#### Escenario: inegi_code es único por estado

- DADO que existe un `State` con `inegi_code = '22'`
- CUANDO se intenta crear otro `State` con `inegi_code = '22'`
- ENTONCES la base de datos lanza una excepción de violación de UNIQUE constraint

#### Escenario: inegi_code de municipio es único solo dentro del mismo estado

- DADO que existen dos `Municipality` en estados distintos, ambos con `inegi_code = '001'`
- CUANDO se siembran ambos
- ENTONCES no se lanza excepción (el unique es compuesto `state_id + inegi_code`)

---

### Requisito: `StateSeeder` siembra los 32 estados desde `db_estados/states.xlsx`

`StateSeeder` DEBE leer `db_estados/states.xlsx` (columnas `state_id`, `state`) y, por cada fila,
hacer `upsert` por `inegi_code` (columna `state_id` del xlsx) con `name` (columna `state`) y
`country_id` de México. DEBE ser idempotente.

#### Escenario: Siembra exitosa de 32 estados

- DADO que existe el país México en `countries`
- CUANDO se ejecuta `StateSeeder`
- ENTONCES `states` contiene exactamente 32 filas
- Y cada fila tiene `inegi_code` no nulo coincidiendo con el `state_id` del xlsx (`'01'`..`'32'`)

#### Escenario: Re-ejecutar StateSeeder no duplica filas

- DADO que `StateSeeder` ya se ejecutó una vez
- CUANDO se ejecuta nuevamente
- ENTONCES el conteo de `states` sigue siendo 32

---

### Requisito: `MunicipalitySeeder` siembra municipios desde `db_estados/municipalities.xlsx`

`MunicipalitySeeder` DEBE leer `db_estados/municipalities.xlsx` (columnas `state_id`,
`municipality_id`, `MUNICIPIO`), resolver `state_id` (FK real) buscando
`states.inegi_code = state_id (xlsx)`, y hacer `upsert` por `(state_id, inegi_code)` con `name`
(normalizado, no se persiste en mayúsculas crudas del xlsx). DEBE requerir que `StateSeeder` haya
corrido antes (los 32 estados deben existir).

#### Escenario: Siembra de municipios vinculados a su estado real

- DADO que `states` ya contiene los 32 estados con `inegi_code`
- CUANDO se ejecuta `MunicipalitySeeder`
- ENTONCES cada `Municipality` creada tiene `state_id` apuntando al estado cuyo `inegi_code`
  coincide con la columna `state_id` del xlsx
- Y `inegi_code` de la municipalidad coincide con la columna `municipality_id` del xlsx

#### Escenario: Nombre de municipio no queda en mayúsculas crudas

- DADO que el xlsx tiene `MUNICIPIO = 'JESÚS MARÍA'`
- CUANDO se siembra ese municipio
- ENTONCES `name` no es igual al string crudo en mayúsculas (se aplica normalización de capitalización)

---

### Requisito: `geo:import` y sus dumps SQL quedan retirados

`GeoCatalogSeeder` NO DEBE invocar `Artisan::call('geo:import')`. El país México se siembra
inline en `GeoCatalogSeeder`. `app/Console/Commands/GeoImportCommand.php` DEBE eliminarse.

#### Escenario: GeoCatalogSeeder no depende de los dumps SQL

- DADO que se ejecuta `GeoCatalogSeeder` en un entorno sin los archivos `paises.sql`,
  `estados.sql`, `municipios.sql`
- CUANDO el seeder corre
- ENTONCES no se lanza ninguna excepción y el catálogo completo (país, 32 estados, municipios)
  queda sembrado

---

## REQ-7 — Tabla `postal_codes` (catálogo administrativo CP ↔ colonia)

### Requisito: Esquema de `postal_codes`

La tabla `postal_codes` DEBE tener: `id`, `postal_code` VARCHAR(5) NOT NULL, `colonia`
VARCHAR(255) NOT NULL, `municipality_id` BIGINT NULL FK → municipalities (nullOnDelete),
`state_id` BIGINT NULL FK → states (nullOnDelete), timestamps. UNIQUE compuesto
`(postal_code, colonia)`. Índice simple sobre `postal_code`.

#### Escenario: Un mismo postal_code admite múltiples colonias

- DADO que `postal_codes` no tiene filas
- CUANDO se insertan dos filas con `postal_code = '76000'` y `colonia` distinta cada una
- ENTONCES ambas filas se insertan sin error

#### Escenario: La combinación postal_code + colonia es única

- DADO que existe una fila con `postal_code = '76000'`, `colonia = 'Centro'`
- CUANDO se intenta insertar otra fila idéntica en `postal_code` y `colonia`
- ENTONCES la base de datos lanza una excepción de violación de UNIQUE constraint

---

### Requisito: `PostalCodeSeeder` siembra desde `db_estados/cp_queretaro.xlsx`

`PostalCodeSeeder` DEBE leer `db_estados/cp_queretaro.xlsx` (columnas `postal_code`, `colonia`,
`state_id`, `imunicipality_id`), resolver `state_id`/`municipality_id` reales vía `inegi_code`, y
hacer `upsert` por `(postal_code, colonia)`. DEBE requerir que `MunicipalitySeeder` haya corrido
antes.

#### Escenario: Siembra exitosa de postal_codes de Querétaro

- DADO que `states` y `municipalities` ya tienen `inegi_code` poblado para Querétaro (`'22'`)
- CUANDO se ejecuta `PostalCodeSeeder`
- ENTONCES `postal_codes` contiene filas con `state_id` apuntando al estado Querétaro
- Y `municipality_id` apunta al municipio cuyo `inegi_code` coincide con `imunicipality_id` del xlsx

---

## REQ-8 — Import de `postal_code_areas` para Querétaro

### Requisito: `CP_PROPERTY` confirmado y geometría de origen

El comando `geo:import-postal-codes` (REQ-2) DEBE usar `d_codigo` como nombre de la propiedad del
código postal al leer `db_estados/Pais-Estado-Municipio/22-Qro.geojson`. DEBE aceptar geometrías
`Polygon` o `MultiPolygon` por feature, normalizando siempre a `MultiPolygon` (`ST_Multi`).

#### Escenario: Import desde el archivo real de Querétaro

- DADO que se ejecuta `php artisan geo:import-postal-codes --path=db_estados/Pais-Estado-Municipio --state=22-Qro`
- CUANDO el comando procesa `22-Qro.geojson`
- ENTONCES se crean/actualizan filas en `postal_code_areas` por cada `d_codigo` distinto del archivo
- Y el comando termina con código de salida 0

### Requisito: Linkage de `postal_code_areas` prioriza `postal_codes`

Al resolver `municipality_id`/`state_id` de una fila de `postal_code_areas`, el comando DEBE
intentar primero `postal_codes.where('postal_code', $cp)->first()`; si no hay fila, DEBE caer al
fallback existente `municipalities.clave = $cp`; si tampoco hay match, las FKs quedan `NULL`.

#### Escenario: Linkage resuelto vía postal_codes

- DADO que existe una fila en `postal_codes` con `postal_code = '76000'` y `municipality_id` no nulo
- CUANDO el importer procesa el CP `76000` desde el geojson
- ENTONCES la fila resultante en `postal_code_areas` tiene el mismo `municipality_id` que la fila
  de `postal_codes`

#### Escenario: Sin match en postal_codes, cae al fallback por clave

- DADO que NO existe fila en `postal_codes` para `postal_code = '76950'`
- Y existe un `Municipality` con `clave = '76950'`
- CUANDO el importer procesa el CP `76950`
- ENTONCES la fila resultante en `postal_code_areas` tiene `municipality_id` igual al de ese
  `Municipality`

---

## Áreas sin cobertura de tests automatizados

Las siguientes verificaciones DEBEN realizarse manualmente (no hay runner JS/E2E configurado):

| # | Verificación |
|---|---|
| M-1 | Botón "Obtener" visible junto a "Dibujar" y "Borrar" en el mapa |
| M-2 | Campo CP deshabilitado visualmente hasta seleccionar Municipio |
| M-3 | "Obtener" pinta el polígono del CP en el mapa (CP con cobertura) |
| M-4 | "Obtener" muestra notificación y no falla cuando CP sin cobertura |
| M-5 | Polígono obtenido es ajustable (vértices editables) |
| M-6 | País no aparece como campo en el formulario de Zona |

---

## Archivos afectados

### Nuevos
- `database/migrations/YYYY_MM_DD_HHMMSS_create_postal_code_areas_table.php`
- `app/Models/PostalCodeArea.php`
- `app/Console/Commands/ImportPostalCodesCommand.php`
- `app/Filament/Resources/ZoneResource/Concerns/ResolvesZonePostalCodePolygon.php`
- `tests/Feature/PostalCodeAreaTest.php`
- `tests/Feature/ZonePostalCodeFetchTest.php`
- `tests/Feature/ImportPostalCodesCommandTest.php`
- `tests/fixtures/geojson/` (archivos GeoJSON mínimos para tests)

### Modificados
- `app/Filament/Resources/ZoneResource.php`
- `app/Filament/Resources/ZoneResource/Pages/CreateZone.php`
- `app/Filament/Resources/ZoneResource/Pages/EditZone.php`
- `resources/views/components/map-polygon-input.blade.php`

---

## Riesgos y asunciones de la especificación

| ID   | Riesgo / Asunción |
|------|-------------------|
| R-1  | El nombre del property CP en el GeoJSON (`codigo_postal`, `cp` o `d_codigo`) es BLOQUEANTE — debe inspeccionarse un archivo real antes de codear el importer. |
| R-2  | `municipalities.clave` guarda un CP representativo — la vinculación es best-effort y puede no tener match. FK siempre nullable. |
| R-3  | CP con geometría disjunta (MultiPolygon) se convierte a la componente más grande — el área cubierta puede no ser exhaustiva. Precisión no crítica. |
| R-4  | `renderExisting()` en el blade solo maneja `type: 'Polygon'` — la conversión MultiPolygon→Polygon SIEMPRE ocurre server-side; el cliente nunca recibe un MultiPolygon. |
| R-5  | No hay runner JS/E2E — los escenarios del blade son manuales. |
