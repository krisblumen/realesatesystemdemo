# Épica 12.1 — Lotes de implementación (contrato de auditoría)

**Proyecto:** New Hauz — Plataforma Inmobiliaria (monolito Laravel)
**Incremento:** Épica 12.1 — Mejora UX del Hero
**Diseño normativo:** [`epica-12-1-mejora-ux-hero.md` v10](epica-12-1-mejora-ux-hero.md) — **GATE DE DISEÑO APROBADO** sobre `9fdbdbd` ([reauditoría](../audits/epica-12-1-reauditoria-diseno.md))
**Fuente única:** [Épica 12 §16 + §18.18](epica-12-administrador-contenidos-frontend.md); RFC-075/077/074 como espejos
**Rama:** `feature/epica-12-content-manager`
**Fecha:** 2026-07-26
**Estado:** 🟡 **P4-A ABIERTO — Lote 12.1-A en implementación**

Este documento es el **contrato que Codex audita** por lote: alcance, archivos, tests obligatorios y evidencia esperada. Regla de gate idéntica a la épica madre (§16.10): **cada lote abre el siguiente solo con veredicto `APROBADO`**; el informe se escribe en `docs/audits/epica-12-1-lote-{a|b}-auditoria-implementacion.md`.

---

## 1. Lotes

Orden **kernel-first**: primero la frontera de media (seguridad e integridad), después la superficie visible.

| Lote | Alcance | Cierra (del diseño v10) | Secciones normativas |
| --- | --- | --- | --- |
| **12.1-A — Pipeline de media + fronteras compartidas** | Colección `images` en disco privado; preview owner-only; promoción post-commit idempotente con volteo de `disk`; reconciliación y reporte; publisher con diff `added/removed` y merge bajo lock; `PublishedMediaReference`; endurecimiento de `FrontendMediaReference` (uuid); fix del renderer para soft-delete/recreación de clave. **Sin cambios de UI.** | C-4, C-5, C-6, C-7, C-9, C-11, C-12, M-1…M-7 (promoción/estados/identidad) | Diseño §7.6–§7.12, §0.8; épica §16.4 + §18.18 (3)(4)(5) |
| **12.1-B — Editor del hero + render** | Schema `hero` extendido (`text_align`, `logo_enabled`, `logo_size`); formulario estructurado del hero en `SectionsRelationManager` (reemplaza el Textarea **solo para `hero`**) con repeater de slides (§7.1–§7.5); `CtaFields` compartido; `presentHero()` (orden, defaults, matriz de fallback, filtro `promoted`, modo A/B); `hero.blade.php` con logo/alineación/carousel; `hero-carousel.js` + CSS del fade. | C-1, C-2, C-3, C-8, C-10, M-1…M-5 (schema/UI/render/a11y/CSP) | Diseño §6, §7.1–§7.5, §8, §9, §10, §11, §12 |

**Fuera de alcance de ambos lotes** (auditor: verificar que NO aparezca): pipeline para `FrontendService.image` (deuda §0.8); borrado físico/prune; `section_id` en el snapshot; conversiones/responsive images para `images`; cambios a policies, versionado optimista o formato del snapshot; formularios de otros tipos de sección.

---

## 2. Lote 12.1-A — contrato detallado

### 2.1 Archivos esperados

| Archivo | Cambio | Diseño |
| --- | --- | --- |
| `app/Models/FrontendSection.php` | `images` → `useDisk('frontend-private')`; sin `singleFile`/`onlyKeepLatest`; sin conversiones/responsive | §7.6 |
| `app/Services/Frontend/PublishedMediaReference.php` | **Nuevo**: `mediaIdsOf()`, `isReferencedByPublishedRevision()`, `owningPage()` (`withTrashed()`), `resolvePublished()`, `danglingPending()` (acotado a `FrontendSection`/`images`) | §7.8, §7.11 |
| `app/Services/Frontend/FrontendMediaReference.php` | Validación de formato uuid (`Str::isUuid`) en `resolve()`/`isEligible()` **antes** de la query | §7.10 |
| `app/Services/Frontend/FrontendPageRenderer.php` | Media publicada resuelta **solo** vía `resolvePublished()` (no `keyBy('section_key')` para autorizar); deja de duplicar la validación de uuid | §7.11 |
| `app/Http/Controllers/FrontendSectionMediaController.php` + `routes/web.php` | **Nuevo**: preview owner-only, **sin middleware `auth`**, policy real (`Gate` → rol+permiso), **404 uniforme en los 5 casos** | §7.7 |
| `app/Services/Frontend/FrontendPagePublisher.php` | **Aditivo**: secuencia de 9 pasos (§7.12) — snapshot anterior **bajo lock**, `added/removed`, locks `media.uuid ASC`, merge de flags, dispatch `afterCommit` | §7.12 |
| `app/Jobs/PromoteFrontendMedia.php` | **Nuevo**: locks `page → sección → media`; revalida referencia; copia conservando ruta relativa; verifica; **voltea `disk`/`conversions_disk`**; merge; invariantes de §7.8 | §7.8, §7.9 |
| `app/Console/Commands/ReconcileFrontendMediaPromotions.php` | **Nuevo**: dos barridos (redespacho + limpieza de `pending` colgante); programado `withoutOverlapping()->onOneServer()` en `routes/console.php` | §7.8 |
| `app/Console/Commands/ReportUnreferencedFrontendMedia.php` | **Nuevo**: solo lectura | §7.5 |

