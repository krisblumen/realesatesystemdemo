# RFC-074 Servicios Ofrecidos y Disponibilidad en el Frontend

> **⚠️ Enmienda normativa C-G-1 (2026-07-24, reconciliación tras la auditoría del Lote G).** El **contenido editorial de `FrontendService` es estrategia A — inmediata (guardar = publicar)**, no draft/publicado. Quedan **retirados** de este RFC: `draft_payload`/`published_payload`/`draft_revision`, `expected_draft_revision_service`, el `FrontendServicePublisher` y el test `T-11s`. La validación dura (elegibilidad fail-closed, `media_id`, CTA derivado) corre **al guardar**, con bump `afterCommit`. Fuente única: la tabla de estrategia por entidad de **§16.9** de la épica. Toda mención abajo al flujo B de servicios es **histórica, no normativa**.
>
> **⚠️ Enmienda normativa (P3 + correcciones posteriores a P3R, 2026-07-20).** Fuente única: **§16** de la épica; donde difiera, **prevalece §16**. Overrides: código `inversion`; columnas `show_in_home`/`show_in_services`; colección `image`; elegibilidad fail-closed; validación + creación de lead bajo locks `service_types`→`frontend_services`; `ServiceTypeResource` usa el mismo protocolo al mutar `active`; backfill no destructivo. ~~El contenido editorial incorpora `draft_revision`; toda mutación de `draft_payload` la incrementa y el publisher exige `expected_draft_revision_service`.~~ *(retirado por C-G-1, arriba)* El CTA por servicio es derivado y `GET /contacto?service=<code>` valida server-side, ignora inválidos uniformemente y pasa `serviceType` elegible a Livewire.
>
> **⚠️ DECISIÓN DE ALCANCE (§18.13 de la épica, 2026-07-21): el BORRADO FÍSICO DE MEDIA SALE DE v1.** Quedan **fuera de alcance** prune, purga física, intent, lease, advisory lock, jobs guardados de Spatie, path generator con scope y barrido de huérfanos, con sus tablas, comandos y tests. Toda mención a esos mecanismos en este RFC es **histórica, no normativa**. Se conservan: media draft en disco privado con controlador owner-only, promoción post-commit idempotente con reconciliación, `SoftDeletes` con `forceDelete` prohibido, índices únicos parciales y el reemplazo que no destruye la imagen publicada. Contrato vigente: **§16.4**.

## Objetivo

Unificar los servicios que la inmobiliaria muestra en el frontend con los servicios que realmente puede recibir en formularios de lead, permitiendo que el `owner` administre contenido, orden, visibilidad y disponibilidad de cada servicio.

Este RFC atiende el punto más sensible de la Épica 12: no todas las inmobiliarias ofrecen los mismos servicios. Por eso, un servicio no es solo una tarjeta de marketing; es una capacidad comercial que debe impactar el sitio público y la captura de leads.

## Épica

Épica 12 — Administrador de Contenidos del Frontend

## Responsable

Por asignar

## Estado

🟡 Correcciones documentales aplicadas; reauditoría independiente pendiente. **Implementación bloqueada** hasta gate `APROBADO`.

---

## Contexto verificado

El código actual ya tiene un catálogo operativo de servicios:

- `app/Models/ServiceType.php`:
  - PK `code`.
  - Campos: `code`, `label`, `color`, `sort_order`, `active`.
  - Relación con `Lead`.
- `app/Filament/Resources/ServiceTypeResource.php`:
  - Permite editar tipos de servicio.
  - Acceso actual: `owner` y `admin`.
- `database/seeders/ServiceTypeSeeder.php`:
  - Siembra `comercializacion`, `arquitectura`, `construccion`.
- `app/Livewire/Leads/LeadCaptureForm.php`:
  - Valida `service_type` contra `service_types.code` con `active = true`.
- `resources/views/livewire/leads/lead-capture-form.blade.php`:
  - Lista servicios activos desde `ServiceType`.

Pero el frontend comercial muestra servicios hardcodeados en:

- `resources/views/welcome.blade.php`.
- `resources/views/site/servicios.blade.php`.

Y ahí aparece un servicio adicional: **Inversión / Inversión inmobiliaria**, que no está sembrado en `ServiceTypeSeeder`.

Conclusión: hoy existe drift entre marketing y operación.

---

## Alcance

### Incluye

