# Tasks — Épica 11: Ayuda / Manual del CMS

Strict TDD active (`composer test`, PHPUnit 12 against PostgreSQL+PostGIS `inmo_test`). Each behavioral
unit gets a "write failing test" task before its "implement" task. Work units follow work-unit-commits
conventions: each unit is a coherent, revertible slice with a clear start/finish boundary.

Legend: `[P]` = can run in parallel with sibling tasks in the same unit (no file overlap). Unmarked tasks
are sequential (either they depend on a prior task's output or touch a shared file).

---

## Work Unit 0 — Theme prerequisite (blocks nothing else, but blocks visual verification)

- [x] **0.1** Register `@tailwindcss/typography` in `resources/css/filament/admin/tailwind.config.js`
      (import `typography` from `@tailwindcss/typography`, add to `plugins: [typography]`) per design.md
      "Theme — enable `prose` for the panel". Confirms the already-present devDependency is wired in.
      *Spec link: Markdown-backed section content (rendering requires `prose` styling to be usable).*
- [x] **0.2** Rebuild the Filament theme (`npm run build:filament` or equivalent build script) and spot
      check the build output includes typography-generated classes (no runtime test needed — this is a
      build-config change, not app behavior).

Sequential (single shared file); no test required (build config, not testable behavior).

---

## Work Unit 1 — Ayuda page skeleton + access gate (TDD)

- [x] **1.1** Write failing test `guest_cannot_access_ayuda` in new
      `tests/Feature/Filament/AyudaPageTest.php`: unauthenticated `GET` to `Ayuda::getUrl()` redirects to
      login. *Spec: "Ayuda page registration and access" — unauthenticated scenario.*
- [x] **1.2** Write failing test `any_panel_user_can_open_ayuda` in the same file: `actingAs` a user with
      each of owner/admin/agente/arquitectura/proyectos (use `userWithRole()` helper + `PermissionSeeder`
      per existing suite convention, e.g. `tests/Feature/Filament/ZoneResourceTest.php`) → `assertOk()`.
      *Spec: "Ayuda page registration and access" — any-role scenario.*
- [x] **1.3** Implement `app/Filament/Pages/Ayuda.php` minimal skeleton: extends `Filament\Pages\Page`,
      `$navigationLabel = 'Ayuda'`, `$title = 'Ayuda'`, `$slug = 'ayuda'`, no `$navigationGroup`,
      `$navigationSort = 99`, `$navigationIcon = 'heroicon-o-question-mark-circle'`,
      `$view = 'filament.pages.ayuda'`, `public static function canAccess(): bool { return auth()->check(); }`.
      Create a placeholder `resources/views/filament/pages/ayuda.blade.php` (`<x-filament-panels::page></x-filament-panels::page>`)
      so the route resolves. Run 1.1–1.2 green. *Spec: "Ayuda page registration and access".*
      Depends on: 1.1, 1.2 (tests must exist and fail first).

---

## Work Unit 2 — Section registry + role-aware visibility (TDD)

Depends on: Work Unit 1 (page class must exist).

- [x] **2.1** Write failing test `owner_sees_all_sections`: owner (`userWithRole('owner')`) opens Ayuda,
      `assertSee` all 13 resource-section labels (Inmuebles, Contratos, Leads, Propietarios, Zonas,
      Proyectos, Lonas asignadas, Solicitudes de lonas, Evidencias, Características, Tipos de servicio,
      Tipos de proyecto, Usuarios). *Spec: "Role-aware section index" — admin/owner scenario.*
- [x] **2.2** Write failing test `agente_sees_operational_but_not_config_or_users`: agente
      (`userWithRole('agente')`) `assertSee`s Inmuebles/Leads/Mi Zona/Mis Lonas, `assertDontSee`s
      Tipos de proyecto and Usuarios. *Spec: "Role-aware section index" — agente scenario.*
- [x] **2.3** Write failing test `arquitectura_role_section_visibility`: assert exactly the sections
      `arquitectura` role's real gates allow (derive expected set from each Resource's actual
      `canViewAny()` logic, not a guess — read the Resource/Policy source before asserting).
      *Spec: "Role-aware section index".*
- [x] **2.4** Write failing test `proyectos_role_section_visibility`: same pattern for the `proyectos`
      role. *Spec: "Role-aware section index".*
