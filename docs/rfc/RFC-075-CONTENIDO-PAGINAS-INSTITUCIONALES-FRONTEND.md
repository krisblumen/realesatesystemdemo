# RFC-075 Contenido Editable de Páginas Institucionales del Frontend

> **⚠️ Enmienda normativa (P3 + correcciones posteriores a P3R, 2026-07-20).** Fuente única: **§16** de la épica; donde difiera, **prevalece §16**. Overrides: registry canónico completo y verificado contra los Blade actuales; cada `section_key` editable apunta a un tipo ejecutable allowlisted; formulario/canales operativos permanecen en kernel; tipos dinámicos independientes `featured_properties` (`Property::featured()`), `opportunity_properties` (`Property::opportunity()`) y `featured_projects` (`Project::is_featured`); inversionistas no inventa `metrics`; snapshot completo con `draft_revision` incrementada por toda mutación y publisher con `expected_draft_revision`; CTA anidado `{label,type,target}`; media privada promovida por job idempotente y reconciliación.
>
> **Correcciones de la reauditoría P5 (2026-07-21):** (1) el schema `hero` incorpora **`slides`** (`0..6`, `sort_order`, `decorative`/`alt`, fallback = las 4 URLs de `welcome.blade.php:12-16`) — ⚠️ **ese fallback quedó luego acotado a `home` por §18.18 de la épica: es POR PÁGINA**; sin `slides` el contrato no podía representar el home real. (2) Las cinco páginas canónicas se crean con una **migración que invoca `SeedFrontendPages`**, NO con `FrontendPageSeeder` — producción despliega con `migrate --force` sin seeders (`CI-CD-PIPELINE.md:46-58`); el seeder queda solo para dev/test. (3) `FrontendSection` usa **`SoftDeletes`** y tiene `forceDelete` prohibido por policy, para que borrar una sección no elimine la media que la revisión publicada viva referencia (`InteractsWithMedia.php:51-63`).
>
> **Corrección C-3/M-7 (2026-07-21) — ⚠️ HISTÓRICA, supersedida por §18.13:** describía locks sobre `media.uuid ASC` y un prune con recheck bajo READ COMMITTED. **Ese subsistema no existe en v1.** Contrato vigente: **§16.4** — se valida existencia/owner/colección sin lock sobre `media`, porque ninguna ruta la borra.
>
> **Corrección C-3 — frontera BD↔filesystem (P9, 2026-07-21):** la transacción del prune **no borra la fila `media`**. Spatie borra los archivos de forma síncrona en el evento `deleted` (`MediaObserver.php:55-65`), dentro de la transacción y sin `afterCommit`: un rollback restauraría la fila pero no los archivos. La transacción **solo escribe un intent durable**; la purga física ocurre **post-commit y fuera de transacción**, eliminando **archivos primero y la fila después**, con retry idempotente. Contrato autoritativo: **§16.4.2** de la épica.
>
> **Corrección C-3 — purga estricta y observable (P10, 2026-07-21):** la purga **no puede apoyarse en `DefaultFileRemover::removeAllFiles()`**, que es `void`, ignora el retorno de `delete()` y captura toda excepción (`DefaultFileRemover.php:17,44,50-52,72,78-80,113,119-121`); con `throw=false`/`report=false` en los discos (`config/filesystems.php:37,49`), un fallo de borrado es **silencioso**. `PurgeFrontendMediaFiles` inventaría las rutas con la fila viva, borra evaluando cada retorno y **verifica ausencia** de original, conversiones y responsive images antes de tocar la fila. Cualquier `false`, excepción o residuo conserva fila y marca y fuerza retry.
>
> **Espejo subordinado — fallback de `hero.slides` es POR PÁGINA (enmienda §18.18 de la épica, 2026-07-25).** La épica fue enmendada en **§16.1.1 y §16.7** (fuente única; este RFC solo la refleja). La frase de §«Payloads estructurados → `hero`» —«sin slides publicados, el fallback son las cuatro URLs actuales en su orden»— **queda superada**: definía el fallback de un tipo compartido por cinco páginas usando el contenido de una sola. Regla vigente: cada página cae a **su** fondo hardcodeado actual — `home` = las 4 URLs Unsplash (`welcome.blade.php:12-16`); `nosotros`, `servicios` e `inversionistas` = su PNG de encabezado (`:11` de cada vista); `contacto` = **sin imagen de fondo**. **No cambian** la cardinalidad `0..6`, el orden por `sort_order`, ni que un `slides: []` publicado **no** revive el fallback. Matriz completa: `docs/epicas/epica-12-1-mejora-ux-hero.md` §8.
>
> **Espejo subordinado — alcance del uploader no destructivo (enmienda §18.18 de la épica).** El mandato de `NonDestructiveMediaUpload` aplica a campos **con relación Spatie**. El estado de lista de `hero.slides` (array de `media_id` en el payload) usa `FileUpload` base, que **no tiene ruta de borrado**, con la misma garantía contractual y pruebas de no borrado. Sigue prohibido `SpatieMediaLibraryFileUpload` directo, `singleFile()`, `onlyKeepLatest()`, `forceDelete` y el borrado físico.
>
> **⚠️ DECISIÓN DE ALCANCE (§18.13 de la épica, 2026-07-21): el BORRADO FÍSICO DE MEDIA SALE DE v1.** Quedan **fuera de alcance** prune, purga física, intent, lease, advisory lock, jobs guardados de Spatie, path generator con scope y barrido de huérfanos, con sus tablas, comandos y tests. Toda mención a esos mecanismos en este RFC es **histórica, no normativa**. Se conservan: media draft en disco privado con controlador owner-only, promoción post-commit idempotente con reconciliación, `SoftDeletes` con `forceDelete` prohibido, índices únicos parciales y el reemplazo que no destruye la imagen publicada. Contrato vigente: **§16.4**.
>
> **Espejo subordinado — el registro pasa de cinco a seis páginas (enmienda §18.21 de la épica, 2026-07-30).** El campo `key` (línea ~138) y el conteo de «5 páginas» del árbol de archivos quedan **desactualizados**: `/proyectos` se suma como sexta página canónica (`hero→hero`, `projects_list→featured_projects`, `final_cta→cta`), mismo patrón exacto que las cinco existentes, cero tipos de sección nuevos. **No contradice** «No incluye: crear páginas públicas nuevas desde el CMS» — la ruta y el controlador (`ProjectController@index`) ya existían; lo nuevo es que su contenido pase a ser administrable, igual que ya lo era el de las otras cinco. Detalle completo (incluida la reconciliación spec/design sobre precedencia del logo propio y el cierre del riesgo del chip A-74): `docs/epicas/epica-12-administrador-contenidos-frontend.md` §18.21 y `openspec/changes/cms-pagina-proyectos/`.

