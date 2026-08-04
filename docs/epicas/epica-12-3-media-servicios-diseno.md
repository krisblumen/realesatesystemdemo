# Épica 12.3 — Diseño: media privada para `FrontendService.image`

**Versión:** v2 — corrige C-1, C-2, C-3, C-4, M-1…M-6 y Mn-1…Mn-3 de `docs/audits/epica-12-3-media-servicios-auditoria-diseno.md`
**Estado:** propuesta de diseño, pendiente de gate
**Cierra:** la deuda declarada en Épica 12.1 §0.8 y §0.9
**Contrato de implementación:** `docs/epicas/epica-12-2-lotes-implementacion.md` §9
**Subordinado a:** Épica 12 §16.4 (fuente única) y al pipeline aprobado en 12.1-A

---

## 0. Qué corrige esta versión

La v1 fue **RECHAZADA** con cuatro hallazgos críticos. Los cuatro eran correctos y se verificaron en el código antes de reescribir. Se registran acá porque el error de fondo importa más que la corrección:

| # | Qué afirmaba la v1 | Qué dice el código | Dónde se corrige |
| --- | --- | --- | --- |
| **C-3** | «Dos servicios con el mismo uuid: **imposible por construcción**» | `image_media_id` es `uuid nullable` con FK y **sin índice único**; el único parcial es por `code` (`2026_07_23_100000_create_frontend_services_table.php:39,52,55`) | §5 |
| **C-4** | Una interfaz de cinco métodos con `lockChainFor(): ?Media` | La implementación aprobada devuelve `array{?FrontendPage, ?Media}` (`PublishedMediaReference.php:184`) y el job usa además `isPromoted`, `clearPending`, `markPromoted`, `isReferencedByPublishedRevision` (`PromoteFrontendMedia.php:59-93`) | §3 |
| **C-2** | «El guardado hace lo que en páginas hace el publisher» | Nunca se escribió. `afterSave()` sólo apunta la columna y hace `bump()`: sin transacción, sin lock, sin `pending`, sin dispatch (`EditFrontendService.php:24-41`) | §4 |
| **C-1** | «Resolver la URL por estado de promoción, igual que páginas» | El render hace `resolve(...)->getUrl()` sin mirar promoción (`FrontendServicesService.php:180-188`) y la interfaz no declaraba nada de resolución pública | §6 |

**La lección de C-3 es la que vale.** «Imposible por construcción» era una garantía que la base de datos no da: `FrontendMediaReference::isEligible()` valida una consulta individual, no impide que existan dos filas vivas con el mismo `image_media_id`. Se afirmó una invariante sin verificar que estuviera impuesta. Esta versión no afirma ninguna invariante que no tenga un índice, un lock o una prueba detrás.

---

## 1. Estado verificado en el código (no supuesto)

| Hecho | Dónde | Consecuencia |
| --- | --- | --- |
| La colección `image` se registra **sin `useDisk`** | `FrontendService.php:66` | Hereda `config/media-library.php:36` → disco **`public`**. Toda imagen de servicio es pública desde que se sube. |
| La verdad editorial es una **columna** | `image_media_id` en `$fillable:36` | No hay snapshot que recorrer. |
| «Guardar es publicar» (estrategia A) | docblock del modelo, líneas 20-21 | **No existe borrador**: ni `draft_revision`, ni `published_revision`, ni versionado optimista. |
| `image_media_id` **no tiene índice único** | migración `:39,52` | Dos filas vivas pueden compartir uuid. Ver §5. |
| `afterSave()` no participa de ninguna promoción | `EditFrontendService.php:24-41` | Toda la secuencia de §4 hay que escribirla. |
| El render emite `getUrl()` sin mirar promoción | `FrontendServicesService.php:180-188` | Ver §6. |
| El uploader ya es no destructivo | `NonDestructiveMediaUpload` en `FrontendServiceResource.php:69-72` | Cláusula «uploader sin ruta de borrado» de §16.4 **ya cerrada**. |
| La colección no usa `singleFile()` | comentario en `registerMediaCollections()` | Reemplazar no borra: se acumulan versiones. |
| `PublishedMediaReference` se prohíbe tocar servicios | docblock `:31-32` | Contradicción con el contrato de 12.3. Ver §3. |
| `danglingPending()` acotado a `FrontendSection`/`images` | `:151-157` (hallazgo M-6 de 12.1) | La reconciliación **hoy no ve** servicios. |
| Un test **exige** que la reconciliación no toque servicios | `FrontendMediaPromotionTest.php:252-268` (TA-12) | Hay que actualizarlo intencionalmente. Ver §9.2. |
| La policy **no permite** `restore` | `FrontendServicePolicy.php:44` | Ver §8.2: restore no es operación de dominio en v1. |
| La invalidación es por generación durable, en `afterCommit`, vía `FrontendPublisher::invalidate()` → `bump()` | `FrontendMediaObserver.php:61` + `FrontendCachePublisher.php:16-19` | Es el ÚNICO protocolo; no se introduce otro. Ver §7. |
| La ruta owner-only existe sólo para secciones | `routes/web.php:47` | Ver §8.1. |
| La reconciliación corre cada 15 min | `routes/console.php:15-19` | Es red de seguridad, no mecanismo primario. |

