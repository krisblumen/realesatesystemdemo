# RFC-059 — Reporte de bugs corregidos del Login Admin

Durante la integración del theme y login del panel admin (RFC-059) se detectaron y corrigieron **3 bugs** (uno de ellos rompía el panel por completo) y se aplicaron 1 mejora visual y 1 corrección de higiene de repo. Los tres bugs nacieron del **mismo merge mal cerrado** y **ninguno era visible revisando el diff línea por línea** — aparecieron al *ejecutar* la app y al *validar dependencias*.

| Campo | Valor |
|---|---|
| Proyecto | NEW HAUZ |
| RFC | RFC-059 — Theme Admin + Login |
| Rama de trabajo | `features/ux-ui-admin-login-screen` (mergeada vía PR #5, ya eliminada) |
| Rama destino | `develop` (`948c58c`) |
| Fecha | 2026-06-18 |
| Verificación | Render en vivo `/admin/login` → HTTP 200, claro y oscuro |
| Documento relacionado | [`docs/uxui-reports/RFC-059-cierre-tecnico.md`](../uxui-reports/RFC-059-cierre-tecnico.md) |

---

## Resumen rápido

| ID | Severidad | Bug | Causa raíz | Commit |
|---|---|---|---|---|
| BUG-01 | 🔴 Alta (visual) | Theme sin *preflight* de Tailwind → inputs desbordados y fuente serif | Theme de Filament v3 compilado por el pipeline Tailwind v4 | Integrado vía PR #5 (`cc55422`) |
| BUG-02 | 🔴 Crítica | Panel admin caído (HTTP 500) en todas las rutas, incl. login | Conflicto de merge: `EnsureUserIsActive` en middleware global **sin su `use` import** | `2b67604` |
| BUG-03 | 🟠 Media | `composer.lock` desincronizado → `composer install` roto en clon limpio | El lock no se regeneró al mergear `composer.json` con nuevas dependencias | `3f72864` |
| MEJORA | — | Logo flotante 5×→4× sobre la tarjeta | Petición de diseño | Integrado |
| HIGIENE | — | Cache trackeado generaba ruido en cada sesión | `.atl/.skill-registry.cache.json` versionado | `948c58c` |

---

## BUG-01 — Theme admin sin *preflight* de Tailwind

🔴 **Severidad:** Alta (defecto visual en toda la pantalla de login)
🔎 **Detección:** Solo al renderizar la app en vivo. Las auditorías por lectura de código **no lo vieron** porque el código fuente "se veía correcto".

### Síntomas (medidos en el DOM)

| Métrica | Valor roto |
|---|---|
| `box-sizing` de los inputs | `content-box` → el campo se salía ~11px de su marco redondeado |
| Fuente de etiquetas/cuerpo | `Times` (serif del navegador) en lugar de la fuente de marca |
| `box-sizing:border-box` en el CSS compilado | **0 reglas** (preflight ausente) |

### Causa raíz

El proyecto usa **Tailwind v4** (`@tailwindcss/vite`), pero el theme de **Filament v3** estaba registrado con `->viteTheme()` e incluido en el input de Vite, así que el plugin v4 lo procesaba. **Tailwind v4 no expande las directivas `@tailwind base/components/utilities`** (v4 usa `@import "tailwindcss"`), por lo que el *preflight* (reset CSS, incl. `box-sizing` y fuente base) nunca se generaba. El `@import` de `base.css` de Filament sí traía los componentes precompilados → la página se veía "casi bien" y la revisión estática pasó.

### Corrección (Opción A — receta oficial de Filament v3.3)

| Archivo | Cambio |
|---|---|
| `package.json` | Script `build:filament` con CLI Tailwind v3, encadenado en `build` |
| `vite.config.js` | Se quita `theme.css` del input de Vite (el plugin v4 ya no lo toca) |
| `resources/css/filament/admin/tailwind.config.js` | Rutas `content` a root-relative (`./app/...`) para el CLI v3 |
| `app/Providers/Filament/AdminPanelProvider.php` | `->viteTheme(...)` → `->theme(asset('css/filament/admin/theme.css'))` |
| `public/css/filament/admin/theme.css` | Artefacto compilado (CLI Tailwind v3), trackeado en git |

### Verificación

```
box-sizing: content-box → border-box        ✅
fuente: Times → Inter, ui-sans-serif, …      ✅
desborde input vs tarjeta: +11px → -49px     ✅
composer/tailwind compilado: preflight presente ✅
```

---

## BUG-02 — Panel admin caído (HTTP 500) por conflicto de merge

🔴 **Severidad:** Crítica (el panel admin completo, incluida la pantalla de login, devolvía 500)
🔎 **Detección:** Auditoría post-merge + análisis estático de clases + arranque en vivo.

### Síntoma

`GET /admin/login` → **HTTP 500** en cuanto el entorno arrancaba.

### Causa raíz

El merge de `develop` en la rama del login resolvió mal el conflicto en `AdminPanelProvider.php`: añadió `EnsureUserIsActive::class` al array `->middleware([])` **global** pero **perdió el `use App\Http\Middleware\EnsureUserIsActive;`**. Sin el import, `EnsureUserIsActive::class` resuelve al namespace actual → `App\Providers\Filament\EnsureUserIsActive`, **clase que no existe** → Laravel falla al instanciar el middleware.

Además quedaba **duplicado**: el helper `adminAuthMiddleware()` ya lo registra correctamente (vía `class_exists`) en `authMiddleware`, que es su lugar arquitectónico correcto (es un check **post-autenticación**: `$request->user()?->isSuspended()`).

**Evidencia:**
```
class_exists('App\Providers\Filament\EnsureUserIsActive')  → false  (lo que producía la línea)
class_exists('App\Http\Middleware\EnsureUserIsActive')     → true   (la clase real)
diff rama-login vs merge: una sola línea añadida (66a67)
```

### Corrección — `2b67604`

Se eliminó la línea del middleware global. El registro correcto en `authMiddleware` vía `class_exists` ya estaba presente.

```diff
             ->middleware([
                 EncryptCookies::class,
                 AddQueuedCookiesToResponse::class,
                 StartSession::class,
-                EnsureUserIsActive::class,
                 AuthenticateSession::class,
```

### Verificación

`GET /admin/login` → **HTTP 200**, render correcto en claro y oscuro, sin errores de consola. `EnsureUserIsActive` queda solo en el guard `class_exists` de `authMiddleware`.

---

## BUG-03 — `composer.lock` desincronizado con `composer.json`

🟠 **Severidad:** Media (no rompía el entorno actual, pero **rompería cualquier clon limpio / CI / producción**)
🔎 **Detección:** Al revisar cambios sueltos del working tree.

### Síntoma

El `composer.lock` commiteado en `develop` **no incluía** paquetes que `composer.json` exige:

| Paquete faltante en el lock | Constraint en `composer.json` | Usado por |
|---|---|---|
| `spatie/laravel-permission` | `^8.0` | Roles y permisos (Épica 1/2) |
| `spatie/laravel-medialibrary` | `^11.23` | Tabla `media` (Épica 3) |
| + árbol de deps (`composer/semver`, `spatie/image`, `maennchen/zipstream-php`, etc.) | — | — |

Un `composer install` en un clon limpio quedaría con dependencias incompletas → funciones de roles/permisos y media library rotas.

### Causa raíz

Otro artefacto del mismo merge: cuando las Épicas agregaron estos paquetes a `composer.json`, el `composer.lock` **no se regeneró** al resolver el conflicto.

### Corrección — `3f72864`

Se regeneró y commiteó el lock correcto (solo añade, no elimina ni hace downgrade).

### Verificación

```
composer install --dry-run
→ Verifying lock file contents can be installed on current platform.
→ Nothing to install, update or remove        ✅ (lock en sync con composer.json y vendor/)
```

---

## Mejora aplicada — Logo flotante

A petición de diseño, el logo del login pasó a flotar **fuera y por encima de la tarjeta**, más grande.

| Aspecto | Antes | Después |
|---|---|---|
| Tamaño (`brandLogoHeight`) | `2.5rem` (40px) | `10rem` (160px) |
| Posición | dentro de la tarjeta | `position:absolute`, flotando sobre el borde superior (gap 11px) |

> Nota técnica: el tamaño del logo lo controla un **estilo inline** que inyecta `brandLogoHeight` (gana sobre el CSS). Cambiarlo requiere solo `php artisan filament:optimize-clear`, **no** recompilar el theme.

---

## Higiene de repositorio — `948c58c`

`.atl/.skill-registry.cache.json` (un fingerprint de 64 bytes que se regenera con la actividad) estaba **trackeado** y generaba ruido en `git status` cada sesión. Se sacó del tracking (`git rm --cached`) y se ignoró en `.gitignore`. El registry real (`.atl/skill-registry.md`) sigue versionado.

---

## Observaciones

- **`.env` ausente tras re-clone.** El repo fue re-clonado y `.env` está gitignored (correcto), así que faltaba → `MissingAppKeyException`. Es **setup de entorno**, no un bug de código. Requiere `cp .env.example .env` + `php artisan key:generate` + configurar DB.
- **Estado de git cambiante durante la auditoría.** Hubo checkouts de rama en paralelo; al pasar por la rama feature (versión con bugs) el render daba 500. Esperado, no una regresión.
- **La rama feature ya estaba mergeada y sin commits propios.** Una rama de feature mergeada debe **eliminarse**, no mantenerse "actualizada" — mantenerla viva fue lo que causó confusión.
- **El theme de Filament no tiene HMR.** Al editar `theme.css` hay que recompilar con `npm run build:filament` (o `npm run build`).

---

## Recomendaciones

- [ ] **CI en cada PR que ejecute la app, no solo lea código.** Un workflow con `composer install`, `composer validate`, `npm run build` y un smoke test de `/admin/login` (esperar HTTP 200) habría cazado **los tres bugs** antes del merge.
- [ ] **Regenerar `composer.lock` al resolver merges que tocan `composer.json`.** Añadir `composer validate` como check obligatorio.
- [ ] **Ramas de feature efímeras:** ramificar → trabajar → PR → merge → **borrar**. No mantener ramas mergeadas.
- [ ] **No versionar archivos de cache.** Revisar que `.atl/`, cachés y artefactos generados estén en `.gitignore`.
- [ ] **Verificación en vivo antes de aprobar visuales.** Para un RFC cuyo objetivo es fidelidad visual, "aprobado por lectura de código" es insuficiente.
- [ ] **Documentar el workflow del theme:** Filament v3 requiere Tailwind v3; el theme se compila aparte con `build:filament` y no tiene HMR.

---

## Historial de commits (en `develop`)

| Commit | Tipo | Descripción |
|---|---|---|
| `2b67604` | fix(admin) | Quita `EnsureUserIsActive` duplicado del middleware global |
| `3f72864` | fix(deps) | Sincroniza `composer.lock` con `composer.json` (spatie permission + medialibrary) |
| `948c58c` | chore | Deja de trackear el cache `.atl/.skill-registry.cache.json` |
| `cc55422` | merge | PR #5 — integra theme + login (incluye BUG-01 corregido y la mejora del logo) |

---

## Lección de fondo

> **Tres bugs distintos nacieron del mismo merge mal cerrado** — un `use` import perdido, un lock no regenerado y un cache mal versionado. Ninguno era detectable revisando el diff a ojo. Solo aparecieron al **ejecutar la app**, **validar dependencias** y **limpiar el working tree**.

La conclusión práctica: la calidad no la garantiza la revisión humana del diff, sino un **PR con verificación automática que ejecute el sistema real**. Es la diferencia entre revisar el plano y caminar por el edificio terminado.

---

*Generado el 2026-06-18 · Auditoría y correcciones post-merge del RFC-059.*
