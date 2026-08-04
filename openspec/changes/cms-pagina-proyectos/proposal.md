# Proposal: administrar la página /proyectos desde el CMS

## Intent

`/proyectos` es la única página pública del sitio que quedó fuera del CMS:
`routes/web.php:28-29` → `ProjectController@index` → `resources/views/site/proyectos.blade.php`,
con todo hardcodeado (logo de A-74 tres veces en `:29,:39,:53`, antetítulo `:31`,
h1 `:32`, gradientes literales `:48` y `:111`). Cambiar un logo o un título hoy
exige un deploy.

**Esto es una EXTENSIÓN DE ALCANCE de RFC-075, no un bugfix.** RFC-075 fijó cinco
keys (`docs/rfc/RFC-075-CONTENIDO-PAGINAS-INSTITUCIONALES-FRONTEND.md:121-127,:138`)
y su §«No incluye» `:94` excluye «Crear páginas públicas nuevas desde el CMS». Se
amplía porque esa exclusión apunta a CREAR rutas nuevas, y acá la ruta pública ya
existe y está viva: lo que se suma es su ADMINISTRACIÓN, no una página. Distinto de
`:100` («Edición de inmuebles o proyectos»), que habla de los REGISTROS de Project,
no de esta pantalla.

El caso real que lo motiva es A-74 Arquitectura: una división con imagen comercial
PROPIA, que el hero del CMS hoy no puede mostrar.

## Scope

### In Scope

- `proyectos` como sexta página canónica del registro cerrado, con tres secciones:
  `hero`→`hero`, `projects_list`→`featured_projects`, `final_cta`→`cta`.
- Logo propio y OPCIONAL en el hero, independiente de la marca del sitio.
- Fallbacks §16.7 (`hero_fallback.proyectos` + fondos) = el aspecto actual, tal cual.
- Cutover del blade estático al render del CMS.
- Botón del hero al sitio del asociado: `primary_cta` type `url` — `CtaResolver`
  ya valida `^https://` y marca `external` (`app/Support/Frontend/CtaResolver.php:116-126`).
  Cero código nuevo.

### Out of Scope

- Editar registros de Project (siguen en su módulo — RFC-075 `:100`).
- Abrir el registro a páginas arbitrarias / page builder.
- Breadcrumb `:24-27`, `site.partials.project-card`, `project-nav`.
- Subir el logo de A-74 a Media automáticamente (lo hace el owner).
- Color libre de fondos: sigue mandando `brand_palette` (§6.1).

## Capabilities

### New Capabilities

- `cms-pagina-proyectos`: registro canónico, seed, fallbacks §16.7 y cutover del
  render de `/proyectos`.
- `hero-logo-propio`: logo por hero, opcional, con la convención `media_id` anidada.

### Modified Capabilities

- None a nivel de archivo (`openspec/specs/` está vacío). Se extienden ADITIVAMENTE
  dos contratos vivos: `FrontendSectionSchema::SPECS['hero']` (clave nueva opcional)
  y `config('frontend-sections.pages')` (key nueva). Ningún snapshot publicado se
  invalida.

## Approach

| Decisión | Qué | Por qué |
|---|---|---|
| Logo propio | `'logo' => ['object', ['media_id' => '?media', 'alt' => '?string']]` en el spec del hero | validación de elegibilidad, promoción al publicar y reporte de huérfanas recorren el payload buscando la clave `media_id`; un `hero_logo_media_id` plano sería invisible para los tres. Anidado hereda la regla de accesibilidad (`media_id` ⇒ `alt` no vacío). Misma convención que `team.spotlight`. |
| Resolución gratis | `presentHero` ya llama `resolveTree` (`FrontendPageRenderer.php:193`), que resuelve todo nodo con `media_id` (`:384-387`) | el presenter no necesita rama nueva: `logo.media_url` sale resuelto. |
| Precedencia | logo propio presente → gana; `logo_enabled` sigue gobernando el logo de MARCA (`:244-248`); `logo_size` se reusa | aditivo: las cinco páginas ya publicadas no cambian. |
| Destacado + fondo | reusar `featured_projects` TAL CUAL: ya trae `media_id`/`alt`, `eyebrow`, `title`, `background_color`, `primary_cta`, `limit` | el header A-74 (`:52-60`) y el fondo (`:48`) ya caben. Cero schema nuevo. |
| Cierre | reusar el tipo `cta` (`background_color`, `title_color`) | es literalmente el bloque `:110-116`. |
| Layout | variante de render POR PÁGINA, como `hero_variants` | el carrusel de páginas de 6, el swipe móvil y el estado vacío son presentación, no payload; clases enteras y literales (§6.1). |
| Gradientes | `background_color` sin elegir → la clase literal del gradiente ACTUAL; elegido → clase plana de `brand_palette` | §16.7: los gradientes de `:48` y `:111` no existen en la paleta cerrada, y el cutover no debe cambiar el aspecto. |