---

## 2. Por qué este lote no es «aplicar lo mismo»

**El predicado de promoción de 12.1 no tiene contraparte en servicios.**

En páginas la pregunta es «¿la revisión **publicada** todavía nombra este uuid?». Existe porque hay dos estados y la promoción es el puente. En servicios **no hay dos estados**: guardar es publicar. Esa pregunta ahí no es difícil de responder, es **sin sentido**.

El equivalente exacto es la columna:

> **Predicado de servicios:** un uuid está vigente si es el `image_media_id` de un `FrontendService` **vivo** (no soft-deleted).

Mismo rol en la máquina de estados, distinta definición de «vigente».

### 2.1 Qué gana el sitio (el argumento honesto)

En páginas el beneficio era que el **borrador** no se filtrara. En servicios ese argumento **no aplica**: no hay borrador. Si el único motivo fuera uniformidad, este lote no valdría su costo.

El motivo real es una fuga concreta: **una imagen reemplazada queda accesible para siempre.** La colección no borra —y no debe—, así que al cambiar la foto de un servicio la anterior deja de referenciarse pero **su URL pública sigue viva**, enumerable en `/storage/`, indefinidamente. Puede ser material que el cliente pidió bajar.

Con el pipeline privado, dejar de referenciar **es** dejar de ser accesible.

**Y lo que este lote NO arregla, dicho acá y no en una nota al pie:** las versiones superadas que **ya** están en público siguen accesibles. Esconderlas exige mover o borrar archivos y v1 prohíbe el borrado físico (§18.13). Es **deuda de confidencialidad declarada**, medible por §9.1, no un aislamiento completo.

---

## 3. Arquitectura: un mecanismo, dos predicados (cierra C-4)

El contrato pide reutilizar `PublishedMediaReference`; el docblock de esa clase se prohíbe a sí misma tocar servicios. La v1 propuso extraer una interfaz, pero **la propuso incompleta**: `lockChainFor(): ?Media` contra el `array{?FrontendPage, ?Media}` real, y sin las operaciones de estado que el job efectivamente usa. Esa interfaz no era implementable sin tocar la clase aprobada — lo mismo que la v1 prohibía. Contradicción interna.

### 3.1 Las tres piezas

**a) `MediaLockChain`** — resultado tipado del lock, con el dueño incluido:

```php
final class MediaLockChain
{
    public function __construct(
        public readonly ?Model $owner,   // FrontendPage | FrontendService
        public readonly ?Media $media,
    ) {}

    public function isComplete(): bool
    {
        return $this->owner !== null && $this->media !== null;
    }
}
```

**b) `MediaPromotionState`** — la máquina de estados, **una sola**. Hoy vive dentro de `PublishedMediaReference` (`:222-268`) y depende únicamente de `custom_properties`: es idéntica para cualquier dueño. Se extrae con **firmas y cuerpos sin cambios** y `PublishedMediaReference` conserva sus métodos públicos como delegación de una línea, de modo que sus pruebas de 12.1-A/B siguen valiendo tal cual.

Contiene `isPromoted`, `isPending`, `markPending`, `clearPending`, `markPromoted` y las constantes `PENDING`, `PROMOTED`, `AUTHORIZING_REVISION`. Las tres invariantes (promoted terminal; promoted ⇒ no pending; pending ⇒ referenciada) se conservan textualmente.

**c) `PromotableMediaOwner`** — lo único que difiere por dueño:

```php
interface PromotableMediaOwner
{
    /** El morph configurado, NUNCA un FQCN hardcodeado (Mn-1). */
    public function modelType(): string;

    public function collection(): string;

    /** Toma la cadena de locks de ESTE dueño y devuelve dueño + media. */
    public function acquireLockChain(string $uuid): MediaLockChain;

    /**
     * ¿El uuid sigue referenciado por el contenido VIGENTE?
     * Se evalúa SOBRE EL DUEÑO YA BLOQUEADO: releer bajo lock es lo que impide
     * promover una imagen que dejó de ser la actual durante la copia.
     */
    public function isReferencedByLiveContent(string $uuid, Model $lockedOwner): bool;

    /** Filas `pending` de este dueño que ya nadie referencia. */
    public function danglingPending(): iterable;

    /** Contexto de log: identidad del dueño, nunca contenido editorial. */
    public function logContext(Model $owner): array;
}
```

`acquireLockChain` se llama distinto que `lockChainFor` **a propósito**: `PublishedMediaReference` conserva su método actual intacto y agrega un adaptador de tres líneas.

```php
public function acquireLockChain(string $uuid): MediaLockChain
{
    [$page, $media] = $this->lockChainFor($uuid);

    return new MediaLockChain($page, $media);
}
```

**Restricción normativa, dicha con precisión** —la formulación anterior («ningún cuerpo se reescribe») se contradecía con la delegación de §3.1(b), porque delegar ES reescribir un cuerpo—:

1. **Ninguna firma pública cambia.** Ni nombres, ni parámetros, ni tipos de retorno.
2. **Los métodos con lógica de dominio** —`mediaIdsOf`, `isReferencedByPublishedRevision`, `resolvePublished`, `owningSection`, `owningPage`, `danglingPending`, `lockChainFor`— **conservan su cuerpo intacto**. Son los que la auditoría de 12.1-A corrigió y aprobó.
3. **Los cinco métodos de estado** —`isPromoted`, `isPending`, `markPending`, `clearPending`, `markPromoted`— **sí cambian de cuerpo**: pasan a ser una llamada de una línea a `MediaPromotionState`, que recibe la lógica **textualmente**. Es el único cuerpo que se toca, y se toca para que exista **una** máquina de estados en vez de dos.
4. **Criterio de verificación:** **ninguna aserción** de los tests de 12.1-A y 12.1-B cambia. Si una aserción necesita cambiar, la extracción alteró comportamiento y debe revertirse.

   *Precisión incorporada durante la implementación:* la formulación original decía «los tests pasan sin modificarse», y era demasiado absoluta — confundía comportamiento con **firma de una dependencia inyectada**. Cinco tests invocaban el job como `->handle(app(PublishedMediaReference::class))`, acoplándose a su dependencia concreta; al pasar el job a resolver la estrategia, rompen con `TypeError` **sin un solo fallo de aserción**. La corrección es de invocación —`app()->call([$job, 'handle'])`, que además desacopla el test de las dependencias del job— y queda registrada acá en vez de aplicarse en silencio. El criterio verificable es el de las aserciones.

Ese código pasó cinco gates y una corrección de orden de locks hallada por auditoría; la precisión sobre qué se conserva no es formalismo, es lo que hace verificable la restricción.

### 3.2 Resolución de estrategia, fail-closed

Un registry `PromotableMediaOwners` mapea `model_type → estrategia`. **Un `model_type` desconocido no promueve nada**: el job registra el hecho y termina sin tocar la fila. Nunca cae a una estrategia por defecto — un tipo nuevo sin estrategia declarada es exactamente donde una promoción indebida se colaría.

`PromoteFrontendMedia` deja de tipar `PublishedMediaReference` y pide el registry. Su cuerpo —copiar, verificar existencia y tamaño, voltear `disk`/`conversions_disk`, idempotencia por lock— **no cambia**. El guard estructural T3-11 exige que no aparezca un `if`/`match` por tipo dentro del job: la variación vive en la estrategia o no vive.

### 3.3 Cadena de locks de servicios

> **`service (withTrashed) → media(uuid ASC)`**

Páginas usa `page → section(id ASC) → media(uuid ASC)`. **Son jerarquías disjuntas**: una media pertenece a una sección o a un servicio, nunca a ambas, así que ningún actor toma las dos y no hay ciclo posible. Se declara acá porque un orden que no está escrito es el que alguien invierte — el hallazgo C-A-1 de 12.1-A fue exactamente eso, con un comentario que decía lo contrario del código.

`withTrashed()` en el dueño: un servicio dado de baja sigue siendo el dueño legítimo de su archivo, igual que una sección soft-deleted en §7.11.

---

