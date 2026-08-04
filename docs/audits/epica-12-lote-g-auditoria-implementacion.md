# Auditoría de implementación — Épica 12, Lote G: Preview, publicación y QA

| Campo | Valor |
| --- | --- |
| Proyecto | New Hauz |
| Fecha de reauditoría | 2026-07-24 |
| Auditor | Codex, independiente |
| Rama auditada | `feature/epica-12-content-manager` |
| Commit auditado | `f86e530` — correcciones C-G-1, M-G-1, M-G-2, Mn-G-1 y Mn-G-2 |
| Alcance | Lote G y regresiones sobre A–F |

## 1. Veredicto

**APROBADO.** Los hallazgos bloqueantes de la auditoría anterior fueron
corregidos y verificados contra el sistema ejecutándose sobre PostgreSQL real.
No quedan correcciones obligatorias para habilitar el cierre del Lote G.

## 2. Evidencia real

### Verificación base

| Verificación | Resultado |
| --- | --- |
| `composer validate --strict` | ✅ `./composer.json is valid` |
| `composer install --no-interaction --prefer-dist` | ✅ lock instalable; sin cambios de dependencias |
| `DB_DATABASE=inmo_test php artisan migrate:fresh --env=testing --force --seed` | ✅ limpio contra PostgreSQL real |
| Tests focales `Frontend(PreviewAccess\|DraftIsolation\|PreflightValidation\|PublishObservability)Test` | ✅ 10 tests, 26 assertions |
| Suite `DB_DATABASE=inmo_test php artisan test` | ✅ 807 tests, 3,188 assertions, exit code 0 |
| `./vendor/bin/pint --test` | ✅ limpio |
| `npm run build` | ✅ Vite y tema Filament compilados |
| Integridad del diff del commit auditado | ✅ solo archivos aditivos de Épica 12/documentación; no modifica User, Property, Project, Zone, Media ni ServiceType existentes |

El build emitió únicamente la advertencia conocida de `caniuse-lite` desactualizado;
no fue un fallo de compilación.

### Autorización, HTTP y aislamiento

- Sin sesión, `/admin/frontend/preview/servicios` respondió **403** y no mostró
  el shell de preview.
- Con sesiones HTTP reales, `admin`, `agente`, `arquitectura` y `proyectos`
  recibieron **403** en `/admin/frontend/preview/servicios`.
- Con owner, la preview respondió **200** y mostró el banner
  `Vista previa — borrador sin publicar. No es el sitio en vivo.`.
- `/admin/frontend/preview/no-existe` para owner devolvió **404** uniforme.
- Las cinco claves canónicas (`home`, `nosotros`, `servicios`,
  `inversionistas`, `contacto`) cargaron el banner de preview sin 500.
- La ruta pública `/servicios` respondió **200** y no mostró un marcador de
  draft antes de publicar; el mismo marcador apareció en la preview owner.

### Publicación, BD, caché y trazabilidad

Se ejecutó una edición draft real en PostgreSQL con el marcador
`REAUDIT-G-DRAFT-20260724` y luego una publicación mediante
`FrontendPagePublisher`:

```text
draft_revision enviado: 2
revision persistida después de publicar: 1
published_by: 21
published_at: 2026-07-24T19:45:44+00:00
generación de caché: 2 → 3
snapshot: seo, sections, is_enabled, generated_from_ids
snapshot contiene el marcador: sí
```

Antes de publicar, el marcador no apareció en `/servicios`; después de publicar
sí apareció. La base registró actor, timestamp, revisión y snapshot completo.
El log real contiene:

```text
frontend.previewed {"actor":21,"entity":"page:servicios"}
frontend.published {"actor":21,"entity":"page:servicios","revision":2}
frontend.cache_generation_bumped {"entity":"page:servicios"}
```

También se simuló una página deshabilitada: la preview mostró el aviso
`Esta página está deshabilitada...` y el test focal confirmó que el SEO draft
llega al `<title>`.

### DOM y QA responsive

En el navegador real, a **390×844**, la toolbar corregida reportó:

```text
label: y=68, h=20, w=358
select: y=100, h=38, w=358
link: y=150, h=20, w=358
iframe: x=17, y=263, w=356, h=633
```