- [x] **2.5** Write failing test `index_groups_match_sidebar`: owner sees group headings General,
      Operación, Lonas, Configuración, Seguridad, Mi trabajo. *Spec: "v1 section coverage".*
- [x] **2.6** Implement `sectionRegistry()` (private static method on `Ayuda`) with all 17 entries exactly
      as specified in design.md's "Section registry — full mapping" table: `key`, `label`, `group`,
      `file`, and `gate` closures delegating uniformly to `PropertyResource::canViewAny()`,
      `LeadResource::canViewAny()`, `ZoneResource::canViewAny()`, `PropertyOwnerResource::canViewAny()`,
      `ProjectResource::canViewAny()`, `ContratoIntermediacionResource::canViewAny()`,
      `LonaBatchResource::canViewAny()`, `LonaRequestResource::canViewAny()`,
      `LonaEvidenceResource::canViewAny()`, `FeatureResource::canViewAny()`,
      `ProjectTypeResource::canViewAny()`, `ServiceTypeResource::canViewAny()`,
      `UserResource::canViewAny()`, `AgentDashboard::canAccess()`, `AgentLonas::canAccess()`, plus the two
      always-`true` gates (introduccion, panel). **Do not replicate role/permission lists — every gate
      must call the owning class's real method (ADR-1).**
      *Spec: "Role-aware section index" — gate delegation requirement (locked, no duplication).*
- [x] **2.7** Implement `visibleSections(): array` — iterate the registry, keep only entries whose gate
      returns `true`, group by `group` key. *Spec: "Role-aware section index".*
- [x] **2.8** Wire `visibleSections()` into `ayuda.blade.php`'s index view (grouped index rendering per
      design.md's "Índice agrupado" block) and run 2.1–2.5 green.
      *Spec: "Role-aware section index", "v1 section coverage".*

Sequential within this unit (shared `Ayuda.php` file and shared view file). Tasks 2.1–2.5 can be written
`[P]` relative to each other (all in the same new test file, but non-overlapping test methods) before 2.6
implements them all at once.

---

## Work Unit 3 — Markdown rendering + `?seccion` routing + gate re-check (TDD)

Depends on: Work Unit 2 (registry + visibility must exist).

- [x] **3.1** Write failing test `requesting_permitted_seccion_renders_markdown`: owner requests
      `?seccion=inmuebles` (with `resources/help/inmuebles.md` containing a known marker heading),
      `assertSee` that heading text rendered as HTML. *Spec: "Markdown-backed section content" — render
      scenario.*
- [x] **3.2** Write failing test `requesting_forbidden_seccion_shows_index_not_content`: agente requests
      `?seccion=usuarios` → `assertDontSee` the Usuarios article body, page still shows the index (soft
      "no disponible" state). *Spec: "Role-aware section index" — implicit closed-by-default; ADR-3.*
- [x] **3.3** Write failing test `missing_markdown_file_renders_placeholder`: invoke `renderMarkdown()`
      directly (via `ReflectionMethod`, `setAccessible(true)`) with a filename guaranteed to never exist
      in `resources/help/` → placeholder text, no 500. Does NOT depend on a real section (e.g. `zonas`)
      lacking its `.md`, since Work Unit 4 later authors all 17 files and that would break the test.
      *Spec: "Markdown-backed section content" — missing file scenario. Correction from design audit M-2,
      see `docs/audits/epica-11-auditoria-diseno.md`.*
- [x] **3.4** Add `#[\Livewire\Attributes\Url] public ?string $seccion = null;` to `Ayuda.php`.
      *Spec: "Markdown-backed section content".*
- [x] **3.5** Implement `renderMarkdown(string $file): string` using
      `Str::markdown(File::get(resource_path("help/{$file}.md")), ['html_input' => 'escape', 'allow_unsafe_links' => false])`
      with an `File::exists()` guard returning the friendly placeholder
      (`'<p>Este contenido todavía no está disponible.</p>'`) when absent. *Spec: "Markdown-backed section
      content" — missing file scenario; graceful failure (no unhandled error).*
- [x] **3.6** Implement `currentSection(): ?array` — resolves `$seccion` ONLY against
      `visibleSections()` (never the full registry), returns `null` (→ index + soft notice) when
      `$seccion` is set but not found in the visible set. *Spec: "Role-aware section index" gate,
      ADR-3 fail-fast/closed-by-default.*