## 4. La secuencia de guardado, completa (cierra C-2)

Hoy `afterSave()` apunta la columna y hace `bump()`. Nada más. Esta es la secuencia normativa que la reemplaza — **la reconciliación es red de seguridad, no el mecanismo de publicación**.

### 4.1 En la transacción del guardado

1. **Abrir transacción** y bloquear el servicio: `FrontendService::withTrashed()->whereKey(...)->lockForUpdate()`.
2. **Resolver el candidato**: el uuid que dejó el uploader (media más reciente de la colección `image`).
3. **Validar** con `FrontendMediaReference::isEligible($uuid, $service, 'image')` — uuid bien formado, morph correcto, `model_id` del servicio, colección `image`. Inelegible ⇒ `image_media_id = null` (fallback), sin excepción.
4. **Escribir** `image_media_id` bajo el mismo lock.
5. **Marcar `pending`** con `MediaPromotionState::markPending()`, tomando el lock de la fila de media. `promoted` es terminal: una media ya promovida no vuelve a `pending`.
6. **Desmarcar el anterior**: si el uuid saliente quedó `pending` y ya no es la columna, se le limpia el flag — el análogo exacto de lo que hace el publisher de páginas al soltar una referencia.
7. **Commit.**

### 4.2 Después del commit

8. **Despachar `PromoteFrontendMedia` en `afterCommit`.** Nunca dentro de la transacción: un job que corre antes del commit lee un estado que puede no existir.
9. La invalidación de caché ocurre por el protocolo vigente y **sólo** por él (§7).

### 4.3 En el job

10. Tomar `acquireLockChain($uuid)` → `service (withTrashed) → media`.
11. Si ya está `promoted` ⇒ limpiar un `pending` residual y salir. Idempotente por construcción: dos corridas, la segunda no hace nada.
12. **Revalidar el predicado sobre el dueño ya bloqueado.** Si `image_media_id` dejó de ser este uuid ⇒ `clearPending` y salir **sin promover**. Este paso es el que impide promover una imagen que dejó de ser la actual mientras se copiaba.
13. Copiar al disco público **preservando `getPathRelativeToRoot()`**; verificar existencia y tamaño en destino; recién entonces voltear `disk` y `conversions_disk` y marcar `promoted`.
14. Ante fallo: **la media queda `pending`**, el job falla y reintenta. Nunca se marca `promoted` sin bytes verificados.

### 4.4 Observabilidad

Un registro por promoción con `uuid`, tipo y clave del dueño, estado anterior, estado final y generación de caché resultante. **Identidad, nunca contenido editorial** — la misma regla de RFC-077 que ya rige para `frontend.published`.

---

## 5. Unicidad de `image_media_id` (cierra C-3)

La v1 afirmó que la colisión era imposible. **Es posible**: no hay índice único.

**Migración aditiva:**

```sql
CREATE UNIQUE INDEX frontend_services_image_media_id_unique
    ON frontend_services (image_media_id)
    WHERE deleted_at IS NULL AND image_media_id IS NOT NULL;
```

Parcial y sobre filas vivas, con el mismo criterio que el índice de `code` que ya existe (§16.1.2). Declarado en SQL crudo, nunca como `unique()` de Blueprint, que generaría un índice total.

**Decisión sobre soft-delete:** un servicio dado de baja **no** retiene la exclusividad, y aun así restaurarlo no puede colisionar. La razón no es el índice sino la propiedad: una media pertenece a un `model_id`, y `isEligible()` impide que otro servicio la referencie legítimamente. El índice cubre lo que la validación no puede: una escritura directa por SQL o un bug que saltee el servicio de dominio.

**Y esto es lo que la v1 debió decir desde el principio:** la validación protege el camino de la aplicación; el índice protege la base. Confiar sólo en la primera es llamar «invariante» a una convención.

Se prueba con **SQL directo** —no por el formulario— y con **dos conexiones PostgreSQL reales** que intentan la colisión a la vez (T3-6, T3-7).

---

## 6. Resolución pública: una sola regla (cierra C-1)

La v1 dijo «resolver por estado de promoción» sin especificar cómo, con tres estados en la cabeza. La regla es **una**:

> **Sólo una media `promoted` se emite en el render público. Todo lo demás cae al fallback existente.**

Concretamente, `FrontendServicesService::imageUrl()` pasa a:

1. `FrontendMediaReference::resolve($uuid, $service, 'image')` — pertenencia y sintaxis, como hoy;
2. `MediaPromotionState::isPromoted($media)` — si no, **`null`**;
3. `Media::getUrl()` sólo entonces.

