# Épica 12 — Auditoría independiente de diseño

**Proyecto:** New Hauz — Plataforma Inmobiliaria
**Fecha:** 2026-07-20
**Auditor:** Codex (modelo Sol)
**Documento auditado:** `docs/epicas/epica-12-administrador-contenidos-frontend.md` + RFC-071→RFC-077
**Rama auditada:** `feature/epica-12-content-manager`
**Commit auditado:** `48468ce`

---

## 1. Veredicto

🔴 **RECHAZADO — GATE DE DISEÑO CERRADO**

El diseño consolidado mejora correctamente la aditividad, el orden kernel-first, la separación `ServiceType`/contenido editorial y las auditorías por lote. Sin embargo, todavía no constituye un contrato implementable único: los RFCs contradicen decisiones de la épica, la publicación no produce un snapshot completo ni seguro ante concurrencia, la media draft permanece pública, el singleton no está garantizado físicamente y el owner-only depende de un permiso que el despliegue normal no crea.

No debe comenzar el Lote A. Claude debe corregir la épica y los RFCs afectados; después corresponde P3R con nueva evidencia y veredicto binario.

## 2. Evidencia verificada en código real

### Comandos ejecutados

| Verificación | Resultado |
| --- | --- |
| `git diff --name-status develop...HEAD` | Sólo documentos de Épica 12; no hay código ni migraciones previas modificadas. Aditividad documental confirmada. |
| `git diff --name-status 07d0414..HEAD` | P1 actualizó únicamente la épica general; RFC-071→077 quedaron sin reconciliar. |
| `php artisan route:list --path=admin --except-vendor` | No existen aún rutas del CMS frontend, coherente con una etapa sólo de diseño. |
| `composer validate --strict` | `composer.json` válido y lock sincronizado. |
| `composer show enshrined/svg-sanitize` | Paquete no instalado. |
| `DB_DATABASE=inmo_test php artisan test tests/Feature/Auth/PermissionSeederTest.php tests/Feature/Leads/PublicLeadCaptureTest.php` | ✅ 12 tests, 58 assertions, PostgreSQL real. Confirma la matriz y compuerta actuales, no el diseño futuro. |

### Contratos reales comprobados

- `ServiceType.active` es la compuerta server-side actual de leads: `app/Livewire/Leads/LeadCaptureForm.php:59-93,102-120`.
- `owner` y `admin` pueden crear/editar tipos activos: `app/Filament/Resources/ServiceTypeResource.php:28-60,100-117`.
- `service_types.code` es `string(30)` y PK: `database/migrations/2026_06_29_190235_create_service_types_table.php:14-20`.
- Las policies del proyecto se registran explícitamente: `app/Providers/AppServiceProvider.php:72-81`.
- El deploy manual recomendado ejecuta `migrate --force`, pero no seeders: `docs/deployment/CI-CD-PIPELINE.md:46-58`.
- PHPUnit usa caché `array`; producción usa `database`: `phpunit.xml:26`, `config/cache.php:17-18`.
- Media Library usa disco público y path generator por defecto: `config/media-library.php:32-36,138-145`; `/storage` es público: `config/filesystems.php:41-49`.
- El frontend usa Tailwind v4 y tokens `@theme`: `resources/css/app.css:1-77`; Vite sólo carga Montserrat e Inter: `vite.config.js:14-21`.
- El layout actual duplica navegación desktop/móvil y contiene footer/CTAs hardcodeados: `resources/views/components/layouts/public.blade.php:63-124,131-180`.

### Decisiones correctamente diseñadas

- Cuatro tablas nuevas sin reescribir esquemas de User/Property/Project/Media/Zone/ServiceType: épica §16.1, líneas 329-370.
- `ServiceType` sigue operativo y `FrontendService` editorial: épica §16.6, líneas 407-429.
- Elegibilidad de leads continúa validándose server-side.
- Tema por variables runtime, sin recompilar Tailwind.
- Contenido estructurado, sin page builder ni HTML/CSS/JS libre.
- Kernel de lectura/fallback desde Lote A y auditoría bloqueante por lote: épica §16.8–16.10.

## 3. Hallazgos críticos

