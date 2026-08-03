# Design: administrar la página /proyectos desde el CMS

## Technical Approach

Extensión **aditiva** del motor de RFC-075/076: una key nueva en el registro, UNA clave nueva
(`logo`) en `SPECS['hero']`, y una **variante de render por página** (`catalog`) para el listado.
Cero tipos nuevos, cero dependencias, cero cambios en las cinco páginas publicadas. El cutover
copia el patrón ya usado por `servicios`/`inversionistas`: `@if (! $cms['fallback'])` → snapshot;
`@else` → el blade actual **verbatim**, así §16.7 se cumple por construcción y no por traducción.

## Architecture Decisions

| # | Decisión | Alternativa descartada | Razón |
|---|---|---|---|
| D1 | `pages.proyectos` en config + migración que llama `app(SeedFrontendPages::class)->run()` | migración con `DB::table` propia; sólo seeder | `key` es `string(30)->unique()`, no hay enum ni CHECK: el «enum» ES el config. `firstOrCreate` crea sólo la página nueva y sus 3 secciones. Precedente: `2026_07_24_100200`. Producción despliega con `migrate --force`, sin seeders. |
| D2 | La misma migración **siembra payloads** de `projects_list` (eyebrow+title) y `final_cta` (copy actual + `background_color: neutral-4`) | dejarlos en `null` | Un `payload: null` viaja al snapshot (`FrontendPagePublisher:125-131`) y el partial `cta` dibujaría una **tarjeta navy vacía**. Precedente textual: `2026_07_27_110000`. `projects_list` va **sin** `background_color` para que herede el gradiente literal (D6). |
| D3 | Hero draft sembrado desde `hero_fallback.proyectos`, **omitiendo `slides`** | copiar `slides => []` como `2026_07_28_100100` | Esa migración ya corrió y no volverá a alcanzar a `proyectos`; sin sembrar, el editor abre en blanco sobre una página con texto (el defecto que ella cerró). `slides => []` mataría el backdrop §16.7 en la primera publicación; ausente lo conserva. |
| D4 | `logo: {media_id, alt}` anidado; `logo_enabled` es EL interruptor y la precedencia es **propio > marca**; fallback cuando la clave `logo` está **ausente** | `hero_logo_media_id` plano; `logo_enabled` sólo para la marca | `mediaIds()` (`FrontendPageContentService:354-371`) recorre buscando `media_id` exacto: plano sería invisible para validación, promoción y huérfanas. Con `logo_enabled` gobernando ambos, «sin logo» queda expresable; si el propio lo ignorara, quitar la imagen revive A-74 por fallback y no hay forma de apagarlo. Ausencia = «no inicializado», **idéntico a `slides`** (`:209`). **Reconciliación con el spec:** `specs/hero-logo-propio/spec.md` proponía originalmente que el logo propio ignora `logo_enabled`; se corrigió a favor de esta fila por la razón de arriba (defecto de capacidad, no empate de tradeoffs) — el spec quedó enmendado en el propio archivo, ver su nota de corrección. |
| D5 | Tamaño con el enum `logo_size` YA existente + rampa propia (`xl` → `h-48 sm:h-56` = 14rem) | `hero_logo_sizes` nuevo; entrada en `hero_variants` | El partial ya tiene rampa aparte para `featured` (`hero.blade.php:44-56`): es la misma técnica. **Verificado: `proyectos` NO necesita `hero_variants`** — `standard` (`:94-95`, `py-20`, `max-w-[760px]`, clamp 34/5vw/56) es byte a byte el degradado izq→der de `proyectos.blade.php:20-21,23,28,32`. Móvil queda en 12rem (mejor que 14rem fijo) y muere el `style=` inline, superficie que §6.1 ya había eliminado del hero. |
| D6 | `projects_list` → tipo `featured_projects` + variante **`catalog`** por página (`config.project_list_variants`), que decide autoridad (todos los `Project`, `latest()`), layout (carrusel de 6 + swipe), estado vacío y fondo por defecto | reusar `featured_projects` tal cual; tipo nuevo `project_list` | Tal cual divergía en TRES cosas: autoridad (`is_featured` vs todos), estado vacío (no dibuja nada vs «Pronto publicaremos») y layout. Un tipo nuevo duplicaría 8 claves de schema, una rama de compilador y un formulario por presentación (DRY/YAGNI). Precedente de autoridad por página: `SERVICE_LOCATION` (`FrontendPageRenderer:37`); de presentación por página: `hero_variants`. |
| D7 | Los DOS gradientes: el de 3 paradas (`:48`) es clase **literal** default de la variante `catalog`; el de 4 (`:111`) **no es portable** y sobrevive sólo en la rama de fallback | interpolar hex desde el payload | §6.1: clases enteras y literales o Tailwind no las compila. El partial `cta` no conoce la página, así que su default sólo puede ser de paleta; `neutral-4` (`bg-stone`) es su vecino y va sembrado (D2). |
| D8 | `ProjectController@index` se **queda**; el blade llama al renderer como las otras cinco | mover `render()` al controlador; `Route::view` | `$projects` sólo lo consume la rama de fallback, y es una query — su lugar es el controlador. Mover el `render()` crearía dos formas de renderizar una página del CMS. |
| D9 | Fieldset del logo: `->statePath('payload.logo')` **incondicional, sin `visible()`**, dentro de `heroFields()` | `Fieldset->statePath()->visible(fn ($record))` | Es el defecto real de Filament diagnosticado por bisección: `statePath()` + `visible($record)` hermano de rutas absolutas al mismo `payload` corrompe la hidratación del hermano. La forma segura ya probada es `featuredProjectsFields():876-897`, y el `match()` de `fieldsForMountedType():94` ya gatea. |
| D10 | El chip A-74 aplica `brightness-0 invert` SÓLO al logo del **fallback**, nunca al logo que sube el owner: el renderer marca el logo derivado del fallback con `from_fallback: true` y el partial condiciona el filtro a esa marca (Fase 4, commit `8bcb569`) | dejar el filtro incondicional sobre cualquier imagen (estado original de la Fase 3); un `@if` sólo por si el owner sube un logo de color | El filtro convierte cualquier imagen en silueta blanca. Sobre el logo A-74 hardcodeado está bien (así se veía siempre, §16.7 manda conservarlo), pero sobre el que sube el owner le borraba el color de marca — justo lo que "logo propio" existe para mostrar. La regla es por ORIGEN, no por posición: desde el payload ambos casos se ven iguales, así que el origen tiene que viajar explícito. Cierra el Open Question "chip A-74 sin resolver" de abajo. |

