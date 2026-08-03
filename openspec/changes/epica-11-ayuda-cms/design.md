# Design — Épica 11: Ayuda / Manual del CMS

In-panel, role-aware help manual delivered as a single custom Filament Page (`Ayuda`) that renders
git-versioned Markdown files. The visible section index is filtered by reusing each Resource's real
`canViewAny()` gate, so the manual mirrors the sidebar exactly and never drifts. Zero new packages,
zero DB, zero schema change.

## Quick path (what gets built)

1. `app/Filament/Pages/Ayuda.php` — custom `Filament\Pages\Page`, ungrouped (Filament renders the ungrouped block at the TOP of the sidebar — the real, accepted position, not the bottom), `canAccess() = true` for any panel user.
2. A **section registry** (private static method in `Ayuda.php`) mapping each section to `{label, group, file, gate}`; every `gate` delegates to the owning Resource's real `canViewAny()`.
3. `resources/views/filament/pages/ayuda.blade.php` — index of allowed sections + selected section rendered from Markdown via `Str::markdown()`, routed by a `?seccion=` Livewire URL property.
4. `resources/help/*.md` — one file per section, Spanish (MX), authored from a shared template.
5. Register `@tailwindcss/typography` in the Filament theme config so rendered Markdown uses `prose`.
6. Feature tests (strict TDD) asserting per-role which sections appear/hide, plus gate-bypass and missing-file handling.

## Architecture decision record

### ADR-1 — Reuse `Resource::canViewAny()` uniformly for every gate (do NOT replicate the Policy call)

**Decision.** Each section's `gate` closure calls the owning Resource's own static
`canViewAny(): bool` — e.g. `fn (): bool => \App\Filament\Resources\PropertyResource::canViewAny()`.
This is applied uniformly to ALL resource-backed sections, regardless of how that resource internally
gates (Policy, named permission, or inline `hasAnyRole`).

**Why this over `fn () => auth()->user()->can('viewAny', Property::class)`.**
The real gates in this codebase are heterogeneous (verified in the resources):

| Internal gate mechanism | Example resources | Actual check inside `canViewAny()` |
|---|---|---|
| Policy `viewAny` | Property, Lead, Feature, LonaBatch, LonaRequest, ContratoIntermediacion | `auth()->user()?->can('viewAny', Model::class)` |
| Named permission | Zone (`zones.manage`), PropertyOwner (`owners.manage`), Project (`projects.manage`), User (`users.view`), LonaEvidence (`lonas.manage`) | `auth()->user()?->can('<permission>')` |
| Inline role list | ProjectType, ServiceType | `auth()->user()?->hasAnyRole(['owner','admin'])` |

If the manual replicated `can('viewAny', Model)`, it would be **wrong** for the permission-based and
inline-role resources (5 + 2 of 13) and would **duplicate** the Policy call for the rest. Calling
`Resource::canViewAny()` is the single source of truth Filament itself uses to show/hide the sidebar
entry, so the manual index becomes a mirror of the sidebar **by construction**. When a Policy or
permission changes, the manual follows automatically — the drift risk flagged in exploration #775 is
eliminated, not merely mitigated. This is DRY (`core-dry`) and honors SRP: the Resource owns "who may
see me", the manual only asks.

**Rejected alternative.** Per-section duplicated role/permission lists in `Ayuda.php` or `config/help.php`.
Rejected: guarantees drift, needs the author to know each resource's internal mechanism, and re-opens
the exact coupling the épica set out to avoid.

**Agent pages** (`AgentDashboard`, `AgentLonas`) are `Filament\Pages\Page`, not Resources — they expose
`canAccess()` instead of `canViewAny()`. Their gates delegate to `::canAccess()` respectively.

### ADR-2 — Single page + `?seccion=` query param, NOT one Filament page per section

**Decision.** One `Ayuda` page. Section selection is a Livewire public property
`#[\Livewire\Attributes\Url] public ?string $seccion = null;`. No `?seccion` → render the grouped index.
With a valid, permitted `?seccion` → render that section's article plus a "back to index" link.