## Objetivo

Permitir que el usuario `owner` administre el contenido principal de las páginas institucionales del frontend público mediante secciones estructuradas, seguras y versionables en base de datos, manteniendo fallbacks equivalentes al sitio actual.

Este RFC no crea un page builder libre. Define un sistema acotado de páginas y bloques permitidos para que el owner pueda editar textos, imágenes, métricas, CTAs y secciones comerciales sin romper diseño, seguridad ni SEO.

## Épica

Épica 12 — Administrador de Contenidos del Frontend

## Responsable

Por asignar

## Estado

🟡 Correcciones documentales aplicadas; reauditoría independiente pendiente. **Implementación bloqueada** hasta gate `APROBADO`.

---

## Contexto verificado

El frontend público actual tiene contenido institucional hardcodeado en varias vistas:

- `resources/views/welcome.blade.php`:
  - Hero slides.
  - Mensaje principal.
  - Bloque de servicios.
  - Proyectos destacados con branding A-74.
  - Bloque de inversionistas.
  - Partners.
  - CTA final.
- `resources/views/site/nosotros.blade.php`:
  - Hero.
  - Historia.
  - Estadísticas.
  - Valores.
  - Equipo.
  - Bloque A-74.
  - CTA.
- `resources/views/site/servicios.blade.php`:
  - Hero.
  - Listado de servicios hardcodeado.
  - Bullets, imágenes y CTAs.
  - CTA final.
- `resources/views/site/inversionistas.blade.php`:
  - Hero.
  - Recorrido editorial de tres paneles con imágenes.
  - Alcance del servicio en cuatro tarjetas.
  - Audiencia objetivo y resultados esperados.
  - CTA final.
