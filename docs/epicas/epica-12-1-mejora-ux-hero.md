# Épica 12.1 — Mejora UX del Hero (editor amigable + logo, alineación, carousel y pipeline de media)

**Proyecto:** New Hauz — Plataforma Inmobiliaria (monolito Laravel)
**Incremento:** Épica 12.1 — Mejora UX del Hero
**Épica madre:** [Épica 12](epica-12-administrador-contenidos-frontend.md) — **§16 es la fuente única normativa**; este incremento la enmienda en **§18.18**
**RFC:** [RFC-075](../rfc/RFC-075-CONTENIDO-PAGINAS-INSTITUCIONALES-FRONTEND.md) (espejo subordinado a §16)
**Rama:** `feature/epica-12-content-manager`
**Fecha:** 2026-07-25
**Revisión:** **v11** — reconcilia el diseño con lo IMPLEMENTADO en el Lote B (M-B-1 de la [reauditoría de implementación](../audits/epica-12-1-lote-b-auditoria-implementacion.md)). El gate de diseño sobre v10 (`9fdbdbd`) sigue vigente; esto no reabre decisiones, documenta tres desviaciones deliberadas que el código ya ejecuta. Ver **§0.0**.
**Estado:** 🟢 **DISEÑO APROBADO (v10) + reconciliación de implementación (v11)**
**Acceso CMS:** exclusivo para usuarios con rol `owner`

---

## 0. Respuesta a las auditorías

### 0.0 Reconciliación con la implementación (v11)

Tres decisiones se tomaron **al implementar** el Lote B y el código ya las ejecuta. Se documentan acá porque un contrato que no describe lo que el sistema hace deja de servir como contrato — y el próximo lote leería la forma vieja.

| Lo que decía v10 | Lo que hace el código | Por qué |
| --- | --- | --- |
| `config('frontend-sections.hero_fallback_slides')`: solo las imágenes de fallback por página | **`config('frontend-sections.hero_fallback')`**: el hero COMPLETO por página (eyebrow, title, subtitle, CTAs, logo y slides) | C-B-1 obligó a que la ruta sin publicación pase por `presentHero()`. Para eso el fallback necesita el **texto**, no solo el fondo: con slides únicamente, una instalación nueva renderizaba un fondo sin título y **sin `<h1>`**. |
| Modo A: `div` con `background-image`, sin `<img>` (§16.1.1) | **`<img aria-hidden="true" alt="">`** dentro de la capa ya oculta | Un atributo `style="background-image:url(…)"` era la última superficie inline, y **ninguna directiva de CSP la admite sin `unsafe-inline`** (M-B-2 exigía cerrarlo). Para tecnología asistiva el resultado es idéntico: la capa entera está `aria-hidden` y las imágenes no tienen alt. Se propaga a la fuente única como punto **(6) de §18.18**. |
| No existía | **`config('frontend-sections.hero_variants')`**: `featured` (home), `compact` (contacto), `standard` (resto) | Unificar el renderer no debía cambiar cómo se ve el sitio. Es presentación **por página**, NO editable y **fuera del payload**: no toca el schema ni exige enmienda de §16.1.1. |

**Lo que NO cambió:** el schema del hero, el formato del snapshot, las invariantes de promoción, el orden de locks, la matriz de estados de §8 ni los dos modos DOM excluyentes.


### 0.1 Reauditoría de v9 → v10 (hallazgos abiertos)

| Hallazgo | Resolución en v10 | Sección |
| --- | --- | --- |
| **C-11** Quedaban RFC-077 y RFC-074 sin propagar — y **mi verificación del criterio fue defectuosa** | **Error propio reconocido:** declaré el criterio sobre `docs/epicas docs/rfc` (directorios) pero lo **ejecuté contra dos archivos sueltos**, produciendo un falso negativo. Ejecutado correctamente sobre los directorios, aparecían RFC-077 `:104,:142,:305` y RFC-074 `:159,:370`. **Corregidos:** RFC-077 suma el paso **4-bis** (locks `media.uuid ASC` + merge) y actualiza inventario a `FrontendMediaReference` + `PublishedMediaReference`; RFC-074 marca el flujo de estrategia B como **bloque histórico no normativo** y usa la clase real, aclarando que `FrontendService.image` está **fuera** del pipeline de 12.1 (por eso «sin lock» **sí** es correcto ahí). Criterio re-ejecutado sobre los directorios completos: **cero activos**. | RFC-077, RFC-074, §8 |
| **M-7** Faltaba API concreta para resolver media publicada por uuid+página | **Método nombrado**: `PublishedMediaReference::resolvePublished(string $uuid, FrontendPage $page, string $collection = 'images'): ?Media` — valida formato uuid, exige `model_type`/colección, resuelve propietario con `withTrashed()` y verifica pertenencia a la página. **Única vía** del renderer: sin queries ad-hoc ni autorización duplicada. | §7.8, §7.11 |
| **M-8** RFC-074 mezclaba estrategia A vigente con restos de B | Bloque de estrategia B delimitado como **histórico no normativo**; se explicita que la vigente es **A («guardar = publicar»)**. | RFC-074 |
| **Mn-1** `section_id` divergía del contrato de snapshot | **Descartado**: no se agrega al snapshot. La resolución no lo necesita (identidad = `Media.model_id`) y evitarlo mantiene **el formato del snapshot sin cambios**, de modo que las revisiones ya publicadas quedan arregladas sin republicar. | §7.11 |

### 0.2 Reauditoría de v8 → v9 (cerrados)

| Hallazgo | Resolución (v9) | Sección |
| --- | --- | --- |
| **C-11** Propagación incompleta: quedaban pasos e inventarios **activos** negando el lock de `media` en publicación | **Barrido sistemático** (no parches puntuales) sobre los cuatro puntos señalados: épica `:548` (lock completo del job), `:723` + nuevo paso **4-bis** (la publicación con promoción toma `media` por `uuid ASC` con merge), `:877` (inventario), y RFC-075 `:439`, `:441`, `:512`. Se distingue el motivo: la **validación** no necesita lock (nada borra media en v1); el lock existe por la **carrera de referencia**. Las mutaciones **draft** siguen sin lock —`:714` se conserva y se aclara—. Los registros históricos §18.x (B-3) **no** se tocan: eran correctos para su contexto. | épica §16.3/§16.4/inventario, RFC-075 |
| **C-12** `withTrashed()` no bastaba: soft-delete + recreación de `section_key` ata el snapshot al propietario equivocado | **Verificado en vivo** (A `hero` id 15 → soft-delete → B `hero` id 25 ⇒ `keyBy('section_key')` devuelve **B**, y la media de A no resuelve). **Identidad estable = la propia fila `media`**: el `model_id` de la media **es** su propietario, inmune a la recreación de claves; se verifica que pertenezca a una sección **de esta página** (`withTrashed()`) y a la colección `images`. **No cambia el formato del snapshot** ⇒ arregla también las revisiones ya publicadas. `section_id` se agrega a publicaciones nuevas solo para trazabilidad. | §7.11 |
| **M-5** Contratos activos citaban `FrontendMediaReferenceService` (clase inexistente) | Reemplazado por **`FrontendMediaReference`** en todos los contratos activos (épica `:714`, `:723`, `:877`; RFC `:441`), y añadido `PublishedMediaReference` a los inventarios. Las menciones en registros históricos §18.x quedan como historia. | épica, RFC-075 |
| **M-6** `danglingPending()` sin alcance definido | **Acotado explícitamente** a `model_type = FrontendSection` + `collection_name = images`. Excluye `FrontendService` y `FrontendSetting`, con prueba de que la reconciliación **no** toca sus flags. | §7.8, §14 |
| **Mn-1** «los tres documentos ya coinciden» era prematuro | Sustituido por una afirmación **verificable**: se declara el comando de verificación y se exige **cero** afirmaciones activas de «publicación sin lock de media». | §8 |

### 0.3 Reauditoría de v7 → v8 (cerrados)

| Hallazgo | Resolución (v8) | Sección |
| --- | --- | --- |
| **C-11** Contratos de lock incompatibles: §16.3 decía «publicar no bloquea `media`» y mi propia fila de riesgo repetía «el job solo toma `media`» | **Precisado en la fuente única** (§16.3 + punto **(4)** de §18.18): la afirmación sigue vigente **para mutaciones draft**, pero la **publicación con promoción SÍ** bloquea `media` con el orden global `page → sections(id ASC) → media(uuid ASC)`; se explica que el lock no protege contra borrado (no existe) sino contra la **carrera de referencia**. **Corregida la fila de riesgo** del diseño (era texto stale de v6). RFC-075 `:172` **no** requiere cambio: habla solo de mutaciones draft, donde la regla sigue siendo correcta. | épica §16.3/§18.18, §7.9, §15 |
| **C-12** Una sección soft-deleted rompía la resolución de media de la revisión publicada | **Verificado en vivo** (soft-delete de `hero` en `servicios`: `sections()` 3→2, el mapa del renderer pierde `hero`, `media_url` queda `null`). Es un **defecto preexistente del render (Lote F)** que afecta a **todos** los tipos con media. **Contrato:** renderer y `owningPage()` resuelven propietarios con **`withTrashed()`**; el propietario solo **acota** la consulta de media, la vigencia la da el snapshot. Propagado a la fuente única (punto **(5)** de §18.18). | §7.11, épica §16.4 |
| **M-4** El flujo del publisher no estaba alineado con la API concreta | **Secuencia paso a paso** del publisher (9 pasos) con inyección explícita de `PublishedMediaReference`, momento exacto de lectura del snapshot anterior (**bajo lock**), cálculo de `added/removed` y merge por uuid. | §7.12 |
| **Mn-2** `owningPage()` no distinguía relaciones con `SoftDeletes` | Nota explícita en el contrato del método y en §7.11. | §7.8, §7.11 |