### 2.2 Invariantes que el auditor debe verificar (con test que las ejercite)

1. `promoted = true` ⇒ `pending_promotion` ausente.
2. `pending_promotion = true` ⇒ referenciada por la `published_revision` vigente.
3. `promoted` es **terminal** (perder referencia no despromueve).
4. Un solo lock order: `page → sections(id ASC) → media(uuid ASC)` en publisher **y** job; mutaciones draft siguen **sin** lock de `media`.
5. Ningún uuid malformado llega a PostgreSQL (`SQLSTATE 22P02` imposible desde las fronteras).
6. Ninguna ruta ejecuta `Media::delete()`/`forceDelete`; el archivo privado **no** se borra tras promover.
7. El render público solo emite media `promoted`; media no promovida se **omite** (sin placeholder ni «versión anterior»).
8. El formato del snapshot **no cambia** (sin `section_id`).

### 2.3 Matriz de tests del lote A

Concurrencia = **dos conexiones PostgreSQL independientes** (regla §16.11 de la épica), sin `sleep` como sincronización.

| # | Test | Tipo |
| --- | --- | --- |
| TA-1 | Media adjuntada en draft vive en `frontend-private` (assert del `disk` de la fila, no solo `getUrl()`) | Feature |
| TA-2 | Preview: **404 uniforme** en los 5 casos — anónimo; autenticado con `frontend.manage` directo **sin rol owner**; sección inexistente; uuid ajeno; **uuid malformado** (sin excepción SQL en logs); owner recibe el archivo por stream | Feature/HTTP |
| TA-3 | `resolve()`/`isEligible()` con `null`, `''`, `'not-a-uuid'`, uuid truncado ⇒ `null`/`false` **sin consulta inválida** | Unit/Feature |
| TA-4 | `resolvePublished()`: `null` ante uuid malformado / otra página / otra colección / `model_type` ≠ `FrontendSection` / inexistente; devuelve la Media con owner **soft-deleted** de la misma página | Feature |
| TA-5 | Tras el job: fila con `disk='public'`, `getUrl()` apunta al disco público, original existe en destino, **copia privada intacta**; job 2× ⇒ una sola copia | Feature/Queue |
| TA-6 | `pending_promotion` se marca en la transacción y **desaparece con rollback** | Feature/DB |
| TA-7 | Republicar media ya `promoted` ⇒ no escribe `pending_promotion`; residuo artificial ⇒ el job lo limpia (invariante 1) | Feature |
| TA-8 | **Cancelación de `pending`**: publicar A → republicar sin A antes del job ⇒ publisher limpia el flag; job despachado igual **no promueve**; reconciliación limpia el colgante | Feature/Queue |
| TA-9 | **Carrera concurrente publisher↔job** (2 conexiones): el que llega segundo espera el lock; resultado determinista; nunca queda promovida una media que la revisión final no referencia; caso simétrico | Feature/DB |
| TA-10 | **Soft-delete tras publicar**: la página pública sigue mostrando la imagen; cubre también un tipo **no-hero** (`team`) | Feature/HTTP |
| TA-11 | **Recreación de clave**: publicar A (`hero`) → soft-delete → crear B con el mismo `section_key` ⇒ la revisión vigente sigue resolviendo la media de **A**; nueva publicación de B cambia el owner deliberadamente | Feature |
| TA-12 | Reconciliación: redespacha lo no promovido referenciado; **no toca** flags de `FrontendService`/`FrontendSetting`; el scheduler no registra ningún comando que borre archivos | Feature/Schedule |
| TA-13 | Quitar/reordenar/reemplazar slides en payload no ejecuta `Media::delete()` (assert de fila y archivo) | Feature |
| TA-14 | Regresión: suite completa previa verde (publicación, stale publisher T-11, caché, render) | Suite |

