# Épica 12.3 — Evidencia del flujo completo, medida en el navegador

**Fecha:** 2026-07-27 · **Lotes:** 12.3-A, 12.3-B y 12.3-C
**Contrato:** `docs/epicas/epica-12-3-media-servicios-diseno.md` (v2, gate APROBADO)

Se registran **mediciones**, no capturas: un número es reproducible y comparable entre corridas. Todo se obtuvo con el servidor de preview sobre la base de **desarrollo**, y el estado se **revirtió** al terminar (§5).

## Por qué esta verificación importa en este lote

El uploader del panel pasó a servir la vista previa por una **ruta nueva**. Si esa ruta fallara, el owner subiría una foto y vería un recuadro roto justo después de subirla — un defecto que ninguna prueba de PHPUnit habría mostrado, porque todas verifican bytes y estados, no lo que el navegador puede cargar.

## 1. Migraciones sobre la base de desarrollo

```
2026_07_27_100000_add_unique_image_media_id_to_frontend_services  6.29ms DONE
2026_07_27_100100_mark_public_service_images_as_promoted ........ 9.33ms DONE
```

Los cuatro servicios de desarrollo no tenían imagen, así que no hubo legacy que reconocer. Lo verificado es que la migración corre limpia y no rompe nada.

## 2. La secuencia de guardado (§4)

Ejecutando `SyncFrontendServiceImage` sobre un servicio real:

| Medición | Valor | Contrato |
| --- | --- | --- |
| `image_media_id` | apunta a la media nueva | Columna bajo lock ✅ |
| `disk` | **`frontend-private`** | Nunca nace pública ✅ |
| `pending_promotion` | **`true`** | Marcada para promover ✅ |
| `promoted` | **`NULL`** | Todavía no ✅ |

## 3. El render público NO emite lo pendiente (§6)

`/servicios` con la imagen en estado `pending`:

| Medición | Valor |
| --- | --- |
| ¿El HTML menciona el uuid? | **no** |
| ¿Aparece `frontend-private` o la ruta owner-only? | **no** |
| ¿El servicio se sigue mostrando? | **sí** |
| Excepciones en la página | **ninguna** |

La regla única funciona: sólo `promoted` llega al HTML, y la ausencia de foto **no rompe el bloque**.

## 4. El panel SÍ la muestra, por la ruta owner-only (§8.1)

`/admin/frontend/servicios/1/edit` con la misma imagen pendiente:

| Medición | Valor |
| --- | --- |
| URL de la vista previa | `/admin/frontend/servicios/1/media/8529d414-…` |
| ¿Usa `/storage`? | **no** |
| ¿La imagen carga? | **sí** — se ve el PNG de prueba |
| `Qué se ve en la imagen` | marcado **obligatorio** (`*`) |

Es exactamente el caso que motivó la verificación: sin la ruta nueva, acá habría un recuadro roto.

## 5. Promoción y vuelta al estado inicial

Tras ejecutar el job:

| Medición | Antes | Después |
| --- | --- | --- |
| `disk` | `frontend-private` | **`public`** |
| `promoted` | `NULL` | **`true`** |
| Archivo en el disco público | no | **sí** |
| `src` en `/servicios` | — | **`/storage/44/verificacion-12-3.png`** |
| `alt` renderizado | — | «Foto de verificacion del lote 12.3» |
| ¿Se filtra la ruta owner-only al público? | no | **no** |

**Reversión.** La media de prueba y su archivo público se retiraron, y la columna volvió a `NULL`:

```
image_media_id=NULL
media de prueba restante=0
media de servicios total=0
```

`/servicios` quedó como estaba: 5 encabezados, 5 imágenes estáticas, sin rastros de la prueba y sin excepciones.

## Lo que esta verificación NO cubre

- **El reemplazo de una foto ya promovida en el navegador.** Está probado en PHPUnit (T3-7, T3-13), pero no se ejecutó acá para no dejar residuos en la base del cliente.
- **La migración con datos legacy reales.** Desarrollo no tenía imágenes de servicio, así que los tres caminos de §7 —vigente con archivo, vigente sin archivo, superada— se prueban en PHPUnit y no se observaron sobre datos preexistentes.