### 0.4 Reauditoría de v6 → v7 (cerrados)

| Hallazgo | Resolución (v7) | Sección |
| --- | --- | --- |
| **C-10** La fuente única (§16.4) seguía prometiendo «versión pública anterior o placeholder», contradiciendo la conducta v6 | **Corregida la fuente única**: §16.4 marca esa frase como **superada** y declara la conducta vigente —media no `promoted` **se omite**; si no queda ninguna ⇒ **sin imagen**; **no existen** versión anterior ni placeholder—, con el motivo técnico (el snapshot guarda **una sola** referencia `media_id`; no hay representación de «versión anterior»). Registrado como punto **(3) de §18.18**, que además fija los estados e invariantes en la fuente. | épica §16.4, §18.18 |
| **M-3** El «predicado único» no era una API concreta y el job, con solo el lock de `media`, no hacía atómica la comprobación frente a una publicación concurrente | **(a) Se materializa** en una clase nombrada y testeable aislada: **`PublishedMediaReference`** (4 métodos, reutiliza el walker existente `FrontendPageContentService::mediaIds()`). **(b) El job pasa a tomar los locks en el orden global `page → sección → media`**: reteniendo el lock de página, `published_revision` no puede cambiar entre la evaluación y la escritura ⇒ la decisión es **atómica**. Se documenta el coste acotado del lock y la frontera BD↔filesystem. Pruebas de carrera **concurrentes en PostgreSQL**, no secuenciales. | §7.8, §7.9 |

### 0.5 Reauditoría de v5 → v6 (cerrados)

| Hallazgo | Resolución (v6) | Sección |
| --- | --- | --- |
| **M-2** `pending` huérfano cuando una publicación posterior quita la referencia antes de que corra el job | **Nueva transición `pending → draft`** con **invariante 2** (`pending ⇒ referenciada por la revisión vigente`) y **un predicado único** aplicado en tres puntos: publisher (determinista, por diff con la revisión anterior), job (revalida bajo lock antes de copiar, cierra la carrera en vuelo) y reconciliación (barrido de limpieza). Nunca borra archivos. `promoted` se declara **terminal**. | §7.8 |
| **Mn-1** «validador ya adoptado por el proyecto» no era exacto | Redacción corregida: `Str::isUuid()` está **disponible en la versión instalada de Laravel**; el código actual usa regex duplicadas y esta es precisamente la adopción que el incremento introduce. | §7.10 |
| **Mn-2** Referencias históricas al fallback de 4 URLs | Marcadas como **históricas/home-scoped** en la épica (`:793`, `:1037`) y en RFC-075 (`:5`); referencias `welcome.blade.php:5-10` → `:12-16`. | épica, RFC-075 |

### 0.6 Reauditoría de v4 → v5 (cerrados)

| Hallazgo | Resolución (v5) | Sección |
| --- | --- | --- |
| **C-9** UUID malformado podía llegar a PostgreSQL (`SQLSTATE 22P02`) antes del 404 | **La validación sintáctica se mueve a la frontera compartida**: `FrontendMediaReference::resolve()`/`isEligible()` devuelven `null`/`false` ante un uuid malformado, **antes** de tocar la query. Se elimina la duplicación del regex en el renderer (era el *drift* que el propio docblock de la clase advertía). Todo llamador —renderer, servicio y el controlador nuevo— queda protegido por construcción. | §7.7, §7.10 |
| **M-1** `pending_promotion` quedaba stale al republicar media ya promovida | **Máquina de estados explícita con invariante**: `promoted = true ⇒ pending_promotion ausente`. El publisher **no** marca `pending_promotion` si ya está `promoted`; y el job, en su salida temprana, **limpia** `pending_promotion` bajo el mismo lock (doble garantía idempotente). | §7.8 |
| **Mn-1** Frase stale en el cuerpo de RFC-075 | Marcada como **texto histórico no normativo**, tachada y redirigida a la matriz §8; se corrige `welcome.blade.php:5-10` → `:12-16`. | RFC-075 `:301-306` |

### 0.7 Reauditoría de v3 → v4 (cerrados, se conservan)

La auditoría de v4 confirmó **cerrados C-1 a C-8** y todos los medios/menores previos. Se mantienen sin cambios:

| Hallazgo | Resolución (v4) | Sección |
| --- | --- | --- |
| **C-2** La épica seguía contradiciendo el fallback por página | **Se enmendó la FUENTE ÚNICA**: épica §16.1.1 y §16.7 ahora declaran el fallback por página (registro en **§18.18**). RFC-075 pasó a **espejo subordinado**. Los tres documentos dicen lo mismo. | §8 |
| **C-4** La promoción no definía la representación pública de `getUrl()` | **Estrategia elegida y explícita:** tras copiar y verificar, el job **cambia `disk` y `conversions_disk` de la fila `Media`** bajo lock. `getUrl()` pasa a resolver contra el disco público sin resolvers paralelos. | §7.8 |
| **C-5** `can:frontend.manage` no expresa el doble gate real | Autorización por la **policy real** del modelo (`Gate::authorize('view', $section)` ⇒ `hasRole('owner') && can('frontend.manage')`). Sin middleware de permiso suelto. | §7.7 |
| **C-6** `auth` redirige al anónimo (no 403/404 uniforme) | **Contrato fijado: 404 uniforme** para anónimo, no-owner, sección inexistente y uuid ajeno. **Sin middleware `auth`** en la ruta; el controlador aborta 404. Anti-enumeración. | §7.7 |
| **C-7** Carrera sobre `custom_properties` | **Protocolo de concurrencia:** orden de locks `page → sections(id ASC) → media(uuid ASC)`; relectura y **merge** de `custom_properties` bajo lock, nunca sobrescritura del JSON completo. | §7.9 |
| **C-8** `FileUpload` base contradecía el mandato de `NonDestructiveMediaUpload` | **Reconciliado en la fuente única** (§18.18): el mandato aplica a campos **con relación Spatie**; el estado de lista usa `FileUpload` base, que **no tiene ruta de borrado**, con la misma garantía contractual y pruebas. | §7.1 |
| **M-1** «versión pública anterior o placeholder» ambiguo y contradictorio | **Eliminado.** Conducta única: **media no promovida ⇒ esa slide no se renderiza**; si no queda ninguna ⇒ **sin imagen** (coherente con §8). | §7.8, §8 |
| **M-2** Conversiones/responsive sin tratamiento | La colección `images` **no registra conversiones ni `withResponsiveImages()`** (verificado: `FrontendSection` no declara ninguna). La promoción trata **solo el original**; queda **prohibido** agregar conversiones sin extender el job. | §7.8 |
| **M-3** El claim de §16.4 podía leerse como global | **Tabla de alcance** explícita: qué cláusulas de §16.4 cierra 12.1 (para `FrontendSection.images`) y cuáles siguen abiertas (`FrontendService.image`). | §0.3 |

### 0.8 Cambio de alcance respecto de v2 (declarado)

La v2 decía «el dominio de publicación no se toca». **Ya no es cierto y se declara:** el publisher recibe **pasos aditivos** (marcar `pending_promotion`, lock de `media`, dispatch en `afterCommit`). **No** cambian el lock de página/secciones, el snapshot, el versionado optimista ni `expected_draft_revision`.

### 0.9 Alcance de §16.4 (deuda declarada)

| Cláusula §16.4 | `FrontendSection.images` (12.1) | `FrontendService.image` |
| --- | --- | --- |
| Disco privado para draft | ✅ Cierra | ❌ **Abierta** (deuda) |
| Preview owner-only | ✅ Cierra | ❌ **Abierta** |
| Promoción post-commit idempotente | ✅ Cierra | ❌ **Abierta** |
| Reconciliación programada | ✅ Cierra (comando común, reutilizable) | ❌ **Abierta** |
| Sin borrado físico / `forceDelete` prohibido | ✅ Ya vigente, se conserva | ✅ Ya vigente |
| Uploader sin ruta de borrado | ✅ Cierra (§7.1) | ✅ Ya vigente (`NonDestructiveMediaUpload`) |

**12.1 NO clausura §16.4 globalmente.** Las imágenes de servicios siguen requiriendo el mismo aislamiento, en incremento propio.

### 0.10 Hueco preexistente que 12.1 cierra (verificado en código)

El pipeline de §16.4 **no existe** en el repositorio: no hay controlador owner-only de media frontend; `FrontendSection.php:49-52` registra `images` **sin `useDisk`** y `config/media-library.php:36` usa disco `public` por defecto (⇒ la media de draft sería pública); no hay `promoted`/`pending_promotion`; no existen `PromoteFrontendMedia`, `ReconcileFrontendMediaPromotions` ni comandos `frontend:media:*`. Es **deuda de los lotes A–G**, no introducida por este diseño.

### 0.11 Hallazgos ya cerrados en rondas previas

C-1 (contrato de lista) · C-3 (modos DOM A/B) · M-1 `sort_order` · M-2 CSP · M-3 `CtaFields` · M-4 reglas de imagen · M-5 logo · Mn-1…Mn-4. Se conservan.

---

## 1. Objetivo

1. Reemplazar el `Textarea` «Contenido (JSON)» del hero por un formulario estructurado (la UI que RFC-075 «Interfaz en Filament» ya aprobó).
2. Elevar el hero con **logo de marca**, **alineación** y **carousel con fade**.
3. Cerrar la **frontera de privacidad de media** de §16.4 para `FrontendSection.images`.

---

