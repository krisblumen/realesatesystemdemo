# Reauditoría de implementación — Épica 12.1, Lote A

**Proyecto:** New Hauz — Plataforma inmobiliaria (monolito Laravel)  
**Fecha:** 2026-07-26  
**Auditor:** Codex, auditor de implementación independiente  
**Rama auditada:** `feature/epica-12-content-manager`  
**Implementación auditada:** `9ad0bac`
**Correcciones reauditadas:** `74a7087` — `fix(frontend): lote 12.1-A — cadena de locks única y correcciones de la auditoría`
**Contrato:** `docs/epicas/epica-12-1-lotes-implementacion.md` §2
**Diseño aprobado:** `docs/epicas/epica-12-1-mejora-ux-hero.md` v10

## 1. Veredicto

### **APROBADO**

La reauditoría confirma que las correcciones cerraron los hallazgos bloqueantes y que el Lote A cumple el gate funcional, de seguridad, concurrencia, estilo y regresión sobre PostgreSQL real. La adquisición de locks de job y reconciliación quedó centralizada en una única rutina `page → section → media`; el test de privacidad cubre ahora `model_type` ajeno y sección inexistente; Pint quedó limpio; y la suite completa permanece verde.

> **GATE LOTE A: APROBADO**

El Lote B queda habilitado.

## 2. Evidencia real

### Verificación base

| Verificación | Resultado |
| --- | --- |
| `composer validate --strict` | ✅ `./composer.json is valid`; `composer.lock` consistente |
| `DB_DATABASE=inmo_test php artisan migrate:fresh --env=testing --force --seed` | ✅ Migraciones y seeders completados contra PostgreSQL real |
| Tests focales de Lote A | ✅ 30 tests, 104 assertions, exit 0 |
| `DB_DATABASE=inmo_test php artisan test` | ✅ 871 tests, 871 pasados, 3,399 assertions, exit 0; 412,020 ms |
| `./vendor/bin/pint --test` | ✅ Sin archivos pendientes |
| `npm run build` | ✅ Vite y `build:filament` completados; solo warnings informativos de Browserslist/Tailwind |
| `git diff --check` | ✅ Sin errores de whitespace |

### Concurrencia y lock order

- `app/Services/Frontend/PublishedMediaReference.php:184-217` concentra la cadena compartida en `lockChainFor()`.
- La secuencia efectiva comprobada en código es:
  1. `FrontendPage::...->lockForUpdate()` (`:203`);
  2. `FrontendSection::withTrashed()...->lockForUpdate()` (`:211`);
  3. `Media::...->lockForUpdate()` (`:215`).
- `app/Jobs/PromoteFrontendMedia.php:53-60` y `app/Console/Commands/ReconcileFrontendMediaPromotions.php` usan esa rutina; ya no ensamblan la secuencia individualmente.
- `FrontendMediaPromotionConcurrencyTest` ejercita con conexiones PostgreSQL separadas la contención del job sobre sección, la contención del publisher sobre página y una guardia explícita del orden `page → section → media` (`tests/Feature/Frontend/FrontendMediaPromotionConcurrencyTest.php:96-201`). Los 30 tests focales pasaron.
- El contador de reconciliación se incrementa únicamente después de ejecutar `clearPending()` bajo lock. La ejecución posterior a la limpieza informó `No unreferenced frontend section media.`

### HTTP, disco y privacidad

- `php artisan route:list --path=admin/frontend/secciones -v` confirmó `frontend.sections.media` con middleware `web`; el controlador aplica autenticación y policy internamente para devolver 404 uniforme.
- Se creó una media temporal en PostgreSQL real: `disk=frontend-private`, ruta relativa `8/audit-slide.png`.
- Servidor Laravel real en `127.0.0.1:8001`: sección válida sin sesión, UUID malformado, sección inexistente y UUID válido inexistente devolvieron **404**, `Content-Type: text/html; charset=utf-8` y cuerpo de 6,586 bytes en cada caso.
- La media temporal no existió en `storage/app/public`; tras la limpieza tampoco quedó en `storage/app/frontend-private`. La fila temporal fue eliminada y `frontend:media:report-unreferenced` terminó sin archivos.
- Los tests focales cubren owner, admin/agente no autorizados, UUID ajeno o malformado, colección incorrecta, `model_type` no perteneciente a `FrontendSection`, sección inexistente y sección soft-deleted.

### Aditividad y alcance

- La implementación y corrección del Lote A no modifican migraciones ni archivos de `User`, `Property`, `Project`, `Zone`, `Media` o `ServiceType` existentes.
- No se agregaron policies nuevas fuera del contrato del módulo ni se introdujeron borrados físicos en comandos de promoción/reconciliación.
- El árbol ya tenía modificaciones no relacionadas (`.atl/skill-registry.md`, auditoría del Lote E y `public/css/filament/admin/theme.css`); no fueron tocadas durante esta auditoría.

## 3. Hallazgos críticos

### C-A-1 — Orden global de locks invertido

**Estado:** **RESUELTO.**

La auditoría anterior confirmó que `PromoteFrontendMedia` hacía `page → media → section`, contrario al contrato `page → section → media`. La corrección `74a7087` extrajo `PublishedMediaReference::lockChainFor()` y el job/reconciliación llaman ahora a esa única cadena. La secuencia fue verificada por lectura directa, guardia automatizada y pruebas de contención con PostgreSQL real.