### 2.4 DoD del lote A

- [ ] Todos los archivos de §2.1, sin tocar los «fuera de alcance».
- [ ] TA-1…TA-14 verdes sobre PostgreSQL real; Pint limpio; `npm run build` verde.
- [ ] Invariantes de §2.2 con test que las ejercite.
- [ ] Suite completa verde **con el preview server detenido**.
- [ ] Commits convencionales; excluidos `.atl/skill-registry.md`, `docs/audits/epica-12-lote-e-auditoria-implementacion.md`, `public/css/filament/admin/theme.css`.

---

## 3. Lote 12.1-B — contrato detallado

*(Se implementa solo con 12.1-A `APROBADO`.)*

### 3.1 Archivos esperados

| Archivo | Cambio | Diseño |
| --- | --- | --- |
| `config/frontend-sections.php` | + `hero_text_aligns`, `hero_logo_sizes`, **`hero_fallback`** (hero completo por página) y **`hero_variants`** (presentación por página, no editable) | §6.1, §8, §0.0 |
| `app/Services/Frontend/FrontendSectionSchema.php` | + tokens `?text_align`/`?logo_size`; + campos en `SPECS['hero']` | §6.1 |
| `app/Filament/Forms/Components/CtaFields.php` + `FrontendSettingsPage.php` | **Nuevo** componente compartido (`make()` + `guidance()`, `type` `->live()`); la página reusa y elimina los privados | §6.2 |
| `.../RelationManagers/SectionsRelationManager.php` | Formulario del hero (Texto/Botones/Presentación/Slides) **solo para `hero`**; adaptador de slides §7.1–§7.5 (upload `FileUpload` base a `frontend-private`, `addMediaFromDisk` que mueve, reenumeración de `sort_order`, todo vía `saveSectionDraft`) | §7.1–§7.5 |
| `app/Services/Frontend/FrontendPageRenderer.php` | + `presentHero()`: orden `(sort_order, media_id)`, defaults materializados, matriz de fallback por página/estado, filtro `promoted`, selección modo A/B | §8, §9 |
| `resources/views/frontend/sections/hero.blade.php` | **Único renderer del hero** (publicado y fallback). Logo, alineación, modos A/B excluyentes y variante por página. Modo A con `<img aria-hidden alt="">` — sin superficie inline | §9, §0.0 |
| `resources/js/hero-carousel.js` + `resources/css/app.css` | Fade CSS-only (`nh-hero-delay-0..5`), pausa/reanudar por JS Vite, `prefers-reduced-motion`; **sin inline** | §10 |

### 3.2 Matriz de tests del lote B

| # | Test | Tipo |
| --- | --- | --- |
| TB-1 | Schema: allowlists de `text_align`/`logo_size`; clave desconocida rechazada; payload sin campos nuevos válido (compat); regla `decorative`/`alt` | Unit/Feature |
| TB-2 | Rehidratación del formulario con 0/1/6 slides; guardar produce el payload canónico; reemplazo genera uuid nuevo sin borrar el anterior | Feature/Livewire |
| TB-3 | Reglas de imagen server-side: MIME, 3072 KB, mín. 1200×675, **SVG rechazado**, `alt` ≤ 150 — cada rechazo probado | Feature |
| TB-4 | Fallback: **5 rutas × cada fila de la matriz §8** (sin publicar / clave ausente / `[]` / no elegible-no promovida / válidas) vía HTTP/DOM | Feature/HTTP |
| TB-5 | Orden: payload invertido y con `sort_order` duplicado ⇒ orden determinista; reenumeración 0..n-1 al guardar | Feature |
| TB-6 | Modo A: capa `aria-hidden`, sin `role=img`, botón Pausar/Reanudar presente con >1 slide; 1 slide ⇒ estática sin control | Feature/DOM |
| TB-7 | Modo B: sin `aria-hidden`, **una** `<img>` con alt, sin autoplay, las demás slides no se renderizan | Feature/DOM |
| TB-8 | `prefers-reduced-motion`: sin animación ni temporizador en ambos modos | **Excepción contractual (ver §3.4)** |
| TB-9 | CSP: el hero renderizado no contiene `<script>`/`<style>` inline; clases de delay del set fijo | Feature/DOM |
| TB-10 | Logo: `alt=""`+`aria-hidden` con H1; `alt=site_name` sin H1; clase por `logo_size`; oculto por default | Feature/DOM |
| TB-11 | `CtaFields`: guía reactiva en los 5 tipos, en settings **y** en el hero; validación server-side vía `CtaResolver` | Feature/Livewire |
| TB-12 | El owner ve campos (no JSON) para `hero`; los demás tipos conservan el Textarea | Feature |
| TB-13 | Regresión: suite completa verde; QA visual **medido** a 390 px y desktop, sobre servidor aislado (§3.4) | Suite + medición reproducible |