## 2. Base normativa

| Contrato | Fuente | Aporte |
| --- | --- | --- |
| Schema `hero`: `eyebrow?, title, subtitle?, primary_cta?, secondary_cta?, slides[0..6]{media_id, alt, decorative, sort_order}` | §16.1.1 / [`FrontendSectionSchema`](../../app/Services/Frontend/FrontendSectionSchema.php) | El formulario produce exactamente este payload. |
| Slides `0..6`; orden por `sort_order`; `animation-delay` derivado; `decorative:true`⇒`alt:null`; `slides:[]` no revive fallback; las slides son **fondo decorativo**, emitido como **`<img aria-hidden="true" alt="">`** dentro de la capa oculta (la técnica `background-image` de §16.1.1 quedó superada por §0.0 / §18.18 punto 6: exigía un atributo `style` inline incompatible con CSP; la intención de accesibilidad se conserva íntegra) | §16.1.1 (`:450-454`) + **§0.0 / §9.3** | Carousel, orden, a11y. |
| **Fallback por página** (enmienda) | épica **§16.1.1, §16.7, §18.18** | Matriz §8. |
| Media draft privada, preview owner-only, promoción post-commit idempotente, reconciliación, **render solo `promoted`**, sin borrado físico | épica §16.4 | §7.6–§7.9. |
| **Uploader: el mandato aplica a campos con relación Spatie** (enmienda) | épica **§16.4, §18.18** | §7.1. |
| Edición draft atómica (lock page→sections, validación, bump `draft_revision`) | §16.3 / [`FrontendPageContentService`](../../app/Services/Frontend/FrontendPageContentService.php) | Se reutiliza. |
| Presenter único (público **y** preview) | [`FrontendPageRenderer::present()`](../../app/Services/Frontend/FrontendPageRenderer.php) `:142-200` | Boundary de orden, defaults, fallback y filtro `promoted`. |
| Policies owner-only reales | [`FrontendSectionPolicy:15-50`](../../app/Policies/FrontendSectionPolicy.php) — `hasRole('owner') && can('frontend.manage')` | Autoriza el preview (§7.7). |
| Logo on-dark | RFC-071 / [`FrontendSettingsService`](../../app/Services/Frontend/FrontendSettingsService.php) `:115-120` | `$profile['brand']['logo_dark_url']`. |
| CTAs `{label,type,target}` | RFC-073 / [`CtaResolver`](../../app/Support/Frontend/CtaResolver.php) | Botones del hero. |
| Media privada owner-only ya probada | [`ContratoMediaController`](../../app/Http/Controllers/ContratoMediaController.php) | Patrón base (con las correcciones C-5/C-6). |

---

## 3. Evidencia verificada

| Área | Verificado | Implicación |
| --- | --- | --- |
| Editor | `SectionsRelationManager:31-42` — `Textarea` JSON. | Se reemplaza (solo hero). |
| Render hero | `hero.blade.php:7-36` — solo primera slide; texto fijo a la izquierda; sin logo. | Cambios de render. |
| Heroes por página | **Cada página tiene su propia fila** `hero` con payload propio (ids 1, 9, 15, 18, 23). | Lo compartido es la **plantilla**, no el contenido: no hace falta `hero_home` aparte (§18.18). |
| Uploader | `NonDestructiveMediaUpload:22-63` — hidrata single-uuid por **columna**; `deleteAbandonedFiles()` no-op. | No aplica a estado de lista (§7.1). |
| Presenter | `FrontendPageRenderer:116-200` — resuelve `media_url` con `$media?->getUrl()`; conserva orden; sin `presentHero()`. | Orden/fallback/`promoted` van acá. |
| **`getUrl()`** | `DefaultUrlGenerator:12` → `$this->getDisk()->url(...)` — **usa el disco de la fila `Media`**. | Copiar bytes + flag **no** hace pública la URL ⇒ hay que voltear `disk` (§7.8). |
| Conversiones | `FrontendSection` **no** declara `registerMediaConversions` ni `withResponsiveImages()`. | Promoción del **original solamente** (M-2). |
| Columnas `media` | Incluye `disk` y `conversions_disk`. | El volteo es un `UPDATE` de dos columnas. |
| Disco privado | `config/filesystems.php:53-62` → `frontend-private` **ya existe**. | Se usa tal cual. |
| Ruta privada precedente | `routes/web.php:45-49` — solo middleware `auth`. | **Insuficiente** (C-5/C-6): se corrige en §7.7. |
| Fallbacks reales | home = 4 URLs (`welcome.blade.php:12-16`); `nosotros:11`, `servicios:11`, `inversionistas:11` = 1 PNG c/u; `/contacto` sin fondo. | Matriz §8. |
| CTA actual | `FrontendSettingsPage:159-219` — `ctaFields()` privado **sin** `->live()`; guía en `targetGuidance()`. | §6.2. |

---

## 4. Problema

1. El owner no puede editar el hero sin saber JSON.
2. El hero guarda hasta 6 slides pero muestra una sola, sin rotación.
3. Faltan logo, alineación y carousel.
4. La media editorial en borrador **no tiene frontera de privacidad**.

---

## 5. Alcance

### Incluye
1. Formulario estructurado del hero (reemplaza el `Textarea` **solo para `hero`**).
2. Schema hero + `text_align`, `logo_enabled`, `logo_size`.
3. Render: logo, alineación, carousel con modos A/B.
4. `CtaFields` compartido (con `guidance`).
5. **Pipeline §16.4 para `FrontendSection.images`** (§0.6).

### No incluye
- Formularios de los otros 13 tipos.
- Pipeline para `FrontendService.image` (deuda §0.3).
- Borrado físico / prune (fuera de v1).
- Cambios en lock de página/secciones, snapshot o `expected_draft_revision`.
- Conversiones o responsive images para `images` (prohibidas sin extender el job).

---

## 6. Schema y CTAs

### 6.1 Delta de `SPECS['hero']`

Tokens allowlisted nuevos (mismo mecanismo que el token `layout` existente):
- `?text_align` → `config('frontend-sections.hero_text_aligns')` = `['left','center','right']`
- `?logo_size` → `config('frontend-sections.hero_logo_sizes')` = `['sm','md','lg','xl']`

```
'hero' => [
    'eyebrow' => '?string', 'title' => 'string', 'subtitle' => '?string',
    'primary_cta' => '?cta', 'secondary_cta' => '?cta',
    'slides' => ['list', 6, ['media_id' => 'media', 'alt' => '?string', 'decorative' => '?bool', 'sort_order' => 'int_min0']],
    'text_align'   => '?text_align',   // default: left
    'logo_enabled' => '?bool',         // default: false
    'logo_size'    => '?logo_size',    // default: md
],
```

Opcionales; validados en borrador **y** publicación; fuera de allowlist ⇒ rechazo; **sin migración** (`jsonb`); snapshots existentes siguen válidos.

### 6.2 `CtaFields` compartido

Se extraen **juntos** `ctaFields()` **y** `targetGuidance()` a **`App\Filament\Forms\Components\CtaFields`**:
- `CtaFields::make(string $prefix = '', bool $withLabel = true): array` → `[label?, type, target]`, rutas `{$prefix}.label|type|target` (`''` ⇒ sin punto, para repeaters).
- `type` = `Select` **`->live()`** con las 5 opciones.
- `target` toma `placeholder`/`helperText` de `CtaFields::guidance(?string $type)`.
- Consumidores: `FrontendSettingsPage` **y** el formulario del hero.
- Validación server-side: `CtaResolver::resolve()`. La UI no es autoridad.

---

## 7. Contrato de media

### 7.1 Uploader y estado del formulario (C-1, C-8)

`Repeater('slides')`, máx. 6, reordenable. Por item: `media_id` (**hidden**), `upload` (`FileUpload` **base**, `->disk('frontend-private')->directory("section-uploads/{$sectionId}")->image()`, efímero), `alt` (≤150), `decorative` (default `true`), `sort_order` (derivado).

**Reconciliación normativa (§18.18):** el mandato de `NonDestructiveMediaUpload` aplica a campos **con relación Spatie**, porque su propósito es neutralizar `deleteAbandonedFiles()`. El estado de **lista** vive en el payload (array de `media_id`), no en una columna única, por lo que no puede usar su hidratación single-UUID. `FileUpload` base **no establece relación Spatie y no tiene ninguna ruta de borrado**. Rige la **misma garantía contractual** y las mismas pruebas de no borrado. Prohibidos: `SpatieMediaLibraryFileUpload` directo, `singleFile()`, `onlyKeepLatest()`, `forceDelete`, borrado físico.

### 7.2 Hidratación

`media_id` → preview por la **ruta owner-only** (§7.7), resolviendo con `FrontendMediaReference::resolve($media_id, $section, 'images')`. `upload` arranca vacío (solo para reemplazar). Pruebas: **0, 1 y 6** slides.

### 7.3 Copia del temporal

- **Estado recibido:** `FileUpload` con `disk('frontend-private')` entrega **ruta relativa (string)** en ese disco.
- **Revalidación server-side previa:** MIME real, peso y dimensiones mínimas (§11) **antes** de adjuntar.
- **API:** `$section->addMediaFromDisk($path, 'frontend-private')->toMediaCollection('images', 'frontend-private')` — **mueve** el archivo (sin `preservingOriginal`), sin duplicados ni temporal colgante.
- El `uuid` resultante va a `media_id`; `upload` se descarta del payload.

### 7.4 Guardado (orden exacto)