- `resources/views/leads/create.blade.php`:
  - Hero y texto introductorio fijo.
  - Formulario Livewire y canales operativos que no son contenido de sección.

Parte del frontend ya usa datos dinámicos (`Property`, `Project`, `ServiceType`), pero la narrativa institucional sigue fija.

---

## Alcance

### Incluye

- Administrar contenido estructurado de páginas públicas existentes.
- Soportar secciones predefinidas por página.
- Editar textos, imágenes, métricas, bullets y CTAs.
- Activar/desactivar secciones permitidas.
- Ordenar secciones cuando el diseño lo permita.
- Usar Media Library para imágenes.
- Mantener fallbacks del contenido actual.
- Integrarse con RFC-071, RFC-072, RFC-073 y RFC-074.
- Tests de autorización, render, fallbacks y seguridad de contenido.

### No incluye

- Crear páginas públicas nuevas desde el CMS.
- Crear rutas dinámicas.
- Page builder visual libre.
- HTML libre.
- CSS o JavaScript editable.
- Componentes arbitrarios.
- Edición de inmuebles o proyectos; eso sigue en sus módulos existentes.
- Gestión avanzada de SEO por página — puede quedar como extensión futura si no se define aquí.

---

## Actor autorizado

Solo `owner` puede administrar contenido institucional.

| Rol | Acceso esperado |
| --- | --- |
| `owner` | ✅ Puede editar contenido de páginas. |
| `admin` | ❌ 403 / sin navegación. |
| `agente` | ❌ 403 / sin navegación. |
| `arquitectura` | ❌ 403 / sin navegación. |
| `proyectos` | ❌ 403 / sin navegación. |

---

## Páginas incluidas

| Key | Ruta | Vista actual | Objetivo |
| --- | --- | --- | --- |
| `home` | `/` | `welcome.blade.php` | Portada comercial editable. |
| `nosotros` | `/nosotros` | `site/nosotros.blade.php` | Historia, equipo, valores y confianza. |
| `servicios` | `/servicios` | `site/servicios.blade.php` | Contenido de servicios, integrado con RFC-074. |
| `inversionistas` | `/inversionistas` | `site/inversionistas.blade.php` | Propuesta para inversionistas. |
| `contacto` | `/contacto` | `leads/create.blade.php` | Texto de contacto y apoyo al formulario. |

---

## Modelo propuesto

### `FrontendPage`

Campos normativos:

- `id`.
- `key` — único: `home`, `nosotros`, `servicios`, `inversionistas`, `contacto`.
- Estado draft: `is_enabled`, `seo`.
- Snapshot: `published_revision`, `published_at`, `published_by`.
- `revision` — contador de publicaciones.
- `draft_revision bigint NOT NULL DEFAULT 1` — versión optimista del estado de trabajo.
- timestamps.

### `FrontendSection`

Campos normativos:

- `id`.
- `frontend_page_id`.
- `section_key` — identificador estable de sección.
- `type` — tipo permitido de bloque.
- `payload` JSON validado.
- `is_enabled` boolean.
- `sort_order`.
- **`deleted_at` (`SoftDeletes`)** — obligatorio: impide que Spatie borre la media referenciada por la `published_revision` viva (`InteractsWithMedia.php:51-63`). `forceDelete` prohibido por policy.
- timestamps.

**Índices: todos parciales, creados por DDL con nombre explícito** (§16.1.2 de la épica es la fuente):

```sql
CREATE UNIQUE INDEX frontend_sections_page_section_key_active_unique
  ON frontend_sections (frontend_page_id, section_key) WHERE deleted_at IS NULL;
CREATE UNIQUE INDEX frontend_sections_page_sort_order_active_unique
  ON frontend_sections (frontend_page_id, sort_order)  WHERE deleted_at IS NULL;
```

PostgreSQL no admite predicado en un constraint `UNIQUE`, así que se usa `CREATE UNIQUE INDEX ... WHERE ...` (patrón ya presente en el repo: `create_lona_requests_table.php:31-35`). Ningún UNIQUE global sobrevive: dejaría el `sort_order` de una fila borrada ocupado para siempre, impidiendo reordenar o recrear la sección.

Media collection: `images`; cada referencia usa `media_id` UUID + `alt` o `decorative=true`.