- Extender la administración owner-only de servicios ofrecidos.
- Usar `ServiceType` como fuente operativa de disponibilidad.
- Agregar contenido frontend por servicio: descripciones, bullets, imágenes, íconos/estilo y CTAs.
- Permitir activar/desactivar servicios ofrecidos.
- Permitir controlar si un servicio aparece en home, página de servicios y formularios de lead.
- Reconciliar el servicio `Inversión inmobiliaria` con el catálogo operativo.
- Actualizar home y página de servicios para renderizar servicios activos desde configuración.
- Mantener fallbacks equivalentes al contenido actual.
- Tests de autorización, render público y validación anti-manipulación.

### No incluye

- Crear servicios por inmobiliaria en multitenancy completo.
- Crear flujos operativos específicos para cada servicio.
- Cambiar el modelo `Lead` salvo ajustes aditivos necesarios.
- Crear páginas nuevas por servicio con rutas dinámicas.
- Administrar precios, paquetes o comisiones.
- Automatizaciones específicas por servicio.

---

## Actor autorizado

La administración de servicios ofrecidos para el frontend debe ser exclusiva de `owner`.

| Rol | Acceso esperado |
| --- | --- |
| `owner` | ✅ Puede administrar disponibilidad y contenido de servicios. |
| `admin` | ❌ No accede al nuevo módulo de frontend. |
| `agente` | ❌ No accede. |
| `arquitectura` | ❌ No accede. |
| `proyectos` | ❌ No accede. |

> Nota: el recurso existente `ServiceTypeResource` hoy permite `owner/admin`. Este RFC no debe abrir el nuevo administrador de frontend a `admin`. Si se reutiliza o modifica ese recurso, la implementación debe cuidar no romper permisos existentes sin decisión explícita.

---

## Decisión central

`ServiceType` debe seguir siendo la fuente de verdad para saber si un servicio está disponible operativamente.

El contenido frontend puede resolverse de dos formas:

### Opción recomendada: `FrontendService` vinculado a `ServiceType`

Crear un modelo nuevo `FrontendService` con relación 1:1 hacia `service_types.code`.

Ventajas:

- Mantiene `ServiceType` pequeño y operativo.
- Separa catálogo de leads de contenido marketing.
- Evita mezclar campos editoriales largos en la tabla operativa.
- Permite evolucionar contenido sin tocar lógica core de leads.

### Opción alternativa: extender `ServiceType`

Agregar campos editoriales directamente en `service_types`.

Ventajas:

- Menos tablas.
- Edición más directa.

Desventajas:

- Mezcla operación y marketing.
- Puede crecer desordenadamente.
- Mayor riesgo de afectar pruebas existentes de leads.

**Recomendación:** usar `FrontendService` vinculado a `ServiceType`.

---

## Modelo propuesto

### `FrontendService`

Campos normativos:

- `id`.
- `service_type_code string(30)` — FK a `service_types.code`. **Su unicidad es un índice único PARCIAL con nombre explícito**, creado por DDL (`CREATE UNIQUE INDEX frontend_services_service_type_code_active_unique ON frontend_services (service_type_code) WHERE deleted_at IS NULL`); ver §16.1.2 de la épica. **No** lleva `->unique()` de Blueprint: con `SoftDeletes` un UNIQUE global impediría recrear el servicio de un `code` borrado. PostgreSQL no admite predicado en un constraint `UNIQUE`, por eso es índice y no constraint.
- **`deleted_at` (`SoftDeletes`)** — obligatorio: impide que Spatie borre la media referenciada por `published_payload` (`InteractsWithMedia.php:51-63`). `forceDelete` prohibido por policy.
- `draft_payload` / `published_payload` — `{title,short_description,long_description,bullets,icon,image_media_id,image_alt}`.
- `draft_revision bigint NOT NULL DEFAULT 1` — versión optimista exclusiva del contenido editorial de este servicio.
- `published_at`, `published_by`.
- `show_in_home` boolean.
- `show_in_services` boolean.
- `allow_leads` boolean.
- `sort_order`.
- timestamps.

Media collections:

- `image` — imagen principal; draft privada y promoción según RFC-075/077.

No existen campos CTA por servicio en v1. El kernel deriva `{label:"Solicitar información",url:route('leads.create',['service'=>code])}` solo cuando el servicio es elegible para leads.