Implementación con **test primero** (`strict_tdd: true`, `composer test`): el spec
del hero, la promoción del logo y el fallback de la página se prueban antes de
tocar el render.

## Alternativas descartadas

| Alternativa | Por qué no |
|---|---|
| Prender `logo_enabled` y usar el logo de marca | inyecta el logo de New Hauz (`:246`). A-74 es OTRA imagen comercial: es justo el requisito que falta. |
| `hero_logo_media_id` plano | invisible para validación, promoción y reporte de huérfanas. |
| Tipo de sección nuevo `partner_hero` | duplicaría slides, CTAs, alineación y accesibilidad del hero por UNA clave (DRY/YAGNI). |
| Dejar el blade estático y parametrizar solo el logo con un Setting | no cubre título/antetítulo/descripción/destacado/fondo/CTA, y deja `/proyectos` fuera del ciclo draft→publish: sin preview, sin snapshot, sin auditoría de medios. |

## Affected Areas

| Área | Impacto | Qué cambia |
|---|---|---|
| `config/frontend-sections.php` | Modified | `pages.proyectos` (3 secciones), `section_labels.projects_list`, `hero_fallback.proyectos`, fallbacks de fondo |
| `app/Services/Frontend/FrontendSectionSchema.php` | Modified | `SPECS['hero']['logo']` opcional |
| `app/Services/Frontend/FrontendPageRenderer.php` | Modified | precedencia logo propio vs marca en `presentHero` |
| `app/Actions/Frontend/SeedFrontendPages.php` + migración/seed | Modified | alta de la página `proyectos` y sus secciones |
| Editor del hero (Filament) | Modified | subida del logo propio (`media_id` + `alt`) |
| `resources/views/site/proyectos.blade.php` | Removed/Modified | cutover al render del CMS |
| Partials de render (`hero`, `featured_projects`, `cta`) | Modified | variante de página para el listado |
| `app/Http/Controllers/ProjectController.php` | Modified | pasa por el contenido publicado |

Sin geometría PostGIS/Magellan involucrada. Sin dependencias nuevas.

## Risks

| Riesgo | Prob | Mitigación |
|---|---|---|
| El cutover cambia el aspecto de `/proyectos` | Media | fallbacks §16.7 con los valores literales de hoy + test de render que compara hero, fondo y cierre contra el estado hardcodeado |
| La clave `logo` invalida snapshots del hero ya publicados | Baja | es OPCIONAL; extender `FrontendHeroContractMatrixTest` con el caso «sin logo propio» |
| El logo propio no se promueve al publicar → hero sin logo | Media | la convención `media_id` anidada lo mete en el pipeline; test de promoción sobre `hero.logo.media_id` |
| El carrusel de 6 + swipe se degrada al pasar por el partial compartido | Media | la variante por página conserva el markup y el JS actuales |
| Ambigüedad de precedencia logo propio vs `logo_enabled` | Media | regla explícita en el spec + test de las cuatro combinaciones |
| El hero de `/proyectos` necesite su propio `hero_variants` (degradado izq→der, logo de 14rem) | Media | decisión de `sdd-design`; si hace falta, es una entrada de config, no código nuevo |

## Rollback Plan

1. Revertir la ruta a `ProjectController@index` con el blade estático (el archivo se
   conserva hasta que `sdd-verify` cierre).
2. Despublicar/borrar la página `proyectos` (migración de seed reversible en `down()`).
3. `SPECS['hero']['logo']` es opcional: quitarla no invalida ningún snapshot ya
   publicado de las otras cinco páginas.

## Dependencies

- Logo de A-74 subido a Media por el owner (hoy vive en `public/images/brand/a74-arquitectura.png`).
- URL real del sitio del asociado para el CTA (`https://`), si aplica.
- Nada más: `CtaResolver`, `featured_projects` y `cta` ya están implementados.

## Success Criteria

- [ ] `/proyectos` renderiza desde el contenido publicado y, sin inicializar, se ve
      IDÉNTICA a hoy (§16.7).
- [ ] El owner cambia logo, antetítulo, título, descripción, CTA externo, destacado,
      fondo del listado y cierre desde el panel, sin deploy.
- [ ] El hero muestra el logo de A-74, independiente del de la marca; las otras cinco
      páginas siguen mostrando el de marca sin cambios.
- [ ] Un `logo.media_id` sin `alt` es rechazado por el schema.
- [ ] El logo propio se valida, se promueve al publicar y NO aparece como huérfano.
- [ ] `composer test`, `./vendor/bin/pint --test` y `./vendor/bin/phpstan analyse` verdes.
