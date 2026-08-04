# Épica 11 — Ayuda / Manual del usuario del CMS

## Why

Los usuarios del panel (owner, admin, agente, arquitectura, proyectos) no tienen
ninguna referencia dentro del sistema para saber cómo usar cada sección. Hoy la
única ayuda contextual es la descripción de una línea que aparece en el Dashboard
(los widgets `*SectionHeader`), que:

- Solo se ve agrupada en el Dashboard, no al abrir una sección concreta.
- Es un titular corto, no un instructivo (no explica pasos, campos ni ejemplos).

Consecuencia: alguien del equipo tiene que explicar de viva voz lo básico a cada
agente nuevo una y otra vez. Eso no escala, es inconsistente entre quien explica,
y se pierde cuando esa persona no está disponible.

**Qué queremos lograr**: un manual del usuario dentro del propio panel — siempre
disponible, versionado con el código, filtrado por rol — para que cada usuario
pueda auto-resolver "cómo hago X" sin depender de otra persona.

## What Changes

### En alcance (in-scope)

1. **Nueva Filament Page "Ayuda"** (`app/Filament/Pages/Ayuda.php`) en la
   navegación del panel, siguiendo el patrón de `AgentDashboard`/`AgentLonas`
   (custom `Filament\Pages\Page`: `$navigationIcon`, `$navigationLabel = 'Ayuda'`,
   `$slug = 'ayuda'`, `$view`, `canAccess()`).
   - `canAccess()` habilita a **TODOS** los roles del panel (la ayuda nunca se
     restringe por rol). Reutiliza los roles de `User::canAccessPanel` o retorna
     `true` para cualquier usuario autenticado del panel.
2. **Índice del manual filtrado por rol**: la lista **interna** de secciones se
   filtra por usuario, mostrando solo las secciones a las que ese usuario
   realmente puede entrar. El filtro **reutiliza el chequeo de acceso real de
   cada Resource** (p. ej. `PropertyResource::canViewAny()`), no duplica listas de
   roles.
3. **Contenido del manual = archivos Markdown** en `resources/help/*.md`, uno por
   sección, versionados en git, renderizados con
   `Illuminate\Support\Str::markdown()` (commonmark ya está vendorizado
   transitivamente — CERO dependencias nuevas).
4. **Copy del manual = contenido propio, más extenso** por sección (pasos, campos,
   ejemplos). NO reutiliza los titulares cortos de los `SectionHeader`. Dos
   niveles: titular corto en Dashboard + manual profundo.
5. **Revisión ligera del copy existente de los `*SectionHeader`** del Dashboard
   para consistencia de tono/términos. Esto NO es construcción nueva: las
   descripciones de una línea **ya existen y están pobladas** en los widgets. Solo
   se revisan/afinan, nada más.

### Fuera de alcance (out-of-scope)

- **Búsqueda** dentro del manual (v1 = índice simple + una página por sección).
- **Almacenamiento en BD** del contenido del manual.
- **UI editable por admin** para el manual (es contenido git-only, se actualiza
  por deploy).
- **Backfill de descripciones en las páginas List** de cada Resource (el gap
  detectado en la exploración de que los `SectionHeader` solo aparecen en el
  Dashboard NO se aborda aquí).
- Capa de i18n / `lang/` (todo el copy es Español (México) inline, como el resto
  del proyecto).

## Approach

### 1. La página Ayuda

Nueva `app/Filament/Pages/Ayuda.php` extendiendo `Filament\Pages\Page`, modelada
sobre `AgentLonas.php`:

```
$navigationIcon  = 'heroicon-o-question-mark-circle'
$navigationLabel = 'Ayuda'
$title           = 'Ayuda'
$slug            = 'ayuda'
$view            = 'filament.pages.ayuda'
canAccess()      = cualquier usuario del panel (no role-gated)
```

Navegación: **Ayuda es una página sin grupo** (blank-label group). Filament
hardcodea ese grupo al INICIO del sidebar (`NavigationManager`, sort `-1`),
antes de cualquier grupo con nombre (`Operación` / `Lonas` / `Configuración` /
`Seguridad`); `navigationSort` de la página no puede moverla al fondo. Se
acepta esa posición real — no se fuerza al fondo para no sobrescribir el
`NavigationManager`. Verificado en `docs/audits/epica-11-auditoria-diseno.md`.