`EditAction->using()` → `saveSectionDraft` (servicio sin cambios):
1. Validar **metadatos** (alt/decorative/regla cruzada) sin tocar disco.
2. Revalidar y adjuntar cada `upload` nuevo (§7.3) → `media_id`.
3. Conservar `media_id` de slides sin upload nuevo.
4. Reenumerar `sort_order = 0..n-1` según el repeater.
5. `saveSectionDraft(...)` revalida schema + elegibilidad bajo lock y bumpea `draft_revision`.

### 7.5 Orden y no destrucción

- `sort_order` reenumerado 0..n-1; el presenter ordena por `(sort_order asc, media_id asc)` (desempate estable).
- Quitar/reordenar reescribe **solo** el payload; **nunca** `Media::delete()`. Reemplazar **no borra** la anterior: cambia el uuid.
- **upload-ok + save-fail:** el archivo queda en disco privado **sin referencia** (acumulación no destructiva, §16.4/§18.13), visible en `frontend:media:report-unreferenced`. Adjuntar recién en el paso 2 minimiza huérfanos.

### 7.6 Colección privada

`FrontendSection::registerMediaCollections()` → `addMediaCollection('images')->useDisk('frontend-private')`. **Sin** `singleFile()`/`onlyKeepLatest()`. **Sin** conversiones ni `withResponsiveImages()` (M-2): agregarlas exige extender el job de promoción.

### 7.7 Preview owner-only (resuelve C-5 y C-6)

- **Ruta:** `GET /admin/frontend/secciones/{section}/media/{uuid}` → `FrontendSectionMediaController`.
- **Sin middleware `auth`** (evita el redirect 302 que rompía el contrato uniforme).
- **Autorización por la policy real**, no por permiso suelto:
  1. `abort_unless(Auth::check(), 404)`
  2. `abort_unless(Gate::allows('view', $section), 404)` ⇒ ejecuta `FrontendSectionPolicy` = `hasRole('owner') && can('frontend.manage')`
  3. `abort_unless(FrontendMediaReference::resolve($uuid, $section, 'images') !== null, 404)` — el uuid debe ser **sintácticamente válido** (§7.10, garantizado por la frontera) y pertenecer a **esa** sección y colección.
- **Contrato de respuesta: 404 uniforme** en **cinco** casos: anónimo, autenticado no autorizado (incluido `frontend.manage` directo sin rol owner), sección inexistente, uuid ajeno y **uuid malformado**. Anti-enumeración: nunca se distingue «no existe» de «no podés» de «está mal escrito».
- **Ningún uuid malformado alcanza la base de datos** (C-9): la frontera lo rechaza antes de construir la query, de modo que la ruta no puede provocar `SQLSTATE 22P02` con una entrada trivial.
- Sirve el archivo por stream desde `frontend-private`. **Nunca** emite URL pública.
- **Nota:** un usuario con `frontend.manage` asignado directo **sin** rol owner recibe 404 (la policy exige ambos). Caso de prueba obligatorio.

### 7.8 Promoción draft → público (resuelve C-4, M-1, M-2)

**Estrategia elegida:** *voltear el disco de la fila `Media` tras copia verificada.* Se descartan las alternativas (representación pública separada / resolver paralelo) porque duplicarían la fuente de verdad del uuid publicado; ésta mantiene **una sola** representación, como exige §16.4.

- `published_revision` referencia media **solo por `media_id`**.
- **Durabilidad:** dentro de la **misma transacción** del snapshot, cada `media_id` referenciado **que no esté ya `promoted`** se marca `pending_promotion: true` en `media.custom_properties` (**sin cambio de schema**), con el protocolo de §7.9. Un rollback elimina snapshot y marca juntos.

**Máquina de estados (resuelve M-1).** Estados en `custom_properties`, con **invariante: `promoted = true` ⇒ `pending_promotion` ausente**.

| Estado | Flags | Significado |
| --- | --- | --- |
| `draft` | sin flags | Media adjunta, nunca publicada. Vive solo en `frontend-private`. |
| `pending` | `pending_promotion: true` | Referenciada por una revisión publicada, aún no copiada al disco público. |
| `promoted` | `promoted: true` (sin `pending_promotion`) | Copiada y verificada; la fila apunta al disco público. |

| Transición | Actor | Regla (bajo lock, con merge §7.9) |
| --- | --- | --- |
| `draft → pending` | Publisher | Solo si `promoted !== true`. **Si ya está `promoted`, NO escribe nada** (republicar media ya pública es no-op). |
| `pending → promoted` | Job | **Revalida la referencia** (predicado único, abajo); copia, verifica, voltea `disk`, marca `promoted: true` y **quita** `pending_promotion`. |
| `promoted → promoted` | Job | Salida temprana **idempotente**: si detecta `promoted === true` **y** un `pending_promotion` residual, lo **limpia** bajo el mismo lock y termina. |
| **`pending → draft`** (pérdida de referencia) | Publisher · Job · Reconciliación | Si la media **ya no está referenciada** por la `published_revision` vigente, se **quita `pending_promotion`** sin copiar y sin borrar archivos. Ver «Cancelación» abajo. |

**`promoted` es TERMINAL.** Perder la referencia **no** desmarca ni "despromueve" una media ya pública: sus bytes ya son públicos, y v1 no borra archivos. Solo `pending` se cancela.

**Invariantes** (verificadas por test):
1. `promoted = true` ⇒ `pending_promotion` **ausente**.
2. `pending_promotion = true` ⇒ la media **está referenciada** por la `published_revision` vigente.
3. No existe transición de salida desde `promoted`.

**Cancelación de `pending` por pérdida de referencia (resuelve M-2).** Escenario: se publica una revisión que referencia A (A queda `pending`); **antes** de que corra el job, una publicación posterior reemplaza o elimina A. La `published_revision` vigente ya no la referencia, la reconciliación no la ve (solo recorre referenciadas no promovidas) y A quedaría `pending` para siempre, violando la invariante 2.

**Una sola fuente de verdad, tres puntos de aplicación.** La autoridad es un único predicado —«¿este uuid está referenciado por la `published_revision` vigente?»— materializado en una **clase concreta y testeable de forma aislada** (mismo criterio que §7.10: una definición, varios llamadores):

**`App\Services\Frontend\PublishedMediaReference`** *(nuevo)*
| Método | Contrato |
| --- | --- |
| `mediaIdsOf(FrontendPage $page): array<string>` | uuids referenciados por `published_revision`. Reutiliza `FrontendPageContentService::mediaIds()` (ya público) para recorrer el snapshot; **no** duplica el walker. |
| `isReferencedByPublishedRevision(string $uuid, FrontendPage $page): bool` | Predicado autoritativo. `false` si el uuid es malformado (§7.10), si la página no tiene snapshot, o si no aparece en él. |
| `owningPage(Media $media): ?FrontendPage` | Resuelve `media → FrontendSection → FrontendPage` **con `withTrashed()`** (§7.11): una sección borrada lógicamente sigue siendo propietaria válida de su media publicada. Permite al job conocer la página a bloquear (una media pertenece a **una** sección, y una sección a **una** página). |
| `resolvePublished(string $uuid, FrontendPage $page, string $collection = 'images'): ?Media` | **(M-7)** Resolución de media para una **revisión publicada**, sin conocer el propietario de antemano: valida **formato uuid** (§7.10) → localiza la fila `Media` → exige `model_type = FrontendSection` y `collection_name = $collection` → resuelve el propietario con **`withTrashed()`** → verifica que pertenezca a **`$page`**. Devuelve `null` en cualquier fallo. **Es la única vía** por la que el renderer resuelve media publicada: no consulta `Media` por su cuenta ni duplica la autorización. |
| `danglingPending(): iterable<Media>` | Filas con `pending_promotion` **no** referenciadas por ninguna `published_revision` vigente. **Acotado (M-6)** a `model_type = FrontendSection` **y** `collection_name = 'images'`: **excluye** `FrontendService` y `FrontendSetting`, que están fuera del alcance de 12.1 (§0.8). Alimenta el barrido de la reconciliación. |

Los tres actores consultan **esta misma clase**; no son verdades distintas, es el mismo predicado evaluado en momentos distintos:

| Punto | Momento | Acción |
| --- | --- | --- |
| **Publisher** (determinista) | En la transacción de publicación | Calcula los `media_id` de la revisión **anterior** que **no** están en la nueva; a los que sigan `pending` (no `promoted`) les **quita** el flag. Mismo orden de locks `page → sections(id ASC) → media(uuid ASC)`. |
| **Job** (cierra la carrera en vuelo) | Bajo los locks `page → sección → media` (§7.9), **antes de copiar** | Si el predicado es falso ⇒ **quita `pending_promotion`, no copia y termina**. Impide promover bytes que ya nadie referencia (un job despachado antes del reemplazo). |
| **Reconciliación** (red de seguridad) | Ejecución programada | Barre filas con `pending_promotion = true` **no** referenciadas por ninguna `published_revision` vigente y **limpia** el flag. Recupera casos de proceso caído entre publicación y job. |

En los tres casos: **nunca se borran archivos** (la media vuelve a ser `draft` en disco privado, inventariable por `frontend:media:report-unreferenced`) y se respeta el merge bajo lock de §7.9.

Doble —de hecho, triple— garantía deliberada, igual que en la invariante 1: el publisher no crea flags stale, el job no promueve lo desreferenciado, y la reconciliación limpia lo que se haya escapado.