### C-1 — No existe una única especificación normativa

**Estado:** CONFIRMADO.

**Evidencia:** P1 sólo modificó la épica general. Los RFCs conservan decisiones incompatibles:

- RFC-072 todavía permite Poppins y ocho tokens; la épica limita fuentes/tokens: `RFC-072:76-121` vs. épica `341-343,400-405`.
- RFC-074 usa `inversion_inmobiliaria`, campos/colecciones `show_on_*` y `service-image`; la épica usa `inversion`, `show_in_*` e `image`: `RFC-074:129-199` vs. épica `348-356,425-429`.
- RFC-075 exige tipos como `featured_properties`, `featured_projects`, `story` y `contact_intro`; la allowlist consolidada no los contiene: `RFC-075:149-212` vs. épica `365-370`.
- RFC-077 pide draft para tema/CTAs críticos y `published_by`; la épica publica todo `FrontendSetting` inmediatamente y omite el actor: `RFC-077:78-112,143-153` vs. épica `461-472`.

**Impacto:** dos implementadores pueden producir modelos, migraciones, payloads y pruebas incompatibles siguiendo documentos oficialmente vigentes. La afirmación de coherencia de §17 no es verificable.

**Corrección:** actualizar RFC-071→077 con las decisiones finales o agregar una enmienda normativa explícita en cada RFC. Debe quedar un solo nombre por campo, código, colección media, estrategia de publicación, tipo de sección y cache key.

### C-2 — La publicación no crea un snapshot completo ni seguro ante concurrencia

**Estado:** CONFIRMADO.

**Evidencia:** `FrontendPage` guarda SEO e `is_enabled` fuera de draft/publicado; `FrontendSection.is_enabled`, orden y referencias media tampoco forman parte clara del snapshot (`epica:360-370`). La publicación sólo indica copiar payloads dentro de una transacción, sin locks ni control de versión (`epica:467-471`). PostgreSQL opera con `READ COMMITTED`; el repositorio usa `lockForUpdate()` cuando necesita exclusión real (`AppServiceProvider.php:132-142`). RFC-077 exige `published_by`, ausente del modelo final (`RFC-077:143-153`).

**Impacto:** el público puede observar SEO/estado/media de borrador antes de publicar, una publicación concurrente puede mezclar revisiones y no queda registrado quién publicó.

**Corrección:** definir una revisión publicable por página que incluya SEO, enabled, orden, secciones y referencias media. Publicar con lock pesimista en orden determinista o `lock_version` optimista; persistir `published_by`, `published_at` y revisión dentro de la misma transacción. T-11 debe intercalar dos conexiones PostgreSQL reales.

### C-3 — La media draft se declara aislada, pero sigue públicamente accesible

**Estado:** CONFIRMADO.

**Evidencia:** el diseño acepta el disco público y llama “aislamiento por referencia” a no emitir la URL (`epica:392-398`). El disco real expone `/storage` (`config/filesystems.php:41-49`) y el path generator es el default (`config/media-library.php:138-145`). Además, los payloads finales no especifican un identificador media que diferencie draft de publicado (`epica:348-369`). El propio proyecto usa disco privado para impedir que una URL salte autorización (`ContratoIntermediacion.php:191-201`).

**Impacto:** una imagen de borrador puede descargarse directamente si se conoce o enumera su URL; la garantía “draft no se filtra” de RFC-077 es falsa.

**Corrección:** media draft en disco privado, lectura mediante controlador owner-only y promoción/copia a colección pública después del commit. Draft y published payload deben guardar UUID/ID de media más `alt`/decorative. Probar 403/404 anónimo antes de publicar y acceso público después.

### C-4 — El singleton no está garantizado físicamente

**Estado:** CONFIRMADO.

**Evidencia:** `UNIQUE(singleton_key)` con default `default` (`epica:333-346`) permite varias filas con claves distintas: `default`, `secondary`, etc. No existe `CHECK` que fuerce el valor constante.

**Impacto:** B-3 continúa abierto; escritura directa, import o bug puede crear múltiples configuraciones válidas y volver no determinista el render.

