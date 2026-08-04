# RFC-077 Preview, Publicación y QA Visual del Frontend

> **⚠️ Enmienda normativa C-G-1 (2026-07-24, reconciliación tras la auditoría del Lote G).** El **contenido editorial de `FrontendService` es estrategia A — inmediata (guardar = publicar)**. La **única** entidad con flujo draft→publicado y **preview owner-only es `FrontendPage`** (+ sus `FrontendSection`). Quedan **retirados** de este RFC: el protocolo de publicación de servicio, `FrontendServicePublisher`, `draft_payload`/`published_payload`/`draft_revision`/`expected_draft_revision_service` de servicio, el preview editorial de servicios y el test obligatorio **T-11s** (`FrontendServicePublishConcurrencyTest`). Fuente única: la tabla de estrategia por entidad de **§16.9** de la épica. Toda mención abajo al flujo B de servicios es **histórica, no normativa**.
>
> **⚠️ Enmienda normativa (P3 + correcciones posteriores a P3R, 2026-07-20).** Fuente única: **§16** de la épica; donde difiera, **prevalece §16**. Overrides: snapshot completo; páginas usan `draft_revision` + `expected_draft_revision`; ~~contenido editorial de servicios usa su propio `draft_revision` + `expected_draft_revision_service`; toda mutación del draft correspondiente incrementa su contador y ambos publishers rechazan stale bajo lock~~ *(servicios retirados por C-G-1, arriba)*; media privada promovida por `PromoteFrontendMedia` post-commit, con retry idempotente y `ReconcileFrontendMediaPromotions`; `pageKey` inválido devuelve 404 uniforme; pruebas reales con dos conexiones PostgreSQL.
>
> **Reconciliación preview/publicación (cierra contradicción P3R §4.5):** la estrategia por entidad de §16.9 es autoritativa. **Solo las entidades de estrategia B** (páginas institucionales + contenido editorial de servicios) tienen flujo draft→publicado con preview owner-only. Las entidades de **estrategia A** (tema visual, contacto, nav, footer, CTAs globales de `FrontendSetting`, y disponibilidad de servicios) son **inmediatas: guardar = publicar**, con validación dura al guardar; su "preview" es el sitio en vivo. Por lo tanto **el DoD de este RFC NO exige preview previa para tema ni CTAs globales** — exigirla contradice §16.9 y sería una prueba imposible. Las validaciones preflight (contraste, CTAs válidos, etc.) se ejecutan **al guardar** en estrategia A y **al publicar** en estrategia B.
>
> **⚠️ DECISIÓN DE ALCANCE (§18.13 de la épica, 2026-07-21): el BORRADO FÍSICO DE MEDIA SALE DE v1.** Quedan **fuera de alcance** prune, purga física, intent, lease, advisory lock, jobs guardados de Spatie, path generator con scope y barrido de huérfanos, con sus tablas, comandos y tests. Toda mención a esos mecanismos en este RFC es **histórica, no normativa**. Se conservan: media draft en disco privado con controlador owner-only, promoción post-commit idempotente con reconciliación, `SoftDeletes` con `forceDelete` prohibido, índices únicos parciales y el reemplazo que no destruye la imagen publicada. Contrato vigente: **§16.4**.

## Objetivo

Definir un flujo seguro para que el usuario `owner` revise cambios del frontend antes de publicarlos, con controles mínimos de QA visual, accesibilidad y regresión pública.

Este RFC cierra la Épica 12 como capa de operación editorial: no agrega nuevos tipos de contenido, sino que asegura que los cambios configurables de RFC-071 a RFC-076 puedan revisarse, publicarse y verificarse sin romper el sitio público.

## Épica

Épica 12 — Administrador de Contenidos del Frontend

## Responsable

Por asignar

## Estado

🟡 Correcciones documentales aplicadas; reauditoría independiente pendiente. **Implementación bloqueada** hasta gate `APROBADO`.

---

## Contexto

Los RFCs anteriores permiten editar:

- Perfil público y media base.
- Tema visual.
- Navegación, footer y CTAs.
- Servicios ofrecidos.
- Contenido institucional.
- Render centralizado con cache y fallbacks.

Sin un flujo de revisión, cualquier guardado puede impactar producción inmediatamente. Para un CMS owner-only esto puede ser aceptable en cambios simples, pero es riesgoso para páginas, servicios, CTAs y tema visual.

RFC-077 define el contrato mínimo de preview/publicación y QA.

---

## Alcance

### Incluye

- Preview autenticado owner-only para cambios del frontend.
- Estado de publicación para contenido sensible si aplica.
- Checklist visual antes de merge/despliegue.
- Validaciones de accesibilidad mínimas.
- Pruebas de regresión pública.
- Verificación de cache/invalidación después de publicar.
- Registro básico de quién publicó y cuándo.

### No incluye

- Workflow editorial multiusuario.
- Aprobaciones por varios roles.
- Historial completo tipo CMS enterprise.
- Versionado visual avanzado.
- Comparador visual automatizado con screenshots en CI, salvo que se decida implementarlo luego.
- Publicación programada.
- Rollback automático completo.

---

## Actor autorizado

Solo `owner` puede previsualizar cambios no publicados y publicar.

| Rol | Preview | Publicar |
| --- | --- | --- |
| `owner` | ✅ | ✅ |
| `admin` | ❌ | ❌ |
| `agente` | ❌ | ❌ |
| `arquitectura` | ❌ | ❌ |
| `proyectos` | ❌ | ❌ |

---

## Decisión de publicación

La estrategia ya está cerrada por tipo de contenido:

### Estrategia A — Guardado inmediato con preview simple

Aplica a `FrontendSetting` completo (identidad, contacto, SEO defaults, tema, navegación, footer y CTAs globales) y a disponibilidad de `FrontendService` (`show_*`, `allow_leads`). Guardar = publicar con validación dura e invalidación post-commit.

El cambio se guarda y publica de inmediato, siempre que pase validaciones.

### Estrategia B — Borrador / Publicado

Aplica a páginas institucionales (página + secciones como snapshot) y contenido editorial de servicios (`draft_payload`→`published_payload`). Tema y CTAs globales **no** pertenecen a B.

Para páginas, `frontend_pages.draft_revision` aumenta en cada mutación de página/sección. La UI guarda la revisión observada y la envía como `expected_draft_revision`; publicar con una revisión stale falla sin efectos.

Para contenido editorial de servicios, `frontend_services.draft_revision` aumenta en cada mutación de `draft_payload`. La UI guarda la revisión observada y la envía como `expected_draft_revision_service`; publicar con una revisión stale falla sin cambiar `published_payload` ni metadatos de publicación. Los toggles inmediatos de disponibilidad no pertenecen a ese draft.

Toda mutación draft o publicación que agregue/cambie UUIDs conserva los locks de entidad (página→secciones, o servicio) y **valida** cada `media_id` —existencia, owner y colección— antes de escribir JSON. ⚠️ **PRECISADO por §18.18 de la Épica 12 (incremento 12.1):** la **validación** no necesita lock, pero **la publicación con promoción de `FrontendSection.images` SÍ bloquea `media`** (`page → sections(id ASC) → media(uuid ASC)`) por la **carrera de referencia**, no por borrado. Para **mutaciones draft** sigue siendo cierto que **no se bloquea `media`**: en v1 ninguna ruta la borra (§16.4 de la épica). `FrontendMediaReferenceUnavailable` revierte la transacción completa.

---

## Preview

### Reglas

- La preview requiere sesión autenticada de owner.
- La preview no debe ser indexable.
- La preview no debe exponer token público reusable.
- La preview debe renderizar el mismo layout público con datos draft.
- La preview debe indicar visualmente que no es producción.

### Rutas sugeridas

```text
/admin/frontend/preview/{pageKey}
```