| Situación | Se emite | Por qué |
| --- | --- | --- |
| Media nueva, privada, aún no promovida | **No** — fallback | Es la ventana entre el commit y el job. Preferimos sin foto a una URL rota o privada. |
| Media promovida | **Sí** | Bytes verificados en el disco público. |
| Media legacy pública marcada por la migración | **Sí** | La migración la deja `promoted` (§7): entra por la misma regla, no por una excepción. |
| Media legacy que la migración **omitió** (archivo faltante) | **No** — fallback | Fail-closed: mejor sin imagen que una URL rota. La migración la reporta (§7). |
| Uuid ajeno, mal formado o ausente | **No** — fallback | `resolve()` ya lo cubre. |

**Una sola regla y no tres casos**: la migración garantiza que lo vigente y servible ya está `promoted`, así que el render no necesita saber qué es «legacy». Eso es lo que hace el contrato auditable.

**El fallback es el que ya existe** (Mn-3): `image_url = null` y `image_alt` cayendo al título (`FrontendServicesService.php:151-152`). Una media pendiente, inválida, ajena o ausente **nunca rompe el bloque**; el servicio se renderiza sin foto.

**Conversiones (Mn-2):** la colección `image` **no** declara conversiones y este lote **no** las agrega. Se repite acá la invariante que el job ya advierte (`PromoteFrontendMedia.php:104-111`): si alguna vez se agrega un derivado, debe promoverse como **familia** —original y conversiones juntos— o quedaría una miniatura privada colgando de un original público. Agregar una conversión a `image` sin modificar el contrato de promoción queda **prohibido**.

---

## 7. Migración de lo existente (cierra M-3)

**No se mueve ni un archivo.** Una imagen que hoy está en el disco público y es el `image_media_id` de un servicio vivo cumple exactamente la definición de `promoted`: copiada, verificada y sirviéndose. Marcarla reconoce el estado en el que ya está.

**Forward-only, idempotente y transaccional en los metadatos.** Recorre `model_type = FrontendService`, `collection_name = 'image'`, `disk = 'public'`:

| Condición | Acción |
| --- | --- |
| Es el `image_media_id` de un servicio vivo **y el archivo existe con tamaño > 0** | `promoted` |
| Es la columna vigente **pero el archivo falta o mide 0** | **No se marca.** Se reporta en la salida del comando. Fail-closed: el servicio cae al fallback antes que servir una URL rota. |
| No es la columna vigente | **Se deja tal cual, sin flags.** Es una versión superada; marcarla `promoted` la volvería intocable para siempre. |

Verificar el archivo antes de marcar es la garantía que el job ya tiene (`PromoteFrontendMedia.php:113-137`) y que la v1 no heredaba. Una segunda corrida no cambia nada (T3-12).

Recién después, `registerMediaCollections()` agrega `->useDisk('frontend-private')`: sólo afecta a subidas **nuevas**, porque el disco vive en la fila y ya está fijado para las viejas.

**Invalidación (M-1):** la migración bumpea la generación **una vez al final**, después del commit, por el protocolo vigente. No se introduce `Cache::forget` ni un segundo contador. Y el flip de promoción del job **también** invalida vía `FrontendMediaObserver`, que ya observa `Media` de `FrontendService` (`:18-26`) y llama `DB::afterCommit(fn () => app(FrontendPublisher::class)->invalidate())` (`:61`), cuya implementación `FrontendCachePublisher::invalidate()` **es** el `bump()` de la generación (`FrontendCachePublisher.php:16-19`) — sin esa invalidación, una respuesta cacheada conservaría `image_url = null` después de promover. Se prueba con `generation_before < generation_after` junto al cambio de disco y de estado (T3-4).

---

## 8. Preview owner-only y ciclo de vida

### 8.1 La ruta (cierra M-5)

```
GET /admin/frontend/servicios/{service}/media/{uuid}   → frontend.services.media
```

**Especificación normativa, calcada de `FrontendSectionMediaController`:**