- **`DB::afterCommit`** → despacha `PromoteFrontendMedia` por `media_id`.
- **`PromoteFrontendMedia($uuid)` — idempotente:**
  1. Resuelve la página propietaria (`PublishedMediaReference::owningPage`) y abre transacción tomando los locks en el **orden global**: `FrontendPage` → `FrontendSection` → fila `media` (§7.9). **Esto es lo que hace atómica la decisión** (M-3): mientras el job retiene el lock de la página, ninguna publicación puede cambiar `published_revision`, así que el predicado no puede invalidarse entre la lectura y la escritura.
  2. Si `custom_properties.promoted === true` ⇒ **limpia `pending_promotion` si está presente** y termina sin copiar.
  2-bis. **Revalida la referencia** con `isReferencedByPublishedRevision()`: si la media **ya no** está referenciada ⇒ **quita `pending_promotion`, no copia y termina** (transición `pending → draft`, §M-2).
  3. Copia el **original** de `frontend-private` al disco público **conservando la ruta relativa** de Spatie (`{id}/{file_name}`), de modo que el path siga siendo válido tras el volteo.
  4. **Verifica** existencia y tamaño en destino. Cualquier fallo ⇒ no marca y reintenta.
  5. **`UPDATE` de la fila:** `disk = 'public'`, `conversions_disk = 'public'`, y `custom_properties` **mergeado** (§7.9): `promoted: true`, se quita `pending_promotion`.
  6. Commit. A partir de acá **`getUrl()` resuelve contra el disco público** sin resolvers paralelos.
- **El archivo privado NO se borra** (sin borrado físico en v1): queda inventariado por `frontend:media:report-unreferenced`.
- **Solo el original.** La colección no tiene conversiones ni responsive images; si alguna vez se agregan, el job **debe** extenderse para promover la familia completa antes de marcar `promoted` (prohibido dejarlo implícito).
- **`ReconcileFrontendMediaPromotions`:** comando idempotente con **dos barridos** complementarios, programado como `frontend:media:reconcile` con `withoutOverlapping()->onOneServer()`:
  1. **Redespacho:** `media_id` **referenciados** por revisiones publicadas y **no** `promoted` ⇒ vuelve a despachar el job.
  2. **Limpieza (M-2):** filas con `pending_promotion = true` **no** referenciadas por ninguna `published_revision` vigente ⇒ **quita el flag** (`pending → draft`), sin copiar ni borrar archivos.
- **Conducta única ante media no promovida (M-1):** el presenter **omite esa slide**. Si no queda ninguna renderizable ⇒ **sin imagen** (§8). **No** existe «versión pública anterior» ni placeholder — esa frase queda eliminada del contrato.
- **Fallo de dispatch:** se registra con `media_id` y revisión; **no** revierte una publicación confirmada. La reconciliación es el mecanismo de recuperación.

### 7.9 Concurrencia sobre `custom_properties` (resuelve C-7)

- **Orden de locks global:** `FrontendPage` → `FrontendSection` (`id ASC`) → `media` (**`uuid ASC`**). **Publisher y job toman los tres en ese mismo orden** (resuelve M-3). Un solo orden para todos los actores ⇒ **sin ciclos de espera**.
- **Por qué el job también bloquea la página (M-3).** Si el job tomara solo `media`, existiría esta carrera: bloquea A, lee una `published_revision` que aún la contiene, empieza a promover, y una publicación concurrente retira A ⇒ el job dejaría públicos bytes que ya nadie referencia, contradiciendo la invariante 2. Reteniendo el lock de la página, `published_revision` **no puede cambiar** entre la evaluación del predicado y la escritura del estado: la decisión es atómica. El job resuelve la página con `PublishedMediaReference::owningPage()` (una media pertenece a una sección, y una sección a una página).
- **Coste del lock acotado y aceptado.** El job retiene el lock de página durante la copia del archivo. Está acotado por el límite de **3 MB** por imagen (§11) sobre disco local, y publicar es una acción **poco frecuente y disparada por el owner**, no una ruta de request público. Se prefiere una publicación que espere milisegundos a una promoción no atómica.
- **Frontera BD↔filesystem.** Si la transacción del job fallara **después** de copiar, el archivo público queda huérfano pero la fila sigue en `frontend-private` y **sin** `promoted`: el render **nunca** lo sirve (solo emite `promoted`) y queda inventariado por `frontend:media:report-unreferenced`. Coherente con §16.4: el filesystem no participa del rollback, por eso el estado autoritativo es la fila, no el archivo.
- **Observabilidad (opcional pero especificada):** el publisher registra en `custom_properties` la `revision` que autorizó la promoción; el job loguea revisión autorizante vs. observada. Sin contenido editorial en logs.
- **Merge, nunca sobrescritura:** toda escritura de `custom_properties` **relee la fila bajo lock**, mezcla únicamente las claves propias (`pending_promotion`, `promoted`) y persiste el JSON resultante. Está prohibido escribir el JSON construido desde un modelo cargado antes del lock.
- **Pruebas PostgreSQL concurrentes:** publisher y job en paralelo; publicación posterior no borra un `promoted` previo; rollback de publicación no deja `pending_promotion`; **republicar media ya promovida no deja flag stale** (invariante §7.8).

### 7.10 Frontera única de validación de `uuid` (resuelve C-9)

`media.uuid` es una columna **UUID nativa de PostgreSQL**: consultarla con una cadena malformada produce `SQLSTATE 22P02`, no un «no encontrado». Hoy [`FrontendMediaReference`](../../app/Services/Frontend/FrontendMediaReference.php) `:43-54` solo rechaza `null`/`''` y consulta directo, mientras la validación de formato vive **duplicada** en `FrontendPageRenderer::uuid()` `:259-261`. Esa duplicación es exactamente el *drift* que el docblock de la propia clase advierte («reuse this instead of re-implementing the check»): el renderer está protegido, pero cualquier llamador nuevo —como el controlador de preview— hereda el hueco.

**Contrato:**
- `FrontendMediaReference::resolve()` e `isEligible()` **validan el formato uuid antes de construir la query** y devuelven `null`/`false` ante un valor malformado. Ningún uuid inválido llega a PostgreSQL desde esta frontera.
- Se usa `Illuminate\Support\Str::isUuid()`, **disponible en la versión de Laravel instalada** (`vendor/laravel/framework/.../Str.php:645`), en lugar de duplicar una expresión regular por llamador. **Aún no es convención del proyecto**: hoy `FrontendPageRenderer` y `FrontendSectionSchema` usan regex propias; esta adopción es precisamente lo que introduce el incremento.
- `FrontendPageRenderer::uuid()` **deja de duplicar** la validación y delega en la frontera (o se elimina, si el resolver ya cubre el caso). Una sola definición de «uuid válido» en todo el subsistema.
- El gate de schema (`FrontendSectionSchema`, token `media`) **se conserva**: sigue siendo la primera barrera al guardar (short-circuit C-E4). La frontera es defensa en profundidad para las rutas que no pasan por el schema.

**Compatibilidad:** endurecer la frontera no cambia ningún resultado previamente válido — un uuid bien formado se comporta igual; uno malformado pasa de «excepción SQL» a «no elegible», que es la semántica que el render ya asumía.

### 7.11 Resolución de propietario tras soft-delete (resuelve C-12)

**Defecto verificado en vivo (preexistente, Lote F).** Con la sección `hero` de `servicios` soft-deleted: `sections()->get()` pasa de 3 a 2 filas mientras `withTrashed()` sigue devolviendo 3; el mapa de propietarios del renderer (`FrontendPageRenderer:118-121`, `keyBy('section_key')`) **ya no contiene `hero`**, `resolveTree()` recibe `$owner = null` (`:188-196`) y **`media_url` queda `null`**. Resultado: **la página publicada pierde la imagen sin que nadie publique nada**, justo lo contrario de la garantía de retención de §16.4.

**Causa.** El propietario se usa para **acotar** la consulta de media (`model_type` + `model_id` + colección). Resolverlo con la relación por defecto aplica el `SoftDeletingScope`, que expresa **vigencia editorial** — un criterio de *draft*, no de *publicado*. Pero la vigencia de lo publicado la da **el snapshot**, no la fila draft.

**Segundo defecto, también verificado en vivo: `withTrashed()` NO alcanza.** El índice único es **parcial** (`WHERE deleted_at IS NULL`), así que una sección borrada y una viva **pueden compartir `section_key`**. Reproducción: sección A (`hero`, id 15) publicada → soft-delete de A → se crea B (`hero`, id 25). Con `withTrashed()` + `keyBy('section_key')`, el mapa devuelve **B (id 25)**, no A. Como `FrontendMediaReference::resolve()` exige `model_id == owner`, la media de A **no resuelve** ⇒ imagen rota. `section_key` es una **clave editorial reutilizable**, no una identidad histórica.

**Contrato — la identidad la aporta la propia fila `media`, no `section_key`:**
- **Resolución de media (renderer):** para cada `media_id` del snapshot, el renderer llama a **`PublishedMediaReference::resolvePublished($uuid, $page, 'images')`** (M-7) — **una API concreta y compartida**, no una query ad-hoc. Dentro: valida formato uuid (§7.10), localiza la fila `Media`, y su **`model_id` ES el propietario** — identidad estable, inmune a la recreación de claves —; verifica `model_type = FrontendSection`, colección `images`, y que el propietario (resuelto con **`withTrashed()`**) pertenezca a **esta página**. **No se usa `keyBy('section_key')` para autorizar media.**
  - *Ventaja decisiva:* **no cambia el formato del snapshot**, así que las revisiones **ya publicadas** quedan arregladas sin republicar ni migrar datos.
  - *Seguridad equivalente:* el gate primario sigue siendo `saveSectionDraft`/publicación, que exigen `model_id == esa sección`; por construcción un snapshot solo puede contener media de la sección que la posee (invariante al cierre de §7.12). La verificación a nivel página en el render es defensa en profundidad.