Toda creación/edición/reordenamiento/borrado de sección y toda edición draft de página usa `FrontendPageContentService`: dentro de `DB::transaction`, ejecuta `SET TRANSACTION ISOLATION LEVEL READ COMMITTED` como primera sentencia SQL, toma `FrontendPage::lockForUpdate()` y las secciones afectadas por `id ASC`, **valida** los `media_id` del JSON final —existencia, owner y colección— y recién entonces escribe, incrementando `draft_revision` en la misma transacción. **No se bloquea `media`**: en v1 ninguna ruta la borra (§16.4 de la épica).

El prune de §16.4.1 de la épica limita candidatos a `FrontendSection/images` y `FrontendService/image`; excluye sin excepción `FrontendSetting` (`logo-light`, `logo-dark`, `favicon`, `default-og-image`). Tras bloquear cada media, reconsulta `FrontendSection.payload`, `FrontendService.draft_payload`, `FrontendPage.published_revision` y `FrontendService.published_payload`; **solo escribe el intent durable** (`lock→recheck→intent`, nunca delete en transacción) si sigue sin referencias y cumple 30 días. El borrado físico y el de la fila ocurren después del commit, con inventario completo —incluida la familia responsive `media_library_original`—, borrado estricto y **verificación de ausencia**; los jobs de conversiones/responsive corren en versiones guardadas que abortan ante un intent activo. Contrato autoritativo: **§16.4.2**.  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->

---

## Tipos de sección permitidos

El sistema usa esta allowlist cerrada de tipos ejecutables. Cada entrada del registry define renderer Blade, schema validable, media, multiplicidad y adapter de fallback; no es una etiqueta descriptiva sin implementación.

| Tipo | Uso |
| --- | --- |
| `hero` | Encabezado principal con título, subtítulo, imagen/fondo y CTA. |
| `rich_text` | Texto estructurado sin HTML libre. |
| `metrics` | Lista de estadísticas controladas. |
| `values` | Tarjetas de valores/beneficios. |
| `team` | Tarjetas de equipo. |
| `partners` | Logos o nombres de aliados. |
| `feature_sequence` | Secuencia ordenada de paneles editoriales con imagen y variante de layout allowlisted. |
| `audience_outcomes` | Composición tipada de audiencia objetivo + resultados esperados. |
| `cta` | Franja de llamada a la acción. |
| `featured_projects` | Sección que consume proyectos existentes. |
| `featured_properties` | Sección que consume inmuebles existentes. |
| `opportunity_properties` | Sección independiente que consume inmuebles `opportunity()`. |
| `service_list` | Sección que consume RFC-074. |

Contratos mínimos de los tipos compuestos: `feature_sequence = {items:[{eyebrow,title,body,media_id,alt,layout}]}` con `layout ∈ {split_media_end,split_media_start,full_overlay}`; `audience_outcomes = {eyebrow,title,audience_items:[string],result:{eyebrow,title,items:[string],quote?:string}}`. Sus renderers escapan texto, resuelven `media_id` y rechazan campos/variantes desconocidos.

No se aceptan tipos creados por usuario en v1.

---

## Registry canónico por página

Estas son las secciones editables necesarias para un cutover sin regresión. La fila es estable: no se permite sustituir el `type` por valores genéricos como `varios` ni ejecutar queries arbitrarias desde payload.