**Evidencia de cierre:** `app/Services/Frontend/PublishedMediaReference.php:166-217`, `app/Jobs/PromoteFrontendMedia.php:53-60`, `tests/Feature/Frontend/FrontendMediaPromotionConcurrencyTest.php:180-201`.

**Impacto residual:** ninguno observado en la reauditoría. El riesgo de que los actores de media adquieran filas en órdenes distintos queda reducido al compartir la rutina.

## 4. Hallazgos medios

### M-A-1 — Pint no estaba limpio

**Estado:** **RESUELTO.**

`tests/Feature/Frontend/FrontendSectionMediaPrivacyTest.php` fue normalizado y `./vendor/bin/pint --test` terminó sin cambios pendientes.

### M-A-2 — Cobertura incompleta de TA-2/TA-4

**Estado:** **RESUELTO.**

`FrontendSectionMediaPrivacyTest` ahora incluye casos explícitos para sección inexistente (`:199-207`) y media cuyo `model_type` no es `FrontendSection` (`:209-221`), además de los casos de UUID malformado, colección incorrecta, página ajena y sección soft-deleted. La suite focal pasó con 30 tests.

## 5. Hallazgos menores

### Mn-A-1 — Contador de reconciliación impreciso

**Estado:** **RESUELTO.**

La corrección mueve el incremento del contador para que ocurra solo cuando la transacción confirma la limpieza efectiva del flag pendiente. La ejecución real de `frontend:media:reconcile --dry-run` terminó con `0 promotion(s) re-queued, 0 stale pending flag(s) cleared.` y el reporte posterior no encontró archivos sin referencia.

No quedan hallazgos menores abiertos que bloqueen el lote.

## 6. Regresiones detectadas

No se detectaron regresiones: la migración limpia, los 871 tests de la suite, el build de assets, Pint y los comandos de reconciliación pasan sobre PostgreSQL real. No se modificaron archivos de los catálogos previos ni sus migraciones.

## 7. Riesgos de seguridad e integridad

| Riesgo | Evidencia | Estado |
| --- | --- | --- |
| Draft expuesto por URL pública | Media en `frontend-private`, fuera de `storage/app/public`; HTTP anónimo 404 | ✅ Controlado |
| Acceso de usuario no owner | Policy exige rol `owner` y permiso `frontend.manage`; tests focales | ✅ Controlado |
| Enumeración por sección/UUID | Fallos anónimo, sección inexistente, UUID ajeno/malformado uniformes | ✅ Controlado |
| UUID malformado llega a PostgreSQL | Guardas de formato antes de consultar la columna UUID | ✅ Controlado |
| Publicar media no promovida | Render público omite media no promovida; tests de render/publisher | ✅ Controlado |
| Carrera/deadlock por lock order | Cadena única `page → section → media` y pruebas reales de contención | ✅ Controlado |
| Borrado físico accidental | Reconciliación reporta, no elimina; fixture temporal limpiado explícitamente | ✅ Controlado |

## 8. Riesgos de mantenimiento

1. La rutina compartida cubre job y reconciliación; el publisher mantiene una secuencia de conjunto (`page → sections → media`) documentada en el mismo servicio. Toda futura ruta que modifique estas entidades debe reutilizar la rutina o conservar el mismo orden.
2. La prueba estática del orden protege una invariante importante, pero debe mantenerse junto con las pruebas de contención PostgreSQL si cambia el esquema o la estrategia de publicación.
3. El build emite warnings de dependencias de Browserslist/Tailwind, sin impacto en el gate del Lote A; conviene tratarlos como mantenimiento separado.

## 9. Tests faltantes

No quedan tests faltantes obligatorios del contrato del Lote A. Como mejora no bloqueante, puede agregarse en el futuro una prueba de integración que compare explícitamente los encabezados completos entre fallos anónimos y autorizados, aunque los status, tipo de contenido y tamaño ya fueron verificados en HTTP real.

## 10. Correcciones obligatorias

Ninguna. Los tres hallazgos de la auditoría anterior quedaron cerrados y no se detectó un hallazgo nuevo que requiera reabrir el lote.

## 11. Correcciones recomendadas

- Mantener `lockChainFor()` como único punto de adquisición de locks para cualquier nuevo actor de media individual.
- Conservar la prueba de orden estática y las pruebas reales de contención al modificar `FrontendPagePublisher`, `PromoteFrontendMedia` o la reconciliación.
- Resolver en una tarea separada los warnings de Browserslist/Tailwind si el equipo quiere un build sin advertencias.

## 12. Checklist final antes de avanzar

- [x] Job y reconciliación: `page → section → media`.
- [x] Carrera publisher/job probada con conexiones PostgreSQL reales.
- [x] TA-2 y TA-4 completos, incluyendo sección inexistente y `model_type` ajeno.
- [x] `./vendor/bin/pint --test` limpio.
- [x] `composer validate --strict` limpio.
- [x] `migrate:fresh --env=testing --seed` limpio.
- [x] Suite completa verde sobre `inmo_test`.
- [x] `npm run build` verde.
- [x] HTTP real confirma 404 uniforme y media draft fuera del disco público.
- [x] No se tocaron migraciones ni contratos de épicas anteriores.

## 13. Decisión explícita del gate

> **GATE LOTE A: APROBADO**

El Lote A queda técnicamente cerrado y se habilita el inicio del Lote B.
