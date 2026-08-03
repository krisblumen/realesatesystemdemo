# Auditoría de implementación — Épica 12.3 Media privada de servicios

**Proyecto:** New Hauz — Plataforma Inmobiliaria  
**Fecha:** 2026-07-27  
**Auditor:** Codex  
**Rama auditada:** `feature/epica-12-content-manager`  
**Commit auditado:** `acaba2a` — `docs(prompts): prompt de auditoría de implementación de la Épica 12.3`  
**Commits de implementación auditados:** `80b5cee`, `1c4967b`, `d986bb6`  
**Contrato directo:** `docs/epicas/epica-12-3-media-servicios-diseno.md` v2, con `GATE DE DISEÑO 12.3: APROBADO`

## 1. Identidad y veredicto

**Veredicto:** APROBADO.

La implementación cumple el contrato de diseño 12.3: mantiene un solo pipeline de promoción, agrega estrategia de servicios fail-closed, mueve las nuevas imágenes de `FrontendService.image` al disco privado, exige `image_alt`, resuelve el render público sólo para media `promoted`, protege la unicidad en PostgreSQL con índice parcial y expone preview owner-only con 404 uniforme.

No encontré hallazgos críticos, medios ni menores que bloqueen el merge. La suite completa cerró verde sobre PostgreSQL real: **1042 tests, 4472 aserciones, exit 0**.

## 2. Prueba de ejecución

### Comandos obligatorios

```text
acaba2a
docs(prompts): prompt de auditoría de implementación de la Épica 12.3
./composer.json is valid
{"tool":"pint","result":"passed"}
```

### Migración limpia en `inmo_test`

```text
2026_07_24_100100_create_frontend_sections_table ............... 3.04ms DONE
2026_07_24_100200_seed_frontend_canonical_pages ............... 25.73ms DONE
2026_07_27_100000_add_unique_image_media_id_to_frontend_services  1.02ms DONE
2026_07_27_100100_mark_public_service_images_as_promoted ...... 19.21ms DONE

INFO  Seeding database.
...
Database\Seeders\ZoneSeeder ..................................... 16 ms DONE
Database\Seeders\OwnerSeeder ................................... 243 ms DONE
Database\Seeders\AgentSeeder ................................... 471 ms DONE
```

### Suite completa

```text
{"tool":"phpunit","result":"passed","tests":1042,"passed":1042,"assertions":4472,"duration_ms":523508}
EXIT: 0
1042
```

### Build

```text
✓ built in 594ms

> build:filament
> npx tailwindcss@3 --input ./resources/css/filament/admin/theme.css --output ./public/css/filament/admin/theme.css --config ./resources/css/filament/admin/tailwind.config.js --minify

npm warn exec The following package was not found and will be installed: tailwindcss@3.4.19
Browserslist: caniuse-lite is outdated. Please run:
  npx update-browserslist-db@latest
  Why you should do it regularly: https://github.com/browserslist/update-db#readme

Rebuilding...

Done in 749ms.
```

Los warnings de `npm run build` coinciden con los explícitamente excluidos por el prompt; el comando terminó en **exit 0**.

### Pruebas focales de 12.3

```text
{"tool":"phpunit","result":"passed","tests":39,"passed":39,"assertions":92,"duration_ms":4954}
EXIT: 0
```

Archivos ejecutados:

- `tests/Feature/Frontend/FrontendMediaStrategyTest.php`
- `tests/Feature/Frontend/FrontendServiceMediaPrivacyTest.php`
- `tests/Feature/Frontend/FrontendServiceMediaPromotionTest.php`
- `tests/Feature/Frontend/FrontendServiceAltTextTest.php`

### Verificación directa de BD/render/fail-closed

```text
INDEX_DEF=CREATE UNIQUE INDEX frontend_services_image_media_id_unique ON public.frontend_services USING btree (image_media_id) WHERE ((deleted_at IS NULL) AND (image_media_id IS NOT NULL))
UNIQUE_DIRECT=OK_REJECTED Illuminate\Database\UniqueConstraintViolationException
BRAND_OWNER_STRATEGY=NULL_FAIL_CLOSED
BRAND_AFTER_JOB_DISK=public;PROMOTED=false
PENDING_RENDER_URL=NULL
PROMOTED_RENDER_URL=URL
```

