# Reauditoría de implementación — Épica 12, Lote A

| Campo | Valor |
| --- | --- |
| Proyecto | New Hauz — Plataforma Inmobiliaria |
| Fecha | 2026-07-22 |
| Auditor | Codex (independiente) |
| Rama auditada | `feature/epica-12-content-manager` |
| Lote | A — Kernel + Perfil público |
| Base | PostgreSQL real, `inmo_test` |
| Auditoría anterior | Rechazada por M-4; corrección verificada en esta reauditoría |

## 1. Veredicto

**APROBADO.**

Las correcciones cerraron M-1, M-2, M-3, M-4, M-5, C-1 y C-2. La autorización owner-only, el singleton, los fallbacks sin configuración, la invalidación de Media, la concurrencia y la degradación ante fallos de caché funcionan en código, tests y verificaciones vivas. El Lote A queda habilitado para abrir el Lote B.

## 2. Evidencia real

### Comandos base

| Verificación | Resultado | Evidencia |
| --- | --- | --- |
| `composer validate --strict` | **PASS** | `./composer.json is valid`; `composer.lock` aceptado como sincronizado. |
| `DB_DATABASE=inmo_test php artisan migrate:fresh --env=testing --force --seed` | **PASS** | PostgreSQL creó todas las migraciones y completó todos los seeders; `ZoneSeeder: 2 zona(s) cargadas`. |
| `DB_DATABASE=inmo_test php artisan test` | **PASS** | 542 tests, 1,961 assertions, salida 0. |
| Tests focales de Lote A + permisos | **PASS** | 43 tests, 131 assertions. |
| `./vendor/bin/pint --test` | **PASS** | `pint.json` excluye artefactos documentales no fuente; Pint no reportó cambios. |
| `npm run build` | **PASS** | Vite y tema Filament completaron el build. Persisten sólo avisos de Browserslist y descarga de `tailwindcss@3.4.19` por `npx`. |
| `git diff --check` | **PASS** | Sin errores de whitespace. |

### HTTP público y autorización

- `GET http://127.0.0.1:8001/admin/frontend/configuracion` sin sesión respondió **302** a `/admin/login`.
- `GET /` sin configuración respondió **200** y conservó `newhauz-on-light.svg`, `newhauz-on-dark.svg`, `newhauz_monogram.ico`, `meta_image_newhauz.jpg`, `524422722623` y `hola@newhauz.com.mx`.
- `tests/Feature/Frontend/FrontendSettingsPageTest.php:35-45` ejercita la ruta HTTP real: owner **200** y admin/agente/arquitectura/proyectos **403**.
- Se intentó repetir la inspección DOM con el navegador local, pero la política de red del navegador bloqueó `127.0.0.1:8001`; no se presenta esa vía como evidencia de esta reauditoría. La captura anterior queda sólo como evidencia histórica: [`artifacts/epica-12-lote-a/owner-access.png`](artifacts/epica-12-lote-a/owner-access.png).

### PostgreSQL y pruebas negativas

- `ZoneSeeder` ahora transforma `POLYGON` a `MULTIPOLYGON` (`database/seeders/ZoneSeeder.php:43-45,73-79`); `tests/Feature/Zones/ZoneSeederGeometryTest.php` valida tipo, validez e idempotencia.
- `FrontendMediaReference` valida UUID, modelo propietario y colección (`app/Services/Frontend/FrontendMediaReference.php:24-54`); el formulario rechaza referencias inválidas antes de actualizar (`app/Filament/Pages/FrontendSettingsPage.php:151-180`).
- `FrontendMediaObserver` registra bump `afterCommit` para Media del singleton (`app/Observers/FrontendMediaObserver.php:30-52`; registro en `AppServiceProvider.php:116-118`).
- La prueba viva agregó un logo de marca y observó generación **2 → 3**.
- La prueba viva persistió `not-an-email` y `x1` directamente; el read model devolvió el fallback exacto `hola@newhauz.com.mx`, WhatsApp `524422722623` y `https://wa.me/524422722623`.
- La prueba viva configuró un store inexistente; el read model devolvió `hola@newhauz.com.mx` y `https://wa.me/524422722623` sin lanzar excepción.
- Los tests de concurrencia ahora mantienen una transacción abierta, usan `lock_timeout` y verifican bloqueo real (`FrontendSettingSingletonTest.php:21-70`, `FrontendCacheGenerationTest.php:47-92`).

## 3. Hallazgos críticos

**No hay hallazgos críticos atribuibles al Lote A.** La preparación con seed, Pint y la suite completa están verdes.

## 4. Hallazgos medios

### M-4 — Fallback de caché ante store inexistente — RESUELTO

