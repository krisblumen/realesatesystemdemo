# Help Manual Specification

## Purpose

In-panel, role-aware user manual for the Filament admin panel, backed by
git-versioned Markdown files. Lets any authenticated panel user self-resolve
"how do I X" without depending on another person, without search, DB storage,
or an admin-editable UI.

## Requirements

### Requirement: Ayuda page registration and access
The system MUST register a Filament Page at `app/Filament/Pages/Ayuda.php`
with `navigationLabel = 'Ayuda'`, `slug = 'ayuda'`, no navigation group. Ayuda
is a blank-label (ungrouped) page; Filament's `NavigationManager` hardcodes
the ungrouped block to render at the TOP of the sidebar (sort `-1`), before
any named group (Operación, Lonas, Configuración, Seguridad) — a page's
`navigationSort` cannot move it below those groups. This is the accepted,
real position; the manual does NOT force Ayuda to the bottom.
`canAccess()` MUST return `true` for any authenticated panel user regardless
of role (owner, admin, agente, arquitectura, proyectos).

#### Scenario: Any authenticated role can access Ayuda
- GIVEN a logged-in panel user with role agente
- WHEN they open the panel navigation
- THEN "Ayuda" is visible without a group heading, at the top of the sidebar
  (Filament's real rendering position for ungrouped pages)
- AND navigating to `/ayuda` (or panel-prefixed slug) succeeds

#### Scenario: Unauthenticated user is blocked
- GIVEN no authenticated session
- WHEN a request is made to the Ayuda page route
- THEN the panel's standard auth redirect applies (same as any other page)

### Requirement: Role-aware section index
The system MUST build the manual's section index by filtering a declarative
section map (`section-key => [title, markdown file, access gate]`) against the
current user. Each entry's access gate MUST delegate to the real access check
already used by that section (e.g. `PropertyResource::canViewAny()`,
`LeadResource::canViewAny()`) — it MUST NOT duplicate or hardcode role lists.
Sections whose gate returns `false` for the current user MUST be omitted from
the index entirely.

#### Scenario: Agente sees only sections they can access
- GIVEN a user with role agente who passes `PropertyResource::canViewAny()`
  and `LeadResource::canViewAny()` but fails the Usuarios section gate
- WHEN they open Ayuda
- THEN the index shows Inmuebles and Leads help entries
- AND the index does NOT show a Usuarios help entry

#### Scenario: Admin/owner sees all sections
- GIVEN a user with role admin or owner who passes every section's access
  gate
- WHEN they open Ayuda
- THEN the index shows all documented sections: Inmuebles, Contratos, Leads,
  Propietarios, Zonas, Proyectos, Lonas asignadas, Solicitudes de lonas,
  Evidencias, Features/Características, Tipos de servicio, Tipos de proyecto,
  Usuarios

#### Scenario: Access gate change is picked up without index changes
- GIVEN a Resource's `canViewAny()`/Policy is later modified to grant or
  revoke a role
- WHEN the same user opens Ayuda afterward
- THEN the index reflects the updated access automatically, with no change
  required to the Ayuda section map's gate closures

### Requirement: Markdown-backed section content
Each visible section entry MUST render its content from a Markdown file under
`resources/help/*.md`, converted via `Illuminate\Support\Str::markdown()`. No
new package dependency is introduced. Content MUST be independent, longer-form
copy (steps, fields, examples) — not a reuse of the short `*SectionHeader`
dashboard strings.

#### Scenario: Selecting a section renders its Markdown
- GIVEN a user whose index includes "Inmuebles"
- WHEN they select the Inmuebles entry
- THEN `resources/help/inmuebles.md` is read and rendered as HTML via
  `Str::markdown()` inside the Ayuda view

#### Scenario: Mapped section file is missing
- GIVEN a section entry is present in the section map and passes its access
  gate, but its mapped `.md` file does not exist on disk
- WHEN the user selects that section
- THEN the page MUST NOT throw an unhandled error
- AND the page shows a graceful "content not available" state instead of
  the rendered Markdown

### Requirement: v1 section coverage
The section map MUST cover 17 help entries: 2 always-visible general entries
(introducción, panel) + 13 resource-backed sections (Inmuebles, Contratos,
Leads, Propietarios, Zonas, Proyectos [Operación]; Lonas asignadas,
Solicitudes de lonas, Evidencias [Lonas]; Features/Características, Tipos de
servicio, Tipos de proyecto [Configuración]; Usuarios [Seguridad]) + 2 agent
pages (Mi Zona, Mis Lonas). Admin/owner see the 13 resource-backed sections
plus the 2 general entries; an agente sees whichever resource-backed sections
their own gates allow plus Mi Zona/Mis Lonas plus the 2 general entries. Each
entry MUST have a corresponding non-empty `resources/help/<section>.md` file
at delivery time.

#### Scenario: All panel sections are documented
- GIVEN the full section map defined in Ayuda.php
- WHEN counting entries against the current panel's resource list
- THEN all 17 help entries (2 general + 13 resource-backed + 2 agent pages)
  each have one section-map entry and one corresponding `.md` file

### Requirement: Dashboard SectionHeader copy consistency review
The system SHOULD review existing `*SectionHeader` widget descriptions for
tone/terminology consistency with the new manual copy. This is a refinement of
already-populated strings, not new construction — no new slots, props, or
structural changes to the widgets are introduced by this change.

#### Scenario: SectionHeader copy is reviewed, not rebuilt
- GIVEN existing `*SectionHeader` widgets with populated one-line
  descriptions
- WHEN the manual content is authored
- THEN each SectionHeader's copy is checked for consistent tone/terms against
  the corresponding manual section
- AND no SectionHeader widget gains new structural elements as a result

### Requirement: Explicit non-goals
The system MUST NOT implement, in this change: full-text search within the
manual, database-backed storage of manual content, an admin-editable UI for
manual content, or a backfill of `SectionHeader`-style descriptions onto
Resource List pages.

#### Scenario: No search capability is present
- GIVEN the Ayuda page as delivered
- WHEN inspecting the view and page class
- THEN there is no search input or query-based filtering of manual content

#### Scenario: No database table backs manual content
- GIVEN the change's migrations (none) and models
- WHEN inspecting the database schema
- THEN no new table or column stores manual content — it is Markdown files
  only

#### Scenario: List pages remain unchanged
- GIVEN existing Resource List pages
- WHEN this change is applied
- THEN no List page gains a new `SectionHeader`-style description as part of
  this change