⚠️ **BLOQUE HISTÓRICO (estrategia B de servicios, retirada por C-G-1; ver cabecera de este RFC): NO NORMATIVO.** La estrategia vigente para contenido editorial de servicios es **A («guardar = publicar»)**; `draft_payload`/`published_payload`, `FrontendServiceContentService` y `FrontendServicePublisher` **no se implementan**. Se conserva solo como registro. — Toda mutación confirmada de `draft_payload` —incluidos texto, bullets, icono y referencias media— usa `FrontendServiceContentService` dentro de una transacción. Como primera sentencia SQL ejecuta `SET TRANSACTION ISOLATION LEVEL READ COMMITTED` —sin depender del default de conexión—; luego toma `FrontendService::lockForUpdate()`, extrae los UUID del JSON final y `FrontendMediaReferenceService` **valida** que cada uno exista y pertenezca a ese `FrontendService`/colección `image`; solo entonces escribe el JSON e incrementa `draft_revision`. **No se bloquea `media`**: en v1 ninguna ruta la borra (§16.4 de la épica), así que un UUID validado no puede quedar colgante. Un UUID faltante o inelegible lanza `FrontendMediaReferenceUnavailable` y revierte payload y revisión completos.

`FrontendServicePublisher` conserva el mismo orden: servicio → media UUID ASC. Tras comparar `expected_draft_revision_service`, bloquea y valida todos los UUID del `published_payload` final antes de copiarlo y marcar `pending_promotion`. Si prune ganó y borró una fila, el publisher aborta sin cambiar `published_payload`, `published_by` ni `published_at`. Los toggles inmediatos `show_in_*`/`allow_leads` no incrementan esta revisión porque no forman parte del draft editorial. El protocolo completo de prune y ambos interleavings está en §16.4.1 de la épica.  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->

### Relación con `ServiceType`

- `FrontendService belongsTo ServiceType` usando `service_type_code` → `service_types.code`.
- `ServiceType hasOne FrontendService`.

---

## Semántica de disponibilidad

La disponibilidad final de un servicio debe cumplir:

```text
Servicio ofrecido públicamente = ServiceType.active && FrontendService.show_in_* según ubicación
Servicio aceptado en leads = ServiceType.active && FrontendService.allow_leads
```

Reglas:

- Si `ServiceType.active = false`, el servicio no aparece públicamente y no acepta leads, aunque `FrontendService` diga lo contrario.
- Si no existe `FrontendService`, el servicio no aparece ni acepta leads: no hay fallback que conceda permisos comerciales.
- Si `FrontendService.allow_leads = false`, el servicio puede aparecer como informativo solo si producto lo permite, pero no debe enviarse como lead seleccionable.
- Si un formulario intenta enviar un servicio inactivo o no permitido para leads, debe fallar con mensaje claro.
- El formulario bloqueado/forzado por servicio también debe validar disponibilidad, no confiar en el hidden input.

---

## Reconciliación de “Inversión inmobiliaria”

Hoy el frontend muestra `Inversión inmobiliaria`, pero `ServiceTypeSeeder` no lo crea.

Este RFC exige cerrar una decisión durante implementación:

### Decisión normativa

Agregar `inversion` como `ServiceType` real mediante `SeedInversionService`, acción insert-if-missing idempotente y no destructiva.

Valores iniciales:

- `code`: `inversion`.
- `label`: `Inversión inmobiliaria`.
- `color`: `primary` o `warning`.
- `sort_order`: 4.
- `active`: `true` en instalación limpia para preservar el contenido visual.
- `FrontendService.allow_leads`: `false` inicialmente, porque el formulario actual no ofrece inversión.

Si inversión es solo contenido institucional y no debe generar leads, entonces debe documentarse explícitamente y `allow_leads = false`.

---

## Contenido editable por servicio

El owner podrá editar:

- Título público.
- Descripción corta para tarjetas.
- Descripción larga para página de servicios.
- Beneficios/bullets.
- Imagen.
- Ícono permitido.
- Orden.
- Mostrar en home.
- Mostrar en página de servicios.
- Permitir captura de lead.
- Disponibilidad para leads; el CTA se deriva y no es editable.

No se permite HTML libre. Si se usa Markdown para descripciones largas, debe escaparse HTML y bloquear links inseguros.

---

## Render en frontend

### Home

La sección de servicios en `resources/views/welcome.blade.php` debe dejar de usar array hardcodeado y consumir servicios activos configurados para home.

Regla:

- Mostrar solo servicios con `ServiceType.active = true` y `FrontendService.show_in_home = true`.
- Ordenar por `sort_order`.
- Si no hay configuración, usar fallback equivalente a servicios actuales.

### Página Servicios

`resources/views/site/servicios.blade.php` debe consumir servicios activos configurados para página de servicios.

