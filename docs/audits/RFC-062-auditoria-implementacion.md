# Auditoría de implementación — RFC-062 Control de Lonas Asignadas

**Proyecto:** New Hauz  
**Rama auditada:** `feature/control-lonas-asignadas`  
**Fecha:** 2026-07-13  
**Auditoría:** ejecución real sobre Laravel + PostgreSQL `inmo_test`, Chrome headless y PDF generado.

## Veredicto

✅ **Aprobación condicionada.** La implementación cumple los puntos críticos del RFC en migraciones, índice parcial, suite completa, aislamiento por rol, M-1, M-3, generación PDF+QR y garantía UI de “sólo cámara”.

⚠️ **No mergear sin corregir 1 hallazgo medio:** el flujo Livewire del agente acepta `property_id` manipulado fuera de las opciones visibles, incluyendo inmuebles de otro agente o no publicados. Esto viola el contrato “sólo inmuebles publicados/propios” para el QR sugerido.

## Evidencia ejecutada

### Dependencias, migración y suite

- `composer validate --strict` → ✅ `./composer.json is valid`
- `composer install --no-interaction` → ✅ lock en sync, nada por instalar.
- `DB_DATABASE=inmo_test php artisan migrate:fresh --env=testing --force` → ✅ migraciones limpias, incluyendo `2026_07_13_000001_create_lona_requests_table`.
- `DB_DATABASE=inmo_test php artisan test` → ✅ `368 passed`, `1464 assertions`, PostgreSQL real.

Nota: `migrate:fresh --seed` falla en `ZoneSeeder` por un `Polygon` donde `GeometryCast` espera `MultiPolygon`; eso es preexistente/no RFC-062, pero queda como regresión operativa del seed completo.

### Índice único parcial por SQL directo

Índice real en PostgreSQL:

```sql
CREATE UNIQUE INDEX lona_requests_agent_tipo_pendiente_unique
ON public.lona_requests USING btree (agent_id, operation_type)
WHERE (((estado)::text = 'pendiente'::text) AND (deleted_at IS NULL))
```

Prueba viva:

```json
{
  "duplicate_same_type": "blocked: 23505",
  "different_type_count": 2,
  "soft_delete_new_venta_count": 1
}
```

Resultado: segunda solicitud `pendiente` del mismo `agent_id + operation_type` falla; tipo distinto pasa; soft-delete libera el slot por `deleted_at IS NULL`.

### Evidencia sólo cámara

DOM/template auditado sin comentarios Blade:

```json
{
  "actualInputTags": ["<input type=\"text\" wire:model=\"ubicacionReferencia\" ...>"],
  "fileInputs": [],
  "getUserMediaCount": 1,
  "toDataURL": true
}
```

Validación servidor:

```php
'photoData' => ['required', 'string', 'max:7000000', closure]
str_starts_with($value, 'data:image/jpeg;base64,')
str_starts_with($value, 'data:image/png;base64,')
addMediaFromBase64(..., 'image/jpeg', 'image/png')
```

Conclusión: la UI no ofrece selector de galería; la única fuente visible es `navigator.mediaDevices.getUserMedia()` + `canvas.toDataURL('image/jpeg', 0.85)`. Riesgo residual aceptado: feed de cámara virtual o payload Livewire manipulado no son distinguibles criptográficamente por el backend.

### M-1 — inmueble del QR no se copia a unidades

Ejecución real de `LonaBatchApprovalService::grant()` con inmueble publicado:

```json
{
  "batch_id": 1,
  "unit_property_ids": [null, null],
  "pdf_media_count": 1
}
```

Resultado: ✅ `LonaUnit.property_id` nace `NULL`; el inmueble del QR queda desacoplado de la colocación real.

### M-3 — sólo agentes activos reciben lonas

```json
{
  "m3": [
    "ValidationException: Sólo se pueden asignar lonas a un agente activo.",
    "ValidationException: Sólo se pueden asignar lonas a un agente activo."
  ]
}
```

Resultado: ✅ usuario sin rol `agente` y agente suspendido son rechazados.

### PDF + QR

PDF generado:

```text
storage/app/public/2/lona-venta-1.pdf
media collection: diseno-pdf
canonical esperado: https://audit.newhauz.test/inmuebles/canonical-rfc062
```

Extracción/lectura:

```bash
pdfimages -png storage/app/public/2/lona-venta-1.pdf /tmp/rfc062-pdf/img
Chrome headless + BarcodeDetector sobre img-000.png
```

QR decodificado:

```json
["https://audit.newhauz.test/inmuebles/canonical-rfc062"]
```

Resultado: ✅ el QR del PDF apunta a `Property::canonical()`.

### Aislamiento por rol en vivo

```json
{
  "owner_lona_batches_status": 200,
  "owner_lona_requests_status": 200,
  "admin_lona_batches_status": 200,
  "admin_lona_requests_status": 200,
  "agent_lona_batches_status": 403,
  "agent_lona_requests_status": 403,
  "agent_mis_lonas_status": 200,
  "admin_mis_lonas_status": 403
}
```

Resultado: ✅ owner/admin acceden a bandejas; agente queda aislado de recursos admin; agente accede a `/admin/mis-lonas`; no-agente 403.

### Botón “Solicitar más” bloqueado

Livewire real con unidad `venta` pendiente:

```json
{
  "request_more_venta_disabled_html_contains_disabled": true
}
```

Resultado: ✅ el botón queda deshabilitado si existen unidades pendientes del mismo tipo.