O equivalente dentro de Filament.

### Seguridad

- No aceptar `pageKey` fuera de allowlist.
- No permitir preview de rutas arbitrarias.
- No exponer datos draft a usuarios no owner.
- Usar `noindex, nofollow` en preview.

---

## Publicación

Al publicar una página:

1. Abrir transacción, ejecutar `SET TRANSACTION ISOLATION LEVEL READ COMMITTED` como primera sentencia SQL —sin confiar en el default de conexión— y bloquear `FrontendPage`, luego sus `FrontendSection` por `id`.
2. Comparar `expected_draft_revision` con la revisión actual; si difiere, rechazar como stale.
3. Validar la entidad completa y construir snapshot con SEO+enabled+orden+secciones+`media_id`.
4. Extraer los UUID del snapshot y **validarlos** con **`FrontendMediaReference`** (existencia, owner, colección y **formato uuid**; la validación en sí no toma lock).
4-bis. **(§18.18 de la Épica 12)** Leer, **bajo el lock ya tomado**, los UUID de la revisión **anterior** con `PublishedMediaReference::mediaIdsOf()`; calcular `added`/`removed`; tomar `lockForUpdate()` sobre esas filas `media` **ordenadas por `uuid ASC`** y **mergear** `custom_properties` (nunca sobrescribir el JSON completo).
5. Escribir `published_revision`, `published_by`, `published_at`, incrementar `revision`, marcar `pending_promotion` en `added` **que no estén ya `promoted`** y **limpiar** el flag en `removed` que sigan `pending` (transición `pending → draft`). Secuencia completa: `docs/epicas/epica-12-1-mejora-ux-hero.md` §7.12.
6. Tras commit, invalidar caché y despachar `PromoteFrontendMedia`. El job idempotente promueve y marca; `ReconcileFrontendMediaPromotions` recupera callback/dispatch perdido.
7. Mostrar confirmación clara al owner. Un rollback no copia media, no encola y no altera el snapshot previo.

El contenido editorial de `FrontendService` se publica con un protocolo propio y completo:

1. Abrir transacción, ejecutar `SET TRANSACTION ISOLATION LEVEL READ COMMITTED` como primera sentencia SQL y bloquear su fila `FrontendService::lockForUpdate()`.
2. Comparar `expected_draft_revision_service` con `frontend_services.draft_revision`; si difiere, rechazar como stale sin efectos.
3. Validar el `draft_payload` y extraer los UUID: cada fila debe existir y pertenecer al mismo `FrontendService`/colección `image` (validación, sin lock sobre `media`).
4. Solo entonces copiarlo completo a `published_payload`, registrando `published_by`/`published_at` y media pendiente.
5. Tras commit, despachar promoción media y hacer el único bump global de caché de RFC-076.

No participa de `frontend_pages.draft_revision`: cada servicio es su propio punto de serialización. Publicar no incrementa `draft_revision`, porque no cambia `draft_payload`.

---

## Validaciones pre-publicación

### Perfil / settings

- Nombre público presente.
- Contacto válido.
- WhatsApp normalizado si existe.
- Logos válidos o fallback disponible.

### Tema

- Colores válidos.
- Contraste mínimo aprobado.
- Fuentes en allowlist.
- Sin CSS libre.

### Navegación / CTAs

- Al menos navegación mínima usable.
- No links `#`.
- No URLs inseguras.
- CTAs con label y target válido.

### Servicios

- Servicio visible debe tener título y descripción.
- Servicio que acepta leads debe estar activo en `ServiceType`.
- Servicio inactivo no debe aparecer en preview pública normal.
- Inversión inmobiliaria debe estar reconciliada según RFC-074.

### Páginas

- Un H1 por página.
- Hero con título.
- Imágenes con alt text o decorativas.
- Payloads válidos.
- Secciones requeridas presentes o fallback disponible.

---

## QA visual mínimo

Antes de cerrar implementación de esta épica, verificar visualmente:

- Home desktop.
- Home móvil.
- Nosotros desktop/móvil.
- Servicios desktop/móvil.
- Inversionistas desktop/móvil.
- Contacto desktop/móvil.
- Header desktop/móvil.
- Footer desktop/móvil.
- Lead form con servicios activos/inactivos.

Se deben revisar:

- Layout no roto.
- Logos correctos.
- CTAs visibles.
- Menú móvil usable.
- Contraste legible.
- Imágenes cargan o fallback funciona.
- Servicios deshabilitados no aparecen.

---

## QA automatizado mínimo

Tests requeridos:

- Owner accede a preview.
- Admin y demás roles reciben 403 en preview.
- Preview no usa cache publicada si hay draft.
- Publicar invalida cache.
- Publicar registra `published_by` y `published_at`.
- Guardar un tema inválido se rechaza antes de publicar el cambio inmediato.
- Guardar un CTA inválido se rechaza antes de publicar el cambio inmediato.
- Servicio inconsistente no se publica o queda excluido según regla.
- Páginas públicas siguen renderizando con contenido publicado.
- Draft no se filtra en rutas públicas normales.
- `FrontendPublishConcurrencyTest::test_stale_publisher_is_rejected_after_draft_mutation` usa dos conexiones y prueba que el snapshot previo no cambia.
- ~~`FrontendServicePublishConcurrencyTest::test_stale_service_publisher_is_rejected_after_draft_payload_mutation`~~ — **RETIRADO por C-G-1:** servicios son estrategia A; no hay publisher de servicio ni T-11s.
- `FrontendMediaPromotionTest::test_rollback_does_not_copy_or_enqueue_media`.
- `FrontendMediaPromotionTest::test_enqueue_failure_is_recovered_by_reconciliation`.
- `FrontendMediaPromotionTest::test_promotion_retry_is_idempotent`.
- `FrontendMediaReferenceConcurrencyTest::test_draft_writer_first_makes_prune_keep_the_referenced_media` usa dos conexiones PostgreSQL y barreras sin sleeps.  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->
- `FrontendMediaReferenceConcurrencyTest::test_prune_first_makes_draft_writer_roll_back_without_dangling_json`.  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->
- `FrontendMediaReferenceConcurrencyTest::test_publisher_first_makes_prune_keep_the_published_media`.  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->
- `FrontendMediaReferenceConcurrencyTest::test_prune_first_makes_publisher_roll_back_without_dangling_json`.  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->
- `FrontendMediaReferenceConcurrencyTest::test_manual_prune_uses_the_same_protocol_against_a_concurrent_writer` ejecuta el comando manual contra otra conexión.  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->
- `FrontendMediaReferenceConcurrencyTest::test_writers_and_pruner_force_read_committed_before_their_first_query` cambia el default de sesión, ejecuta writer/publisher/pruner y afirma `SHOW transaction_isolation = read committed` dentro de cada transacción protegida.  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->
- `FrontendMediaPurgeAtomicityTest` (T-9h, PostgreSQL + disco real): rollback tras el intent conserva fila y archivos; commit dispara la purga posterior; retry tras fallo de archivos completa sin revivir la fila; retry tras borrar la fila es no-op; media marcada bloquea writers; dispatch perdido se recupera; manual y programado usan el mismo pruner y job.  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->
- `FrontendMediaPurgeStrictnessTest` (T-9i/T-9j): `delete()` que devuelve `false`, excepción absorbible y eliminación parcial conservan fila e intent; el inventario incluye la **familia responsive del original** (`media_library_original`) y una sola responsive superviviente impide borrar la fila.  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->
- `FrontendMediaDerivativeRaceTest` (T-9k): los jobs guardados de conversiones y responsive **abortan en su punto de escritura** ante un intent activo o fila ausente; la ventana de asentamiento detecta un derivado reaparecido y conserva la fila; el barrido de huérfanos elimina derivados sin fila `media` y nunca toca colecciones de `FrontendSetting`; la config apunta a las clases guardadas.  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->
- `FrontendMediaPruneScopeTest::test_only_section_images_and_service_image_are_candidates` y `::test_setting_brand_collections_are_never_candidates`.  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->
- `FrontendCacheGenerationTest` cubre inicialización y bumps concurrentes.
- `FrontendServiceCtaTest` cubre preselección válida/invalidación uniforme.
- `FrontendFooterRenderTest` cubre links deshabilitados.
- `FrontendThemeRuntimeTest` cubre variables emitidas y utilities semánticas consumidas.
- `FrontendHomeSectionsTest` separa Property featured/opportunity y Project featured.