La captura móvil mostró label, selector, enlace, banner amarillo, navegación
móvil y contenido sin quedar debajo del header sticky. A **1440×1000**, la
captura mostró toolbar horizontal, banner, navegación pública, CTA y contenido
sin overflow visible.

## 3. Hallazgos críticos

**Ninguno abierto.**

### Reconciliación de C-G-1 — RESUELTO

La auditoría anterior bloqueaba el lote porque esperaba estrategia B para el
contenido editorial de `FrontendService`. La decisión de producto fue cambiada
explícitamente a estrategia A:

- La tabla normativa de la Épica 12 §16.9 declara `FrontendService` como
  **guardar = publicar** y deja a `FrontendPage` como única entidad B
  (`docs/epicas/epica-12-administrador-contenidos-frontend.md:694-701`).
- RFC-074 y RFC-077 incluyen la enmienda normativa C-G-1 que retira publisher,
  payload publicado/draft, revisión y preview propios de servicios
  (`docs/rfc/RFC-074-SERVICIOS-OFRECIDOS-FRONTEND.md:3-5`,
  `docs/rfc/RFC-077-PREVIEW-PUBLICACION-QA-FRONTEND.md:3-7`).
- El modelo, migración y Resource de `FrontendService` implementan el contrato
  A; no existe publisher de servicios por diseño.
- El contrato operativo vigente es coherente: disponibilidad y contenido de
  servicios se guardan con validación dura e invalidación post-commit; las
  páginas institucionales conservan snapshot, revisión optimista y preview.

El cuerpo de RFC-074/RFC-077 aún conserva bloques históricos del antiguo flujo B,
pero los encabezados los declaran expresamente no normativos y §16.9 es la fuente
única. Se registra como riesgo de mantenimiento, no como contradicción activa ni
bloqueante.

## 4. Hallazgos medios

**Ninguno bloqueante.**

Los hallazgos M-G-1 y M-G-2 de la auditoría anterior quedan cerrados:

### M-G-1 — RESUELTO: toolbar móvil

`resources/views/filament/pages/frontend-preview.blade.php` ahora usa composición
mobile-first (`flex-col`, `w-full`, `sm:flex-row`, `sm:w-auto`). La geometría
DOM y las capturas anteriores demuestran que el header ya no cubre el selector.

### M-G-2 — RESUELTO: SEO y estado draft

`FrontendPageRenderer::renderDraft()` devuelve `enabled`, `seo` y `sections`
(`app/Services/Frontend/FrontendPageRenderer.php:78-105`). El controlador pasa
SEO y el aviso de página deshabilitada al shell
(`app/Http/Controllers/FrontendPreviewController.php:57-63`), y el layout
público aplica el mismo contrato de metadatos (`resources/views/frontend/preview-shell.blade.php:14`).
El test focal `FrontendDraftIsolationTest` verifica `<title>` y el estado
deshabilitado; además se confirmó el aviso en navegador real.

## 5. Hallazgos menores

### Mn-G-1 — RESUELTO: comentario del middleware

El comentario de `FrontendPreviewController` ya describe correctamente que la
ruta usa `web` y que el 403 depende del gate explícito del controlador
(`app/Http/Controllers/FrontendPreviewController.php:7-26,41-48`).

### Mn-G-2 — RESUELTO: matriz automatizada de roles

`FrontendPreviewAccessTest` itera ahora `admin`, `agente`, `arquitectura` y
`proyectos` (`tests/Feature/Frontend/FrontendPreviewAccessTest.php:36-43`).
La prueba fue ejecutada dentro de los 10 tests focales y la misma matriz fue
confirmada por HTTP real.

### Mn-G-3 — Observación no bloqueante: revisión del log de publicación

En la ejecución real, la fila persistió `revision=1`, mientras que el evento
`frontend.published` registró `revision=2`. Actor, entidad, timestamp de la fila,
snapshot y bump son correctos, pero el campo adicional `revision` del log queda
desfasado y puede confundir una investigación operativa.