## Hallazgos críticos

Ninguno.

## Hallazgos medios

### M-IMP-1 — `property_id` del agente es manipulable fuera de las opciones visibles

**Impacto:** un agente puede enviar una solicitud de lonas con `property_id` de otro agente o con un inmueble no publicado si manipula el payload Livewire. La UI filtra `ownPublishedPropertyOptions()`, pero la acción vuelve a hacer `Property::query()->find($data['property_id'])` sin scope de propietario ni `published()` antes de llamar a `LonaRequestService::submit()`.

Evidencia negativa ejecutada:

```json
BYPASS_PROPERTY_ID={"other_property_id":1,"request_property_id":1}
DRAFT_BYPASS={"draft_status":"borrador","request_property_id":1}
```

Archivos implicados:

- `app/Filament/Widgets/AgentLonaUnitsWidget.php`
- `app/Services/Lonas/LonaRequestService.php`

Corrección obligatoria: validar el inmueble en el backend, no sólo en las opciones del select. Para solicitud del agente: `Property::published()->where('agent_id', $agent->id)->findOrFail($property_id)` o pasar el ID al servicio y que el servicio haga la validación. Agregar tests para propiedad ajena y no publicada.

## Hallazgos menores

### Mn-IMP-1 — `grant()` no revalida que el inmueble del QR esté publicado

El formulario admin filtra propiedades publicadas, pero `LonaBatchApprovalService::grant()` acepta cualquier `Property`. Es mejor que el servicio preserve el invariante de dominio: si hay `$property`, debe estar publicada. Si el caso admin permite cualquier propiedad publicada del sistema, documentarlo; si debe ser del agente, validarlo también.

### Mn-IMP-2 — `migrate:fresh --seed` sigue roto fuera del alcance RFC-062

El seed completo falla en `ZoneSeeder` por geometría `Polygon` vs `MultiPolygon`. No bloquea RFC-062 porque `migrate:fresh` y suite pasan, pero afecta ambientes que dependan de `composer setup`/seed completo.

## Regresiones revisadas

- Épica 2 roles/permisos: ✅ `lonas.manage` y `lonas.place` agregados; matriz del seeder actualizada a 11 permisos.
- Épica 4 Property: ✅ `Property::canonical()` consumido sin modificar `app/Models/Property.php`.
- RFC-061 dashboard/agente: ✅ `AgentLonaUnitsWidget::$isDiscovered = false`; no se contamina el dashboard general.
- Archivos que NO se tocan: ✅ no aparecen modificados `app/Models/User.php`, `app/Models/Property.php`, `app/Http/Controllers/PropertyPdfController.php`, `app/Filament/Pages/AgentDashboard.php`.

## Riesgos de seguridad

- La garantía “sólo cámara” es fuerte a nivel UI contra galería: no hay `<input type=file>` y la fuente visible es `getUserMedia`.
- No es una garantía antifraude absoluta: payload Livewire o cámara virtual pueden inyectar imagen. Esto ya está documentado como riesgo residual del RFC.
- Riesgo real nuevo: `property_id` manipulado permite sugerir QR para propiedad ajena/no publicada. Corregir antes de merge.

## Tests faltantes

Obligatorios antes de merge:

1. Agente no puede enviar `property_id` de otro agente en `requestMoreVenta/Renta`.
2. Agente no puede enviar `property_id` no publicado en `requestMoreVenta/Renta`.
3. `LonaRequestService::submit()` valida la propiedad aunque se lo llame fuera del widget.
4. `LonaBatchApprovalService::grant()` rechaza propiedad no publicada para QR, o test explícito si negocio decide permitirlo.

Recomendados:

- Test de límite `cantidad <= 50` a nivel servicio o validación explícita de dominio.
- Test de filename/mime si se mantiene soporte PNG en backend; hoy todo se guarda como `evidencia-{id}.jpg` aunque el backend acepta PNG.

## Correcciones obligatorias

1. Cerrar M-IMP-1: validar `property_id` de solicitudes del agente con `published()` + `agent_id = auth()->id()` en backend.
2. Agregar tests negativos para propiedad ajena y no publicada.
3. Confirmar si `grant()` debe aceptar cualquier propiedad publicada o sólo propiedades del agente receptor; dejarlo probado.

## Correcciones recomendadas

- Mover invariantes de `property_id` y `cantidad` al servicio de dominio, no depender sólo de Filament.
- Documentar explícitamente que “sólo cámara” es garantía de UI, no prueba forense.
- Revisar `ZoneSeeder`/geometría porque afecta `composer setup` o ambientes nuevos con seed completo.

## Checklist final antes de merge

- [x] `composer install` / `composer validate --strict`
- [x] `migrate:fresh` limpio contra `inmo_test`
- [x] Índice único parcial probado por SQL directo
- [x] `php artisan test` completo verde sobre PostgreSQL
- [x] DOM sin `<input type=file>` real
- [x] `photoData` valida prefijo MIME y tamaño máximo
- [x] M-1 probado: unidades con `property_id NULL`
- [x] M-3 probado: no-agente/suspendido → `ValidationException`
- [x] PDF generado en `diseno-pdf`
- [x] QR decodificado apunta a `canonical()`
- [x] Aislamiento por rol probado por HTTP
- [x] Botón “Solicitar más” deshabilitado con pendientes
- [ ] Corregir manipulación de `property_id` en solicitudes del agente
- [ ] Agregar tests faltantes del bypass de propiedad
- [ ] Re-ejecutar suite completa