---

## SEO

- Las rutas de preview deben enviar `noindex, nofollow`.
- La preview no debe aparecer en sitemap.
- Las rutas públicas normales deben usar contenido publicado.
- Si una página no tiene meta propios, usar defaults de RFC-071.

---

## Observabilidad

Registrar eventos básicos:

- `frontend.previewed` opcional.
- `frontend.published`.
- `frontend.publish_failed`.
- `frontend.cache_generation_bumped`.

Datos mínimos:

- Actor.
- Entidad.
- Timestamp.
- Resultado.
- Mensaje de error si aplica.

No registrar contenido sensible completo en logs.

---

## Archivos esperados

```text
app/
  Filament/
    Pages/
      FrontendPreview.php                        (o acción equivalente)
  Services/
    Frontend/
      FrontendPreviewService.php
      FrontendPagePublisher.php                 (supersede el nombre genérico FrontendPublishService)
      FrontendServicePublisher.php
      FrontendPreflightValidator.php
      FrontendMediaReference.php                 (nombre real; valida existencia/owner/coleccion + formato uuid; la VALIDACION no toma lock)
      PublishedMediaReference.php                (predicado de referencia publicada; owningPage con withTrashed; resolvePublished por uuid+pagina)
      FrontendMediaPruner.php                    (usado por manual y scheduler)  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->
  Jobs/
    PromoteFrontendMedia.php                    (post-commit, idempotente)
    PurgeFrontendMediaFiles.php                 (purga fisica estricta post-commit; verifica ausencia  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->
                                                 antes de borrar la fila)
  Console/Commands/
    ReconcileFrontendMediaPromotions.php        (re-encola promociones pendientes)
    PruneFrontendMedia.php                      (manual; delega al pruner compartido)  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->
    MaintainFrontendMedia.php                   (único programado; mismo pruner)  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->

database/
  migrations/
    xxxx_create_frontend_pages_table.php         (incluye publicación + draft_revision)
    xxxx_create_frontend_services_table.php      (incluye draft/published payload + draft_revision)

resources/
  views/
    frontend/preview-shell.blade.php             (si aplica)

routes/
  console.php                                    (programa solo frontend:media:reconcile; ningun comando destructivo)

tests/
  Feature/Frontend/
    FrontendPreviewAccessTest.php
    FrontendPublishFlowTest.php
    FrontendPreflightValidationTest.php
    FrontendDraftIsolationTest.php
    FrontendPublishConcurrencyTest.php
    FrontendServicePublishConcurrencyTest.php
    FrontendMediaPromotionTest.php
    FrontendMediaReferenceConcurrencyTest.php
    FrontendMediaPruneScopeTest.php  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->
    FrontendMediaPurgeAtomicityTest.php            (intent, rollback, orden archivos-fila, retry)  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->
    FrontendMediaPurgeStrictnessTest.php           (delete=false, excepcion, parcial, responsive del original)  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->
    FrontendMediaDerivativeRaceTest.php            (jobs guardados, advisory lock, settle window)  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->
    FrontendMediaOrphanSweepTest.php               (scope por ruta, huerfanos, separacion de FrontendSetting)  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->
    FrontendMediaMaintenanceScheduleTest.php       (orquestador: reconcile -> prune -> prune-orphans)  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->
    FrontendMediaLockLifecycleTest.php             (excepcion, unlock verificado, reconexion, lease vencido)  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->
```