**Corrección:** `singleton_key` constante con `CHECK (singleton_key = 'default')` + UNIQUE, o booleano constante `true` con CHECK+UNIQUE. Mantener prohibición de delete y probar carrera con dos conexiones independientes.

### C-5 — Owner-only no queda garantizado ni desplegable

**Estado:** CONFIRMADO.

**Evidencia:** las policies propuestas exigen sólo `frontend.manage` (`epica:372-378`), no rol `owner`; cualquier no-owner que reciba el permiso accede. El patrón estricto ya existe como permiso + rol (`app/Policies/ZonePolicy.php:32-37`). Además, el diseño sólo modifica `PermissionSeeder` (`epica:531-535`), pero el deploy recomendado ejecuta migraciones sin seeders (`docs/deployment/CI-CD-PIPELINE.md:46-58`). `PermissionSeederTest` fija actualmente 14 permisos (`tests/Feature/Auth/PermissionSeederTest.php:15-51`) y tampoco figura en la lista de cambios.

**Impacto:** un permiso asignado por error abre el módulo a otro rol; en el caso opuesto, producción puede desplegar el código sin crear `frontend.manage` y dejar al owner bloqueado.

**Corrección:** policies/gates deben exigir `hasRole('owner') && can('frontend.manage')`. Incluir registro de policies en `AppServiceProvider`, actualizar `PermissionSeederTest` y definir mecanismo productivo obligatorio: migración idempotente de permiso/asignación o paso de deploy `db:seed --class=PermissionSeeder`, con prueba específica.

## 4. Hallazgos medios

### M-1 — El registro de secciones no representa el frontend actual

**Estado:** CONFIRMADO. La allowlist consolidada (`epica:365-370`) no cubre varias secciones exigidas por RFC-075 (`149-212`) ni explica cómo conservar los bloques dinámicos de `HomeController.php:16-42`.

**Impacto:** PD-9/cutover no puede implementarse ni probarse sin perder secciones.

**Corrección:** registry canónico con page key, section key estable, renderer, schema, multiplicidad, media y adaptador de fallback. Mapear explícitamente cada sección actual.

### M-2 — La elegibilidad de servicios falla abierta

**Estado:** CONFIRMADO. La épica trata `FrontendService` ausente como `allow_leads=true` (`epica:418-423`), mientras `admin` puede crear un `ServiceType` activo (`ServiceTypeResource.php:28-60,100-113`). Esto contradice la fórmula que exige ambos registros (`epica:411-414`). Validación y `Lead::create()` tampoco forman una operación atómica (`LeadCaptureForm.php:76-93`).

**Impacto:** un servicio nuevo puede aceptar leads sin aprobación editorial del owner; una desactivación concurrente puede admitir un lead posterior a la validación.

**Corrección:** después del backfill, ausencia de `FrontendService` debe fallar cerrada. Crear configuración editorial nueva con `allow_leads=false`; validar y crear el lead transaccionalmente con locks o una garantía equivalente. Agregar casos admin-crea-servicio y disable concurrente.

### M-3 — La estrategia de caché permite staleness y omite mutaciones

**Estado:** CONFIRMADO. `afterCommit + Cache::forget` sin TTL/versionado (`epica:451-457`) permite que un cache miss concurrente repueble datos viejos después del `forget`. Tampoco se especifica invalidación por cambios de `ServiceType.active` ni altas/bajas de `Media`, aunque ambos alteran el render.

**Impacto:** un publish o apagado puede no reflejarse indefinidamente.

**Corrección:** keys con generación/revisión publicada o TTL corto como red de seguridad; invalidación explícita para Setting, ServiceType, FrontendService, Page, Section y Media. Probar la carrera de refill con dos conexiones/store database.

### M-4 — Tema y seguridad CSS no tienen contrato único

**Estado:** CONFIRMADO. La épica guarda sólo `primary/accent/background/text` pero promete contraste de CTA sin colores de contraste (`epica:341-343,400-405`); RFC-072 define otro schema (`80-145`). El renderer confía en datos “ya validados” (`epica:385`) y no exige normalización defensiva al render.

**Impacto:** implementación inconsistente, contraste incomprobable y riesgo de inyección si existen datos legacy/importados fuera de Filament.

