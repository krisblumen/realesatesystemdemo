# Auditoría RFC-038 — Catálogo `service_type` en Leads

**Auditado por:** Claude (Arquitecto técnico)  
**Fecha:** Junio 2026  
**Estado:** ✅ Cerrado — Todos los hallazgos resueltos  
**RFC base:** RFC-038-LEAD-SERVICE-TYPE.md  

---

## Resumen ejecutivo

El diseño inicial presentaba dos vulnerabilidades críticas de seguridad Livewire, una validación frágil desacoplada del catálogo, y dos reglas de negocio no especificadas que afectan la integridad relacional de los leads. Todos los hallazgos fueron resueltos antes de la implementación. El RFC fue actualizado para reflejar el diseño final aprobado.

---

## Hallazgos

### 🔴 CRÍTICO-1 — `$service_type_locked` público sin `#[Locked]`

**Versión auditada:**
```php
public string $service_type = 'comercializacion';
public bool $service_type_locked = false;
```

**Problema:** En Livewire v3, cualquier propiedad pública puede ser actualizada por el cliente enviando un payload a `POST /livewire/update`. El route model binding de `/inmuebles/{property:slug}` solo protege el GET inicial; las actualizaciones Livewire no pasan por ese route. Un atacante podría:
1. Setear `service_type_locked = false`
2. Cambiar `service_type` a cualquier valor válido
3. Llamar `submit()` → lead guardado con `service_type` manipulado

**Resolución:** ✅ Cerrado

Usar `#[Locked]` de Livewire v3 en una propiedad separada:

```php
public string $service_type = 'comercializacion';

#[Locked]
public ?string $forced_service_type = null;
```

En `mount()` cuando `$locked = true`:
```php
$this->forced_service_type = $serviceType;
```

En `submit()` antes de `validate()`:
```php
if ($this->forced_service_type !== null) {
    $this->service_type = $this->forced_service_type;
}
```

El discriminador en la vista es `$forced_service_type !== null` (la prop con `#[Locked]`), no `$service_type_locked`.

---

### 🔴 CRÍTICO-2 — Default `'comercializacion'` silencia validación `required` en `/contacto`

**Versión auditada:** El RFC especificaba radio buttons visibles y campo requerido en `/contacto`.

**Problema:** Con `public string $service_type = 'comercializacion'`, la regla `required` siempre pasa aunque el visitante nunca haya interactuado con el campo.

**Resolución:** ✅ Cerrado — Regla de negocio aclarada

`/contacto` **no** muestra radio buttons. El formulario de contacto general también corresponde a consultas sobre inmuebles (Comercialización), por lo que `service_type` se fija automáticamente en `comercializacion` igual que en la ficha de inmueble.

**Ambas superficies públicas usan el mismo patrón:**
```blade
service-type="comercializacion" :locked="true"
```

Los radio buttons del componente existen en el template pero ninguna superficie pública actual los renderiza. Solo el CMS Filament expone las tres opciones.

---

### 🟡 MAYOR-3 — Validación hardcodeada, desacoplada del catálogo

**Versión auditada:**
```php
'service_type' => ['required', 'in:comercializacion,arquitectura,construccion'],
```

**Problema:** Si el catálogo evoluciona (nuevos tipos, renombres), esta regla queda desfasada sin error de compilación.

**Resolución:** ✅ Cerrado — Catálogo en DB

El catálogo vive en la tabla `service_types`. La validación usa `Rule::exists()`:
```php
'service_type' => ['required', Rule::exists('service_types', 'code')->where('active', true)],
```

Si un tipo se desactiva (`active = false`) o se agrega uno nuevo, la validación se actualiza automáticamente sin cambios de código. Ver MAYOR-4 para el diseño completo del catálogo.

---

### 🟡 MAYOR-4 — Invocación en `show.blade.php` sin `property_id`

**Versión auditada:**
```blade
<livewire:leads.lead-capture-form :service-type="'comercializacion'" :locked="true" />
```

**Problema:** Falta `:property-id="$property->id"`. Sin él, el lead creado desde la ficha de inmueble no queda vinculado al inmueble, y el `booted()` del modelo `Lead` no puede heredar la zona comercial.

**Resolución:** ✅ Cerrado

Invocación correcta en `show.blade.php`:
```blade
<livewire:leads.lead-capture-form
    :property-id="$property->id"
    source="web"
    service-type="comercializacion"
    :locked="true"
/>
```