---

## Reglas técnicas

- No publicar contenido que falle validaciones críticas.
- No mostrar drafts en rutas públicas normales.
- No crear preview pública anónima.
- No indexar preview.
- No depender de cache stale para publicar.
- No implementar historial complejo salvo necesidad real.
- La publicación debe invalidar únicamente con bump global post-commit de RFC-076; no usa clear/`forget` dirigido.
- El orden de locks es página→secciones `id ASC`, o servicio. Media se **valida** sin lock (en v1 nada la borra, §16.4); ningún publisher escribe JSON antes de validar sus `media_id`.
- Candidate discovery de prune es advisory. Manual y scheduler llaman el mismo `FrontendMediaPruner`; cada transacción ejecuta primero `SET TRANSACTION ISOLATION LEVEL READ COMMITTED`, bloquea media y reconsulta las cuatro fuentes JSON. El mutex no es safety boundary y no se confía en el aislamiento por default del ambiente.  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->
- **La transacción del prune NO borra la fila `media` ni toca el filesystem** (§16.4.2 de la épica): Spatie borra los archivos de forma síncrona en `deleted` (`MediaObserver.php:55-65`), dentro de la transacción, así que un rollback dejaría la fila apuntando a archivos inexistentes. La transacción solo escribe un **intent durable**; `PurgeFrontendMediaFiles` hace la purga física **post-commit y fuera de transacción**, borrando **archivos primero y la fila después** (con eventos suprimidos) para que ningún fallo intermedio deje huérfanos. El retry es idempotente y el dispatch perdido se recupera por barrido.  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->
- **Exclusión real con los jobs de derivados, en dos mecanismos.** Tras entrar a `handle()`, Spatie ejecuta múltiples escrituras (`ResponsiveImageGenerator.php:44-46,50,111`; `PerformConversionAction.php:51`), así que un chequeo inicial no protege; y **un advisory lock solo tampoco**, porque es de sesión y fuera de transacción Laravel reconecta y reintenta (`Database/Connection.php:998-1004,1020-1023`), perdiendo el lock sin que el job se entere. La **autoridad de correctitud** es un **lease durable** (`frontend_media_activity`, PK `media_id`, `expires_at` derivado del `$timeout` del job): es dato, sobrevive a la reconexión, y el worker mata al job al vencer su timeout, de modo que un job vivo nunca sobrevive a su lease. El **advisory lock** cubre solo las secciones cortas de adquisición/liberación del lease y la decisión del pruner, sobre una **conexión dedicada `pgsql_locks`**, con `pg_backend_pid()` capturado y comparado, liberación en `finally` con `pg_advisory_unlock` verificado y `pg_advisory_unlock_all()` de cierre. Sin lease disponible, el pruner registra `skipped_busy` (§16.4.2).  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->
- **El barrido de huérfanos es decidible por ruta.** `DefaultPathGenerator` emite solo `{media_id}` (`:36-45`), por lo que una vez borrada la fila el scope sería irreconstruible. Se registra `FrontendMediaPathGenerator` en `custom_path_generators` (`config/media-library.php:154`) para que la media editorial viva bajo `frontend-editorial/{collection}/{media_id}/`; `FrontendSetting` nunca está en ese subárbol. El barrido corre **dentro de `frontend:maintain-media`**, al final de reconcile → prune → prune-orphans (§16.4.2).  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->
- **La purga es estricta y observable.** No se usa `DefaultFileRemover::removeAllFiles()` como prueba de éxito: es `void`, ignora el retorno de `delete()` y **absorbe excepciones** (`DefaultFileRemover.php:17,44,50-52,72,78-80,113,119-121`), y los discos declaran `throw=false`/`report=false` (`config/filesystems.php:37,49`). El job inventaría las rutas con la fila viva, borra evaluando cada retorno y **verifica la ausencia** de original, conversiones y responsive images. Cualquier `false`, excepción o archivo residual **conserva la fila y la marca**, incrementa `attempts` y provoca retry (§16.4.2).  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->
- Prune solo considera `FrontendSection/images` y `FrontendService/image`; excluye `FrontendSetting` logo/favicon/OG.  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->