**Corrección:** schema autoritativo con cada par de contraste y mapping `--nh-*`; validar al guardar y normalizar nuevamente en el boundary de render. Test con valor persistido malicioso que intente cerrar `<style>`. Retirar Poppins del RFC.

### M-5 — Backfill “idempotente” puede sobrescribir estado operativo

**Estado:** CONFIRMADO. Se exige `updateOrInsert` (`epica:425-429`) aunque ese patrón actualiza valores existentes (`migration ...add_service_type_fk...:21-31`). Ejecutar `migrate` dos veces no reejecuta una migración registrada, por lo que T-12 (`epica:503`) tampoco demuestra idempotencia real.

**Impacto:** una instalación con `inversion` personalizado/inactivo puede ser reactivada o sobrescrita.

**Corrección:** insert-if-missing y backfill no destructivo. Extraer una operación invocable en test, ejecutarla dos veces contra filas previamente personalizadas y comprobar que no cambia estado ni contenido.

### M-6 — Footer, CTAs y SEO siguen sin contrato implementable

**Estado:** CONFIRMADO. El schema consolidado sólo ofrece dos campos `*_cta_route` y no define footer tipado (`epica:333-345,380-388`), mientras RFC-073 exige `{target_type,target}` y footer configurable (`131-181`). SEO/canonical/sitemap/JSON-LD sólo aparecen como resumen de Lote F (`epica:484-486`), sin precedencia, comportamiento HTTP de páginas deshabilitadas ni tests. Las rutas institucionales actuales son incondicionales (`routes/web.php:19-26`).

**Impacto:** destinos seguros, footer y SEO pueden resolverse de formas incompatibles o quedar incompletos.

**Corrección:** un value object CTA compartido `{label,type,target}` y payload footer validado; decidir 404/410/noindex para páginas apagadas; documentar precedencia SEO, canonical, sitemap y schemas JSON-LD con tests.

### M-7 — La matriz de tests no prueba las garantías declaradas

**Estado:** CONFIRMADO. Faltan pruebas para actor/revisión publicada, pageKey inválido, media draft directa, ServiceType/Media cache, servicio sin FrontendService, refill race, deploy sin seeder, schema por sección, typed CTAs, SEO y comportamiento real de teclado/Escape/`aria-expanded` (`epica:488-505`; `RFC-077:224-237`).

**Impacto:** los bloqueantes anteriores podrían implementarse incorrectamente con suite verde.

**Corrección:** ampliar la matriz con tests nombrados y evidencia PostgreSQL/HTTP/DOM; concurrencia debe usar conexiones independientes, no llamadas secuenciales.

## 5. Hallazgos menores

### Mn-1 — Fallback de CTA documentado incorrectamente

La épica registra “Contacto” (`epica:449`), pero el header real muestra “Agenda una cita” (`public.blade.php:80-84`). Corregir el valor exacto.

### Mn-2 — Tipo FK y unique redundante

`service_types.code` es `string(30)`, mientras el diseño dice `char` y declara UNIQUE dos veces (`epica:348-356`). Especificar `string('service_type_code', 30)->unique()` y un solo constraint.

### Mn-3 — `FrontendSetting.is_active` carece de semántica

El singleton no eliminable contiene `is_active` (`epica:345`), pero el tri-estado no explica su efecto (`epica:431-437`). Eliminarlo o definir qué renderiza al estar `false` sin revivir fallbacks.

### Mn-4 — SVG sigue siendo una decisión abierta

PD-6 figura cerrado, pero §16.4 permite sanitizar o prohibir SVG y el paquete no está instalado. Para v1 se recomienda prohibir SVG; si se conserva, declarar dependencia exacta y pruebas de contenido real, handlers y referencias remotas.

## 6. Riesgos de seguridad

| Riesgo | Severidad | Control exigido |
| --- | --- | --- |
| Acceso no-owner por permiso asignado fuera del seeder | Crítica | Rol `owner` + permiso en cada policy/gate y URL directa. |
| Exposición directa de media draft | Crítica | Disco privado + controlador autorizado + promoción al publicar. |
| CSS persistido malicioso | Alta | Validación estricta al guardar y normalización/escape al render. |
| CTA con protocolo/destino inseguro | Alta | Value object tipado y resolver central con allowlist/HTTPS. |
| POST de lead contra servicio sin configuración o deshabilitado | Alta | Fail-closed y validación/creación atómica. |
| Preview de pageKey arbitrario | Media | Enum/allowlist server-side, 404 uniforme y pruebas por rol. |

