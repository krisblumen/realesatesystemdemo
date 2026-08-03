# Épica 12.2 y deuda 12.3 — Lotes de implementación (contrato de auditoría)

**Proyecto:** New Hauz — Plataforma Inmobiliaria (monolito Laravel)
**Alcance:** los tipos de sección restantes + el retiro del editor JSON + la deuda de media de servicios
**Fuente única:** [Épica 12 §16 + §18.18](epica-12-administrador-contenidos-frontend.md); RFC-075/077/074 como espejos
**Diseño heredado:** [Épica 12.1 v10](epica-12-1-mejora-ux-hero.md) — sus §6, §7 y §11 son **contrato vigente** para todo lo que sigue
**Contrato previo:** [Lotes 12.1-A / 12.1-B](epica-12-1-lotes-implementacion.md)
**Rama:** `feature/epica-12-content-manager`
**Fecha:** 2026-07-26
**Estado:** 🔵 **PLANIFICADO — no inicia hasta que 12.1-B tenga gate `APROBADO`**

> **Este documento NO repite 12.1-B.** El formulario del hero y su render están contratados en `epica-12-1-lotes-implementacion.md` §3. Acá empieza lo que hoy **no tiene contrato**: los 12 tipos de sección restantes, el retiro del `Textarea` JSON y la deuda declarada de `FrontendService.image`.

---

## 1. Estado real del registro canónico (verificado)

`config('frontend-sections.pages')`, contado sobre el registro vigente:

| Tipo | Instancias | Dónde | Media | Lote |
| --- | --- | --- | --- | --- |
| `hero` | 5 | las cinco páginas | ✅ | **12.1-B** (ya contratado) |
| `cta` | 5 | `home.investors_block`, `home.final_cta`, `nosotros.final_cta`, `servicios.final_cta`, `inversionistas.final_cta` | — | 12.2-A |
| `rich_text` | 2 | `nosotros.story`, `contacto.contact_intro` | — | 12.2-A |
| `values` | 2 | `nosotros.values`, `inversionistas.service_scope` | — | 12.2-A |
| `metrics` | 1 | `nosotros.metrics` | — | 12.2-A |
| `partners` | 1 | `home.partners` | — | 12.2-A |
| `audience_outcomes` | 1 | `inversionistas.audience_outcomes` | — | 12.2-A |
| `team` | 1 | `nosotros.team` | ✅ | 12.2-B |
| `feature_sequence` | 1 | `inversionistas.investment_path` | ✅ | 12.2-B |
| `service_list` | 2 | `home.services_home`, `servicios.services_list` | — (dinámico) | 12.2-C |
| `featured_properties` | 1 | `home.featured_properties` | — (dinámico) | 12.2-C |
| `opportunity_properties` | 1 | `home.opportunity_properties` | — (dinámico) | 12.2-C |
| `featured_projects` | 1 | `home.featured_projects` | — (dinámico) | 12.2-C |

**`gallery` estaba en la allowlist de tipos pero NO lo usaba ninguna página.** Era un tipo ejecutable sin punto de entrada: no se podía editar porque no existía ninguna sección canónica de ese tipo. **Resuelto en 12.2-D: se retiró** (§7.2 y épica 12 §18.19).

**Nota de riesgo (bajo):** a diferencia del hero, estos tipos **ya tienen su `SPECS` cerrada**. 12.2 es sustancialmente UI: si un lote necesita tocar `FrontendSectionSchema`, es una **desviación** que debe declararse y justificarse, no un detalle de implementación.

---

## 2. Lotes

Gate bloqueante por lote, igual que 12.1: cada uno abre el siguiente solo con veredicto `APROBADO`. Informes en `docs/audits/epica-12-2-lote-{a|b|c|d|e}-auditoria-implementacion.md` y `epica-12-3-auditoria-implementacion.md`.