---

## Riesgos

| Riesgo | Impacto | Mitigación |
| --- | --- | --- |
| Draft visible públicamente | Información no aprobada en producción. | Aislamiento draft/publicado. |
| Preview indexada | SEO y contenido duplicado. | `noindex`, rutas autenticadas. |
| Publicación rompe layout | Sitio público dañado. | Preflight + QA visual. |
| Generación de cache no aumenta | Owner no ve cambios publicados. | Bump global post-commit obligatorio + TTL corto. |
| Workflow demasiado complejo | Implementación lenta. | Publicación simple por entidad, sin enterprise CMS. |
| Prune concurrente confirma UUID colgante | Draft/publicado apunta a media inexistente. | Protocolo compartido de locks/recheck + rollback nominal y pruebas de dos conexiones. |  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->

---

## Definition of Done

- Owner puede previsualizar cambios **de estrategia B** (páginas institucionales) antes de publicar. Las entidades de estrategia A (tema, contacto, nav, footer, CTAs globales, **y todo `FrontendService` — disponibilidad y contenido editorial, por C-G-1**) son inmediatas: guardar = publicar, y su preview es el sitio en vivo (§16.9).
- Otros roles no pueden acceder a preview ni publicar.
- Drafts no aparecen en rutas públicas normales.
- Guardar (estrategia A) y publicar (estrategia B) ejecutan las validaciones preflight que apliquen.
- Publicar/guardar invalida cache (bump de generación, §16.8).
- Publicar registra actor y timestamp.
- Cada mutación draft de página/sección incrementa `frontend_pages.draft_revision`; una UI stale no puede publicar el snapshot.
- ~~Cada mutación de `FrontendService.draft_payload` incrementa `frontend_services.draft_revision`; el publisher exige `expected_draft_revision_service`.~~ **RETIRADO por C-G-1:** servicios son estrategia A; su contenido se guarda y publica al instante con validación dura.
- Rollback no promueve media; enqueue perdido se reconcilia; retries son idempotentes.
- Draft/publish bloquean y validan UUIDs antes de escribir; prune manual/programado usa el mismo protocolo **lock→recheck→intent** (nunca delete en transacción) y excluye media de marca. La purga física es post-commit, archivos antes que fila, idempotente (§16.4.2).  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->
- Preview usa `noindex, nofollow`.
- Tests cubren acceso, draft isolation, stale publisher, promoción durable, publicación, invalidación y carreras draft/publish/manual-prune con dos conexiones PostgreSQL y ambos interleavings.  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->
- QA visual mínimo documentado y ejecutado.
- `php artisan test` verde sobre PostgreSQL real.
- Pint limpio.
- `npm run build` verde.

---

## Dependencias

- RFC-071 — Perfil público y configuración base.
- RFC-072 — Tema visual configurable.
- RFC-073 — Navegación, footer y CTAs globales.
- RFC-074 — Servicios ofrecidos y disponibilidad.
- RFC-075 — Contenido editable de páginas institucionales.
- RFC-076 — Render público, caché y fallbacks.
- Épica 12 documento general: `docs/epicas/epica-12-administrador-contenidos-frontend.md`.

---

## Cierre de serie RFC-071 → RFC-077

Con este RFC, la Épica 12 queda dividida en siete pasos implementables:

1. RFC-071 — Perfil público y configuración base.
2. RFC-072 — Tema visual configurable.
3. RFC-073 — Navegación, footer y CTAs globales.
4. RFC-074 — Servicios ofrecidos y disponibilidad.
5. RFC-075 — Contenido editable de páginas institucionales.
6. RFC-076 — Render público, caché y fallbacks.
7. RFC-077 — Preview, publicación y QA visual.