No se diseñó Markdown como requisito final; debe mantenerse prohibido. Si se incorpora después, `Str::markdown()` requiere `html_input=escape` y `allow_unsafe_links=false`.

## 7. Riesgos de mantenimiento

- Drift inevitable mientras la épica y RFCs definan nombres y estrategias distintos.
- Registry de secciones sin key/render/schema central generará condicionales dispersos en Blade.
- Cinco servicios con contratos inconsistentes de cache/DTO pueden divergir sin tests de forma.
- Backfill dentro de migración sin operación idempotente testeable es difícil de verificar.
- El registry estático de Ayuda (`app/Filament/Pages/Ayuda.php:262-297`) debe actualizarse en Lote G; hoy no figura en archivos esperados.
- La modificación preexistente `.atl/skill-registry.md` no pertenece a esta auditoría y no fue tocada.

## 8. Sobreingeniería detectada

No se detecta sobreingeniería estructural grave. Cuatro tablas, JSON validado para settings/secciones, kernel de lectura y publicación por lotes son proporcionales al alcance.

Sí debe evitarse incorporar sanitización SVG como subproyecto opcional en v1: prohibir SVG reduce dependencia, superficie de ataque y matriz de pruebas. Tampoco se justifica historial de versiones avanzado; una revisión publicada atómica con actor y lock es suficiente.

## 9. Recomendaciones obligatorias

1. Reconciliar RFC-071→077 con §16; no basta declarar que la épica “gana”.
2. Rediseñar snapshot/publicación con media, SEO, estado, orden, actor y concurrencia.
3. Garantizar singleton mediante CHECK+UNIQUE.
4. Hacer owner-only con rol + permiso y cerrar creación productiva del permiso.
5. Hacer privada la media draft y escoger una política SVG definitiva.
6. Completar registry de secciones y mapping del frontend actual.
7. Cambiar elegibilidad faltante a fail-closed y cerrar carrera de captura.
8. Endurecer caché contra stale refill e invalidar ServiceType/Media.
9. Unificar theme, CTA, footer y SEO en contratos implementables.
10. Ampliar la matriz de pruebas con los casos de C-1→C-5 y M-1→M-7.

## 10. Recomendaciones opcionales

- Añadir baselines visuales responsive para home y páginas institucionales en Lote G.
- Incorporar CSP con nonce para el `<style>` runtime cuando se aborde hardening HTTP.
- Registrar una métrica de fallback/caché por dominio además de warnings.
- Evaluar historial de publicaciones sólo después de observar necesidad operativa real.

## 11. Checklist para Claude

- [ ] Actualizar RFC-071 con singleton físico, owner+permiso, deploy y media final.
- [ ] Actualizar RFC-072 con schema único `--nh-*`, contraste y fuentes reales.
- [ ] Actualizar RFC-073 con CTA tipado, footer y destinos exactos.
- [ ] Actualizar RFC-074 con code único de inversión, fail-closed y backfill no destructivo.
- [ ] Actualizar RFC-075 con registry completo, stable keys, schemas, media y multiplicidad.
- [ ] Actualizar RFC-076 con generation keys/TTL, ServiceType/Media y stale-refill test.
- [ ] Actualizar RFC-077 con snapshot, locks/revision, actor, media privada y pageKey inválido.
- [ ] Corregir §16.1–16.15 para que coincida literalmente con los RFCs.
- [ ] Agregar `AppServiceProvider`, `PermissionSeederTest`, `routes/web.php`, Ayuda y controles media a archivos esperados.
- [ ] Corregir fallback “Agenda una cita”.
- [ ] Ejecutar P3R únicamente después de resolver todos los críticos.
- [ ] Mantener Lote A bloqueado hasta `GATE DE DISEÑO: APROBADO`.

---

**Decisión del gate:** `GATE DE DISEÑO: RECHAZADO`.