### 2. Mapeo sección → Markdown → gate de acceso

Un **arreglo declarativo** (dentro de la página o en `config/help.php`) que mapea
cada sección a: título mostrado, archivo Markdown, y el chequeo de visibilidad
reutilizando el `canViewAny()` del Resource correspondiente. Ejemplo conceptual:

```php
// clave => [título, archivo md, closure de acceso que REUSA el Resource]
'inmuebles'    => ['Inmuebles',    'inmuebles.md',    fn () => PropertyResource::canViewAny()],
'leads'        => ['Leads',        'leads.md',        fn () => LeadResource::canViewAny()],
'zonas'        => ['Zonas',        'zonas.md',        fn () => ZoneResource::canViewAny()],
'propietarios' => ['Propietarios', 'propietarios.md', fn () => PropertyOwnerResource::canViewAny()],
'lonas'        => ['Lonas',        'lonas.md',        fn () => LonaBatchResource::canViewAny()],
// ...
```

**Regla de diseño clave (riesgo de la exploración)**: el gate de cada entrada
DEBE delegar en el `canViewAny()` real del Resource (o su Policy), NUNCA reescribir
la lista de roles. La visibilidad por-resource está dispersa (unos vía Policy con
`auth()->can('viewAny', Model::class)`, otros con `hasAnyRole([...])` inline).
Reusar `canViewAny()` garantiza que el índice del manual siempre coincida con lo
que el usuario ve en el sidebar; si mañana cambia una Policy, el manual se ajusta
solo — sin drift.

### 3. Renderizado

La página lee el arreglo, filtra por el gate de cada entrada, y construye el
índice. Al abrir una sección (vía `?section=` o slug anidado), carga el `.md`
correspondiente y lo pasa por `Str::markdown()` a la vista Blade. Vista v1: índice
+ contenido de la sección seleccionada. Sin búsqueda, sin anclas complejas.

### 4. Contenido inicial

Un `.md` por sección con contenido propio (qué es, para qué sirve, pasos comunes,
campos importantes, ejemplos). Español (México) inline. Redacción sigue el skill
de documentación cognitiva: primero la acción/resultado, luego el detalle.

## Impact

**Nuevo:**
- `app/Filament/Pages/Ayuda.php` — la página del manual.
- `resources/views/filament/pages/ayuda.blade.php` — vista (índice + render md).
- `resources/help/*.md` — un archivo por sección (directorio nuevo en el repo).
- (Opcional) `config/help.php` — el mapeo sección → md → gate, si se externaliza.

**Tocado (mínimo):**
- `app/Providers/Filament/AdminPanelProvider.php` — solo si se declara un grupo de
  navegación nuevo para Ayuda (si va sin grupo, auto-discovery basta).
- `app/Filament/Widgets/*SectionHeader.php` — revisión/afinado de copy existente
  (sin cambios estructurales, sin slots nuevos).

**Sin migraciones, sin dependencias nuevas, sin cambios de esquema.**

## Non-Goals

- NO buscador en el manual (v1).
- NO persistencia en BD del contenido.
- NO UI editable por admin (contenido git-only).
- NO backfill de `SectionHeader` en páginas List de Resources.
- NO capa de traducción / `lang/`.

## Open Questions (para el usuario)

1. ~~**Ubicación en nav**~~ — RESUELTO (auditoría de diseño): Ayuda queda sin
   grupo y Filament la renderiza al inicio del sidebar (posición real para
   páginas sin grupo). No se fuerza al fondo.
2. **Dónde vive el mapeo**: ¿arreglo dentro de `Ayuda.php` (más simple) o
   `config/help.php` (más limpio/testeable)? Recomendación: arreglo en la página
   para v1; externalizar solo si crece.
3. **Secciones a documentar en v1**: ¿todas las del panel (Inmuebles, Leads,
   Zonas, Propietarios, Proyectos, Contratos, Lonas, Configuración, Usuarios) o un
   subconjunto prioritario para la primera entrega? — impacta el volumen de copy.
