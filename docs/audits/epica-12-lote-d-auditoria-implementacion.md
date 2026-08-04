# Auditoría de implementación — Épica 12, Lote D: Servicios ofrecidos y disponibilidad

- **Proyecto:** New Hauz — CMS inmobiliario
- **Fecha:** 2026-07-23
- **Auditor:** Codex (modelo Sol), auditor independiente
- **Rama auditada:** `feature/epica-12-content-manager`
- **HEAD auditado:** `47727a4`
- **Lote:** D — Servicios ofrecidos, elegibilidad y leads
- **Referencias:** `docs/epicas/epica-12-administrador-contenidos-frontend.md` §16.6, §18.13–§18.14; `docs/rfc/RFC-074-SERVICIOS-OFRECIDOS-FRONTEND.md`
- **Corrección auditada:** `47727a4 fix(epica-12): C-D1/M-D1 — sin borrado físico de media y form fail-closed`
- **Auditoría acumulada:** Lote C aprobado en `docs/audits/epica-12-lote-c-auditoria-implementacion.md`

## 1. Veredicto

## **APROBADO**

La reauditoría independiente confirma el cierre de C-D1 y M-D1. La media
anterior sobrevive al reemplazo, el formulario público solo ofrece servicios
elegibles y la validación server-side continúa siendo fail-closed. No quedan
correcciones obligatorias para el Lote D.

## 2. Evidencia real

### 2.1 Verificaciones base obligatorias

| Verificación | Resultado | Evidencia |
|---|---:|---|
| Dependencias | ✅ | `composer validate --strict`: `./composer.json is valid`; `composer install --dry-run --no-interaction --prefer-dist`: lock sincronizado, sin cambios. |
| Migración | ✅ | `DB_DATABASE=inmo_test php artisan migrate:fresh --env=testing --force --seed`: ejecución limpia contra PostgreSQL real. |
| Tests focales | ✅ | Tests del Lote D, incluyendo media, opciones del formulario, índice parcial, servicios y leads: **33 tests, 33 passed, 85 assertions**. |
| Suite acumulada | ✅ | Ejecución controlada única de `DB_DATABASE=inmo_test php artisan test --no-coverage`: **741 tests, 741 passed, 2.947 assertions**, `duration_ms=457105`. |
| Formato PHP | ✅ | `./vendor/bin/pint --test`: `passed`. |
| Build frontend | ✅ | `npm run build`: Vite 8 y build Filament/Tailwind 3 terminaron correctamente. Los avisos de Browserslist/Tailwind son informativos. |
| Higiene | ✅ | `git diff --check` limpio. `.atl/skill-registry.md` conserva un cambio previo no relacionado y no debe incluirse en el commit. |

### 2.2 Índice único parcial verificado en PostgreSQL

Consulta real a `pg_indexes`:

```text
CREATE UNIQUE INDEX frontend_services_service_type_code_active_unique
ON public.frontend_services USING btree (service_type_code)
WHERE (deleted_at IS NULL)
```

Con SQL directo sobre una fila temporal:

```text
duplicado activo: SQLSTATE[23505]
tras soft-delete: nueva fila del mismo service_type_code creada correctamente
```

La prueba permanente `tests/Feature/Frontend/FrontendServicePartialIndexTest.php`
también pasó y confirma unicidad de filas vivas y recreación después de
`deleted_at`.

### 2.3 Cierre de C-D1: media no destructiva

La implementación corregida está en `app/Models/FrontendService.php:59-67`:

```php
$this->addMediaCollection('image');
```

No declara `singleFile()` ni `onlyKeepLatest()`. La reproducción real con
`Storage::fake('public')`, PostgreSQL `inmo_test` y dos uploads produjo:

```json
{
  "before_count": 1,
  "after_count": 2,
  "first_media_exists": true,
  "second_media_exists": true,
  "first_file_exists": true,
  "second_file_exists": true,
  "collection_size_limit": false
}
```