**Why.** One page = one nav entry (the épica wants a single "Ayuda" item), no per-section routing/slug
sprawl, deep-linkable/shareable URLs, and browser-native back/forward via the `#[Url]` attribute
(Livewire syncs query string). KISS/YAGNI: no search, no nested slugs in v1.

**Rejected.** A page subclass per section (13+ nav entries, defeats the single-entry goal) and a
route/controller outside Filament (loses panel chrome, auth middleware, and the `canAccess` gate).

### ADR-3 — Gate is re-checked on section access, not only on index build (fail-fast, closed by default)

The `?seccion` value is user-controllable. `currentSection()` resolves the requested key **only from the
already-filtered visible set** (`visibleSections()`), never from the full registry. A user who guesses
`?seccion=usuarios` without the `users.view` permission gets the index (with a soft "sección no
disponible" note), never the content. Authorization is centralized in one filter; the view cannot
render an unpermitted file (`core-fail-fast`, `core-encapsulation`).

### ADR-4 — Registry lives as a method in `Ayuda.php` for v1 (not `config/help.php`)

Per the locked decision. The registry holds closures (gates), which do not belong in a cached
`config/*.php` (config caching serializes arrays and chokes on closures). Keeping it in the page as a
`private static function sectionRegistry(): array` keeps gates as first-class closures, colocates the
manual's structure with its only consumer, and stays trivially unit-testable. If v2 needs external
editing, extract to a provider — out of scope now (YAGNI).

## Component design

### `app/Filament/Pages/Ayuda.php`

```php
<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ContratoIntermediacionResource;
use App\Filament\Resources\FeatureResource;
use App\Filament\Resources\LeadResource;
use App\Filament\Resources\LonaBatchResource;
use App\Filament\Resources\LonaEvidenceResource;
use App\Filament\Resources\LonaRequestResource;
use App\Filament\Resources\ProjectResource;
use App\Filament\Resources\ProjectTypeResource;
use App\Filament\Resources\PropertyOwnerResource;
use App\Filament\Resources\PropertyResource;
use App\Filament\Resources\ServiceTypeResource;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\ZoneResource;
use Filament\Pages\Page;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;

class Ayuda extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static ?string $navigationLabel = 'Ayuda';

    protected static ?string $title = 'Ayuda';

    protected static ?string $slug = 'ayuda';

    // Sin grupo (blank-label group). Filament hardcodea ese grupo al TOPE del nav
    // (NavigationManager::getNavigationGroups(), sort = -1), antes de cualquier
    // grupo con nombre — navigationSort de la página NO puede moverla al fondo.
    // $navigationSort solo ordena DENTRO del bloque sin grupo: 99 la deja después
    // de Dashboard/"Panel" (sort = -2), que es el único otro ítem sin grupo hoy.
    protected static ?int $navigationSort = 99;

    protected static string $view = 'filament.pages.ayuda';

    #[Url]
    public ?string $seccion = null;

    /** La ayuda nunca se restringe por rol: cualquier usuario del panel entra. */
    public static function canAccess(): bool
    {
        return auth()->check();
    }

    /**
     * Secciones visibles para el usuario actual, agrupadas como en el menú.
     *
     * @return array<string, array<int, array{key:string,label:string,file:string}>>
     */
    public function visibleSections(): array
    {
        $grouped = [];

        foreach (self::sectionRegistry() as $section) {
            if (! ($section['gate'])()) {
                continue;
            }

            $grouped[$section['group']][] = [
                'key' => $section['key'],
                'label' => $section['label'],
                'file' => $section['file'],
            ];
        }

        return $grouped;
    }

    /**
     * Sección seleccionada SOLO si el usuario tiene permiso para verla.
     * Nunca resuelve desde el registro completo — siempre desde lo visible.
     *
     * @return array{key:string,label:string,html:string}|null
     */
    public function currentSection(): ?array
    {
        if ($this->seccion === null) {
            return null;
        }

        foreach ($this->visibleSections() as $sections) {
            foreach ($sections as $section) {
                if ($section['key'] === $this->seccion) {
                    return [
                        'key' => $section['key'],
                        'label' => $section['label'],
                        'html' => $this->renderMarkdown($section['file']),
                    ];
                }
            }
        }

        return null; // Pedida pero no permitida/inexistente => index con aviso suave.
    }

    private function renderMarkdown(string $file): string
    {
        $path = resource_path("help/{$file}.md");

        if (! File::exists($path)) {
            return '<p>Este contenido todavía no está disponible.</p>';
        }

        // html_input=escape: el Markdown es de confianza (git), pero escapamos
        // HTML embebido por defensa en profundidad. Salida vía {!! !!} en Blade.
        return Str::markdown(File::get($path), [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
    }

    /**
     * Registro declarativo: cada gate DELEGA en el canViewAny()/canAccess() real
     * del recurso/página dueño. Nunca se replican listas de roles aquí (ADR-1).
     *
     * @return array<int, array{key:string,label:string,group:string,file:string,gate:callable():bool}>
     */
    private static function sectionRegistry(): array
    {
        return [
            // Siempre visibles
            ['key' => 'introduccion', 'label' => 'Primeros pasos', 'group' => 'General', 'file' => 'introduccion', 'gate' => fn (): bool => true],
            ['key' => 'panel', 'label' => 'Panel general', 'group' => 'General', 'file' => 'panel', 'gate' => fn (): bool => true],

            // Operación
            ['key' => 'inmuebles', 'label' => 'Inmuebles', 'group' => 'Operación', 'file' => 'inmuebles', 'gate' => fn (): bool => PropertyResource::canViewAny()],
            ['key' => 'leads', 'label' => 'Leads', 'group' => 'Operación', 'file' => 'leads', 'gate' => fn (): bool => LeadResource::canViewAny()],
            ['key' => 'zonas', 'label' => 'Zonas', 'group' => 'Operación', 'file' => 'zonas', 'gate' => fn (): bool => ZoneResource::canViewAny()],
            ['key' => 'propietarios', 'label' => 'Propietarios', 'group' => 'Operación', 'file' => 'propietarios', 'gate' => fn (): bool => PropertyOwnerResource::canViewAny()],
            ['key' => 'proyectos', 'label' => 'Proyectos', 'group' => 'Operación', 'file' => 'proyectos', 'gate' => fn (): bool => ProjectResource::canViewAny()],
            ['key' => 'contratos', 'label' => 'Contratos', 'group' => 'Operación', 'file' => 'contratos', 'gate' => fn (): bool => ContratoIntermediacionResource::canViewAny()],

            // Lonas
            ['key' => 'lonas-asignadas', 'label' => 'Lonas asignadas', 'group' => 'Lonas', 'file' => 'lonas-asignadas', 'gate' => fn (): bool => LonaBatchResource::canViewAny()],
            ['key' => 'solicitudes-lonas', 'label' => 'Solicitudes de lonas', 'group' => 'Lonas', 'file' => 'solicitudes-lonas', 'gate' => fn (): bool => LonaRequestResource::canViewAny()],
            ['key' => 'evidencias', 'label' => 'Evidencias', 'group' => 'Lonas', 'file' => 'evidencias', 'gate' => fn (): bool => LonaEvidenceResource::canViewAny()],

            // Configuración
            ['key' => 'caracteristicas', 'label' => 'Características', 'group' => 'Configuración', 'file' => 'caracteristicas', 'gate' => fn (): bool => FeatureResource::canViewAny()],
            ['key' => 'tipos-proyecto', 'label' => 'Tipos de proyecto', 'group' => 'Configuración', 'file' => 'tipos-proyecto', 'gate' => fn (): bool => ProjectTypeResource::canViewAny()],
            ['key' => 'tipos-servicio', 'label' => 'Tipos de servicio', 'group' => 'Configuración', 'file' => 'tipos-servicio', 'gate' => fn (): bool => ServiceTypeResource::canViewAny()],

            // Seguridad
            ['key' => 'usuarios', 'label' => 'Usuarios', 'group' => 'Seguridad', 'file' => 'usuarios', 'gate' => fn (): bool => UserResource::canViewAny()],

            // Páginas de agente (gate = canAccess, no canViewAny)
            ['key' => 'mi-zona', 'label' => 'Mi Zona', 'group' => 'Mi trabajo', 'file' => 'mi-zona', 'gate' => fn (): bool => AgentDashboard::canAccess()],
            ['key' => 'mis-lonas', 'label' => 'Mis Lonas', 'group' => 'Mi trabajo', 'file' => 'mis-lonas', 'gate' => fn (): bool => AgentLonas::canAccess()],
        ];
    }
}
```

> Note: `discoverPages()` in `AdminPanelProvider.php:82` auto-registers this page — no change to the
> provider is required. Ayuda is ungrouped, so Filament's `NavigationManager` renders it at the TOP of
> the sidebar (blank-label group hardcoded to sort `-1`), before the four named nav groups — NOT at the
> bottom. `navigationSort = 99` only orders Ayuda relative to other ungrouped items (after Dashboard's
> "Panel", sort `-2`). Verified against real Filament v3.3.54 source and a rendered-nav smoke test; see
> `docs/audits/epica-11-auditoria-diseno.md`.

### Section registry — full mapping (source of truth)

| Section key | Nav group | Help file | Gate (real check reused) |
|---|---|---|---|
| introduccion | General | `introduccion.md` | always `true` |
| panel | General | `panel.md` | always `true` |
| inmuebles | Operación | `inmuebles.md` | `PropertyResource::canViewAny()` → Policy `viewAny` |
| leads | Operación | `leads.md` | `LeadResource::canViewAny()` → Policy `viewAny` |
| zonas | Operación | `zonas.md` | `ZoneResource::canViewAny()` → perm `zones.manage` |
| propietarios | Operación | `propietarios.md` | `PropertyOwnerResource::canViewAny()` → perm `owners.manage` |
| proyectos | Operación | `proyectos.md` | `ProjectResource::canViewAny()` → perm `projects.manage` |
| contratos | Operación | `contratos.md` | `ContratoIntermediacionResource::canViewAny()` → Policy `viewAny` |
| lonas-asignadas | Lonas | `lonas-asignadas.md` | `LonaBatchResource::canViewAny()` → Policy `viewAny` |
| solicitudes-lonas | Lonas | `solicitudes-lonas.md` | `LonaRequestResource::canViewAny()` → Policy `viewAny` |
| evidencias | Lonas | `evidencias.md` | `LonaEvidenceResource::canViewAny()` → perm `lonas.manage` |
| caracteristicas | Configuración | `caracteristicas.md` | `FeatureResource::canViewAny()` → Policy `viewAny` |
| tipos-proyecto | Configuración | `tipos-proyecto.md` | `ProjectTypeResource::canViewAny()` → inline `hasAnyRole(['owner','admin'])` |
| tipos-servicio | Configuración | `tipos-servicio.md` | `ServiceTypeResource::canViewAny()` → inline `hasAnyRole(['owner','admin'])` |
| usuarios | Seguridad | `usuarios.md` | `UserResource::canViewAny()` → perm `users.view` |
| mi-zona | Mi trabajo | `mi-zona.md` | `AgentDashboard::canAccess()` → `hasRole('agente')` |
| mis-lonas | Mi trabajo | `mis-lonas.md` | `AgentLonas::canAccess()` → `hasRole('agente')` |

17 entradas de ayuda: 2 generales + 13 resource-backed + 2 páginas de agente. Admin/owner ven las 13
resource-backed + generales; agente ve las permitidas por resources + Mi Zona/Mis Lonas + generales.
Covers ALL panel sections (locked requirement). The index groups them under headings matching the
sidebar (`General`, `Operación`, `Lonas`, `Configuración`, `Seguridad`, `Mi trabajo`).

### View — `resources/views/filament/pages/ayuda.blade.php`

Structure (progressive disclosure: index first, article on selection):

```blade
<x-filament-panels::page>
    @php($current = $this->currentSection())

    @if ($current)
        {{-- Vista de artículo --}}
        <div class="flex items-center gap-3">
            <a href="{{ \App\Filament\Pages\Ayuda::getUrl() }}"
               class="text-sm text-primary-600 hover:underline dark:text-primary-400">
                &larr; Volver al índice
            </a>
        </div>

        <article class="prose prose-slate max-w-none dark:prose-invert">
            {!! $current['html'] !!}
        </article>
    @else
        {{-- Índice agrupado --}}
        @if ($this->seccion !== null)
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Esa sección no está disponible para tu cuenta.
            </p>
        @endif

        @foreach ($this->visibleSections() as $group => $sections)
            <section class="space-y-3">
                <h2 class="text-lg font-semibold text-gray-950 dark:text-white">{{ $group }}</h2>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($sections as $section)
                        <a href="{{ \App\Filament\Pages\Ayuda::getUrl(['seccion' => $section['key']]) }}"
                           class="rounded-xl border border-gray-200 bg-white p-4 text-sm font-medium
                                  text-gray-900 shadow-sm transition hover:border-primary-400
                                  hover:shadow dark:border-white/10 dark:bg-white/5 dark:text-white">
                            {{ $section['label'] }}
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach
    @endif
</x-filament-panels::page>
```

Design notes:
- **HTML safety.** `Str::markdown(..., ['html_input' => 'escape'])` neutralizes any raw HTML in the `.md`
  before it is emitted with `{!! !!}`. Content is git-authored/trusted, but escaping is defense in depth
  and costs nothing (`sec-` hygiene). We deliberately use `{!! !!}` because we WANT the converter's own
  generated tags — escaping author HTML, not the generated structure.
- **Missing file.** `renderMarkdown()` returns a friendly placeholder if the file is absent, so a section
  can be listed before its copy lands without a 500 (`core-fail-fast`, graceful).
- **Styling.** Uses Tailwind `prose` (typography plugin, see below) scoped to the article; index cards
  use existing panel design tokens (`primary-*`, rounded-xl, dark variants) consistent with `theme.css`.
  Mobile-first grid: 1 col → `sm:2` → `lg:3` (`resp-mobile-first`).

### Theme — enable `prose` for the panel

`@tailwindcss/typography@^0.5.20` is already a devDependency but is NOT registered in the Filament theme
config. Register it so `prose` classes are generated for the panel build:

```js
// resources/css/filament/admin/tailwind.config.js
import preset from '../../../../vendor/filament/filament/tailwind.config.preset'
import typography from '@tailwindcss/typography'

export default {
    presets: [preset],
    content: [
        './app/Filament/**/*.php',
        './resources/views/filament/**/*.blade.php',
        './vendor/filament/**/*.blade.php',
    ],
    plugins: [typography],
}
```

The existing `content` globs already cover `resources/views/filament/**/*.blade.php`, so the `prose`
classes in `ayuda.blade.php` are scanned. Rebuild via `npm run build:filament`. (Fallback if the team
rejects the plugin: hand-style a `.help-content` block in `theme.css` — more code, less consistent;
plugin preferred.)

## Content layout — `resources/help/`

```
resources/help/
├── introduccion.md
├── panel.md
├── inmuebles.md
├── leads.md
├── zonas.md
├── propietarios.md
├── proyectos.md
├── contratos.md
├── lonas-asignadas.md
├── solicitudes-lonas.md
├── evidencias.md
├── caracteristicas.md
├── tipos-proyecto.md
├── tipos-servicio.md
├── usuarios.md
├── mi-zona.md
└── mis-lonas.md
```

File name === `file` key in the registry (kebab-case, matches `?seccion` where practical). All copy in
Spanish (Mexico), consistent with the existing inline-Spanish convention (no i18n layer).

### Authoring template (every `.md` follows this shape)

```markdown
# <Nombre de la sección>

<Una o dos frases: qué es y para qué sirve dentro de newhauz.>

## ¿Para qué sirve?

<Contexto de negocio breve. Cuándo usar esta sección.>

## Cómo se usa

1. <Primer paso concreto en el panel.>
2. <Segundo paso.>
3. <Resultado esperado / cómo confirmar que salió bien.>

## Campos importantes

- **<Campo>**: <qué significa y cómo llenarlo>.
- **<Campo>**: <...>.

## Preguntas frecuentes

- **<Duda común>** — <respuesta corta>.
```

This is intentionally deeper than the one-line `SectionHeader` dashboard descriptions (steps, fields,
FAQ). The `SectionHeader` copy stays dashboard-only; this épica only reviews that copy for consistency,
it does not reuse it as the manual body (locked decision).

## Testing approach (strict TDD active)

Feature tests hit the real HTTP stack against PostgreSQL+PostGIS (`inmo_test`, per `phpunit.xml`).
Write the test first (red), then the page/registry/view (green), then refactor.

`tests/Feature/Filament/AyudaPageTest.php`:

| Test | Assertion |
|---|---|
| `guest_cannot_access_ayuda` | unauthenticated `GET /admin/ayuda` → redirect to login |
| `any_panel_user_can_open_ayuda` | `actingAs` an active user with ANY panel role → 200 |
| `owner_sees_all_sections` | owner sees Inmuebles…Usuarios labels (assertSee each) |
| `agente_sees_operational_but_not_config_or_users` | agente sees Inmuebles/Leads/Mi Zona/Mis Lonas; `assertDontSee` Tipos de proyecto, Usuarios |
| `arquitectura_role_section_visibility` | asserts exactly the sections its permissions/policies allow |
| `proyectos_role_section_visibility` | idem for `proyectos` role |
| `index_groups_match_sidebar` | group headings render (Operación, Lonas, Configuración, Seguridad) |
| `requesting_forbidden_seccion_shows_index_not_content` | agente `GET /admin/ayuda?seccion=usuarios` → `assertDontSee` the article body, shows "no disponible" (ADR-3 gate re-check) |
| `requesting_permitted_seccion_renders_markdown` | owner `?seccion=inmuebles` → `assertSee` heading text from `inmuebles.md` |
| `missing_markdown_file_renders_placeholder` | invoke `renderMarkdown()` directly (via reflection) with a filename guaranteed to never exist in `resources/help/` → placeholder, no 500. Does NOT depend on any real section lacking its `.md` (avoids breaking once Work Unit 4 authors all 17 files). |

Role/permission setup mirrors the existing suite: seed spatie roles + permissions in the test (same
factory/seed helpers the current Resource tests use). Because gates delegate to the real
`canViewAny()`, these tests double as a drift alarm — if a Resource's gate changes, the corresponding
visibility test fails, surfacing the divergence immediately.

Livewire-level assertion option (faster, complements HTTP tests): `Livewire::test(Ayuda::class)` then
`->set('seccion', 'usuarios')` and assert `currentSection()` is null for a denied role.

## Trade-offs and residual risk

| Topic | Decision / mitigation |
|---|---|
| Gate reuse vs duplication | Reuse `canViewAny()` uniformly (ADR-1). Kills drift; the only cost is one static call per section on index render (negligible). |
| Registry drift when a NEW resource is added | Residual risk: a future resource won't auto-appear in the manual — the registry is manual. Mitigation: a test that iterates `Filament\Resources` discovered classes and asserts each has a help entry can be added later (v2, YAGNI now). Documented as a known follow-up. |
| `html_input => escape` | If a future author intentionally needs raw HTML in a help page, escaping blocks it. Acceptable: Markdown covers the manual's needs; revisit only if required. |
| Typography plugin | Adds `@tailwindcss/typography` to the panel build (already a devDependency). Minor CSS size increase; big readability win. Fallback documented. |
| Single-page `?seccion` | No in-page search/anchors in v1 (locked non-goal). Deep-linkable URLs partially compensate. |
| Ungrouped page renders at nav TOP, not bottom | Accepted, not mitigated: Filament hardcodes the blank-label group to sort `-1` (top). `navigationSort = 99` only orders Ayuda relative to Dashboard within that top block; it cannot push Ayuda below the named groups. |

## Files touched

**New**
- `app/Filament/Pages/Ayuda.php`
- `resources/views/filament/pages/ayuda.blade.php`
- `resources/help/*.md` (17 files)
- `tests/Feature/Filament/AyudaPageTest.php`

**Modified (minimal)**
- `resources/css/filament/admin/tailwind.config.js` (register typography plugin)
- `app/Filament/Widgets/*SectionHeader.php` — copy review only (consistency), no structural change

**Untouched:** `AdminPanelProvider.php` (auto-discovery covers the page), no migration, no new composer dep.

## Next step

`sdd-tasks` once the spec is also ready — break this into TDD-ordered work units (page + registry,
view + rendering, per-section content, theme config, tests).