- [x] **3.7** Wire `currentSection()` into `ayuda.blade.php`: article view with "back to index" link when
      set, soft "sección no disponible" notice + index when `$seccion` is non-null but `currentSection()`
      is null, plain index otherwise (per design.md's full blade structure). Run 3.1–3.3 green.
      *Spec: "Markdown-backed section content".*

Sequential (shared `Ayuda.php` and shared view file; each step builds on the prior).

---

## Work Unit 4 — Content authoring (17 Markdown files, Spanish MX)

Depends on: Work Unit 3 only for the marker-heading pattern used by test 3.1 (that one file,
`inmuebles.md`, needs its first heading finalized before/with 3.1 — all others are independent of code).
All 17 files are `[P]` with each other (no shared file, no code dependency once directory exists).

Each file follows the design.md authoring template: `# <title>` / `## ¿Para qué sirve?` /
`## Cómo se usa` (numbered steps) / `## Campos importantes` / `## Preguntas frecuentes`. Content in
Spanish (Mexico), longer-form than SectionHeader copy (steps, fields, FAQ — not a copy-paste of the
dashboard one-liners).

- [x] **4.1** `resources/help/introduccion.md` — primeros pasos / orientación general al panel.
- [x] **4.2** `resources/help/panel.md` — qué es el dashboard, qué widgets muestra.
- [x] **4.3** `resources/help/inmuebles.md` [P] — alta/edición de inmuebles, campos clave, estados.
      *(Marker heading must match what test 3.1 asserts — coordinate with 3.1.)* NOTE: a MINIMAL
      placeholder (`# Manual de Inmuebles` + one line) already exists from slice 1 to satisfy test 3.1;
      slice 2 replaces it with full content while preserving the `# Manual de Inmuebles` heading (or
      updates the test assertion if the heading changes).
- [x] **4.4** `resources/help/leads.md` [P] — ciclo de vida de un lead, asignación, notas.
- [x] **4.5** `resources/help/zonas.md` [P] — cómo se crean/editan zonas, relación con inmuebles.
- [x] **4.6** `resources/help/propietarios.md` [P] — alta de propietarios, vínculo con inmuebles/contratos.
- [x] **4.7** `resources/help/proyectos.md` [P] — gestión de proyectos inmobiliarios.
- [x] **4.8** `resources/help/contratos.md` [P] — contratos de intermediación, eventos relacionados.
- [x] **4.9** `resources/help/lonas-asignadas.md` [P] — lotes de lonas asignados, flujo de asignación.
- [x] **4.10** `resources/help/solicitudes-lonas.md` [P] — solicitud de nuevas lonas, aprobación.
- [x] **4.11** `resources/help/evidencias.md` [P] — carga y revisión de evidencias de instalación de lonas.
- [x] **4.12** `resources/help/caracteristicas.md` [P] — catálogo de características de inmuebles.
- [x] **4.13** `resources/help/tipos-proyecto.md` [P] — catálogo de tipos de proyecto.
- [x] **4.14** `resources/help/tipos-servicio.md` [P] — catálogo de tipos de servicio.
- [x] **4.15** `resources/help/usuarios.md` [P] — gestión de usuarios y roles/permisos.
- [x] **4.16** `resources/help/mi-zona.md` [P] — vista de zona para el rol agente.
- [x] **4.17** `resources/help/mis-lonas.md` [P] — vista de lonas propias para el rol agente.

*Spec link (all 4.x): "Markdown-backed section content" + "v1 section coverage" — every registry entry
must have a non-empty corresponding `.md` file at delivery time.*

---

## Work Unit 5 — SectionHeader dashboard copy review (SHOULD, refinement only)

Depends on: Work Unit 4 (manual content must exist to compare tone/terms against).

- [x] **5.1** Review `app/Filament/Widgets/InmueblesSectionHeader.php`,
      `LeadsSectionHeader.php`, `ZonasSectionHeader.php`, `PropietariosSectionHeader.php`,
      `AgentesSectionHeader.php` (and the shared `SectionHeaderWidget.php` base if it holds copy) against
      the corresponding `resources/help/*.md` files for tone/terminology consistency. Adjust wording only
      where inconsistent — no new props, slots, or structural changes to any widget.
      *Spec: "Dashboard SectionHeader copy consistency review" (SHOULD).*
      **Result**: all five descriptions are already short, terse noun-phrases ("Inventario y su estado
      actual.", "Captación y evolución de prospectos.", "Cobertura comercial.", "Cartera de propietarios
      y comisiones pactadas.", "Tu conversión de leads."/"Rendimiento del equipo.") — consistent tone and
      length with each other; no rewrite needed. `./vendor/bin/pint --test` clean on all five files (no
      changes made).

No test required — spec explicitly scopes this to copy refinement, not new testable structure (locked:
"no new slots, props, or structural changes").

---

## Work Unit 6 — Full verification (DoD)

Depends on: all prior units.

- [x] **6.1** Run `./vendor/bin/pint` (fix PHP style on all new/modified files). `--test` clean on all
      SectionHeader widgets touched during Work Unit 5 review (no PHP changes needed).
- [x] **6.2** Run full suite (`DB_DATABASE=inmo_test php artisan test`, `composer test` hits a 300s
      timeout in this environment) — `AyudaPageTest`: 15/15 passed, 74 assertions. Full suite: 492/492
      passed, 1823 assertions — matches the slice-1 baseline exactly (no regression from content
      authoring).
- [ ] **6.3** Manual smoke check: log in as each role (owner, admin, agente, arquitectura, proyectos),
      confirm "Ayuda" appears ungrouped at the TOP of the sidebar (Filament's real position for ungrouped
      pages, before Operación/Lonas/Configuración/Seguridad — not at the bottom), index shows the
      expected sections, and at least one Markdown section renders with visible `prose` typography
      (confirms Work Unit 0's theme change took effect). Deferred to `sdd-verify`/human review — not run
      in this automated apply pass (requires a browser session).

---

## Design-audit corrections (post slice-1 apply)

Applied on `feat/ayuda-cms-code`, tracked in `docs/audits/epica-11-auditoria-diseno.md` checklist
(section 12):

- [x] M-1 — Aligned spec/design/tasks/proposal to the real Filament nav position (ungrouped pages render
      at the TOP of the sidebar, not the bottom); honest comment in `Ayuda.php`.
- [x] M-2 — Reworked `test_missing_markdown_file_renders_placeholder` to call `renderMarkdown()` via
      reflection with a guaranteed-absent filename, decoupled from any real section's `.md` state.
- [x] Mn-1 — Normalized section count to "17 entradas: 2 generales + 13 resource-backed + 2 páginas de
      agente" across proposal.md, spec.md, design.md, tasks.md.
- [x] Mn-2 — Added `assertSee('Esa sección no está disponible para tu cuenta.')` to the forbidden-`?seccion=`
      test.

Slice 2 (content authoring) status — all closed except the manual smoke check:
- [x] Crear los 17 `resources/help/*.md` no vacíos (Work Unit 4).
- [x] SectionHeader dashboard copy review (Work Unit 5).
- [x] Full automated verification across both slices (Work Unit 6.1–6.2).
- [ ] Manual smoke check across roles (Work Unit 6.3) — deferred to `sdd-verify`/human review.

## Task-to-requirement traceability summary

| Spec requirement | Tasks |
|---|---|
| Ayuda page registration and access | 1.1–1.3 |
| Role-aware section index | 2.1–2.8, 3.2, 3.6 |
| Markdown-backed section content | 3.1, 3.3–3.7, 4.1–4.17 |
| v1 section coverage | 2.6, 4.1–4.17 |
| Dashboard SectionHeader copy consistency review | 5.1 |
| Explicit non-goals | Verified by absence — no task adds search/DB/editable-UI/List-page copy; confirm during 6.2/6.3 |

## Suggested commit/work-unit boundaries (work-unit-commits)

1. `feat(filament): register typography plugin for panel theme` — Work Unit 0
2. `feat(filament): add Ayuda page skeleton with access gate + tests` — Work Unit 1
3. `feat(filament): role-aware section registry and index for Ayuda` — Work Unit 2
4. `feat(filament): markdown rendering and seccion routing for Ayuda` — Work Unit 3
5. `docs(help): author Ayuda manual content for all sections` — Work Unit 4 (candidate for its own PR —
   see Review Workload Forecast)
6. `chore(filament): review SectionHeader copy for manual consistency` — Work Unit 5
7. Final verification folded into whichever PR closes the change — Work Unit 6