La prueba permanente
`tests/Feature/Frontend/FrontendServiceMediaTest.php` pasó. El render sigue
resolviendo la imagen vigente mediante `image_media_id`; las imágenes
superadas quedan almacenadas y dejan de estar referenciadas, sin borrado
físico.

También se verificó que las colecciones de Épica 12 no declaran
`singleFile()`/`onlyKeepLatest()`; las ocurrencias de esas APIs en otros
modelos pertenecen a épicas anteriores y no fueron modificadas.

### 2.4 Cierre de M-D1: formulario fail-closed

`app/Services/Frontend/FrontendServicesService.php:82-100` agrega
`leadOptions()`, que usa la misma consulta de elegibilidad que
`isLeadEligible()` y selecciona explícitamente `service_type_code` y
`service_types.label`. La vista
`resources/views/livewire/leads/lead-capture-form.blade.php:45-58` consume ese
método y ya no consulta `ServiceType` directamente.

Consulta directa del servicio:

```json
{
  "options": {
    "comercializacion": "Comercialización",
    "arquitectura": "Arquitectura",
    "construccion": "Construcción"
  },
  "inversion_eligible": false,
  "commercialization_eligible": true
}
```

La prueba HTTP real respondió `200` para `/`, `/servicios`, `/contacto`,
`/contacto?service=inversion` y `/contacto?service=comercializacion`. El HTML
de `/contacto` emitió exactamente estos radios:

```json
["arquitectura", "comercializacion", "construccion"]
```

`Inversión inmobiliaria` no apareció. El navegador in-app confirmó el mismo
DOM: **3 radios**, sin inversión y con comercialización presente.

`tests/Feature/Frontend/FrontendLeadFormOptionsTest.php` pasó y cubre inversión
info-only, `ServiceType.active=false`, ausencia de fila `FrontendService` y
`allow_leads=false`. `LeadServiceAvailabilityTest` también pasó: un POST
manipulado continúa rechazándose bajo validación y locks, sin crear `Lead`.

### 2.5 Toggles públicos y restauración

Se cambiaron temporalmente los valores en PostgreSQL y se consultó el HTML
público después de invalidar la generación:

- `comercializacion.show_in_home=false`: desapareció de las tarjetas `<h3>`
  del home; arquitectura, construcción e inversión permanecieron.
- `comercializacion.show_in_services=false`: el contenido de comercialización
  tuvo `0` coincidencias en `/servicios`.
- `ServiceType.active=false`: el contenido de comercialización tuvo `0`
  coincidencias en `/servicios`.

Los valores fueron restaurados. La consulta final confirmó todos los servicios
operativos activos, toggles originales y `inversion.allow_leads=false`.

### 2.6 Aditividad y regresiones

En la capa de migraciones, el diff desde el gate de C solo agrega
`frontend_services`; no modifica migraciones existentes de User, Property,
Project, Media, Zone o ServiceType. La suite completa verde confirma que roles, Property, Leads,
Media Library y los Lotes A-C no presentan regresión observable.

## 3. Hallazgos críticos

Ninguno abierto.

### C-D1 — Cerrado: `singleFile()` borraba media anterior

- **Causa original:** `singleFile()` configuraba `onlyKeepLatest(1)` y Spatie
  llamaba `clearMediaCollectionExcept()`.
- **Cierre:** `47727a4`, `app/Models/FrontendService.php:59-67` elimina el
  límite de colección; `FrontendServiceMediaTest` y la reproducción real
  confirman que filas y archivos anteriores sobreviven.

## 4. Hallazgos medios

Ninguno abierto.

### M-D1 — Cerrado: opción info-only visible en el formulario

- **Causa original:** la vista consultaba todos los `ServiceType` activos.
- **Cierre:** `47727a4`, `FrontendServicesService::leadOptions()` y la vista
  Livewire comparten la regla `ServiceType.active + FrontendService vivo +
  allow_leads`. DOM real y tests confirman que inversión, inactivos y servicios
  sin fila no aparecen.

## 5. Regresiones detectadas