### Payload shape

```php
// FrontendSectionSchema::SPECS['hero'] — clave nueva, OPCIONAL (no invalida snapshots)
'logo' => ['object', ['media_id' => '?media', 'alt' => '?string']],
// La regla universal de checkObject():225-233 aplica sola: media_id ⇒ alt no vacío.

// config/frontend-sections.php
'hero_fallback.proyectos' => [
    'eyebrow' => 'Desarrollos & obra',
    'title' => 'Proyectos con visión, diseño y propósito.',
    'subtitle' => 'Desarrollos residenciales, propiedades y soluciones arquitectónicas…',
    'logo_enabled' => true, 'logo_size' => 'xl',
    'logo' => ['src' => 'images/brand/a74-arquitectura.png', 'alt' => 'A-74 Arquitectura'],
    'slides' => [/* las 4 de proyectos.blade.php:8-11 */],
],
'project_list_variants' => ['proyectos' => 'catalog'],
```

## Data Flow

```
ProjectController@index ──$projects──┐
config.pages.proyectos               │
   │                                 ▼
   ├─ SeedFrontendPages ──► rows ──► site/proyectos.blade.php
   │                                 │
   └─► FrontendPageRenderer::render('proyectos')
         ├ fallback ─► ['hero'=>presentHero(fallback+logo)] ─► @else: hero partial + blade actual
         └ snapshot ─► resolveTree (logo.media_url gratis, :384) ─► frontend.render ─► partials
```

`presentHero`: tras `resolveTree`, si `! array_key_exists('logo', $payload)` → `fallbackHeroLogo($pageKey)`
(`['media_url','alt']|null`), y `fallbackHeroPayload()` hace `unset($configured['slides'], $configured['logo'])`.
Simetría exacta con `slides`. **`resolveTree` no necesita rama nueva.**