1. **Sin middleware `auth`**, por la razón documentada en `routes/web.php:43-46`: el middleware redirige con 302 y HTML, y eso **delata que la ruta existe**.
2. `abort_unless(Auth::check(), 404)`.
3. `abort_unless(Gate::allows('view', $service), 404)` — la policy owner-only vigente (`FrontendServicePolicy.php:24`).
4. `FrontendMediaReference::resolve($uuid, $service, 'image')`, que rechaza el uuid mal formado **antes** de tocar la columna uuid nativa (§7.10). `abort_if(null, 404)`.
5. **Binding con `withTrashed()`**: un servicio dado de baja debe poder previsualizarse en el panel; su media sigue siendo suya.
6. `response()->file($media->getPath())` — bytes **inline** desde el disco privado. **Prohibido** devolver la URL pública como sustituto: sería un preview que sólo funciona cuando ya no hace falta.

**404 uniforme en los cinco casos**: anónimo, autenticado sin permiso, servicio inexistente, uuid mal formado y uuid ajeno. Un 403 donde va un 404 confirma que el recurso existe.

### 8.2 Soft-delete y restore (cierra M-4)

La v1 prometió que restaurar re-promueve «sin intervención manual», como si hubiera un botón. **No lo hay**: `FrontendServicePolicy::restore()` devuelve `false` (`:44`).

**Decisión: restaurar no es una operación de dominio en v1.** Un servicio se da de baja y su imagen deja de estar vigente. Si alguna vez se restaura por SQL administrativo, la reconciliación —que corre cada 15 minutos (`routes/console.php:15-19`)— vuelve a dejar consistente el estado. **SLA declarado: hasta 15 minutos**, y durante esa ventana el servicio renderiza sin foto por la regla de §6.

T3-9 prueba **ese** camino —el real y autorizado—, no una llamada a un comando interno que finge un flujo que no existe.

---

## 9. Reconciliación, reporte y el test que cambia

### 9.1 Reporte (cierra M-2)

`frontend:media:report-unreferenced` hoy filtra sólo `FrontendSection`/`images` (`ReportUnreferencedFrontendMedia.php:27-62`). Se amplía a `FrontendService`/`image` con el predicado de §2.

**Es el instrumento que mide la deuda que §2.1 declara.** Sin él, «aceptamos que el histórico siga público» es una frase sin evidencia. La salida informa, por fila: uuid, dueño, disco, estado y **bytes**, con total al pie — para poder decidir la limpieza física futura con un número y no con una intuición. Idempotente y sin efectos.

T3-10 fija el comportamiento: una imagen **reemplazada aparece**; la **actual no**.

### 9.2 Reconciliación y TA-12

`danglingPending()` está acotado a `FrontendSection`/`images` por el hallazgo M-6 de 12.1, cuyo motivo era no tocar flags de modelos fuera de alcance. Con este lote **`FrontendService` entra; `FrontendSetting` sigue fuera.**

El test `FrontendMediaPromotionTest::test_reconciliation_never_touches_media_outside_this_increment` (`:252-268`) hoy **exige** que la reconciliación no toque servicios. Se actualiza **intencionalmente**, no de callada:

- **conserva** la regresión de `FrontendSetting` —que sigue fuera y es lo que el test protege de verdad—;
- **invierte** la aserción sobre `FrontendService`: su media `pending` sin referencia vigente **sí** debe limpiarse.

Borrarlo sin reemplazo perdería la regresión de marca; dejarlo intacto crearía una contradicción falsa. Y el docblock de `danglingPending()` se actualiza en el mismo commit: un comentario que dice «excluye FrontendService» cuando ya no lo excluye es peor que no tener comentario.

---

## 10. Alcance y decisiones cerradas

### 10.1 Lo que este lote NO hace

1. **No introduce borrador en servicios.** Estrategia A intacta (enmienda C-G-1).
2. **No borra archivos**, ni superados ni des-referenciados (§18.13).
3. **No esconde retroactivamente** las versiones superadas ya públicas (§2.1, medido por §9.1).
4. **No toca `FrontendSetting`** ni la media de contratos, `Property`, `Project`, permisos ni el lead form.
5. **No agrega conversiones** a `image` (§6).
6. **No cambia** el mecanismo de invalidación: generación durable en `afterCommit`, el único protocolo (§16.8).

### 10.2 `image_alt`: decidido (cierra M-6)

La v1 lo dejó como opción «si el gate prefiere». Eso no es un diseño: es trasladarle la decisión al implementador.

> **Decisión v1 de servicios: si un servicio tiene imagen, `image_alt` es OBLIGATORIO.** Máximo 150 caracteres, sin HTML, y se valida en el guardado.

Razones: la regla universal de accesibilidad ya rige para toda media de secciones (`FrontendSectionSchema:152-161`); dejar servicios afuera crearía **dos reglas distintas en el mismo panel**. Y no hay `decorative` acá: la foto de un servicio siempre comunica algo.