No se detectaron regresiones. La suite acumulada terminó con **741/741** y las
verificaciones HTTP/DOM de home, servicios y contacto fueron satisfactorias.
La migración nueva es aditiva y el índice parcial conserva el comportamiento
esperado con soft-delete.

## 6. Riesgos de seguridad

No quedan riesgos bloqueantes en el alcance auditado.

- El reemplazo de imágenes ya no elimina físicamente media de forma implícita.
- El formulario no ofrece servicios que el servidor rechazaría.
- El POST manipulado continúa protegido por validación fail-closed y
  revalidación bajo transacción/locks en
  `app/Livewire/Leads/LeadCaptureForm.php:85-115,134-143`.
- `?service=` continúa validándose en
  `app/Http/Controllers/LeadCaptureController.php:19-27`; un valor inválido o
  no elegible no concede capacidad de crear leads.

## 7. Riesgos de mantenimiento

### Mn-D1 — Deuda documental no bloqueante

RFC-074 todavía contiene bloques normativos antiguos sobre
`draft_payload`/`published_payload` y `FrontendServicePublisher`
(`docs/rfc/RFC-074-SERVICIOS-OFRECIDOS-FRONTEND.md:1-5,141-159`), mientras que
la implementación vigente de este lote usa el contrato confirmado de
“guardar = publicar”. La épica ya identifica el alcance vigente y marca parte
del contenido como histórico, por lo que no bloquea el gate; se recomienda
uniformar las marcas históricas antes de que otro lote consuma ese RFC.

## 8. Tests faltantes

No queda un test obligatorio faltante para cerrar C-D1/M-D1.

Recomendaciones de cobertura futura:

1. Prueba de dos conexiones para un toggle de `active`/`allow_leads` concurrente
   con submit, documentando formalmente el protocolo de locks del RFC-074.
2. Assert de esquema que confirme que ninguna migración futura convierte el
   índice parcial en un `UNIQUE` global; la prueba funcional permanente ya cubre
   el comportamiento actual.
3. Actualizar/limpiar los bloques históricos de RFC-074 para reducir drift
   documental.

## 9. Correcciones obligatorias

Ninguna.

Las correcciones de C-D1 y M-D1 están implementadas en `47727a4`, fueron
verificadas contra el sistema real y tienen cobertura automatizada.

## 10. Correcciones recomendadas

- Marcar uniformemente como históricos los contratos draft/published de RFC-074
  que no aplican a la estrategia vigente del Lote D.
- Mantener `leadOptions()` como única fuente de opciones visuales y evitar que
  futuras vistas consulten `ServiceType` directamente para construir radios de
  lead.
- Conservar la prueba PostgreSQL del índice parcial y soft-delete como guardia
  de esquema.

## 11. Checklist final antes de merge

- [x] `composer validate --strict` y lock sincronizado.
- [x] `migrate:fresh --env=testing --force --seed` limpio en PostgreSQL real.
- [x] Tests focales del Lote D: **33/33**.
- [x] Suite completa: **741/741**.
- [x] `./vendor/bin/pint --test` limpio.
- [x] `npm run build` verde.
- [x] Ninguna colección de Épica 12 usa `singleFile()`/`onlyKeepLatest()`.
- [x] Reemplazar una imagen conserva ambas filas Media y ambos archivos.
- [x] Inversión, inactivos y servicios sin fila no aparecen en el formulario.
- [x] POST manipulado de servicio no elegible sigue fallando.
- [x] Toggles de home, servicios y `ServiceType.active` verificados por HTTP.
- [x] Índice parcial confirmado por SQL directo, duplicado rechazado y
  recreación post-soft-delete exitosa.
- [x] Migraciones protegidas de épicas anteriores no fueron modificadas.
- [ ] `.atl/skill-registry.md` queda fuera del commit por ser un cambio previo no
  relacionado.

## 12. Decisión explícita del gate

El Lote D queda cerrado y habilita el inicio del Lote E. La deuda documental
Mn-D1 no requiere reabrir el lote.

> **GATE LOTE D: APROBADO**