- **Renderer — propietarios para el resto:** donde se sigan necesitando propietarios por clave, se resuelven con **`sections()->withTrashed()`**; el snapshot sigue siendo la única autoridad sobre **qué** secciones se renderizan.
- **`section_id` NO se agrega al snapshot (Mn-1).** Se evaluó incluirlo para trazabilidad y se **descarta**: la resolución no lo necesita (la identidad la da `Media.model_id`) y agregarlo divergiría del contrato de snapshot de la fuente única (épica `:722`, que define `section_key`) sin beneficio funcional. **El formato del snapshot no cambia**, de modo que las revisiones ya publicadas quedan arregladas sin republicar ni migrar.
- **`PublishedMediaReference::owningPage()`:** resuelve `media → FrontendSection` **con `withTrashed()`** (y la página igual), y **documenta explícitamente** el caso de propietario borrado lógicamente. Sin esto, el job no encontraría la página a bloquear y la promoción dejaría de ser determinista (Mn-2 de la auditoría).
- **La autorización no se relaja:** la media sigue debiendo pertenecer a **esa** sección y a la colección `images`. `withTrashed()` no amplía qué media es elegible; solo evita que el scope de borrado lógico oculte al propietario.
- **Alcance:** el arreglo vive en el renderer compartido ⇒ beneficia a **todos** los tipos con media (`team`, `feature_sequence`, `hero`), no solo al hero.

### 7.12 Secuencia del publisher (resuelve M-4)

`FrontendPagePublisher` recibe `PublishedMediaReference` **por inyección de constructor** (como ya recibe `FrontendSectionSchema` y `FrontendMediaReference`); no instancia nada ad-hoc ni reimplementa el predicado. Pasos, en este orden exacto:

| # | Paso | Nota |
| --- | --- | --- |
| 1 | `lockForUpdate()` sobre `FrontendPage` | Inicio del orden global. |
| 2 | `sections()->lockForUpdate()->orderBy('id')` | Igual que hoy; **sin cambios**. |
| 3 | Verificar `expected_draft_revision` | Publicación stale se rechaza. **Sin cambios**. |
| 4 | **Leer el snapshot ANTERIOR** (`published_revision`) y extraer sus uuids con `mediaIdsOf()` | **Bajo lock** (paso 1), nunca desde un modelo cargado antes: es la lectura que hace determinista el diff. |
| 5 | Armar el snapshot NUEVO y extraer sus uuids | Reutiliza el armado actual. |
| 6 | Validar elegibilidad por sección (`isEligible`) | **Sin cambios**: sigue siendo por sección, lo que sostiene la invariante de propiedad única. |
| 7 | Calcular `added = nuevos \ anteriores` y `removed = anteriores \ nuevos` | Diferencia de **conjuntos de uuid**; segura porque una media pertenece a una sola sección (invariante al cierre de esta sección). |
| 8 | `lockForUpdate()` sobre las filas `media` de `added ∪ removed`, **ordenadas por `uuid ASC`**, y **merge** de `custom_properties` (§7.9) | `added` sin `promoted` ⇒ marca `pending_promotion` + `revision` autorizante. `removed` con `pending_promotion` ⇒ **quita** el flag (`pending → draft`). `promoted` ⇒ no se toca (terminal). |
| 9 | Escribir snapshot, `revision`, log; **`DB::afterCommit`** ⇒ dispatch de `PromoteFrontendMedia` por cada uuid de `added` | El dispatch **nunca** dentro de la transacción. |

**Prohibido explícitamente:** construir `custom_properties` desde un modelo leído antes del paso 8; duplicar el walker de uuids (usar `mediaIdsOf()`); reimplementar el predicado de referencia.

**Una media pertenece a exactamente una sección (invariante, resuelve la duda de «media compartida»).** `FrontendMediaReference::isEligible()` exige `model_id == $section`, y tanto `saveSectionDraft` como el publisher validan **por sección**. Por construcción, un `media_id` **no puede** ser referenciado válidamente por dos secciones distintas. Por eso el diff de uuids a nivel página es seguro y `owningPage()` es determinista. (Dentro de **una misma** sección, el mismo uuid sí puede repetirse en dos slides: es una decisión editorial válida y el orden sigue siendo estable por `(sort_order, media_id)`.)

---

## 8. Fallback: matriz normativa única (resuelve C-2)

**Coherencia entre los tres documentos — verificable, no declarativa (Mn-1).** Épica §16.1.1/§16.7 enmendadas (registro **§18.18**), RFC-075 como espejo subordinado y esta matriz. **Criterio de aceptación reproducible:** una búsqueda de afirmaciones **activas** que nieguen el lock de `media` en publicación —`rg -n 'No hace falta bloquear|media solo se VALIDA|SIN lock|FrontendMediaReferenceService' docs/epicas docs/rfc`, **sobre los directorios completos** (incluye RFC-074 y RFC-077, no solo la épica y RFC-075)— debe devolver **cero** resultados fuera de bloques marcados como históricos (§18.x) o limitados explícitamente a mutaciones draft. Igual criterio para `FrontendMediaReferenceService` en contratos activos. Resolución en **boundary único**: `presentHero()` dentro de `FrontendPageRenderer::present()` (alimenta público **y** preview). El Blade **no** decide fallback. Datos en **`config('frontend-sections.hero_fallback')`** (§0.0: contiene el hero completo por página, no solo las imágenes).

| `pageKey` | Fallback (fondo hardcodeado actual, verificado) |
| --- | --- |
| `home` | 4 URLs Unsplash: arquitectura, construcción, comercialización, inversión (`welcome.blade.php:12-16`) |
| `nosotros` | `images/nosotros/header_nosotros.png` (`:11`) |
| `servicios` | `images/servicios/header_servicios.png` (`:11`) |
| `inversionistas` | `images/inversionistas/header_inversionistas.png` (`:11`) |
| `contacto` | **sin fondo** (`/contacto` = `LeadCaptureController`) |

| Estado del snapshot | Resultado |
| --- | --- |
| Página sin publicación | Fallback por `pageKey`. |
| Publicado, clave `slides` **ausente** | Fallback por `pageKey` (no inicializado ⇒ valor actual). |
| Publicado, `slides: []` | **Sin imagen.** No revive el fallback (§16.1.1). |
| Publicado, slides con uuid inválido / no elegible / **no promovido** | Se omiten esas slides; si no queda ninguna ⇒ **sin imagen**. |
| Publicado, ≥1 slide elegible **y promovida** | Render de las válidas, ordenadas (§7.5). |

Defaults materializados en `presentHero()`: `text_align='left'`, `logo_enabled=false`, `logo_size='md'` ⇒ preview y público idénticos. Pruebas HTTP/DOM para las **cinco** rutas × cada fila.

---

## 9. Render (`hero.blade.php`)

### 9.1 Logo
- Si `logo_enabled === true`: `app(FrontendSettingsService::class)->settings()['brand']['logo_dark_url']` (`$profile` en el layout), **encima** de `eyebrow`/`title`.
- **`alt`:** con `title` no vacío (hay H1) ⇒ `alt=""` + `aria-hidden="true"`. Sin `title` ⇒ `alt = $profile['site_name']`.
- **Tamaños (clases fijas):** `sm`→`h-10 sm:h-12` · `md`→`h-12 sm:h-16` · `lg`→`h-16 sm:h-20` · `xl`→`h-20 sm:h-24 lg:h-28`.

### 9.2 Alineación
Clases fijas: `left`→`text-left items-start` · `center`→`text-center items-center mx-auto` · `right`→`text-right items-end ml-auto`. Default `left`. Un solo `<h1>`.

### 9.3 Carousel — dos modos DOM mutuamente excluyentes

El presenter elige el modo: **Modo B si alguna slide renderizable tiene `decorative:false`; si no, Modo A.** Nunca coexisten; ningún `role="img"`/`alt` vive bajo `aria-hidden`.

**Modo A — decorativo (todas `decorative:true`)**
- Slides como **`<img aria-hidden="true" alt="">`** apilados (§0.0): la variante `background-image` de §16.1.1 exigía un atributo `style` inline, incompatible con una CSP sin `unsafe-inline`. Sin rol y sin alt, dentro de una capa ya oculta: para tecnología asistiva es equivalente.
- **Un único** contenedor de la capa con `aria-hidden="true"`.
- Autoplay (cross-fade) si hay **>1** slide y no hay `prefers-reduced-motion`.
- Con autoplay: **botón «Pausar/Reanudar»** (`<button>`, teclado) **fuera** de la capa `aria-hidden`, + pausa en `hover` y `focus-within`.
- 1 sola slide ⇒ estática, sin control.

**Modo B — informativo (≥1 `decorative:false`)**
- **Sin `aria-hidden`** y **sin autoplay**.
- Se renderiza **una sola** imagen: la primera `decorative:false` por orden canónico, como **`<img>` real** con su `alt` (`object-cover`, `absolute inset-0`).
- Las demás **no se renderizan** (evita duplicar alt y rotación silenciosa de contenido con significado).

**`prefers-reduced-motion: reduce`** — ambos modos sin animación ni temporizador. Modo A ⇒ primera slide estática (capa sigue `aria-hidden`, sin control).

**Sin slides renderizables** ⇒ hero sin capa de fondo (§8).

---

## 10. CSS/JS y CSP

- **Fade CSS-only** en `resources/css/app.css` (Vite). **Sin `<style>` inline.** `animation-delay` por set **fijo** `nh-hero-delay-0 … nh-hero-delay-5`; **jamás** se interpola payload en CSS ni se generan clases desde datos.
- **Pausa / reduced-motion: JS Vite** en **`resources/js/hero-carousel.js`**, enganchado por `data-*`. **Sin `<script>` ni handlers inline.** Usa `matchMedia('(prefers-reduced-motion: reduce)')`.
- **CSP:** compatible sin `unsafe-inline` en `script-src`/`style-src`; test de ausencia de inline en el hero.
- El hero legacy del home usa `<style>` inline (`welcome.blade.php:20-27`): **se reemplaza**, no se replica.