| Lote | Alcance | Por qué en este orden |
| --- | --- | --- |
| **12.2-A — Tipos de texto y CTA** | `cta`, `rich_text`, `values`, `metrics`, `partners`, `audience_outcomes` | 6 tipos, 12 instancias, **cero media**: el mayor alcance editorial con el menor riesgo. `cta` solo ya cubre 5 secciones reusando `CtaFields` de 12.1-B. |
| **12.2-B — Tipos con media** | `team`, `feature_sequence` | Reusan **verbatim** el adaptador de slides (12.1-B §7.1–§7.5) y el pipeline de promoción (12.1-A). Van después para que ese contrato ya esté auditado. |
| **12.2-C — Tipos dinámicos** | `service_list`, `featured_properties`, `opportunity_properties`, `featured_projects` | Solo parámetros de presentación. Riesgo distinto: que un formulario deje editar **ítems** y rompa la regla de que los datos vienen del kernel. |
| **12.2-D — Retiro del editor JSON** | Eliminar el `Textarea` de `payload`; retiro de `gallery` | Solo cuando **todos** los tipos canónicos tengan formulario. Mientras exista, es un bypass permanente de la UI amigable y una vía de inyectar payloads a mano. |
| **12.2-E — Correcciones de la verificación visual** | Mínimo de imagen por consumidor; lenguaje del editor; densidad de las tarjetas | **No estaba planificado.** Salió de recorrer el panel en el navegador con los lotes A–D ya verdes, y encontró un defecto de criterio que 1.000 pruebas no podían ver. Se audita aparte porque su origen —y su lección— son distintos. |
| **12.3 — Deuda: media de servicios** | Pipeline §16.4 para `FrontendService.image` | Deuda declarada en 12.1 §0.8. Independiente de 12.2; puede correr en paralelo. |

---

## 3. Requisitos transversales (el auditor los verifica en CADA lote)

Estos no se repiten por lote: aplican a todos y su incumplimiento es hallazgo.

1. **Un tipo, un formulario, sin tocar los demás.** El `Textarea` JSON sigue vigente para los tipos aún no migrados hasta 12.2-D.
2. **Todo guardado pasa por `FrontendPageContentService::saveSectionDraft`.** Ningún Resource escribe `payload` directo (§16.3).
3. **Sin cambios de `SPECS` no declarados.** El rechazo de claves desconocidas queda intacto; un campo nuevo exige justificación explícita en el informe del lote.
4. **`CtaFields` se reutiliza, no se duplica.** Todo CTA valida server-side contra `CtaResolver`.
5. **Media: un solo camino.** Los tipos con media reusan el adaptador y el pipeline existentes. Queda **prohibido** un segundo uploader, `SpatieMediaLibraryFileUpload` directo, `singleFile()`, `onlyKeepLatest()`, `forceDelete` y todo borrado físico.
6. **Aditividad.** El `git diff` del lote no toca policies, migraciones de dominio ajeno, formato del snapshot (**sin `section_id`**), versionado optimista ni `FrontendService.image` (salvo en 12.3).
7. **Reglas de imagen** de 12.1 §11 (PNG/JPEG/WebP, ≤3072 KB, sin SVG, `alt` ≤150) revalidadas **server-side**.
8. **Accesibilidad**: un solo `<h1>` por página; toda imagen con `alt` o `decorative`; estados vacíos que no rompen layout.
9. **Verificación base**: `composer validate --strict` · `pint --test` · `npm run build` · **suite completa** sobre PostgreSQL real, con el **preview server detenido** (OOM, exit 137).
10. **Sin regresión del render público**: las cinco rutas siguen sirviendo su revisión publicada o su fallback por página (12.1 §8).

---

## 4. Lote 12.2-A — Tipos de texto y CTA

### 4.1 Formularios esperados

| Tipo | Campos (derivados de su `SPECS`) |
| --- | --- |
| `cta` | Antetítulo · Título · Cuerpo · CTA principal · CTA secundario (**`CtaFields`**) |
| `rich_text` | Título? · Cuerpo (texto plano multilínea, **sin HTML**) |
| `values` | Título? · Repeater de ítems (Título + Descripción, máx. 12) |
| `metrics` | Repeater de ítems (Etiqueta + Valor, máx. 12) |
| `partners` | Repeater de ítems (Nombre, máx. 24) |
| `audience_outcomes` | Antetítulo? · Título? · Lista de audiencia · **Objeto `result`** (antetítulo?, título?, lista de ítems, cita?) |

### 4.2 Tests del lote (TB2A-n)

| # | Test |
| --- | --- |
| TB2A-1 | Cada uno de los 6 tipos muestra **campos, no JSON**; los tipos aún no migrados conservan el `Textarea` |
| TB2A-2 | Guardar produce el payload canónico exacto de su `SPECS`; el schema lo acepta |
| TB2A-3 | HTML en cualquier campo de texto se **rechaza** (`noHtml`) en los 6 tipos |
| TB2A-4 | Límites de cardinalidad respetados (`values`/`metrics` 12, `partners` 24) y su exceso rechazado |
| TB2A-5 | `audience_outcomes`: `result` es **obligatorio**; falta o forma inválida se rechaza |
| TB2A-6 | `CtaFields` reactivo en los 5 tipos de destino, en las **5** secciones `cta` |
| TB2A-7 | CTA con destino inseguro rechazado en el guardado, no solo en la UI |
| TB2A-8 | Render público de las 5 páginas sin cambios respecto de su snapshot previo |