## 3. Los seis puntos auditados

### 3.1 Un solo pipeline

**Estado:** cumple.

Evidencia:

- `app/Jobs/PromoteFrontendMedia.php:58`  
  `public function handle(PromotableMediaOwners $owners, MediaPromotionState $state): void`
- `app/Jobs/PromoteFrontendMedia.php:71-78`  
  `$media = Media::query()->where('uuid', $this->uuid)->first();` / `$owner = ... $owners->for($media);` / `if ($owner === null) { return; }`
- `app/Services/Frontend/Media/PromotableMediaOwners.php:31-39`  
  `for(Media $media): ?PromotableMediaOwner` devuelve `null` sin default.
- `app/Services/Frontend/Media/MediaPromotionState.php:35-80`  
  la máquina de estados vive en una clase única.
- `app/Services/Frontend/PublishedMediaReference.php:274-296`  
  `isPromoted`, `isPending`, `markPending`, `clearPending`, `markPromoted` delegan a `MediaPromotionState`.

Guard estructural ejecutado:

- `tests/Feature/Frontend/FrontendMediaStrategyTest.php:99-118` quita comentarios con `token_get_all()` y verifica que el job no nombre modelos ni use `instanceof` en código ejecutable.

Verificación viva adicional: media de `FrontendSetting` devuelve estrategia `NULL_FAIL_CLOSED`; el job no cambia disco ni marca `promoted`.

### 3.2 Restricción sobre código aprobado de 12.1

**Estado:** cumple.

Diff verificado de `80b5cee~1` a `80b5cee` en tests existentes:

```text
FrontendHeroContractMatrixTest.php: app()->call([new PromoteFrontendMedia(...), 'handle'])
FrontendMediaPromotionConcurrencyTest.php: 3 invocaciones cambiadas a app()->call(...)
FrontendMediaPromotionTest.php: 1 invocación cambiada a app()->call(...)
```

Evidencia cruda:

```diff
-        (new PromoteFrontendMedia($uuid))->handle(app(PublishedMediaReference::class));
+        app()->call([new PromoteFrontendMedia($uuid), 'handle']);
```

No se cambiaron aserciones de los tests de 12.1-A/B; sólo se adaptó la invocación para que el contenedor inyecte el registry y la máquina de estados.

### 3.3 Resolución pública con una sola regla

**Estado:** cumple.

Evidencia:

- `app/Services/Frontend/FrontendServicesService.php:203-208`

```php
$media = $this->references->resolve($service->image_media_id, $service, 'image');

return $media !== null && $this->promotion->isPromoted($media)
    ? $media->getUrl()
    : null;
```

Verificación viva:

```text
PENDING_RENDER_URL=NULL
PROMOTED_RENDER_URL=URL
```

Prueba focal relevante:

- `tests/Feature/Frontend/FrontendServiceMediaPromotionTest.php:266-286` valida fallback sin romper el bloque.
- `tests/Feature/Frontend/FrontendServiceMediaPromotionTest.php:288-297` valida que un UUID inventado ni siquiera se pueda guardar por FK.

### 3.4 Secuencia de guardado según §4

**Estado:** cumple.

Evidencia:

- `app/Services/Frontend/SyncFrontendServiceImage.php:42-49` abre transacción y bloquea el servicio con `withTrashed()->lockForUpdate()`.
- `app/Services/Frontend/SyncFrontendServiceImage.php:65-71` valida frontera y escribe `image_media_id` bajo lock.
- `app/Services/Frontend/SyncFrontendServiceImage.php:76-84` bloquea media y marca `pending`.
- `app/Services/Frontend/SyncFrontendServiceImage.php:91-96` limpia pending saliente no promovido.
- `app/Services/Frontend/SyncFrontendServiceImage.php:100-104` despacha `PromoteFrontendMedia::dispatch($uuid)->afterCommit()` fuera de la transacción.
- `app/Jobs/PromoteFrontendMedia.php:88-108` toma cadena de locks por estrategia y revalida `isReferencedByLiveContent()` bajo lock antes de copiar.