Regla:

- Mostrar solo servicios con `ServiceType.active = true` y `FrontendService.show_in_services = true`.
- CTA debe respetar disponibilidad de lead.
- Si un servicio no acepta leads, su CTA no debe apuntar al formulario forzado para ese servicio.

### Lead form y preselección CTA

`GET /contacto?service=<code>` deja de ser `Route::view` y usa un controller invocable. El controller acepta un código string de máximo 30 caracteres y consulta la regla fail-closed (`ServiceType.active=true` y `FrontendService.allow_leads=true`). Solo entonces pasa `$preselectedServiceType` a Blade y Blade monta `<livewire:leads.lead-capture-form :service-type="$preselectedServiceType" :locked="false" />`.

Query ausente, malformada, desconocida o inelegible se ignora de forma uniforme: HTTP 200 y formulario sin selección. El submit vuelve a validar y re-verifica ambas filas bajo lock; la preselección nunca concede elegibilidad.

---

## Interfaz en Filament

Crear sección owner-only dentro del administrador de frontend:

- Label sugerido: `Servicios del sitio`.
- Grupo: `Sitio web` o equivalente.

UI esperada:

- Tabla ordenable de servicios.
- Toggle activo operativo o indicador de `ServiceType.active`.
- Toggle `show_in_home`.
- Toggle `show_in_services`.
- Toggle `allow_leads`.
- Edición de contenido frontend.
- Gestión de imagen.

Cuidado: si `ServiceTypeResource` sigue existiendo para `admin`, este nuevo módulo no debe exponerlo a admin.

---

## Seguridad

- Owner-only real por policy/gate y tests HTTP.
- No aceptar HTML libre.
- CTAs deben validar destinos igual que RFC-073.
- Servicios inactivos no pueden ser enviados por manipulación del POST.
- Hidden inputs de formularios forzados no son fuente confiable.
- Imágenes deben validar MIME/tamaño.
- Evitar publicar servicios sin título o con CTA inválido.

---

## Accesibilidad y UX

- Cada imagen de servicio debe tener alt text o marcarse decorativa si aplica.
- Las tarjetas deben mantener jerarquía de headings correcta.
- CTA de cada servicio debe tener texto claro.
- No depender solo del color/icono para distinguir servicios.
- Si no hay servicios activos, mostrar estado vacío controlado o CTA general de contacto.

---

## Archivos esperados

```text
app/
  Models/
    FrontendService.php
  Policies/
    FrontendServicePolicy.php
  Services/
    Frontend/
      FrontendServicesService.php
      FrontendServiceContentService.php          (writer draft; servicio → media UUID ASC)
      FrontendServicePublisher.php               (expected_draft_revision_service + lock)
      FrontendMediaReferenceService.php          (lock/validación compartida con prune)  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->
      FrontendMediaPruner.php                    (advisory discovery + lock/recheck/INTENT; nunca delete en transacción)  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->
  Http/Controllers/
    LeadCaptureController.php                   (valida `?service=` server-side)
  Filament/
    Resources/
      FrontendServiceResource.php               (o sección dentro de FrontendSettingResource)
      ServiceTypeResource.php                   (mutación `active` con locks compartidos)
  Livewire/Leads/
    LeadCaptureForm.php                         (fail-closed + locks + mount serviceType)

database/
  migrations/
    xxxx_create_frontend_services_table.php      (incluye draft_revision)
  seeders/
    ServiceTypeSeeder.php                        (agregar inversión si se cierra así)
    FrontendServiceSeeder.php                    (defaults equivalentes al frontend actual)

resources/
  views/
    welcome.blade.php                            (consume servicios dinámicos)
    site/servicios.blade.php                     (consume servicios dinámicos)
    livewire/leads/lead-capture-form.blade.php   (respeta allow_leads si aplica)
    leads/create.blade.php                       (pasa `serviceType` ya validado)

routes/
  web.php                                       (`/contacto` usa controller)

tests/
  Feature/Frontend/
    FrontendServicesAccessTest.php
    FrontendServicesRenderTest.php
    FrontendServicesLeadAvailabilityTest.php
    FrontendServiceCtaTest.php                  (query válida preselecciona; inválida se ignora)
    FrontendServicePublishConcurrencyTest.php   (publisher stale + 2 conexiones)
    FrontendMediaReferenceConcurrencyTest.php   (draft/publish/manual prune, 2 conexiones)  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->
    FrontendMediaPruneScopeTest.php              (solo Service/image + Section/images)  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->
  Feature/Leads/
    LeadServiceAvailabilityTest.php
```