---

## 5. Lote 12.2-B — Tipos con media

### 5.1 Formularios esperados

| Tipo | Campos |
| --- | --- |
| `team` | Título? · Título/cuerpo de spotlight? · Repeater de miembros (máx. 24): Nombre · Rol? · **Imagen** · `alt` |
| `feature_sequence` | Antetítulo? · Título? · Repeater de paneles (**mín. 1**, máx. 8): Antetítulo? · Título · Cuerpo? · **Imagen** · `alt` · **Layout** (allowlist) |

### 5.2 Contrato de media (heredado, no reinventado)

Reusa **tal cual** el adaptador de 12.1-B §7.1–§7.5 y el pipeline de 12.1-A: `FileUpload` base sobre `frontend-private`, `media_id` oculto, `addMediaFromDisk` que mueve, revalidación server-side previa, y promoción por `PromoteFrontendMedia` con sus tres invariantes.

**Diferencias respecto del hero, a verificar:**
- `team.media_id` es **opcional** (`?media`): un miembro sin foto es válido; con foto, `alt` es obligatorio.
- `feature_sequence.media_id` es **obligatorio** y su `layout` debe estar en `config('frontend-sections.feature_sequence_layouts')`.
- Ninguno de los dos usa `decorative`: si hay imagen, hay `alt`.

### 5.3 Tests del lote (TB2B-n)

| # | Test |
| --- | --- |
| TB2B-1 | Hidratación con 0 / 1 / máximo miembros o paneles |
| TB2B-2 | Imagen nueva → `media_id` nuevo **sin borrar** la anterior (fila y archivo sobreviven) |
| TB2B-3 | Quitar o reordenar un ítem **no** ejecuta `Media::delete()` |
| TB2B-4 | `media_id` de otra sección u otra página rechazado |
| TB2B-5 | `team` sin imagen es válido; **con** imagen y sin `alt` se rechaza |
| TB2B-6 | `feature_sequence` sin imagen se rechaza; `layout` fuera de la allowlist se rechaza; menos de 1 panel se rechaza |
| TB2B-7 | La media de estos tipos vive en `frontend-private` (assert del `disk` de la fila) y solo se emite en público una vez `promoted` |
| TB2B-8 | Preview del borrador usa la **ruta owner-only**, nunca `/storage/` |

---

## 6. Lote 12.2-C — Tipos dinámicos

**El riesgo de este lote es distinto y el auditor debe apuntar ahí:** el formulario no debe abrir ninguna puerta para editar los **ítems**. El payload solo lleva parámetros de presentación; los datos vienen de las autoridades del kernel (`Property::featured()`, `Property::opportunity()`, `Project::is_featured`, `ServiceType` activos) **en cada render**.

| Tipo | Campos permitidos |
| --- | --- |
| `service_list` | Antetítulo? · Título? |
| `featured_properties` · `opportunity_properties` · `featured_projects` | Antetítulo? · Título? · Límite? (entero acotado) |

### 6.1 Tests del lote (TB2C-n)

| # | Test |
| --- | --- |
| TB2C-1 | El formulario **no** expone ningún campo de ítems, ids ni consulta |
| TB2C-2 | Un payload con ids o claves extra se **rechaza** (claves desconocidas) |
| TB2C-3 | Los ítems del render provienen de la autoridad del kernel, no del payload: cambiar un `Property` destacado cambia el render **sin republicar** |
| TB2C-4 | `limit` fuera de rango cae al valor por defecto acotado |
| TB2C-5 | `generated_from_ids` del snapshot sigue registrando el inventario al publicar |
| TB2C-6 | Un servicio inelegible (inactivo o sin `FrontendService`) sigue sin aparecer (fail-closed, RFC-074) |

---

## 7. Lote 12.2-D — Retiro del editor JSON

**Precondición:** 12.1-B, 12.2-A, 12.2-B y 12.2-C con gate `APROBADO`.

1. **Eliminar** el `Textarea::make('payload')` y su `decodePayload()` de `SectionsRelationManager`. Mientras exista, el owner (o cualquiera con la pantalla) puede pegar un payload a mano, saltándose la UI validada — es un bypass permanente, no una red de seguridad.
2. **Decisión sobre `gallery` — TOMADA: se retira.** El owner del proyecto eligió la primera salida el 2026-07-26. `gallery` sale de `config('frontend-sections.types')` y de `FrontendSectionSchema::SPECS` por no tener punto de entrada: ninguna de las cinco páginas del registro lo declara, así que era un tipo ejecutable inalcanzable. Si más adelante se necesita, se agrega junto con su sección canónica y su formulario, no antes.

   **Advertencia para el auditor, porque el nombre se repite:** `gallery` es TAMBIÉN el nombre de una colección de media de Spatie en `Property` y `Project` (`app/Models/Property.php`, `app/Models/Project.php`), que es la galería de fotos del inmueble y del proyecto. Las dos cosas no tienen ninguna relación: esas vistas leen `$model->getMedia('gallery')` directo del modelo y no tocan `FrontendSectionSchema` ni `config('frontend-sections')`. El retiro NO alcanza a esas galerías, y `FrontendSectionEditorClosureTest` lo deja asertado.