---

## 11. Seguridad y reglas de imagen

- **Sin HTML/CSS/JS libre:** enums allowlisted → clases fijas; `noHtml` es el gate del texto.
- **Imágenes:** MIME `image/png, image/jpeg, image/webp`; **SVG prohibido** (§16.4); ≤ **3072 KB**; mínimo **1200×675**; ratio 16:9 recomendado (ayuda, no rígido); `alt` ≤ **150**. Revalidado **server-side** antes de adjuntar.
- **Privacidad:** draft en `frontend-private`; preview solo por policy real con **404 uniforme**; público solo tras promoción efectiva (`disk` volteado).
- **Elegibilidad:** owner + colección validados en `saveSectionDraft` **y** en publicación.
- **CTAs:** `CtaResolver`.
- **Owner-only:** policies **sin cambios**; ahora también autorizan la ruta de media.
- **Rechazo de claves desconocidas** intacto.

---

## 12. Accesibilidad

Un solo `<h1>`; modos A/B excluyentes; autoplay solo decorativo con pausa y teclado; `reduced-motion` estático; logo con `alt` según §9.1 y tope responsive; `decorative:false` ⇒ `alt` obligatorio; estados vacíos sin romper layout.

---

## 13. Archivos esperados

| Archivo | Cambio |
| --- | --- |
| `config/frontend-sections.php` | + `hero_text_aligns`, `hero_logo_sizes`, **`hero_fallback`** (hero completo por página) y **`hero_variants`** (§0.0). |
| `app/Services/Frontend/FrontendSectionSchema.php` | + tokens `?text_align`/`?logo_size`; + campos en `SPECS['hero']`. |
| `app/Services/Frontend/FrontendPageRenderer.php` | + `presentHero()`: orden, defaults, fallback, **filtro `promoted`**, modo A/B. Deja de duplicar la validación de uuid (delega en la frontera, C-9). **Resuelve propietarios con `sections()->withTrashed()`** (C-12) — corrige un defecto preexistente que afecta a todos los tipos con media. |
| `app/Models/FrontendSection.php` | `images` → `useDisk('frontend-private')`; sin conversiones/responsive. |
| `app/Services/Frontend/FrontendMediaReference.php` | **Validación de formato uuid** en `resolve()`/`isEligible()` antes de la query (C-9). |
| `app/Services/Frontend/PublishedMediaReference.php` | **Nuevo (M-3)**: frontera única del predicado de referencia publicada — `mediaIdsOf()`, `isReferencedByPublishedRevision()`, `owningPage()`, `danglingPending()`. Consumida por publisher, job y reconciliación. |
| `app/Http/Controllers/FrontendSectionMediaController.php` | **Nuevo**: preview con policy real + **404 uniforme**. |
| `routes/web.php` | + ruta de media de sección **sin** middleware `auth`. |
| `app/Jobs/PromoteFrontendMedia.php` | **Nuevo**: toma locks `page → sección → media` (M-3), revalida el predicado, copia, verifica y **voltea `disk`/`conversions_disk`**; merge de `custom_properties`. |
| `app/Console/Commands/ReconcileFrontendMediaPromotions.php` | **Nuevo**: redespacha promociones faltantes. |
| `app/Console/Commands/ReportUnreferencedFrontendMedia.php` | **Nuevo** (solo lectura). |
| `routes/console.php` | + `frontend:media:reconcile` con `withoutOverlapping()->onOneServer()`. |
| `app/Services/Frontend/FrontendPagePublisher.php` | **Aditivo**: lock `media` (uuid ASC), marca `pending_promotion` con merge, dispatch en `afterCommit`. |
| `app/Filament/Forms/Components/CtaFields.php` | **Nuevo**: `make()` + `guidance()`. |
| `app/Filament/Pages/FrontendSettingsPage.php` | Reusa `CtaFields`; elimina privados duplicados. |
| `.../RelationManagers/SectionsRelationManager.php` | Formulario del hero + adaptador de slides. |
| `resources/views/frontend/sections/hero.blade.php` | Logo, alineación, modos A/B. |
| `resources/js/hero-carousel.js` | **Nuevo**. |
| `resources/css/app.css` | `nh-hero-delay-0..5` + cross-fade. |
| `docs/epicas/epica-12-...md` | **Enmiendas §16.1.1, §16.7, §16.4 + registro §18.18** (ya aplicadas). |
| `docs/rfc/RFC-075-...md` | Espejo subordinado (ya aplicado). |
| `tests/Feature/Frontend/...` | Cobertura §14. |

**Sin migraciones:** `pending_promotion`/`promoted` viven en `media.custom_properties`; `disk`/`conversions_disk` son columnas existentes.

---

## 14. Plan de pruebas (contrato-first, DOM y disco reales)

**Schema:** allowlist de `text_align`/`logo_size`; clave desconocida rechazada; payload sin campos nuevos válido; regla decorative/alt.

**Privacidad (C-4/C-5/C-6/C-9):** media de draft vive en `frontend-private` (**assert del `disk` efectivo de la fila**, no solo `getUrl()`); la ruta de preview devuelve **404** en los **cinco** casos —anónimo; autenticado **con `frontend.manage` pero sin rol owner**; sección inexistente; uuid ajeno; **uuid malformado**—; owner recibe el archivo. Media **no promovida** nunca aparece en el render público.

**Validación de uuid (C-9):** `resolve()`/`isEligible()` devuelven `null`/`false` ante `null`, `''` y cadenas malformadas (`'not-a-uuid'`, uuid truncado, con caracteres no hex) **sin ejecutar consulta inválida** — el test falla si se produce `SQLSTATE 22P02`. Además: la ruta de preview con uuid malformado responde 404 y **no** deja excepción en logs; el renderer sigue degradando a fallback con un `media_id` corrupto en un snapshot.

**Promoción (C-4/M-2/M-1):** tras el job, la fila tiene `disk='public'` y **`getUrl()` apunta al disco público**; el original existe en destino; **la copia privada NO se elimina** (v1 sin borrado físico); el job es **idempotente** (dos corridas ⇒ una copia, sin duplicar); `pending_promotion` se marca dentro de la transacción y **desaparece con rollback**; **republicar una media ya `promoted` no escribe `pending_promotion`** y, si existiera un residuo, el job lo **limpia** (invariante `promoted ⇒ sin pending_promotion`); la reconciliación redespacha lo no promovido; el scheduler **no** registra ningún comando que borre archivos; **assert de que la colección no genera conversiones ni responsive images**.

**Concurrencia (C-7):** publisher y job concurrentes en PostgreSQL ⇒ ni `promoted` ni `pending_promotion` se pierden; publicación posterior no pisa un `promoted` previo (merge, no sobrescritura).

**`resolvePublished()` (M-7) — aislado:** devuelve `null` ante uuid **malformado**, uuid de **otra página**, uuid de **otra colección**, `model_type` que no es `FrontendSection`, y uuid inexistente; devuelve la `Media` cuando el propietario está **soft-deleted** pero pertenece a la página. El renderer **no** ejecuta consultas propias a `Media` (se verifica que la resolución pasa por esta frontera).

**Identidad del propietario ante recreación de clave (C-12):** publicar la sección A (`hero`) con media promovida → **soft-delete de A** → **crear B con el mismo `section_key`** → (1) la revisión publicada **sigue resolviendo la media de A** (la identidad viene del `model_id` de la propia fila `media`, no de `section_key`); (2) `owningPage()` devuelve la página correcta; (3) una **nueva publicación** de B cambia el propietario **deliberadamente**. Se prueba también que una media cuyo propietario pertenece a **otra página** no resuelve.

**Reconciliación acotada (M-6):** con media `pending` en `FrontendService.image` y en `FrontendSetting`, el comando **no** modifica sus flags ni las despacha; solo actúa sobre `FrontendSection`/`images`.

**Retención tras soft-delete (C-12):** publicar una sección con imagen promovida → **soft-delete de la sección** → (1) la página pública **sigue mostrando la imagen** (la URL se resuelve vía propietario `withTrashed()`); (2) `owningPage()` **encuentra** la página, de modo que job y reconciliación siguen siendo deterministas; (3) el snapshot sigue siendo la única autoridad sobre qué secciones se renderizan (una sección **no** publicada no aparece por estar `withTrashed()`). Se cubre además un tipo **no-hero** con media (`team`) para probar que el arreglo es del renderer compartido.

**Secuencia del publisher (M-4):** el snapshot anterior se lee **bajo lock**; `added/removed` calculados por diferencia de conjuntos; `added` sin `promoted` quedan `pending`, `removed` con `pending` quedan limpios, `promoted` intactos; el dispatch ocurre **después** del commit. Prueba de que `custom_properties` **no** se escribe desde un modelo leído antes del lock (merge, no sobrescritura).

**Propiedad única de media (§7.12):** un `media_id` de la sección X **no** es elegible para la sección Y (`isEligible` falso) ⇒ dos secciones no pueden compartir media; y el mismo uuid repetido en dos slides de **la misma** sección se renderiza sin romper el orden.

**Predicado de referencia publicada (M-3) — aislado:** `PublishedMediaReference` se prueba **por sí solo**: `mediaIdsOf()` extrae los uuids de un snapshot anidado; `isReferencedByPublishedRevision()` da `false` ante uuid malformado, página sin snapshot y uuid ausente, y `true` cuando está presente; `owningPage()` resuelve media→sección→página; `danglingPending()` lista solo `pending` no referenciados.