| Página | `section_key` | `type` allowlisted | Fallback verificado |
| --- | --- | --- | --- |
| home | `hero` | `hero` | `welcome.blade.php:22-54` |
| home | `services_home` | `service_list` | `welcome.blade.php:151-177` + RFC-074 |
| home | `featured_properties` | `featured_properties` | `HomeController.php:16-17` + `welcome.blade.php:179-204` |
| home | `opportunity_properties` | `opportunity_properties` | `HomeController.php:19-20` + `welcome.blade.php:207-241` |
| home | `featured_projects` | `featured_projects` | `HomeController.php:29-34` + `welcome.blade.php:244-294` |
| home | `investors_block` | `cta` | `welcome.blade.php:297-325` |
| home | `partners` | `partners` | `welcome.blade.php:327-335` |
| home | `final_cta` | `cta` | `welcome.blade.php:337-353` |
| nosotros | `hero` | `hero` | `site/nosotros.blade.php:2-20` |
| nosotros | `metrics` | `metrics` | `site/nosotros.blade.php:22-37` |
| nosotros | `story` | `rich_text` | `site/nosotros.blade.php:39-57` |
| nosotros | `values` | `values` | `site/nosotros.blade.php:59-83` |
| nosotros | `team` | `team` | `site/nosotros.blade.php:85-123`; el schema incluye el spotlight A-74 y miembros |
| nosotros | `final_cta` | `cta` | `site/nosotros.blade.php:125-134` |
| servicios | `hero` | `hero` | `site/servicios.blade.php:2-20` |
| servicios | `services_list` | `service_list` | `site/servicios.blade.php:22-52` + RFC-074 |
| servicios | `final_cta` | `cta` | `site/servicios.blade.php:54-61` |
| inversionistas | `hero` | `hero` | `site/inversionistas.blade.php:2-20` |
| inversionistas | `investment_path` | `feature_sequence` | tres paneles de `site/inversionistas.blade.php:22-74` |
| inversionistas | `service_scope` | `values` | “¿Qué incluye?” de `site/inversionistas.blade.php:76-116` |
| inversionistas | `audience_outcomes` | `audience_outcomes` | audiencia + resultado de `site/inversionistas.blade.php:118-161` |
| inversionistas | `final_cta` | `cta` | `site/inversionistas.blade.php:163-172` |
| contacto | `hero` | `hero` | `leads/create.blade.php:2-17` |
| contacto | `contact_intro` | `rich_text` | título/copia de formulario en `leads/create.blade.php:23-27` |

**No existe `inversionistas.metrics` en el Blade actual y el registry no lo crea.** Los datos en “Resultado esperado” pertenecen al schema tipado `audience_outcomes`, no a una sección de métricas independiente.

### Regiones operativas del kernel, no secciones editables

| Página | Región kernel-only | Fuente/autoridad | Contrato de cutover |
| --- | --- | --- | --- |
| home | Buscador de inmuebles | `welcome.blade.php:56-149`, `PropertyType`, `Zone`, ruta `inmuebles.index` | Se conserva funcional; no tiene `section_key` ni payload CMS. |
| contacto | Formulario de leads | `LeadCaptureForm`, `leads/create.blade.php:26`, RFC-074 | Se monta siempre desde kernel y conserva validación/locks; el CMS no puede reemplazarlo ni ocultarlo. |
| contacto | Canales de contacto | `FrontendSetting` + `leads/create.blade.php:30-66` | Teléfono, email, oficina, horarios y WhatsApp son datos operativos del singleton; no se duplican en `FrontendSection`. |

Header, navegación, footer y WhatsApp flotante pertenecen al layout/kernel y a RFC-071/073, no al registry de páginas. Los tipos `service_list`, `featured_properties`, `opportunity_properties` y `featured_projects` sí son secciones registradas, pero sus items se resuelven en kernel desde sus autoridades; el payload solo permite parámetros allowlisted.

---

## Payloads estructurados

Cada tipo de sección debe validar su payload. Ejemplos:

### `metrics`

Todo payload es un objeto (decisión de consistencia §19.5.2): las listas van bajo `items`.

```json
{
  "items": [
    { "label": "Operaciones acompañadas", "value": "+120" },
    { "label": "Zonas estratégicas", "value": "15" }
  ]
}
```

### `values`

```json
{
  "items": [
    { "title": "Transparencia", "description": "Información clara en cada etapa." },
    { "title": "Estrategia", "description": "Decisiones basadas en mercado." }
  ]
}
```

### `hero`

```json
{
  "eyebrow": "Inmobiliaria · Arquitectura · Inversión",
  "title": "Encuentra la propiedad ideal",
  "subtitle": "Acompañamos tu decisión de principio a fin.",
  "primary_cta": {
    "label": "Ver Propiedades",
    "type": "route",
    "target": "inmuebles.index"
  },
  "secondary_cta": {
    "label": "Conocer Proyectos",
    "type": "route",
    "target": "proyectos"
  },
  "slides": [
    { "media_id": "uuid", "alt": null, "decorative": true, "sort_order": 0 }
  ]
}
```

**`slides` es parte del schema `hero` (cierra M-1 de la reauditoría P5).** El home real usa **cuatro** imágenes de fondo rotativas (`welcome.blade.php:12-16`), no un hero de imagen única; un schema sin `slides` no puede representarlas. Reglas normativas (§16.1.1 de la épica es la fuente única):