- **Código corregido:** `app/Services/Frontend/FrontendSettingsService.php:52-78` separa lectura/escritura de caché en bloques `try/catch (Throwable)` y ejecuta `build()` fuera de cualquier captura.
- **Contrato:** el diseño define la caché como optimización; ante fallo debe leerse directamente de DB/fallback (`docs/epicas/epica-12-administrador-contenidos-frontend.md:687-689`).
- **Evidencia del código real:** `CacheManager::resolve()` (`vendor/laravel/framework/src/Illuminate/Cache/CacheManager.php:114-122`) lanza la clase global `\InvalidArgumentException`; ahora queda contenida porque la captura abarca sólo las llamadas a caché.
- **Evidencia viva:** seleccionar `audit-missing-store` devolvió `hola@newhauz.com.mx` y `https://wa.me/524422722623`, sin 500.
- **Tests:** `tests/Feature/Frontend/FrontendReadBoundaryTest.php:74-149` cubre store inexistente, fallo de lectura, fallo de escritura y confirma que un error real de `build()` sigue propagándose.
- **Estado:** cerrado; no se absorben errores de dominio o programación porque `build()` se ejecuta fuera de los bloques de caché.

### Hallazgos cerrados en esta reauditoría

| Hallazgo anterior | Estado | Evidencia actual |
| --- | --- | --- |
| M-1 — UUID Media manipulable | **RESUELTO** | `FrontendMediaReference`; pruebas de otra colección, otro modelo, UUID inexistente y transacción all-or-nothing. |
| M-2 — Media no invalidaba caché | **RESUELTO** | `FrontendMediaObserver`; prueba de alta, baja, rollback y exclusión de Property; prueba viva generación 2→3. |
| M-3 — Contacto inválido no hacía fallback | **RESUELTO** | `normalizedEmail()`/`normalizedWhatsapp()`; prueba viva con SQL directo. |
| M-4 — Fallo de caché no hacía fallback | **RESUELTO** | Captura estructural sólo alrededor de `Cache::get/put`; test de store inexistente y prueba viva con fallback exacto. |
| M-5 — Concurrencia falsa | **RESUELTO** | Transacciones solapadas y `lock_timeout` con conexiones PostgreSQL independientes. |
| C-1 — `migrate:fresh --seed` fallaba | **RESUELTO** | Normalización de `ZoneSeeder` + prueba PostGIS; comando completo PASS. |
| C-2 — Pint global fallaba | **RESUELTO** | Correcciones de formato y `pint.json` excluyendo snippets documentales; comando completo PASS. |

## 5. Hallazgos menores

### Mn-1 — Advertencias de build

`npm run build` es verde, pero `build:filament` descarga `tailwindcss@3.4.19` mediante `npx` y reporta Browserslist desactualizado. No bloquea el Lote A; conviene fijar la dependencia en una tarea de higiene de frontend.

### Mn-2 — Limpieza de fixtures de auditoría

La reauditoría creó usuarios y Media temporales sólo en `inmo_test` y los eliminó al finalizar. No se observó persistencia de esos fixtures en archivos del repositorio.

## 6. Regresiones

- Suite completa verde: no se detectó regresión funcional atribuible a las correcciones.
- El seed completo quedó reproducible y los geometries de ZoneSeeder se validan como `MULTIPOLYGON` válido.
- Pint completo quedó verde sin tocar el archivo de configuración de la aplicación; el nuevo `pint.json` sólo excluye `docs/`.
- No se modificaron migraciones históricas de User, Property, Project, Media, Zone o ServiceType. Las migraciones del frontend son nuevas/aditivas.

## 7. Riesgos de seguridad

- **Owner-only:** conforme en HTTP/framework; la página mantiene doble gate rol owner + permiso `frontend.manage`, y las pruebas confirman 403 por URL directa.
- **Media:** la corrección valida pertenencia al singleton y colección antes de persistir; el renderer mantiene una segunda validación defensiva.
- **Uploads:** las colecciones siguen restringiendo MIME/tamaño y SVG continúa rechazado; desreferenciar no borra físicamente la Media en v1.
- **Caché:** la degradación ante store inexistente y fallos de lectura/escritura quedó verificada; errores del read model siguen propagándose para no ocultar fallas reales.

## 8. Riesgos de mantenimiento

- La solución de M-1 centraliza la referencia Media en `FrontendMediaReference`; los lotes futuros deben reutilizarla y no duplicar la consulta.
- `FrontendMediaObserver` actualmente cubre `FrontendSetting`; cuando existan FrontendService/Page/Section, deberán añadirse al protocolo de invalidación sin reintroducir clears dirigidos.
- `pint.json` debe conservarse como contrato del repositorio: snippets PHP en documentación no deben volver a mezclarse con el scope de fuente.
- La política de captura de caché debe mantenerse estructural: capturar sólo operaciones del store y dejar `build()` fuera.

## 9. Tests faltantes

No quedan tests faltantes bloqueantes para el Lote A. La ruta HTTP owner-only continúa cubierta por Feature/HTTP; la inspección DOM visual queda pendiente sólo como limitación del navegador local, no como bloqueo funcional.

## 10. Correcciones obligatorias

**Ninguna.** Todas las correcciones obligatorias del Lote A fueron verificadas en esta reauditoría.

## 11. Correcciones recomendadas

1. Sustituir `$guarded = []` por `$fillable` explícito en `FrontendSetting`.
2. Mantener un artefacto de logs HTTP/SQL de la reauditoría junto al informe si el entorno de CI lo permite.

## 12. Decisión del gate

**GATE LOTE A: APROBADO**

M-1, M-2, M-3, M-4, M-5, C-1 y C-2 están cerrados con evidencia real. El Lote B queda habilitado.
