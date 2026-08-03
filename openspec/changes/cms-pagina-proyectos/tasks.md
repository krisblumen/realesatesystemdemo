# Tasks: administrar la página /proyectos desde el CMS

Strict TDD activo — runner `php artisan test`. Cada tarea de implementación (GREEN) va precedida por su test (RED). Decisión de precedencia del logo: **gana el design** (`logo_enabled` gobierna AMBOS logos) — ver `sdd/cms-pagina-proyectos/decision-precedencia-logo` (#1090). El escenario «logo propio + `logo_enabled` false» del spec original queda CORREGIDO a NO MOSTRAR.

## Review Workload Forecast

| Field | Value |
|---|---|
| Estimated changed lines | ~750–950 (producción ~380, tests ~400, migración ~100) |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | Work Unit 1 → Work Unit 2 → Work Unit 3 |
| Delivery strategy | ask-on-risk |
| Chain strategy | `feature-branch-chain` (decidido por el owner) — PR1 apunta al tracker `feature/cms-pagina-proyectos`, PR2/PR3 apuntan a la rama hija anterior |

Decision needed before apply: No — resuelto por el owner
Chained PRs recommended: Yes
Chain strategy: feature-branch-chain
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|---|---|---|---|
| 1 | Registro canónico + seed (migración, config, schema) | PR 1 | Base: main. Fila `proyectos` huérfana pero inerte — nada la lee todavía. Revert: `down()` borra la página nueva, cero impacto en las 5 existentes. |
| 2 | Precedencia de logo + autoridad `catalog` + Fieldset Filament | PR 2 | Base: main (o PR1 si `stacked-to-main`). Cambios en `FrontendPageRenderer`/`SectionPayloadCompiler` son retro-compatibles (gateados por ausencia de `logo`/`project_list_variants`) — las 5 páginas publicadas no cambian. Revert seguro. |
| 3 | Cutover público + preview del owner | PR 3 | **COMPLETA** — rama `feature/cms-pagina-proyectos-3-cutover`, commits `a841de4`+`52c952e`+`8e92582`+`7693bb1`. |

## Phase 1: Registro canónico + seed (Work Unit 1) — COMPLETA

Implementada en `feature/cms-pagina-proyectos-1-fundacion`, commits `b521373` (schema) y `3a1c3da` (registro+seed). Progreso detallado: engram `sdd/cms-pagina-proyectos/apply-progress`.

- [x] 1.1 RED — test de seed: `proyectos` crea 1 `FrontendPage` + 3 `FrontendSection` (`hero`,`projects_list`,`final_cta`), idempotente en 2da corrida, las 5 páginas previas intactas (Req: Registro canónico). → `tests/Feature/Frontend/FrontendProyectosPageSeedTest.php`
- [x] 1.2 RED — test: sección `proyectos.hero_2` (clave no registrada) se rechaza por `isCanonicalSection` (Req: Registro canónico, escenario explícito). → mismo archivo
- [x] 1.3 GREEN — `config/frontend-sections.php`: `pages.proyectos` (`hero→hero`,`projects_list→featured_projects`,`final_cta→cta`), `section_labels.projects_list`, `hero_fallback.proyectos` (copiar hero+chip de `site/proyectos.blade.php:29-42`, con `logo_enabled:true`,`logo_size:xl`,`logo:{src,alt}`), `project_list_variants.proyectos = 'catalog'` (D1,D5).
- [x] 1.4 RED — test unitario `FrontendSectionSchema`: `SPECS['hero']['logo']` — `media_id` sin `alt` rechazado; `logo` ausente válido; clave desconocida dentro de `logo` rechazada (Req: Schema opcional + Accesibilidad). → `FrontendPageContractTest::test_hero_logo_is_optional_and_requires_alt_when_present`
- [x] 1.5 GREEN — `app/Services/Frontend/FrontendSectionSchema.php`: agregar `'logo' => ['object', ['media_id' => '?media', 'alt' => '?string']]` a `SPECS['hero']` (línea ~42), misma convención que `spotlight` (línea 68).
- [x] 1.6 RED — test de migración: payload sembrado de `projects_list` trae `eyebrow`+`title` (sin `background_color`, hereda gradiente literal); `final_cta` trae copy actual + `background_color: 'neutral-4'`; hero DRAFT sembrado desde `hero_fallback.proyectos` SIN clave `slides` (Req: Fallback §16.7; hallazgo #3; D2/D3). → mismo `FrontendProyectosPageSeedTest.php`
- [x] 1.7 GREEN — creada `database/migrations/2026_07_30_100000_seed_proyectos_page.php`: registra `pages.proyectos`, llama `app(SeedFrontendPages::class)->run()`, siembra payload de `projects_list`/`final_cta` (D1,D2), siembra hero DRAFT sin `slides` (D3); `down()` borra sólo la página `proyectos` y sus secciones. **Hallazgo no previsto**: `2026_07_28_100100_seed_hero_drafts_from_fallback.php` lee `hero_fallback` de forma dinámica, así que en un `migrate` desde cero también siembra el hero de `proyectos` (con `slides: []`) antes que esta migración — `seedHeroDraft()` se defiende leyendo el payload actual y corrigiendo sólo `slides` cuando ya no está en null (documentado en el docblock de la migración).
- [x] 1.8 Verify — `FrontendProyectosPageSeedTest` (7/7), `FrontendPageContractTest` (16/16), suite completa `tests/Feature/Frontend` (893/893) y `tests/Unit/Frontend` (28/28) en verde; las 5 páginas previas sin cambios de fila ni de conteo de secciones (24, verificado). Efecto colateral corregido en la misma unidad: `FrontendSectionEditorClosureTest` (conteo 24→27) y `FrontendHeroDraftSeedTest` (tolera `slides` ausente) — ambos rotos por el mismo registro dinámico de la 6ª página, no por un cambio de comportamiento en las 5 previas.

## Phase 2: Precedencia de logo + autoridad `catalog` + Filament (Work Unit 2) — COMPLETA

Implementada en `feature/cms-pagina-proyectos-2-servicios`, commits `8253487` (precedencia del logo) y `e14d581` (autoridad `catalog`). Progreso detallado: engram `sdd/cms-pagina-proyectos/apply-progress`.

- [x] 2.1 RED — extender `FrontendHeroContractMatrixTest`: 5 escenarios — (a) propio resuelto + `logo_enabled:true` → PROPIO; (b) propio resuelto + `logo_enabled:false` → **NINGUNO** (spec desactualizado en este punto, corregido por #1090); (c) sin propio + `logo_enabled:true` → MARCA; (d) sin propio + `logo_enabled:false` → ninguno; (e) `logo.media_id` sin promover (edge, render público) → cae a MARCA. Probado contra `nosotros` (regla genérica), no `proyectos` (fallback específico de §16.7).
- [x] 2.2 GREEN — `app/Services/Frontend/FrontendPageRenderer.php::presentHero()`: `data['logo']` = resuelto por `resolveTree()` si el payload trae la clave, o `fallbackHeroLogo($pageKey)` si no (nuevo método, simetría con `fallbackSlides()`); precedencia `logo_enabled` → propio resuelto > marca > ninguno. `fallbackHeroPayload()` ahora también `unset($configured['logo'])`.
- [x] 2.3 RED — test unitario `SectionPayloadCompiler` (en `FrontendHeroEditorTest`): upload de logo compila a `logo.media_id`+`alt` trimeado; sin upload/vacío → clave `logo` OMITIDA del payload (no `logo: null`).
- [x] 2.4 GREEN — `app/Filament/Forms/Sections/SectionPayloadCompiler.php::hero()`: agregado `heroLogo()` reusando `mediaId()`, mismo patrón que `spotlight()`; asigna `$payload['logo']` sólo si resuelve.
- [x] 2.5 RED — test de integración Filament vía `->html()` REAL (`FrontendHeroEditorTest::test_the_own_logo_fieldset_appears_and_sibling_fields_still_hydrate`, NO el walk de `fieldNames()` — bugfix #1081): el Fieldset de logo aparece en `heroFields()` Y `payload.slides`/`payload.text_align` siguen hidratando sin corromperse.
- [x] 2.6 GREEN — `app/Filament/Resources/FrontendPageResource/RelationManagers/SectionsRelationManager.php::heroFields()`: agregado `Fieldset::make('Logo propio (opcional)')->statePath('payload.logo')` **INCONDICIONAL, SIN `->visible()`** (D9); campos `media_id`+`alt` con `SectionImageFields::make(minWidth:200, minHeight:80)`, mismo patrón que `featuredProjectsFields()`.
- [x] 2.7 RED — extender `FrontendMediaPromotionTest::test_the_hero_own_logo_is_found_promoted_and_never_reported_as_orphaned`: `hero.logo.media_id` encontrado por `mediaIds()`, promovido al publicar, NO reportado huérfano. **Pasó en verde desde el primer momento, sin GREEN de producción** — confirma que `mediaIds()`/`mediaIdsOf()` ya recorren el payload buscando la clave literal `media_id` en cualquier profundidad (design D4), sin rama de código nueva.
- [x] 2.8 RED — nuevo `tests/Feature/Frontend/FrontendProjectsCatalogAuthorityTest.php` (4 tests): `project_list_variants.proyectos='catalog'` usa autoridad `Project::query()->latest()` SIN filtro `is_featured`, y sin `limit` es ilimitado; página sin entrada (`home`) sigue filtrando `is_featured` con tope 12 (regresión — hallazgo #2). Probado contra `FrontendPageRenderer::render()` directo (el cutover HTTP de `/proyectos` es Work Unit 3, todavía no ocurrió).
- [x] 2.9 GREEN — `app/Services/Frontend/FrontendPageRenderer.php::projects()`: ahora recibe `$pageKey`; branch por `config('frontend-sections.project_list_variants.{pageKey}')` — `catalog` = todos los `Project`, `latest()`, sin `is_featured`, sin `limit` ilimitado; cualquier otro caso = comportamiento previo (D6).
- [x] 2.10 Verify — `tests/Feature/Frontend` + `tests/Unit/Frontend` completos: **934/934 verde** (921 tras Fase 1 + 13 tests nuevos). Pint limpio. Snapshots de las 5 páginas previas byte-idénticos (ninguna tiene clave `logo` ni entrada en `project_list_variants`, así que ambos cambios son no-op para ellas).

## Phase 3: Cutover público + preview del owner (Work Unit 3) — COMPLETA

Implementada en `feature/cms-pagina-proyectos-3-cutover`, commits `a841de4` (preview) + `52c952e` (badge A-74 + rampa) + `8e92582` (variante `catalog`) + `7693bb1` (cutover del blade). Progreso detallado: engram `sdd/cms-pagina-proyectos/apply-progress`.

- [x] 3.1 **BLOQUEANTE** RED — `FrontendPreviewAccessTest`: `FrontendPreview::pages()` sin `'proyectos'`; `<title>` de `/admin/frontend/preview/proyectos` degradaba a «Vista previa» (hallazgo #1).
- [x] 3.2 GREEN — `FrontendPreview::pages()` y nuevo `FrontendPreviewController::titles()` DERIVADOS de `config('frontend-sections.pages')` en vez de listas hardcodeadas duplicadas — refactor DRY evaluado y aplicado (era acotado y seguro: mismos valores exactos que las listas manuales), cierra la causa raíz del hallazgo #1 en vez de sólo agregar la fila.
- [x] 3.3 RED — `FrontendHeroContractMatrixTest`: matriz de 4 combinaciones del badge A-74 (independiente de `logo_enabled`) + caso sin promover; rampa `xl` de la variante `standard` a `h-48 sm:h-56`.
- [x] 3.4 GREEN — `hero.blade.php`: badge portado tras el subtítulo, gateado por `logo.media_url` resuelto (no por `$showLogo`), `brightness-0 invert` verbatim; rampa `xl` no-featured reemplazada por `h-48 sm:h-56` (D5). Fix de alcance: la aserción pre-existente de `FrontendHeroRenderTest` que fijaba la rampa anterior (`h-20 sm:h-24 lg:h-28`) se actualizó — ningún hero publicado la usaba en producción.
- [x] 3.5 RED — `FrontendProjectsCatalogRenderTest` (6 tests): carrusel vs grilla, estado vacío, gradiente literal por defecto, no-featured en el DOM, regresión de `home`. Probado contra el PARTIAL directo (renderer→view), no HTTP — mismo motivo que 2.8 (el cutover del blade es la tarea 3.7/3.8, corre después en esta misma fase).
- [x] 3.6 GREEN — `FrontendPageRenderer.php::present()`: nuevo `data['variant']` (`'catalog'`/`'default'`) por `project_list_variants`; `featured_projects.blade.php`: rama `catalog` (carrusel de 6 desktop + swipe móvil, estado vacío, gradiente literal D7); nuevo partial `frontend/sections/partials/project-card.blade.php` reutilizado por el carrusel — la grilla default queda INLINE a propósito, sin tocar el DOM ya publicado de `home`.
- [x] 3.7 RED — `FrontendProyectosCutoverTest`: paridad sin publicar contra el blade ORIGINAL (hero, badge, rampa, header de grilla, gradientes literales de 3 y 4 paradas, catálogo completo con no-destacados, sin `<script>` huérfano del carrusel viejo).
- [x] 3.8 GREEN — `site/proyectos.blade.php`: `@if (! $cms['fallback'])` → `frontend.render`; `@else` → hero por el partial compartido + resto VERBATIM (grilla, gradientes, CTA, incluido el `<script>` inline del carrusel legacy que queda MUERTO). `ProjectController@index` sin cambios (D8).
- [x] 3.9 RED test-only sin código nuevo: `CtaResolver::resolve()` directo — `https://a74.example.com`→`external:true`; `javascript:`/`data:`/`//host`→`null`. Más un extremo a extremo publish→render confirmando que el botón externo llega al DOM con `target="_blank"`.
- [x] 3.10 RED test-only sin código nuevo: `background_color` de `featured_projects`/`cta` contra `brand_palette` cerrada — ya genérico, sin cambios de producción.
- [x] 3.11 Verify — `php artisan test tests/Unit/Frontend tests/Feature/Frontend`: **964/964 verde** (934 tras Fase 2 + 30 nuevos). `./vendor/bin/pint --dirty` limpio. Smoke manual preview→publish→público: cubierto por los E2E automatizados (`FrontendPreviewAccessTest`, `FrontendProyectosCutoverTest`); no se ejecutó manualmente en este entorno (sin sesión de navegador disponible).

## Phase 4: Cierre — COMPLETA (3/3)

Implementada en `feature/cms-pagina-proyectos-4-cierre` (hija de
`feature/cms-pagina-proyectos-3-cutover`, tracker
`feature/cms-pagina-proyectos-1-fundacion`, `feature-branch-chain`). Sin push
(mismo criterio que las fases anteriores). Progreso detallado: engram
`sdd/cms-pagina-proyectos/apply-progress`.

- [x] 4.1 Regresión total: `php artisan test` — suite COMPLETA del repo (no sólo Frontend), primera corrida entera de todo el cambio. **1487/1487 verde**, 6294 aserciones, sin fallos reales ni de colisión (corrida en aislamiento, sin procesos concurrentes detectados vía `pgrep`). Re-verificado además el subconjunto `tests/Unit/Frontend`+`tests/Feature/Frontend` en aislamiento: **965/965 verde** (incluye los tests nuevos de `8bcb569`, el fix del badge posterior al cierre de Fase 3).
- [x] 4.2 `./vendor/bin/pint --test` sobre el repo completo (no sólo `--dirty`): **limpio**, cero archivos con estilo incorrecto.
- [x] 4.3 Documentado el trade-off aceptado (`final_cta` con `background_color: neutral-4` en vez del gradiente navy literal, D7) y **corregido, no sólo documentado**, el hallazgo #5 del chip A-74 — commit `8bcb569` (posterior al cierre de Fase 3, ya en esta rama): el filtro `brightness-0 invert` pasó a aplicarse SÓLO al logo del fallback (`from_fallback: true`), nunca al que sube el owner. Ver `design.md` D10 y Open Questions. Dos desvíos documentados formalmente en `design.md`/`specs/hero-logo-propio/spec.md`: (a) la precedencia del logo se resolvió a favor del design contra el spec original (decisión #1090, spec corregido in-place); (b) el badge ya no blanquea el logo del owner (D10, riesgo #5 del design cerrado). Entrada sumada a `docs/epicas/epica-12-administrador-contenidos-frontend.md` §18.21 y nota de alcance en `docs/rfc/RFC-075-CONTENIDO-PAGINAS-INSTITUCIONALES-FRONTEND.md` (RFC-075 listaba cinco páginas administrables; ahora son seis).

## Cambio COMPLETO — 32/32 tareas, 4/4 fases

Todas las fases (`1` Fundación, `2` Precedencia+catalog+Filament, `3` Cutover+preview, `4` Cierre)
están implementadas, verificadas y documentadas. Recomendado: `sdd-verify` sobre el cambio completo
antes de decidir la integración final de la `feature-branch-chain` (`feature/cms-pagina-proyectos-1-fundacion`
como tracker) a `main`.

## Riesgo de tamaño

Excede el budget de 530 palabras de la skill sdd-tasks por el nivel de detalle test-por-test exigido explícitamente por el orquestador (TDD estricto + 5 hallazgos con tarea propia + forecast de PRs encadenados). Se prioriza trazabilidad y reversibilidad por unidad de trabajo sobre el budget — mismo criterio que usaron sdd-spec y sdd-design en este cambio.
