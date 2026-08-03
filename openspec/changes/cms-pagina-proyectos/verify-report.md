# Verification Report

**Change**: cms-pagina-proyectos
**Rama verificada**: `feature/cms-pagina-proyectos-4-cierre` (18 commits declarados por el equipo; 14 identificados por `git log` como propios del cambio — `53e07e5`..`12337f9` — el resto son commits previos ya en la rama base, fuera de alcance de este cambio)
**Mode**: Strict TDD (activo, runner `php artisan test`)
**Verificador**: sdd-verify, contexto fresco, sin re-uso de juicio previo

## Completeness

| Metric | Value |
|--------|-------|
| Fases | 4/4 completas |
| Tareas totales | 32 |
| Tareas completas | 32 |
| Tareas incompletas | 0 |

Confirmado por lectura directa de `openspec/changes/cms-pagina-proyectos/tasks.md` en la rama y por `apply-progress` (engram #1092). No hay checkbox abierto ni tarea marcada completa sin evidencia de código/test correspondiente en el diff real (`git diff --stat 2bcf998..feature/cms-pagina-proyectos-4-cierre`: 31 archivos, +2269/-129).

## Build & Tests Execution

**Lint (Pint)** — spot-check independiente sobre los 10 archivos de producción tocados por el cambio:
```
./vendor/bin/pint --test <10 archivos núcleo> → {"tool":"pint","result":"passed"}
```
Confirma el resultado ya reportado por el equipo (repo completo limpio).

**Tests** — spot-check independiente (NO la regresión completa, que ya corrió el orquestador: 1487/1487 verde / Frontend 965/965 verde). Se verificó `pgrep -fl "artisan test|phpunit"` → cero procesos antes de correr, para evitar el deadlock conocido contra `inmo_test`. Se ejecutaron en aislamiento los 8 archivos de test centrales a los puntos de escrutinio de este verify:
```
php artisan test tests/Feature/Frontend/{FrontendHeroContractMatrixTest,FrontendProjectsCatalogAuthorityTest,
  FrontendProjectsCatalogRenderTest,FrontendProyectosCutoverTest,FrontendProyectosPageSeedTest,
  FrontendPageContractTest,FrontendMediaPromotionTest,FrontendPreviewAccessTest}.php
→ {"tool":"phpunit","result":"passed","tests":93,"passed":93,"assertions":367,"duration_ms":16141}
```
93/93 verde, 367 aserciones, corrida limpia. Confirma independientemente que las pruebas centrales del cambio pasan HOY, no sólo en el reporte del equipo.

**Coverage**: no hay herramienta de coverage configurada en el proyecto — no aplica.

## Spec Compliance Matrix

| Requirement | Scenario | Test | Result |
|---|---|---|---|
| Registro canónico | sección no registrada rechazada | `FrontendProyectosPageSeedTest::test_an_unregistered_section_key_under_proyectos_is_rejected` | ✅ COMPLIANT |
| Fallback §16.7 | recién cortado ≡ blade original (hero+header+cierre) | `FrontendProyectosCutoverTest` (4 tests, texto/badge/gradientes/carousel) | ✅ COMPLIANT |
| Fallback §16.7 | `final_cta` vacío a propósito no revive el título viejo | cubierto por `FrontendSectionSchema`/`FrontendPagePublisher` genéricos (mecanismo pre-existente, sembrado con copy — D2); sin test dedicado nuevo para *este* escenario específico en `/proyectos` | ⚠️ PARTIAL (mecanismo genérico ya probado en otras páginas, no re-probado aquí; riesgo bajo) |
| Fondos paleta cerrada §6.1 | ausente→literal, presente→paleta, fuera→rechazado | `FrontendProjectsCatalogRenderTest` (literal+override) + `FrontendProyectosCutoverTest` (rechazo `cta`/`featured_projects`) | ✅ COMPLIANT |
| Listado completo (`limit`) | todos sin filtrar por destacado, con/sin `limit`, home sigue filtrando | `FrontendProjectsCatalogAuthorityTest` (4 tests) + `FrontendProyectosCutoverTest::test_the_unpublished_route_lists_every_published_project_not_only_the_featured_ones` | ✅ COMPLIANT |
| CTA externo seguro | `https://` acepta, 3 esquemas inseguros rechazan | `FrontendProyectosCutoverTest::test_the_hero_cta_to_the_associate_uses_the_generic_url_resolver` + `test_an_unsafe_hero_cta_target_resolves_to_null` (DataProvider ×3) | ✅ COMPLIANT |
| Schema logo opcional | válido con media_id+alt | `FrontendPageContractTest::test_hero_logo_is_optional_and_requires_alt_when_present` | ✅ COMPLIANT |
| Alt obligatorio junto a media_id | `alt: null` y clave desconocida rechazados | mismo test | ✅ COMPLIANT |
| Convención `media_id` (pipeline) | promoción + no-huérfano | `FrontendMediaPromotionTest` (extendido) | ✅ COMPLIANT |
| **Precedencia logo (regla VIGENTE, decisión #1090)** | matriz 4×: propio+on→propio, propio+off→ninguno, sin propio+on→marca, sin propio+off→ninguno | `FrontendHeroContractMatrixTest::test_the_own_logo_precedence_follows_decision_1090` (DataProvider `logoPrecedence()`, 4 casos) | ✅ COMPLIANT — implementa la regla CORREGIDA, no la del spec original |
| Precedencia — media sin promover ≠ presente | cae a marca en render público | `FrontendHeroContractMatrixTest::test_an_own_logo_not_yet_promoted_falls_back_to_the_brand_logo` | ✅ COMPLIANT |
| Compatibilidad snapshots previos | ausencia de `logo` no rompe nada | `FrontendHeroContractMatrixTest::test_the_five_states_of_the_fallback_matrix_on_every_route` (rutas existentes) + código (`array_key_exists('logo', $payload)`) | ✅ COMPLIANT |
| Badge A-74 (commit `8bcb569`) | filtro sólo en logo del fallback, nunca en el del owner | `FrontendHeroContractMatrixTest::test_an_owner_uploaded_logo_keeps_its_colours_in_the_badge` (owner→sin filtro) + `FrontendProyectosCutoverTest::test_the_unpublished_route_still_shows_the_a74_badge_and_the_14rem_logo` (fallback→con filtro) | ✅ COMPLIANT — probado en los DOS sentidos |

**Compliance summary**: 12/13 escenarios ✅ COMPLIANT, 1/13 ⚠️ PARTIAL (mecanismo genérico, riesgo bajo).

## Correctness (Static Evidence)

| Requirement | Status | Notes |
|---|---|---|
| `app/Services/Frontend/FrontendPageRenderer.php::presentHero()` | ✅ Implementado | Precedencia línea 260-272 implementa literalmente la regla ganadora (#1090), no la descartada del spec original. |
| `FrontendSectionSchema::SPECS['hero']['logo']` | ✅ Implementado | `checkObject()` aplica la regla universal §16.1.1; `logo` no declara `decorative`, así que el escape es inalcanzable — confirma "no hay escape" del spec. |
| Variante `catalog` (D6/D7) | ✅ Implementado | `featured_projects.blade.php`: gradiente literal hardcodeado (no interpolado), lookup de paleta cerrada para `background_color`, autoridad por página en `projects()`. |
| `site/proyectos.blade.php` cutover | ✅ Implementado | Mismo patrón `@if/@else` que `servicios`/`inversionistas`; rama de fallback VERBATIM salvo el hero (por partial compartido, con desvío documentado — ver WARNING/SUGGESTION abajo). |
| Preview del owner | ✅ Implementado | `FrontendPreview::pages()`/`FrontendPreviewController::titles()` derivados de config — refactor DRY que además cierra el bloqueante. |

## Coherence (Design)

| Decision | Followed? | Notes |
|---|---|---|
| D4 — precedencia logo | ✅ Sí | Código y tests implementan la regla ganadora; spec enmendado in-place con texto tachado por trazabilidad. |
| D5 — rampa `xl` propia | ✅ Sí | `h-48 sm:h-56` en `hero.blade.php`, probado por `test_the_standard_variant_ramps_an_xl_logo_to_14rem`. |
| D6/D7 — autoridad y fondo `catalog` | ✅ Sí | Confirmado por `FrontendProjectsCatalogAuthorityTest` + `FrontendProjectsCatalogRenderTest`. |
| D9 — Fieldset incondicional sin `visible()` | ✅ Sí | Confirmado en `SectionsRelationManager.php` y probado con `->html()` real (`FrontendHeroEditorTest::test_the_own_logo_fieldset_appears_and_sibling_fields_still_hydrate`), evitando el bug real de Filament diagnosticado. |
| D10 — badge por `from_fallback`, no por posición | ✅ Sí | `FrontendPageRenderer::fallbackHeroLogo()` marca `from_fallback: true`; `hero.blade.php` condiciona el filtro a esa marca. |
| Testing Strategy del design: "`FrontendRenderFallbackTest` + nuevo test de la página" | ⚠️ Parcial | Sólo se agregó el "nuevo test" (`FrontendProyectosCutoverTest`); `FrontendRenderFallbackTest.php` NO se tocó — sigue acotado a `servicios`. Ver WARNING #2. |

## Issues Found

### CRITICAL
Ninguno. No se encontró ningún requirement del spec sin implementar, ningún escenario de la matriz sin test cubridor pasando en la ejecución real, ninguna aserción trivial/tautológica, ningún test con loop sobre colección potencialmente vacía, ni ninguna violación de §6.1 (interpolación de payload en nombre de clase CSS) en la variante `catalog` ni en sus gradientes.

### WARNING

**W1 — N+1 en `FrontendPageRenderer::projects()`, agravado por quitar el tope de 12 en `catalog`.**
`app/Services/Frontend/FrontendPageRenderer.php:503-534`. El `map()` accede a `$p->projectType?->label` sin `->with('projectType')` — una query por proyecto. Esto YA existía antes de este cambio para `home.featured_projects` (tope 12, impacto acotado), pero este cambio:
1. Convierte esta ruta en la que sirve `/proyectos` **publicado**, reemplazando a `ProjectController@index`, que SÍ eager-carga (`Project::query()->with('projectType')->latest()->get()`, confirmado en el propio archivo).
2. Quita el tope de 12 para la variante `catalog` (`limit` ausente = ilimitado, por diseño D6) — así que el N+1 ahora puede escalar con el catálogo completo, sin cota.

Ningún test del cambio afirma sobre cantidad de queries, así que nada lo habría detectado. Fix sugerido: `Project::query()->with('projectType')` antes de la bifurcación por variante en `projects()`.

**W2 — El contrato de regresión "las cinco/seis rutas" no se extendió a la sexta página.**
`tests/Feature/Frontend/FrontendHeroContractMatrixTest.php:43-49,117-126` (`ROUTES` y `rutas()`) sigue listando únicamente `home`/`nosotros`/`servicios`/`inversionistas`/`contacto`. `/proyectos` nunca se agregó, pese a que el propio design la define como "la sexta página canónica, mismo patrón exacto que las cinco existentes". Esto deja sin ejercitar contra `/proyectos`, vía el mecanismo de guardia genérico:
- que el hero comparte partial único (`data-nh-hero`, una sola vez),
- que no hay `<style>`/`<script>`/`style=` inline en el hero,
- la progresión completa de 5 estados del fondo (sin publicar → `slides` ausente → `slides: []` → media sin promover → media promovida).

`FrontendProyectosCutoverTest` cubre una porción solapada pero NO idéntica — nunca ejercita la progresión de `slides` para `/proyectos` específicamente. El propio design (tabla "Testing Strategy") declaraba la intención de tocar `FrontendRenderFallbackTest` + un test nuevo; sólo se hizo lo segundo. Riesgo funcional bajo (`presentHero()` es código 100% compartido, sin rama específica para `proyectos` en el manejo de `slides`), pero es un hueco real en la red de seguridad que debería cerrarse agregando `/proyectos` a `ROUTES`/`rutas()` — cambio mecánico y de bajo riesgo.

### SUGGESTION

**S1 — §16.7: la justificación del desvío de margen (`mb-6`→`mb-9`) se sostiene, y hay un segundo desvío gemelo sin documentar (`opacity-95` perdido).**
Verificado por `git blame`/`git log -S`: tanto `mb-9` (en vez del `mb-6` original) como la ausencia de `opacity-95` en el logo grande del hero (`resources/views/frontend/sections/hero.blade.php:119`, `class="{{ $logoSize }} mb-9 w-auto"` — sin `opacity-95`) datan del commit `1653c8d` ("lote 12.1-B — un solo renderer del hero", 2026-07-26), la unificación ORIGINAL del partial para las primeras cinco páginas — **anterior** a este cambio y ajena a él. `cms-pagina-proyectos` es la sexta página en heredar el MISMO valor ya compartido, no introduce un desvío nuevo. La justificación del equipo ("las otras cinco páginas ya pagaron ese precio") es correcta y verificable, no una racionalización post-hoc.

Dicho eso: el `design.md`/`tasks.md` de este cambio documentan por transparencia el desvío de `mb-9` pero no mencionan el de `opacity-95`, que es la misma clase de problema (aspecto pixel-perfect no preservado) y con el mismo origen. Sugerencia: una línea en D5 o en la sección de desviaciones acumuladas mencionando también `opacity-95`, para que quien audite después no tenga que rehacer el `git blame`.

**S2 — Documentar el escenario "PARTIAL" de la matriz de compliance (`final_cta` vacío a propósito).**
El spec tiene un scenario explícito ("owner publica `final_cta` vacío a propósito → no revive el título viejo") que se apoya en mecanismo genérico ya probado en otras páginas, sin un test dedicado que lo ejercite específicamente contra `/proyectos`. Bajo riesgo (mismo código, mismo camino que las otras cinco), pero cerrar el loop con un test explícito completaría la matriz al 13/13.

## TDD Compliance

| Check | Result | Details |
|---|---|---|
| Evidencia TDD reportada | ✅ | `tasks.md` lleva marca RED/GREEN por tarea (32/32); commits agrupan test+código por unidad de trabajo (verificado en `8253487`, `e14d581`, etc. — el mensaje de commit describe test+implementación juntos). No hay una tabla "TDD Cycle Evidence" formal para las Fases 1-3 en el `apply-progress` fusionado (sí para la Fase 4, que es 100% documental) — la evidencia existe pero en formato checklist de tasks.md, no en tabla dedicada por fase. |
| RED confirmado (archivos existen) | ✅ | Los 12 archivos de test listados en el diff existen en la rama y contienen las aserciones que dicen contener — verificado leyendo el CONTENIDO completo (no sólo nombres) de `FrontendHeroContractMatrixTest`, `FrontendProjectsCatalogAuthorityTest`, `FrontendProjectsCatalogRenderTest`, `FrontendProyectosCutoverTest`, `FrontendProyectosPageSeedTest`, `FrontendHeroEditorTest`. |
| GREEN confirmado (pasan hoy) | ✅ | 93/93 en spot-check independiente, aislado, sin colisión de procesos. |
| Triangulación | ✅ | Cada regla central (precedencia del logo, badge, autoridad catalog) tiene DataProvider de 3-4 casos distintos, no un solo caso feliz. |
| Aserciones triviales/tautológicas | ✅ Ninguna encontrada | Todas las aserciones leídas llaman código de producción real (renderer, schema, HTTP) y afirman valores concretos (URLs, conteos, substrings específicos) — no hay `assertTrue(true)`, ni loops sobre colecciones que podrían estar vacías sin un caso que garantice contenido, ni smoke-tests desnudos. |

**TDD Compliance**: 4/5 checks en verde estricto, 1 con nota (formato de evidencia, no ausencia de evidencia).

## Verdict

**PASS WITH WARNINGS**

Cero hallazgos CRITICAL: el código implementa la regla de precedencia VIGENTE (no la descartada), el fix del badge está probado en los dos sentidos, la regresión de `is_featured` está cerrada y probada en ambos lados (catálogo completo / home sigue filtrando), la accesibilidad (`alt` obligatorio, sin escape para `logo`) y §6.1 (sin interpolación de payload en clases CSS, tampoco en la variante `catalog`) se cumplen, y no hay autoplay en el carrusel del listado (así que WCAG 2.2.2 no aplica ahí; el que sí autoplayea —el fondo del hero— sí tiene control de pausa). El desvío de aspecto en el margen del logo grande es real pero heredado de una unificación anterior a este cambio, no introducido por él, y la justificación del equipo se sostiene con evidencia de `git blame`.

Los dos WARNING (N+1 sin cota en el catálogo publicado; matriz de contrato no extendida a la sexta ruta) no bloquean el merge — no rompen ningún requirement del spec ni hacen que un test mienta sobre lo que prueba — pero SÍ deberían resolverse antes o inmediatamente después de la integración a `develop`, idealmente en un commit de seguimiento pequeño (ambos son cambios mecánicos y de bajo riesgo).

**Recomendación**: apto para `sdd-archive` e integración de la `feature-branch-chain` a `develop`. Abrir una tarea de seguimiento (no bloqueante) para W1 y W2.