- **`secondary_cta` es un CTA real y nullable, NO `null` fijo.** El hero del home tiene dos CTAs visibles: "Ver Propiedades" → `inmuebles.index` (`welcome.blade.php:46`) y "Conocer Proyectos" → `proyectos` (`:47-49`). Ese par exacto es su fallback; fijar `secondary_cta: null` habría borrado un CTA visible en el cutover. Ambos usan el value object `{label,type,target}` de RFC-073.
- Cardinalidad `0..6`; el orden lo fija `sort_order`, no el índice del array; el `animation-delay` es derivado, no editable.
- `decorative: true` por default (son fondos bajo overlay) y en ese caso `alt` debe ser `null`; con `decorative: false`, `alt` es obligatorio.
- ~~Sin slides publicados, el fallback son las cuatro URLs actuales en su orden~~ — **⚠️ TEXTO HISTÓRICO, NO NORMATIVO (superado por §18.18 de la épica).** El fallback es **por página**: `home` = las 4 URLs Unsplash (`welcome.blade.php:12-16`); `nosotros`, `servicios` e `inversionistas` = su PNG de encabezado (`:11` de cada vista); `contacto` = **sin imagen de fondo**. Matriz normativa completa: `docs/epicas/epica-12-1-mejora-ux-hero.md` §8. **Sigue vigente:** un `slides: []` publicado **no** revive el fallback.
- Solo archivos subidos por el owner (colección `images`, reglas de §16.4). El owner no puede apuntar a un host externo arbitrario.

Regla: el JSON no es libre. Cada tipo debe tener schema/validación propia. Todo CTA de `hero` o sección `cta` usa el value object anidado `{label,type,target}` de RFC-073; cualquier forma plana legacy se rechaza.

---

## Contenido permitido

- Texto plano.
- Listas estructuradas.
- Imágenes gestionadas por Media Library.
- CTAs validados por RFC-073.
- Referencias controladas a entidades existentes cuando aplique:
  - Inmuebles destacados.
  - Proyectos destacados.
  - Servicios activos.

No permitido:

- HTML libre.
- Scripts.
- CSS.
- Iframes.
- URLs inseguras.
- Campos que permitan path traversal.

---

## Render público

Crear un servicio, por ejemplo `FrontendPageContentService`, responsable de:

- Cargar página por key.
- Cargar secciones habilitadas.
- Aplicar orden.
- Validar/normalizar payloads.
- Resolver media.
- Aplicar fallbacks si no hay página/sección.
- Exponer datos simples a Blade.

El render público lee solo `published_revision`. La UI de publicar debe enviar `expected_draft_revision`; si otra conexión confirmó una mutación draft desde que la UI cargó, la publicación termina en conflicto sin cambiar el snapshot.

La media draft vive en `frontend-private`. Tras commit se despacha `PromoteFrontendMedia`; el job es idempotente y `ReconcileFrontendMediaPromotions` vuelve a encolar media publicada no promovida si el callback/dispatch se perdió. Un rollback no copia, no marca y no encola.

Las vistas Blade deben seguir siendo templates controlados. El CMS solo alimenta contenido.

---

## Fallbacks iniciales

Si no existe contenido en BD, cada página debe mantener contenido equivalente al actual.

Esto debe cubrir:

- Hero actual de home.
- Servicios actuales hasta que RFC-074 provea datos.
- Bloque A-74 actual.
- Bloque inversionistas actual.
- Secciones de nosotros actuales.
- Contacto actual.

El deploy de este RFC no debe dejar páginas vacías.

---

## Integración con RFCs previos

- RFC-071: identidad, logos, contacto y SEO defaults.
- RFC-072: tema visual usado por las secciones.
- RFC-073: CTAs y destinos permitidos.
- RFC-074: servicios activos para `services_list`.

Regla clave: este RFC no debe duplicar servicios. La página de servicios debe consumir el catálogo de RFC-074.

---

## Interfaz en Filament

Crear área owner-only para páginas del frontend.

UI sugerida:

- Lista de páginas fijas.
- Acción `Editar contenido`.
- Secciones agrupadas por página.
- Campos según tipo de sección.
- Toggle habilitado/deshabilitado.
- Orden cuando aplique.
- Carga de imágenes.
- Preview básico si el alcance lo permite.