Pruebas:

- `tests/Feature/Frontend/FrontendServiceMediaPromotionTest.php:120-133` prueba `pending` + job encolado.
- `tests/Feature/Frontend/FrontendServiceMediaPromotionTest.php:197-213` prueba que una referencia reemplazada antes del job no se promueve.

### 3.5 Unicidad en BD y migración segura

**Estado:** cumple.

Evidencia:

- `database/migrations/2026_07_27_100000_add_unique_image_media_id_to_frontend_services.php:27-31`

```sql
CREATE UNIQUE INDEX frontend_services_image_media_id_unique
    ON frontend_services (image_media_id)
    WHERE deleted_at IS NULL AND image_media_id IS NOT NULL
```

Verificación directa:

```text
INDEX_DEF=CREATE UNIQUE INDEX frontend_services_image_media_id_unique ON public.frontend_services USING btree (image_media_id) WHERE ((deleted_at IS NULL) AND (image_media_id IS NOT NULL))
UNIQUE_DIRECT=OK_REJECTED Illuminate\Database\UniqueConstraintViolationException
```

Migración de datos:

- `database/migrations/2026_07_27_100100_mark_public_service_images_as_promoted.php:44-48` recorre sólo `FrontendService` + colección `image` + disco `public`.
- `database/migrations/2026_07_27_100100_mark_public_service_images_as_promoted.php:51-56` sólo procesa media vigente en un servicio vivo.
- `database/migrations/2026_07_27_100100_mark_public_service_images_as_promoted.php:61-65` omite archivo faltante o tamaño cero.
- `database/migrations/2026_07_27_100100_mark_public_service_images_as_promoted.php:67-70` sólo marca metadata; no mueve archivos.

Pruebas:

- `tests/Feature/Frontend/FrontendServiceMediaPrivacyTest.php:118-134` SQL directo rechaza duplicado.
- `tests/Feature/Frontend/FrontendServiceMediaPrivacyTest.php:162-219` dos conexiones PostgreSQL reales dejan un solo ganador.

### 3.6 Preview owner-only con 404 uniforme

**Estado:** cumple.

Ruta:

```text
GET|HEAD admin/frontend/servicios/{service}/media/{uuid} frontend.services.media
```

Evidencia:

- `routes/web.php:51-58` registra la ruta sin middleware `auth`, con `withTrashed()`.
- `app/Http/Controllers/FrontendServiceMediaController.php:35-44`

```php
abort_unless(Auth::check(), 404);
abort_unless(Gate::allows('view', $service), 404);
$media = $this->references->resolve($uuid, $service, ServiceMediaReference::COLLECTION);
abort_if($media === null, 404);
```

- `app/Http/Controllers/FrontendServiceMediaController.php:48` sirve bytes inline: `return response()->file($media->getPath());`

Pruebas:

- `tests/Feature/Frontend/FrontendServiceMediaPrivacyTest.php:57-65` owner recibe imagen inline con `content-type: image/png`.
- `tests/Feature/Frontend/FrontendServiceMediaPrivacyTest.php:68-93` anónimo, no-owner, inexistente, UUID mal formado y UUID ajeno responden 404.
- `tests/Feature/Frontend/FrontendServiceMediaPrivacyTest.php:95-105` servicio soft-deleted sigue pudiendo previsualizarse para owner.

## 4. Estado de las declaraciones de §11.1

**Declaración 1: los dos caminos nuevos tienen guard de UUID.** Confirmada.

- `app/Jobs/PromoteFrontendMedia.php:64-66`

```php
if (! Str::isUuid($this->uuid)) {
    return;
}
```

- `app/Services/Frontend/Media/ServiceMediaReference.php:58-60`

```php
if (! Str::isUuid($uuid)) {
    return MediaLockChain::none();
}
```