---

### 🟢 MENOR-5 — Binding innecesario en literal string

**Versión auditada:** `:service-type="'comercializacion'"`  
**Corrección:** `service-type="comercializacion"` (sin colon para literales string)  
**Resolución:** ✅ Cerrado

---

## Reglas de negocio incorporadas durante la auditoría

Estas reglas no estaban especificadas en el RFC original. Fueron definidas durante la auditoría y se incorporaron al RFC actualizado.

### RN-A — Catálogo en base de datos
El catálogo `service_type` vive en la tabla `service_types` (code PK, label, color, sort_order, active). El Owner/Admin lo gestiona desde Filament (ServiceTypeResource). El `code` es inmutable post-creación.

### RN-B — `/contacto` también usa `comercializacion` fijado
Ambas superficies públicas fijan `service_type = 'comercializacion'` de forma transparente. Los radio buttons solo son relevantes para uso futuro o superficies internas.

### RN-C — `property_id` y `zone_id` son `null` para no-comercializacion
Solo leads de `service_type = 'comercializacion'` pueden tener vínculo con inmueble o zona. Los tipos `arquitectura` y `construccion` son independientes. Esta restricción se impone en tres capas:

1. **Modelo** — `booted()` anula `property_id` y `zone_id` si `service_type !== 'comercializacion'`
2. **Filament** — campo `property_id` con `->visible()` reactivo por `service_type`
3. **Validación Livewire** — regla `'prohibited'` para `property_id` cuando no es comercializacion

### RN-D — `property_id` en LeadResource es reactivo
El campo `property_id` usa `->live()` en el Radio + `->visible(fn(Get $get) => $get('service_type') === 'comercializacion')`. Desaparece del formulario al seleccionar `arquitectura` o `construccion`.

---

## Archivos afectados por las correcciones

| Archivo | Cambio por auditoría |
|---------|---------------------|
| `app/Livewire/Leads/LeadCaptureForm.php` | Reemplazar `$service_type_locked` por `#[Locked] $forced_service_type`; guardia en `submit()`; validación condicional de `property_id` |
| `app/Filament/Resources/LeadResource.php` | `->live()` en Radio; `->visible()` reactivo en property_id; options desde DB |
| `app/Models/Lead.php` | `booted()` con guardia de service_type; sin `SERVICE_TYPES` constant |
| `resources/views/leads/create.blade.php` | Agregar `service-type="comercializacion" :locked="true"` |
| `resources/views/inmuebles/show.blade.php` | Mantener `property_id`; agregar parámetros de lock |
| `resources/views/livewire/leads/lead-capture-form.blade.php` | Discriminador: `$forced_service_type !== null` |
| `database/migrations/` | Dividir en: create_service_types + add_col_sin_fk + add_fk |
| `database/seeders/ServiceTypeSeeder.php` | Nuevo — firstOrCreate idempotente |
| `app/Models/ServiceType.php` | Nuevo — code PK string |
| `app/Filament/Resources/ServiceTypeResource.php` | Nuevo — code inmutable en edit |
| `app/Enums/LeadServiceType.php` | Eliminar — catálogo en DB |

---

## Casos QA actualizados

Los siguientes casos del RFC original fueron modificados:

| ID QA | Cambio |
|-------|--------|
| QA-038-06 | ~~"Enviar formulario en /contacto sin seleccionar servicio → error de validación"~~ → **Eliminado**: /contacto no tiene radio buttons |
| QA-038-07 | ~~"Seleccionando Construcción → lead con construccion"~~ → **Reemplazado**: /contacto siempre guarda comercializacion |
| QA-038-09 | Actualizado: tampoco hay radio buttons en /contacto |

Casos nuevos agregados al RFC:

| ID QA | Descripción | Resultado esperado |
|-------|-------------|-------------------|
| QA-038-13 | Crear Lead de Arquitectura desde CMS con property_id | Lead guardado con property_id = null, zone_id = null |
| QA-038-14 | Owner agrega nuevo service_type desde /admin/service-types | Tipo disponible en LeadResource y válido en validación |
| QA-038-15 | Owner desactiva un service_type existente | Deja de aparecer en opciones; leads existentes no se afectan |
| QA-038-16 | Manipular payload Livewire desde /inmuebles/{slug} para cambiar service_type | Backend rechaza o ignora; lead guardado con comercializacion |

---

*Auditoría cerrada. RFC actualizado. Diseño listo para implementación.*