La UI debe evitar que el owner cree tipos no soportados o cambie keys internas críticas.

---

## Seguridad

- Owner-only real por policy/gate y pruebas HTTP.
- No HTML libre.
- Escape en Blade por defecto.
- Si se permite Markdown en algún campo largo, debe usar HTML escapado y links seguros.
- URLs de CTAs deben delegar validación a RFC-073.
- Imágenes deben validar MIME/tamaño.
- Payload JSON debe validarse por tipo antes de guardar.

---

## Accesibilidad y UX

- Cada imagen editable debe tener `alt_text` o marcarse decorativa.
- Mantener un solo H1 por página.
- Las secciones deben conservar orden semántico.
- CTAs deben tener texto claro.
- Métricas no deben ser solo imagen.
- No depender solo de color para comunicar información.
- Estados vacíos deben ser claros y no romper layout.

---

## Archivos esperados

```text
app/
  Models/
    FrontendPage.php
    FrontendSection.php
  Policies/
    FrontendPagePolicy.php
    FrontendSectionPolicy.php
  Services/
    Frontend/
      FrontendPageContentService.php
      FrontendPagePublisher.php                 (pagina -> secciones id ASC -> media uuid ASC en publicacion con promocion; §18.18)
      FrontendSectionSchema.php                  (o validadores por tipo)
      FrontendMediaReference.php                 (nombre real; valida existencia/owner/coleccion + formato uuid; la VALIDACION no toma lock)
      PublishedMediaReference.php                (predicado de referencia publicada; owningPage con withTrashed)
      FrontendMediaPruner.php                    (advisory discovery + lock/recheck/INTENT; nunca delete en transacción)  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->
  Jobs/
    PromoteFrontendMedia.php                    (post-commit, retry idempotente)
    PurgeFrontendMediaFiles.php                 (purga fisica post-commit: inventario, borrado estricto,  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->
                                                 verificacion de ausencia y recien entonces la fila)
  Console/Commands/
    ReconcileFrontendMediaPromotions.php        (recuperación de enqueue perdido)
    PruneFrontendMedia.php                      (manual; delega a FrontendMediaPruner)  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->
    MaintainFrontendMedia.php                   (unico programado: reconcile -> prune -> prune-orphans)  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->
  Filament/
    Resources/FrontendPageResource.php

config/
  frontend-sections.php                         (registry canónico + renderers/schemas)

database/
  migrations/
    xxxx_create_frontend_pages_table.php
    xxxx_create_frontend_sections_table.php
    xxxx_seed_frontend_canonical_pages.php       (FUENTE PRODUCTIVA: invoca SeedFrontendPages)
  seeders/
    FrontendPageSeeder.php                       (SOLO dev/test — no es la fuente productiva)

app/
  Actions/
    Frontend/
      SeedFrontendPages.php                      (insert-if-missing de las 5 páginas, idempotente)

resources/
  views/
    welcome.blade.php
    site/nosotros.blade.php
    site/servicios.blade.php
    site/inversionistas.blade.php
    leads/create.blade.php

routes/
  console.php                                    (programa solo frontend:media:reconcile; ningun comando destructivo)

tests/
  Feature/Frontend/
    FrontendPageAccessTest.php
    FrontendPageRenderTest.php
    FrontendPageFallbackTest.php
    FrontendSectionValidationTest.php
    FrontendPageSectionRegistryTest.php          (cubre todas las regiones Blade canónicas)
    FrontendContactKernelRegionsTest.php         (form/canales no son secciones CMS)
    FrontendPublishConcurrencyTest.php           (stale publisher + 2 conexiones)
    FrontendHomeSectionsTest.php                 (featured/opportunity/project independientes)
    FrontendMediaPromotionTest.php               (rollback/enqueue/reconcile/idempotencia)
    FrontendMediaReferenceConcurrencyTest.php    (draft/publish/manual prune, 2 conexiones)  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->
    FrontendMediaPurgeAtomicityTest.php          (intent, rollback, orden archivos-fila, retry)  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->
    FrontendMediaOrphanSweepTest.php             (scope por ruta, huerfanos, separacion de FrontendSetting)  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->
    FrontendMediaPurgeStrictnessTest.php         (delete=false, excepcion, parcial, familia responsive del original)  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->
    FrontendMediaDerivativeRaceTest.php          (jobs guardados, settle window, barrido de huerfanos)  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->
    FrontendMediaPruneScopeTest.php               (scope editorial; excluye marca)  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->
```