- **Sobre datos existentes:** la migración **no** falla ni bloquea. El requisito aplica al **guardado**, igual que en `team`.
- **En el render** se conserva el fallback vigente al título (`FrontendServicesService.php:151-152`): un registro viejo sin alt nunca rompe la página.

---

## 11. Matriz de tests (T3-n)

Los cinco primeros vienen del contrato §9; el resto sale de los hallazgos de la auditoría.

| # | Test | Cierra |
| --- | --- | --- |
| T3-1 | La imagen sube a `frontend-private`; **ningún `/storage` ni `image_url`** antes de promover | contrato |
| T3-2 | Preview owner-only: **404 idéntico** en anónimo, no-owner, servicio inexistente, uuid mal formado y uuid ajeno; responde **inline** con `Content-Type` seguro | M-5 |
| T3-3 | **Un solo pipeline**: no hay segundo job ni segundo servicio de promoción (guard estructural) | contrato |
| T3-4 | Promoción idempotente: copia verificada, cambio de disco, estado y **`generation_before < generation_after`** en la misma evidencia | M-1 |
| T3-5 | Sin borrado físico en ninguna ruta | §18.13 |
| T3-6 | Dos servicios con el mismo `image_media_id` **rechazados por SQL directo** | C-3 |
| T3-7 | **Dos conexiones PostgreSQL reales** intentando la colisión: una sola gana | C-3 |
| T3-8 | **Guardado normal del Owner**: marca `pending` y **encola** el job sólo después del commit (no ejecutarlo a mano) | C-2 |
| T3-9 | Servicio soft-deleted deja de estar vigente; restaurado por el **camino real** (SQL + reconciliación) vuelve a promoverse dentro del SLA | M-4 |
| T3-10 | El reporte incluye la media **reemplazada** y **no** la actual; sigue excluyendo `FrontendSetting` | M-2 |
| T3-11 | El orden `service → media` está en el **fuente**, y el job **no** tiene `if`/`match` por tipo | C-4 |
| T3-12 | Migración idempotente: vigente marcada, superada sin flags, **archivo ausente reportado y no marcado**, segunda corrida sin cambios | M-3 |
| T3-13 | **Fallo de copia** deja `pending`, permite retry y **nunca** marca `promoted` | C-2 |
| T3-14 | El render cae al fallback con media pendiente, inválida, ajena, reemplazada o ausente, **sin romper el bloque** | C-1, Mn-3 |
| T3-15 | Una URL pública viva **sigue sirviéndose** durante y después de la migración | C-1 |
| T3-16 | Uuid mal formado devuelve `false`, **no** SQLSTATE 22P02 | §7.10 |
| T3-17 | `image_alt` obligatorio al guardar con imagen; un registro viejo sin alt **renderiza** con el fallback al título | M-6 |
| T3-18 | Un `model_type` sin estrategia registrada **no promueve nada** (fail-closed) | §3.2 |
| T3-19 | Regresión: páginas, media de contratos, roles y `ServiceType` sin cambios; los tests de 12.1-A/B verdes sin modificarse | §8 auditoría |

---

## 11.1 Hallazgo de implementación: el guard de sintaxis faltaba en dos lugares

Encontrado al implementar el Lote B, con una prueba que esperaba `false` y recibió una excepción.

**`media.uuid` es una columna uuid NATIVA de PostgreSQL**, así que consultarla con una cadena mal formada lanza SQLSTATE 22P02 — una excepción, no un «no encontrado» (§7.10). La regla ya estaba establecida en `FrontendMediaReference`, pero **no** en los dos caminos que este lote agregó o modificó:

1. `ServiceMediaReference::acquireLockChain()` — corregido en el mismo lote.
2. `PromoteFrontendMedia::handle()` — la resolución de estrategia que el Lote A agregó consultaba la tabla sin validar. Corregido en la **puerta de entrada común** a todas las estrategias, que es donde corresponde.

**Y queda declarado un hueco PREEXISTENTE, no introducido por 12.3:** `PublishedMediaReference::lockChainFor()` (`:236`) consulta `where('uuid', $uuid)` sin guard desde 12.1. Hoy no es alcanzable —sus dos llamadores pasan por el guard del job—, así que **no se toca en este lote**: modificar código aprobado por un riesgo latente que ya está cubierto aguas arriba es exactamente el tipo de cambio que rompe lo que funciona. Se registra para que la próxima revisión de esa clase lo cierre en su propio contrato.