- `app/Services/Frontend/Media/ServiceMediaReference.php:95-101` devuelve `false` si el UUID no es válido o el owner no es `FrontendService`.

**Declaración 2: el hueco preexistente en `PublishedMediaReference::lockChainFor()` queda inalcanzable hoy.** Aceptada con evidencia.

- `app/Jobs/PromoteFrontendMedia.php:64-66` corta UUID mal formado antes de resolver estrategia.
- `app/Services/Frontend/PublishedMediaReference.php:116-122` y `:138-142` ya tienen guard para predicado y render público.
- `app/Console/Commands/ReconcileFrontendMediaPromotions.php:83-87` usa `resolvePublished()`, que valida formato antes de consultar `media.uuid`.

El método `PublishedMediaReference::lockChainFor()` todavía consulta `Media::where('uuid', $uuid)` en `:236` sin guard local, pero los caminos productivos auditados no lo alcanzan con UUID mal formado. Lo dejo como deuda defensiva no bloqueante: si el método se expone a nuevos llamadores, debe incorporar `Str::isUuid()` localmente.

## 5. Hallazgos críticos

Ninguno.

## 6. Hallazgos medios

Ninguno.

## 7. Hallazgos menores

Ninguno.

## 8. Riesgos de seguridad

- **Fuga de media privada al HTML público:** cubierta por `FrontendServicesService::imageUrl()` y verificada con `PENDING_RENDER_URL=NULL`.
- **IDOR/enumeración de preview:** cubierta por 404 uniforme en controlador y pruebas de cinco casos.
- **Promoción de media de marca:** cubierta por registry fail-closed; verificación directa dejó `PROMOTED=false`.
- **Carrera de promoción:** mitigada por relectura bajo lock en el job y pruebas de reemplazo antes de promoción.
- **Histórico público preexistente:** sigue declarado como deuda residual; no es hallazgo porque v1 prohíbe borrado físico y el comando de reporte lo mide.

## 9. Regresiones

No detectadas.

- **12.1:** tests base se adaptaron sólo en invocación; suite completa verde.
- **12.2:** servicios públicos/render/leads no muestran regresión en suite completa y pruebas focales.
- **Property / Project / ServiceType:** suite completa verde; no hay cambios de migraciones o policies de esos dominios en el rango auditado.
- **Media de marca:** verificada fail-closed; el job no la promueve ni la toca.

## 10. Tests faltantes

No detecté un faltante bloqueante. La cobertura de 12.3 incluye estrategia, privacidad, SQL directo, dos conexiones PostgreSQL reales, render, promoción, reconciliación, soft-delete, no borrado físico y `image_alt`.

Recomendación opcional: agregar un test unitario directo para `PublishedMediaReference::lockChainFor('no-soy-un-uuid')` si se decide cerrar defensivamente ese hueco en el futuro. Hoy no es alcanzable por los caminos productivos auditados.

## 11. Correcciones obligatorias

Ninguna.

## 12. Qué se buscó y no se encontró

- **Carreras de promoción y guardado concurrente:** verifiqué locks en `SyncFrontendServiceImage`, `ServiceMediaReference` y `PromoteFrontendMedia`; pruebas de reemplazo y dos conexiones PostgreSQL pasan.
- **Fugas de media privada:** render sólo emite `promoted`; preview sirve inline por ruta owner-only.
- **Rutas de borrado físico:** búsqueda de `singleFile`, `onlyKeepLatest`, `deleteAbandonedFiles`, `Storage::delete`, `removeAllFiles` no encontró ejecución destructiva en el alcance; sólo comentarios prohibitivos.
- **Huecos de autorización en preview:** controlador responde 404 uniforme y pruebas cubren cinco casos.
- **Regresiones sobre páginas 12.1:** tests existentes no cambiaron aserciones; suite completa verde.
- **Desviaciones diseño v2 ↔ código:** no encontré desviaciones bloqueantes.

## 13. Gate explícito

**GATE DE IMPLEMENTACIÓN 12.3: APROBADO**