---

## Reglas técnicas

- No crear rutas dinámicas desde BD.
- No permitir tipos de sección fuera de allowlist.
- No guardar HTML/CSS/JS libre.
- No duplicar servicios de RFC-074.
- No tocar migraciones existentes.
- Seeders deben ser idempotentes.
- Cachear contenido es válido; guardar/publicar invalida únicamente mediante el bump global post-commit de RFC-076.
- La renderización debe evitar N+1 de media/secciones.
- El orden de locks es `FrontendPage` → `FrontendSection.id ASC` → **`media.uuid ASC` en la publicación con promoción y en el job** (§18.18 de la épica). Las referencias media se **validan** sin lock (en v1 nada la borra, §16.4); el lock de `media` existe por la **carrera de referencia**, no por borrado. Ningún Resource escribe JSON directamente.
- Candidate discovery de prune nunca autoriza delete: siempre hay lock de media y recheck post-lock READ COMMITTED.  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->

---

## Riesgos

| Riesgo | Impacto | Mitigación |
| --- | --- | --- |
| Convertirlo en page builder | Alto costo y bugs visuales. | Tipos de sección cerrados. |
| XSS por contenido editable | Seguridad crítica. | Sin HTML libre, escape por defecto. |
| Páginas vacías tras deploy | Mala experiencia pública. | Fallbacks obligatorios. |
| Drift con servicios | Servicios duplicados e inconsistentes. | `services_list` consume RFC-074. |
| Payload JSON inválido | Render roto. | Validadores por tipo. |
| Mala accesibilidad | Sitio difícil de usar. | Alt text, H1 único, CTAs claros. |
| Prune concurrente deja UUID colgante | Página publicada/draft rota. | Protocolo compartido de §16.4.1 + pruebas PostgreSQL de ambos interleavings. |  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->

---

## Definition of Done

- Owner puede editar contenido estructurado de páginas incluidas.
- Otros roles no pueden acceder ni guardar cambios.
- Home, Nosotros, Servicios, Inversionistas y Contacto renderizan desde configuración o fallback.
- El registry cubre cada región editable actual con un `section_key` estable y tipo ejecutable allowlisted; no existe `inversionistas.metrics`.
- Formulario de leads y canales operativos de contacto permanecen kernel-only y no pueden convertirse en payload editorial.
- Home conserva por separado inmuebles destacados, oportunidades inmobiliarias y proyectos destacados.
- Página Servicios consume servicios de RFC-074, no lista duplicada.
- No existe HTML/CSS/JS editable.
- Payloads se validan por tipo de sección.
- CTA de `hero`/`cta` exige la forma anidada `{label,type,target}` y rechaza campos planos legacy.
- Imágenes se gestionan con Media Library.
- Toda mutación draft incrementa `draft_revision`; publisher stale se rechaza bajo locks deterministas.
- Draft y publisher bloquean/validan `media.uuid ASC` antes de escribir JSON; prune manual/programado usa el mismo servicio y recheck post-lock.  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->
- Promoción media tiene job, retry idempotente y reconciliación de dispatch perdido.
- Si no hay contenido en BD, las páginas mantienen contenido equivalente al actual.
- Tests cubren autorización, render, fallbacks, validación y carreras draft/publish/manual-prune con dos conexiones PostgreSQL, barreras explícitas y sin sleeps; alteran el default de sesión y verifican `SHOW transaction_isolation = read committed` dentro de cada transacción protegida.  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->
- `php artisan test` verde sobre PostgreSQL real.
- Pint limpio.
- `npm run build` verde.

---

## Dependencias

- RFC-071 — Perfil público y configuración base.
- RFC-072 — Tema visual configurable.
- RFC-073 — Navegación, footer y CTAs globales.
- RFC-074 — Servicios ofrecidos y disponibilidad.
- Épica 12 documento general: `docs/epicas/epica-12-administrador-contenidos-frontend.md`.

---

## Próximo RFC

RFC-076 — Render público, caché y fallbacks: centralizar la entrega de configuración/contenido al frontend, invalidación de cache y comportamiento estable ante contenido incompleto.