---

## 11.2 Cambio en producción: la promoción pasa a ser síncrona

**Decisión del owner, 2026-07-28, después de dos incidentes en uso real.**

El diseño aprobado despachaba la promoción a la cola (§4.2). En uso, eso significó que el owner subiera fotos al hero, publicara, viera «Publicada» en el panel y el sitio siguiera sin las imágenes: **no había ningún worker corriendo**, así que el job nunca se ejecutaba. Le pasó dos veces seguidas, y la red de rescate tampoco ayudaba porque la reconciliación corre por el scheduler, que necesita otro proceso vivo.

Para quien administra el sitio, un cambio que se guarda, se publica y no aparece es indistinguible de un bug.

**Qué cambia:** `PromoteFrontendMedia` se ejecuta con `dispatchSync()` en los tres puntos que lo invocan —publicación de páginas, guardado de servicios y reconciliación—.

**Qué NO cambia, y sigue verificado por pruebas:**

1. La copia ocurre **fuera de la transacción**, dentro del `afterCommit`. El sistema de archivos no participa de un rollback de PostgreSQL: copiar adentro dejaría archivos públicos de una publicación que nunca ocurrió.
2. Un fallo de copia **no puede tumbar** una publicación ya confirmada. Cada promoción va en su propio `try`: la media queda `pending` y la reconciliación la retoma.
3. La reconciliación sigue siendo la red de rescate — y ahora también promueve en el acto, porque un rescate que depende del mismo worker ausente no rescata nada.

**El costo medido:** 11 a 44 ms por imagen. Publicar una página con seis fotos suma unos 250 ms, muchísimo menos que el problema que la asincronía evitaba.

**Lo que se acepta:** si algún día una página tuviera decenas de imágenes pesadas, publicar se sentiría lento. Con el máximo actual —6 slides de hero, 8 paneles, 24 integrantes— y las imágenes ya optimizadas a WebP de ~200 KB, ese punto está lejos. Si se acerca, la vuelta a la cola exige antes garantizar un worker supervisado, que es la condición que hoy no se cumple.

---

## 12. Riesgos

| Riesgo | Mitigación |
| --- | --- |
| Extraer la máquina de estados cambia el comportamiento aprobado | Se extrae **sin tocar cuerpos**; `PublishedMediaReference` delega. Los tests de 12.1-A/B pasan **sin modificarse** — criterio de gate (T3-19) |
| Una URL privada sale al HTML público | Regla única de §6: sólo `promoted` se emite. T3-1, T3-14 |
| La migración rompe URLs vivas | No mueve archivos; verifica antes de marcar. T3-15, T3-12 |
| Se promueve una imagen que ya no es la actual | Relectura del predicado **bajo el lock del servicio** (§4.3 paso 12). T3-13 |
| Colisión de dueños por escritura directa | Índice único parcial + validación. T3-6, T3-7 |
| Un servicio restaurado queda sin foto | SLA declarado de 15 min (§8.2). T3-9 |
| Caché sirviendo `image_url = null` tras promover | Bump en el flip por el observer vigente. T3-4 |
| Un tipo nuevo entra al job sin estrategia | Fail-closed (§3.2). T3-18 |
| El histórico público sigue expuesto | **Declarado**, no mitigado. Medido por §9.1 |

---

## 13. Criterios del gate

Se aprueba si el auditor confirma que:

1. **C-1** cierra: una sola regla de resolución pública, con fallback definido y los cinco casos de §6 cubiertos.
2. **C-2** cierra: la secuencia de §4 es atómica, observable, con dispatch `afterCommit` y retry idempotente — y la reconciliación **no** es el mecanismo primario.
3. **C-3** cierra: índice único parcial en SQL crudo, decisión explícita sobre soft-delete y pruebas por SQL directo y dos conexiones reales.
4. **C-4** cierra: la abstracción es **ejecutable** —resultado tipado de lock, estado común, resolución fail-closed— y `PublishedMediaReference` conserva firmas y comportamiento.
5. **M-1…M-6 y Mn-1…Mn-3** están resueltos, no diferidos en silencio.
6. El alcance de §10 es completo y §2.1 declara la deuda residual en vez de aparentar que la cierra.
7. La matriz §11 cubre PostgreSQL real, HTTP y caché en los caminos que la auditoría exigió.

**Sin gate `APROBADO` no se escribe código de este lote.**