## File Changes

| Archivo | Acción | Qué |
|---|---|---|
| `config/frontend-sections.php` | Modify | `pages.proyectos`, `section_labels.projects_list`, `hero_fallback.proyectos`, `project_list_variants` |
| `app/Services/Frontend/FrontendSectionSchema.php` | Modify | `SPECS['hero']['logo']` |
| `app/Services/Frontend/FrontendPageRenderer.php` | Modify | `fallbackHeroLogo()`, `unset` del `logo`, variante+autoridad de `projects()` |
| `app/Filament/Forms/Sections/SectionPayloadCompiler.php` | Modify | `heroLogo()` reusando `mediaId()` (único punto upload→media); omite el objeto vacío |
| `.../RelationManagers/SectionsRelationManager.php` | Modify | Fieldset D9 en `heroFields()` |
| `resources/views/frontend/sections/hero.blade.php` | Modify | logo propio + rampa + chip A-74 (`logo.media_url` + `logo.alt`) |
| `resources/views/frontend/sections/featured_projects.blade.php` | Modify | variante `catalog`: carrusel, estado vacío, gradiente literal |
| `resources/views/site/proyectos.blade.php` | Modify | `@if/@else` del cutover |
| `app/Filament/Pages/FrontendPreview.php` · `FrontendPreviewController.php` | Modify | `proyectos` en `pages()` y `TITLES` — **sin esto el owner no puede previsualizar** |
| `database/migrations/…_seed_proyectos_page.php` | Create | D1+D2+D3 |

## Testing Strategy (strict TDD — test primero)

| Capa | Qué | Cómo |
|---|---|---|
| Unit | `SPECS['hero']['logo']`: `media_id` sin `alt` rechazado; `logo` ausente válido; `logo` con clave desconocida rechazado | `FrontendSectionSchema::validate()` directo |
| Unit | Compiler: upload → `logo.media_id` + `alt`; vacío → clave omitida | `SectionPayloadCompiler` con `FrontendSection` en memoria |
| Integration | `mediaIds()` encuentra `hero.logo.media_id`; se promueve al publicar; NO aparece en `ReportUnreferencedFrontendMedia` | extender `FrontendMediaPromotionTest` |
| Integration | Matriz 4×: {propio, marca} × `logo_enabled` {on, off} + fallback A-74 con payload ausente | extender `FrontendHeroContractMatrixTest` |
| Integration | Editor: el Fieldset del logo aparece Y `payload.slides`/`text_align` siguen hidratando | **`->html()` real** estilo `editorHtml()` de `FrontendFeaturedProjectsCtaTest` — el walk de `fieldNames()` NO resuelve `visible($record)` y no detecta esta clase de regresión |
| Integration | Seed idempotente: 6 páginas, las 5 previas intactas, `firstOrCreate` dos veces | nuevo test del seed + `FrontendPageContractTest` |
| E2E | `/proyectos` sin publicar ≡ hoy (h1, eyebrow, gradientes literales, logo A-74, carrusel); publicado ≡ snapshot; sin `Project` → estado vacío | `FrontendRenderFallbackTest` + nuevo test de la página |

## Migration / Rollout

Una migración; `down()` borra la página `proyectos` y sus secciones (las otras cinco no se tocan).
Rollback en caliente **sin deploy**: deshabilitar la página en el panel → `page()` devuelve
`fallback: true` (`FrontendPageContentService:87-89`) → vuelve el aspecto estático exacto.

## Open Questions

- [ ] Al publicar, el hero **pierde las migas de pan**: `frontend/render.blade.php:14` no pasa
      `$breadcrumbs`. Ya le pasa a las otras cinco; el proposal lo declara fuera de alcance.
- [ ] `catalog` sin `limit` consulta todos los `Project` (status quo del controlador). El owner puede acotar.
- [x] ~~El chip A-74 usa `brightness-0 invert`: un logo de color subido por el owner saldría como
      silueta blanca.~~ **RESUELTO en Fase 4** (commit `8bcb569`) — ver decisión D10. El filtro ahora
      sólo se aplica al logo del fallback (`from_fallback: true`); el logo que sube el owner conserva
      su color.