### 7.1 Tests del lote (TB2D-n)

| # | Test |
| --- | --- |
| TB2D-1 | Ningún tipo canónico cae al `Textarea`: los 13 tipos que quedan tras retirar `gallery` muestran formulario |
| TB2D-2 | No existe ninguna ruta de UI que escriba `payload` como texto libre |
| TB2D-3 | El retiro de `gallery` está implementado y probado, y se asienta que las galerías de `Property`/`Project` quedaron intactas |
| TB2D-4 | Suite completa verde; render de las 5 páginas sin cambios |

---

## 8. Lote 12.2-E — Correcciones de la verificación visual

**Commit:** `eb81d42`. **Precondición:** 12.2-A/B/C/D implementados y verdes (1.000/1.000 antes de este lote).

Este lote no estaba en el plan. Nació de recorrer el panel en el navegador después de cerrar 12.2-D, con toda la suite verde, y el auditor debería leerlo sabiendo **por qué existe**: encontró un defecto que ninguna prueba podía encontrar.

### 8.1 El defecto de criterio (lo importante)

Los tres tipos con imagen —`hero`, `team`, `feature_sequence`— exigían un mínimo de **1200×675 px**, porque el requisito se copió del hero al extraer `SectionImageFields` en 12.2-B. Eso es **16:9 apaisado**.

`team` es el **retrato de un integrante del equipo**. Nadie sube una foto de perfil apaisada. El formulario iba a rechazar fotos perfectamente válidas, y nadie se hubiera enterado hasta que un owner real lo intentara.

**Por qué la suite no podía verlo:** todas las pruebas de media usan `UploadedFile::fake()->image('x.png', 1600, 900)`, que satisface cualquier mínimo apaisado. Una prueba que siempre sube la misma imagen no puede detectar que el requisito *de esa imagen* está mal. El error no era de código —el código hacía exactamente lo escrito— sino de **criterio**, y el criterio sólo aparece al preguntarse qué foto va a subir realmente quien administra el sitio.

**Corrección:** `SectionImageFields::make()` exige `minWidth`, `minHeight` y `shape` **sin valor por defecto**. Sin default a propósito: un default es precisamente lo que permitió heredar el valor equivocado sin que nadie lo mirara. `team` pide 600×600 (retrato o cuadrada); `hero` y `feature_sequence` siguen apaisados.

### 8.2 Lenguaje del editor

El modal se titulaba «Editar frontend section» —inglés y jerga— y sus dos primeros campos mostraban `investment_path` y `feature_sequence` deshabilitados: el lugar más visible de la pantalla ocupado por identificadores del registro que el owner no puede cambiar y que no significan nada para él.

Ahora el encabezado es el nombre humano de la sección, los nombres viven en `config('frontend-sections.section_labels')` junto al registro que los define, y la clave interna aparece como texto secundario en la tabla para quien la necesite.

### 8.3 Densidad de las tarjetas

Medido en el navegador, no estimado: una fila de `feature_sequence` medía **585 px** y una de `team` **253 px**; la ayuda de la imagen envolvía en **3 líneas dentro de 239 px**; el «sin imagen todavía» era texto suelto ocupando una columna de la grilla. Tras la corrección: **441 px** y **~215 px**, ayuda en una sola línea, y un recuadro que reserva su lugar para que la fila no salte de alto al subir la primera foto. El campo «qué se ve en la imagen» pasó a estar **junto a la imagen**: estaba al final de la tarjeta, a seis campos de la foto que describe.

### 8.4 Tests del lote (TB2E-n)

| # | Test | Dónde |
| --- | --- | --- |
| TB2E-1 | Una foto **cuadrada** (800×800) se acepta en `team` | `FrontendMediaSectionEditorTest` |
| TB2E-2 | Una foto de 300×300 **se sigue rechazando** en `team`: bajar el mínimo no es quitarlo | ídem |
| TB2E-3 | Esa **misma** foto cuadrada **se rechaza** en un paso de `feature_sequence`: el mínimo por consumidor no es un relajamiento general | ídem |
| TB2E-4 | Toda sección canónica tiene nombre humano en el registro | `FrontendSectionEditorClosureTest` |
| TB2E-5 | El editor muestra el nombre humano y **no** la clave interna ni «Editar frontend section» | ídem |
| TB2E-6 | Suite completa verde y render de las 5 páginas sin cambios | suite |