---

## Reglas técnicas

- No crear catálogo paralelo sin relación con `ServiceType`.
- No romper validación actual de `LeadCaptureForm`.
- No cambiar significado de `comercializacion`, porque hoy tiene reglas especiales de `property_id`.
- No modificar migraciones existentes.
- Las nuevas migraciones deben ser aditivas.
- Los seeders deben preservar contenido actual como default.
- Toda mutación que altere render ejecuta, `afterCommit`, un bump de la generación global de RFC-076; no hace `forget`/clear dirigido. La clave de lectura es `frontend:g{N}:services:{location}`.
- Ninguna escritura de `image_media_id` saltea **`FrontendMediaReference`** *(nombre real de la clase)*: se **valida** existencia, owner y colección antes de escribir el JSON. **No se bloquea `media`** *(correcto para esta ruta: `FrontendService.image` queda **fuera** del pipeline de promoción de 12.1 — §18.18 de la Épica 12; el lock de `media` aplica solo a la publicación con promoción de `FrontendSection.images`)* — en v1 ninguna ruta la borra (§16.4 de la épica), así que un UUID validado no puede quedar colgante.
- `FrontendSetting` y sus colecciones de marca quedan fuera del prune editorial.  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->

---

## Riesgos

| Riesgo | Impacto | Mitigación |
| --- | --- | --- |
| Drift marketing/leads | Se muestra un servicio que no puede solicitarse. | `FrontendService` vinculado a `ServiceType`. |
| Admin accede al nuevo módulo | Cambios no autorizados del frontend. | Owner-only en nuevo recurso/policy. |
| Inversión queda inconsistente | Servicio visible pero no operable. | Decisión explícita y seeder. |
| Manipulación de formulario | Lead para servicio apagado. | Validación server-side con `active` + `allow_leads`. |
| Romper comercialización | Leads de inmuebles pierden reglas especiales. | Tests de regresión para `property_id`. |
| Servicios vacíos | Home/servicios quedan pobres. | Fallbacks y estado vacío controlado. |
| Prune concurrente deja `image_media_id` colgante | Render/publicación rota. | Lock compartido de `media.uuid`, recheck post-lock y rollback nominal (§16.4.1 de la épica). |  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->

---

## Definition of Done

- Existe administración owner-only de servicios del frontend.
- Cada servicio público está vinculado a un `ServiceType`.
- Home renderiza servicios activos configurados para home.
- Página Servicios renderiza servicios activos configurados para esa página.
- Lead form solo acepta servicios con `ServiceType.active = true` y permitidos para leads.
- CTA derivado preselecciona únicamente un código elegible; query inválida/inelegible se ignora uniformemente.
- Toda mutación de `draft_payload` incrementa `draft_revision`; publicar exige `expected_draft_revision_service` y una UI stale no puede sobrescribir contenido publicado.
- Draft y publisher bloquean/validan media en orden UUID antes de escribir JSON; prune concurrente no puede dejar referencias colgantes.  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->
- Mutaciones de `ServiceType.active` y `FrontendService.allow_leads` usan el mismo orden de locks que el submit.
- Un POST manipulado con servicio inactivo falla.
- Se reconcilia `Inversión inmobiliaria` con decisión explícita.
- Admin y demás roles no acceden al nuevo módulo.
- No se modifica ninguna migración existente.
- Tests cubren autorización, render, disponibilidad, regresión de leads y carreras PostgreSQL draft/publish/manual-prune con dos conexiones y ambos interleavings; fuerzan un default de sesión distinto y comprueban que cada camino ejecuta primero `SET TRANSACTION ... READ COMMITTED` y observa `SHOW transaction_isolation = read committed`.  <!-- HISTÓRICO: fuera de alcance v1, §18.13 -->
- `php artisan test` verde sobre PostgreSQL real.
- Pint limpio.
- `npm run build` verde.

---

## Dependencias

- RFC-071 — Perfil público y configuración base.
- RFC-073 — Navegación, footer y CTAs globales.
- `ServiceType` y `LeadCaptureForm` existentes.
- Épica 12 documento general: `docs/epicas/epica-12-administrador-contenidos-frontend.md`.

---

## Próximo RFC

RFC-075 — Contenido editable de páginas institucionales: home, nosotros, servicios, inversionistas y contacto mediante secciones estructuradas con defaults del frontend actual.