**Recomendación:** calcular una sola vez `$newRevision` en
`FrontendPagePublisher` y usar ese valor tanto en `update()` como en el log.
No bloquea este gate porque el contrato mínimo de RFC-077 exige actor, entidad,
resultado y timestamp, y esos datos sí quedaron persistidos correctamente.

### Mn-G-4 — Observación no bloqueante: preview inicial sin payload

Tras un `migrate:fresh --seed`, las secciones canónicas se crean como draft con
`payload=null` por diseño (`app/Actions/Frontend/SeedFrontendPages.php`). En la
preview real, `nosotros`, `inversionistas` y `contacto` mostraron un `<main>` sin
secciones hasta que el owner carga payloads válidos. Las rutas públicas sí
mantienen sus fallbacks y el publisher rechaza payloads inválidos, por lo que no
hay filtración ni página pública rota.

**Recomendación:** mostrar en preview un estado explícito de “borrador
incompleto” o reutilizar el fallback de la página cuando aún no hay payload
válido. Agregar una prueba de preview sobre instalación limpia.

## 6. Regresiones

- **No se detectaron regresiones funcionales:** 807/807 pruebas pasaron sobre
  PostgreSQL real.
- `/servicios` y `/nosotros` respondieron 200; el aislamiento draft/publicado y
  la publicación posterior fueron comprobados por HTTP.
- `npm run build` continúa verde.
- No se modificaron migraciones existentes ni código protegido de User,
  Property, Project, Zone, Media o ServiceType en el commit auditado.

## 7. Riesgos de seguridad

Controles confirmados:

- Gate en orden seguro: usuario autenticado, rol `owner` y permiso
  `frontend.manage` antes de consultar `pageKey`.
- Anónimo y los cuatro roles no-owner recibieron 403 real.
- `pageKey` inválido devuelve 404 uniforme para owner.
- No existe token público reusable; la preview depende de la sesión del owner.
- El shell usa `noindex,nofollow` y banner visible de no producción.
- El draft no llega a rutas públicas antes de publicar.
- El renderer mantiene allowlist de tipos y no permite resolver vistas arbitrarias.

El desfase de `revision` en el log es un riesgo de trazabilidad, no un bypass de
autorización ni una alteración del snapshot.

## 8. Riesgos de mantenimiento

1. RFC-074/RFC-077 conservan párrafos históricos del flujo B de servicios; los
   encabezados los neutralizan, pero conviene retirarlos o marcarlos localmente.
2. El log de publicación contiene una revisión incorrecta en su campo adicional.
3. Una instalación nueva ofrece una preview vacía para páginas cuyos payloads
   aún no fueron cargados; el fallback público no se ve afectado.
4. La suite cubre seguridad y aislamiento, pero no automatiza la geometría CSS de
   la toolbar ni la experiencia de draft incompleto.

## 9. Tests faltantes

No faltan tests para los criterios bloqueantes del gate. Como deuda recomendada:

1. Test que correlacione `frontend.published.revision` con
   `frontend_pages.revision`.
2. Test de preview después de instalación limpia con payloads nulos.
3. QA visual automatizado/documentado de las cinco páginas canónicas en desktop
   y móvil, incluyendo el formulario y el footer.
4. Test de la enmienda C-G-1 que confirme que `FrontendService` publica al
   guardar y no expone un flujo B retirado.

## 10. Correcciones obligatorias

**Ninguna para habilitar el gate.** Las observaciones Mn-G-3 y Mn-G-4 son
recomendaciones de mantenimiento/producto, no bloqueos de Lote G.

## 11. Correcciones recomendadas

1. Corregir el campo `revision` del evento `frontend.published` para que coincida
   con la fila persistida.
2. Limpiar o marcar localmente como histórico el protocolo B retirado de
   RFC-074/RFC-077.
3. Definir la experiencia de preview para payloads incompletos y cubrirla con
   una prueba de instalación limpia.
4. Añadir capturas responsive de las cinco páginas canónicas al paquete de QA.

## 12. Decisión explícita del gate

Los hallazgos críticos, medios y menores bloqueantes de la auditoría previa
quedaron resueltos. El sistema real pasó migración, suite, formato, build,
autorización, aislamiento, publicación, invalidación, trazabilidad básica y QA
responsive.

**GATE LOTE G: APROBADO.**