### 8.5 Qué debe mirar el auditor con atención

1. **Que ningún mínimo haya quedado con default.** Si `SectionImageFields::make()` vuelve a aceptar dimensiones implícitas, el defecto puede repetirse en el próximo consumidor: es la causa raíz, no el síntoma.
2. **Que el mínimo de `hero` y `feature_sequence` NO se haya relajado.** La corrección es por consumidor; aflojar los tres habría sido cambiar un error por otro.
3. **Que los nombres humanos cubran el registro completo** y que agregar una sección sin etiqueta falle una prueba, en vez de mostrar la clave interna en el panel.
4. **Que la evidencia de las mediciones sea reproducible**: `docs/audits/artifacts/epica-12-2-lote-e/qa-visual.md` registra el método y los números, antes y después.
5. **Que este lote no haya tocado el pipeline de media** (disco privado, promoción, sin borrado físico). Cambia requisitos de entrada y textos, nada del contrato de 12.1-A.

---

## 9. Lote 12.3 — Deuda: media de `FrontendService.image`

Cierra lo que 12.1 dejó **explícitamente abierto** (§0.8): las imágenes de servicios siguen en disco público desde el borrador, sin preview owner-only ni promoción.

**Contrato:** aplicar el mismo pipeline ya auditado de 12.1-A a `FrontendService.image`, **reutilizando** `PublishedMediaReference`, `PromoteFrontendMedia` y los comandos — no una segunda implementación.

**Complicación propia a resolver en su diseño:** el contenido editorial de servicios usa **estrategia A («guardar = publicar»)** por la enmienda C-G-1 — no hay `draft_payload` ni revisión publicada. Por lo tanto el predicado «¿referenciada por la revisión vigente?» **no aplica igual** y debe redefinirse para servicios antes de implementar. **Este lote requiere su propio mini-diseño y gate de diseño**, no solo un contrato de implementación.

**Diseño escrito y pendiente de gate:** `docs/epicas/epica-12-3-media-servicios-diseno.md`. Redefine el predicado sobre la columna `image_media_id` de un servicio vivo, resuelve por extracción de interfaz la contradicción entre «reutilizar `PublishedMediaReference`» y el docblock de esa clase que se prohíbe tocar servicios, y declara la migración de las imágenes que hoy ya están en el disco público. Su matriz amplía T3-1…5 hasta **T3-19**. **v1 RECHAZADA** (`docs/audits/epica-12-3-media-servicios-auditoria-diseno.md`, cuatro críticos); la **v2** los corrige y espera reauditoría.

| # | Test |
| --- | --- |
| T3-1 | La imagen de un servicio vive en `frontend-private` y solo se emite pública tras promoción |
| T3-2 | Preview owner-only con 404 uniforme en los cinco casos |
| T3-3 | El pipeline es el **mismo** (sin clases duplicadas): assert de que no aparece un segundo job/servicio de promoción |
| T3-4 | La reconciliación amplía su alcance a esta colección **sin** tocar `FrontendSetting` |
| T3-5 | Sin borrado físico en ninguna ruta |

---

## 10. Instrucciones para el auditor (Codex)

1. **Contrato normativo:** este documento + Épica 12.1 v10 (§6, §7, §11) + Épica 12 §16/§18.18. Toda desviación no declarada es hallazgo.
2. **Verificar los transversales de §3 en cada lote**, no solo la matriz del lote.
3. **Coherencia documental:** re-ejecutar el criterio de 12.1 §8 sobre **los directorios completos** `docs/epicas docs/rfc` ⇒ cero afirmaciones activas contradictorias.
4. **Concurrencia:** exigir dos conexiones PostgreSQL reales donde el lote la declare; rechazar tests secuenciales presentados como concurrentes.
5. **12.2-C merece escrutinio especial:** confirmar que ningún camino permite fijar ítems en el payload y que el render los resuelve del kernel.
6. **12.2-E se audita como corrección, no como funcionalidad nueva** (§8): su matriz es chica, pero la pregunta central es si la causa raíz quedó cerrada —dimensiones sin default— y no sólo el síntoma.
7. **12.3 no se audita como implementación** hasta tener su propio gate de diseño (§9).
8. Veredicto por lote con gate explícito `APROBADO` / `RECHAZADO`. Ningún lote inicia sin el anterior aprobado.