**Carrera publisher ↔ job (M-3) — CONCURRENTE en PostgreSQL, no secuencial:** dos transacciones solapadas: el job toma sus locks y, **mientras los retiene**, una publicación concurrente intenta retirar A. Se verifica que (1) la publicación **espera** el lock de página en vez de colarse, (2) el resultado final es determinista y respeta las tres invariantes, y (3) **no queda una media promovida que la revisión final no referencia**. Caso simétrico: la publicación va primero y el job llega después ⇒ el job encuentra el predicado falso y **no promueve**. Sin `sleep` como sincronización: se coordinan las transacciones explícitamente.

**Cancelación de `pending` (M-2):** publicar referenciando A (queda `pending`) y **republicar reemplazando A antes de que corra el job** ⇒ (1) el publisher deja a A **sin** `pending_promotion` y **sin** `promoted`; (2) el archivo de A **sigue en `frontend-private`, intacto** (no se borra); (3) un job de A despachado **antes** del reemplazo, al ejecutarse, **no copia ni promueve** y termina limpio; (4) la reconciliación **no** promueve a A y limpia cualquier `pending` residual no referenciado. Assert de las **tres invariantes** de §7.8, incluida `promoted` como estado terminal (perder la referencia **no** despromueve).

**Lista de slides (C-1/C-8):** rehidratar 0/1/6; reemplazo genera nuevo `media_id` **sin borrar** el anterior; quitar/reordenar **no** borra archivos (assert en disco); uuid de otra sección/owner rechazado; upload-ok + save-fail deja archivo sin referencia sin romper.

**Orden:** payload invertido y con `sort_order` duplicados ⇒ orden determinista; reenumeración 0..n-1.

**Fallback (C-2):** las **cinco** rutas × cada fila de §8, vía HTTP/DOM.

**Carousel (C-3):** Modo A ⇒ capa `aria-hidden`, sin `role=img`, control presente con >1 slide; Modo B ⇒ **sin** `aria-hidden`, `<img>` con alt, **sin** autoplay, una sola imagen; `reduced-motion` en ambos. Asserts de **DOM/atributos**.

**CSP:** sin `<script>`/`<style>` inline en el hero; clases de delay del set fijo.

**Formulario:** el owner ve campos, no JSON; guardar produce el payload correcto; `CtaFields` reactivo en los 5 tipos.

**Logo:** `alt=""`+`aria-hidden` con H1; `alt=site_name` sin H1; clase por `logo_size`.

**Disciplina:** no correr la suite completa con el preview server abierto (OOM, exit 137).

---

## 15. Riesgos

| Riesgo | Impacto | Mitigación |
| --- | --- | --- |
| Draft servido públicamente | **Exposición** | Disco privado + policy real + 404 uniforme + render solo `promoted`; tests de `disk` efectivo. |
| **Uuid malformado ⇒ error de BD en ruta expuesta** | 500 repetible con entrada trivial; rompe el 404 uniforme | Validación de formato **en la frontera compartida** antes de la query (§7.10); test que falla ante `SQLSTATE 22P02`. |
| **Flags stale al republicar** | Observabilidad y reconciliación confundidas | Invariante `promoted ⇒ sin pending_promotion`, con doble garantía publisher/job bajo lock (§7.8); test de republicación. |
| `getUrl()` apuntando al disco privado | Imagen rota en público | Volteo verificado de `disk`/`conversions_disk` bajo lock; test de `getUrl()` post-promoción. |
| Promoción perdida | Imagen publicada invisible | `pending_promotion` durable + reconciliación idempotente; la slide se omite en vez de romper. |
| Pérdida de flags por JSON stale | Estado inconsistente | Relectura + merge bajo lock; tests concurrentes. |
| Deadlock por locks de `media` | Publicación colgada | **Un único orden para todos los actores**: `page → sections(id ASC) → media(uuid ASC)`. **Publisher y job lo respetan por igual** (§7.9); ningún camino toma `media` antes que página/secciones ⇒ sin ciclos de espera. |
| **Soft-delete rompe la URL de una media publicada** | **Regresión pública sin publicar nada** | Resolución de propietario con `withTrashed()` en renderer y `owningPage()` (§7.11); prueba de publicar → soft-delete → la imagen sigue. |
| Conversiones en disco equivocado | Exposición parcial | Colección **sin** conversiones/responsive; agregar alguna obliga a extender el job. |
| Tocar el publisher | Regresión | Cambio aditivo; suite de publicación existente debe seguir verde. |
| Repeater borra media | Pérdida | Sin componente Spatie ⇒ sin ruta de borrado; prohibidos `singleFile`/`onlyKeepLatest`/`forceDelete`; test de no borrado. |
| Media huérfana acumulada | Deuda de disco | Explícita (§16.4/§18.13) + `report-unreferenced`; **no** se convierte en borrado. |
| Drift entre los tres documentos | Ambigüedad normativa | Épica enmendada (§18.18) + RFC espejo + esta matriz; test que compara config con la matriz. |

---

## 16. Definition of Done

- [ ] Formulario del hero reemplaza el `Textarea` JSON.
- [ ] `SPECS['hero']` extendido, validado en borrador **y** publicación.
- [ ] Contrato de slides §7: hidratación 0/1/6, no borrado, elegibilidad, orden canónico.
- [ ] `images` en `frontend-private`, **sin** conversiones ni responsive.
- [ ] Preview autorizado por **policy real** con **404 uniforme** en los **5** casos (incluido uuid malformado).
- [ ] **Validación de uuid en la frontera compartida** (`FrontendMediaReference`), sin duplicación en el renderer; ningún uuid malformado llega a PostgreSQL.
- [ ] **Invariante `promoted ⇒ sin pending_promotion`** garantizada por publisher **y** job; republicación probada.
- [ ] **Invariante `pending ⇒ referenciada por la revisión vigente`**: transición `pending → draft` aplicada por publisher, job y reconciliación desde **`PublishedMediaReference`**; `promoted` terminal; prueba de «reemplazo antes del job».
- [ ] **El job toma `page → sección → media`** (decisión atómica); prueba de carrera **concurrente** publisher↔job en PostgreSQL.
- [ ] **Un solo lock order en los tres documentos**: draft sin lock de `media`, publicación-con-promoción con `page → sections(id ASC) → media(uuid ASC)`; sin filas de riesgo stale.
- [ ] **Media publicada resuelta por `PublishedMediaReference::resolvePublished()`** (única vía; sin queries ad-hoc en el renderer), por `model_id` y no por `keyBy('section_key')`; propietarios con `withTrashed()`; prueba de publicar → soft-delete → **recrear la clave** → la imagen de la revisión anterior sigue (incluye un tipo no-hero).
- [ ] **`danglingPending()` acotado** a `FrontendSection`/`images`; prueba de que la reconciliación no toca `FrontendService`/`FrontendSetting`.
- [ ] **Criterio de coherencia documental en cero** (§8), ejecutado sobre **los directorios completos** `docs/epicas docs/rfc` (incluye RFC-074 y RFC-077).
- [ ] **Secuencia del publisher (§7.12)** implementada en ese orden, con `PublishedMediaReference` inyectado y merge bajo lock.
- [ ] Fuente única sin la conducta «versión pública anterior o placeholder»; §16.4 y §18.18 coinciden con el diseño.
- [ ] `PromoteFrontendMedia` copia, verifica y **voltea `disk`/`conversions_disk`**; `getUrl()` público verificado.
- [ ] `pending_promotion`/`promoted` con **merge bajo lock**; orden `page → sections → media(uuid ASC)`; tests concurrentes.
- [ ] Reconciliación programada; ningún comando programado borra archivos.
- [ ] `presentHero()` centraliza orden, defaults, fallback, filtro `promoted` y modo A/B.
- [ ] Modos A/B excluyentes + `reduced-motion`.
- [ ] CSS-only + `hero-carousel.js`; test CSP.
- [ ] `CtaFields` compartido usado por settings **y** hero.
- [ ] Reglas de imagen §11 revalidadas server-side.
- [ ] Publisher solo con pasos aditivos; lock de página/secciones, snapshot y versionado intactos.
- [ ] Épica enmendada (§18.18) y RFC-075 como espejo; los tres documentos coinciden.
- [ ] Sin borrado físico, sin `forceDelete`, sin `singleFile`/`onlyKeepLatest`.
- [ ] Tests verdes + Pint; suite completa con preview detenido.
- [ ] Commit sin `.atl/skill-registry.md`.

---

## 17. Decisiones confirmadas por auditoría

Modos DOM A/B ✅ · `CtaFields` compartido ✅ · CSS/JS externo ✅ · sin borrado físico ✅ · `FrontendService.image` como deuda rotulada ✅ · owner-only sin cambiar policies ✅ · sin migraciones ✅ · alcance acotado (sin page builder, sin historial, sin framework de carousel).

---

## 18. Deuda declarada y próximo incremento

**Deuda:** pipeline §16.4 para `FrontendService.image` (§0.6). **No** se declara §16.4 cerrada globalmente.

**Épica 12.2:** replicar «schema → formulario» al resto de los tipos, reutilizando `CtaFields` y el contrato de media de §7. **Ejecutada** en los lotes 12.2-A/B/C/D; `gallery` se retiró en 12.2-D por no tener punto de entrada (épica 12 §18.19).

**Idea futura (12.3, opcional):** un tipo de hero simplificado para páginas institucionales (imagen única sin carousel). **No** es necesario para este gate: los heroes ya son independientes por fila y payload; lo compartido es la plantilla.