### 3.3 DoD del lote B

- [ ] Archivos de §3.1; el Textarea JSON sigue para los tipos no-hero.
- [ ] TB-1…TB-13 verdes **con la excepción de §3.4 aplicada a TB-8**; Pint; build; suite completa con preview detenido.
- [ ] Verificación visual en vivo (nuestro loop: preview server → screenshot → detener → tests).

### 3.4 Excepción contractual — TB-8 (`prefers-reduced-motion`)

**Qué NO se prueba:** la rama de runtime con la preferencia activada, es decir, observar simultáneamente animación detenida, primera slide visible y control oculto **bajo `prefers-reduced-motion: reduce`**.

**Por qué:** el proyecto no tiene runner de JavaScript — `package.json` define únicamente `build` y `dev`. PHPUnit no ejecuta el módulo, y las herramientas de navegador disponibles no exponen emulación de esa media feature. Incorporar un runner (vitest/jsdom o similar) es una decisión de tooling que **excede el alcance del Lote B** y agrega dependencias que nadie pidió para este incremento.

**Alcance exacto de la excepción:** cubre SOLO la activación de la preferencia. No exime de demostrar que el mecanismo existe y funciona.

**Criterio de aceptación equivalente** (los tres, obligatorios):

1. **Regla presente en el CSS COMPILADO**, leída del CSSOM del navegador —no del archivo fuente—: `@media (prefers-reduced-motion: reduce)` debe anular la animación de `.nh-hero-slide` y dejar visible `.nh-hero-delay-0`.
2. **Guard de contrato en PHPUnit** sobre el source de CSS y JS, declarado explícitamente como guard y no como prueba de comportamiento.
3. **Comportamiento de la pausa verificado en navegador** por `animation-play-state` (`running` → `paused` por botón y por hover), que ejercita el mismo mecanismo de detención que usa la rama de reduced-motion.

**Cuándo caduca:** si el proyecto adopta un runner de JS, TB-8 es el **primer** caso a migrar y esta excepción se retira.

Evidencia: `docs/audits/artifacts/epica-12-1-lote-b/qa-visual-y-comportamiento.md`.

---

## 4. Instrucciones para el auditor (Codex)

1. **Contrato normativo:** diseño v10 (`epica-12-1-mejora-ux-hero.md`) + épica §16/§18.18. Cualquier desviación no declarada = hallazgo.
2. **Verificación base por lote:** `composer validate --strict` · `./vendor/bin/pint --test` · `npm run build` · suite completa sobre PostgreSQL real (`inmo_test`).
3. **Coherencia documental:** re-ejecutar el criterio del diseño §8 **sobre los directorios completos** `docs/epicas docs/rfc` ⇒ cero afirmaciones activas contradictorias.
4. **Concurrencia:** exigir dos conexiones PostgreSQL reales en TA-6…TA-9; rechazar tests secuenciales presentados como concurrentes.
5. **Aditividad:** `git diff` del lote no debe tocar policies, migraciones de dominio ajeno, formato del snapshot ni `FrontendService.image`.
6. **Evidencia de privacidad:** assert del `disk` de la fila y respuesta HTTP real de la ruta de preview — no solo unit tests.
7. Veredicto por lote en `docs/audits/epica-12-1-lote-{a|b}-auditoria-implementacion.md` con gate explícito: `APROBADO` / `RECHAZADO`. El lote B no inicia sin el A aprobado.
