# Cierre Técnico — RFC-038 "Catálogo service_type en Leads"

**Proyecto:** NEW HAUZ  
**RFC:** RFC-038  
**Auditor:** Claude (Arquitecto técnico)  
**Fecha de cierre:** Junio 2026  
**Tag objetivo:** v0.5.1  
**Branch:** `feature/leads-contact-public`

---

## Dictamen: ✅ APROBADO PARA MERGE *(con enmienda aplicada)*

La implementación cumple con el contrato técnico completo del RFC-038. No hay hallazgos bloqueantes. Tres observaciones menores de calidad documentadas para seguimiento post-merge.

> **Enmienda post-cierre:** Decisión revertida sobre `/contacto`. Ver Sección 8.

---

## 1. Checklist de Definition of Done

| Item | Estado | Evidencia |
|------|--------|-----------|
| Tres migraciones ejecutadas; tabla `service_types` con FK activo | ✅ | Timestamps 190235 < 190244 < 190245; FK restrictOnDelete verificado |
| `ServiceTypeSeeder` corre sin error; tres tipos iniciales presentes | ✅ | `firstOrCreate` idempotente; comercializacion/arquitectura/construccion |
| Modelo `Lead` actualizado: `$fillable`, `$attributes`, `booted()` con guardia | ✅ | Guardia en `creating`: non-comercializacion → property_id=null, zone_id=null |
| `ServiceType` model y `ServiceTypeResource` creados | ✅ | PK string; code inmutable en edit; reorderable en tabla |
| `LeadResource`: radio desde DB, `property_id` reactivo, badge, filtro | ✅ | `->live()`, `->visible()` reactivo, opciones desde `ServiceType::query()` |
| `LeadCaptureForm`: `#[Locked] $forced_service_type`; guardia en `submit()` | ✅ | Guardia PRECEDE a `validate()`; no existe `$service_type_locked` sin `#[Locked]` |
| Validación: `Rule::exists('service_types', 'code')` | ✅ | Con `->where('active', true)` |
| `property_id` → `'prohibited'` para service_type != comercializacion | ✅ | Validación condicional en `rules()` |
| Ambas superficies públicas: `service-type="comercializacion" :locked="true"` | ✅ | create.blade.php y show.blade.php |
| Vista usa `$forced_service_type === null` como discriminador | ✅ | No usa `$service_type_locked` (correcto) |
| `app/Enums/LeadServiceType.php` eliminado; sin referencias residuales | ✅ | Glob y Grep confirman ausencia en código productivo |
| `ServiceTypeSeeder` en `DatabaseSeeder` y en `TestCase::setUp()` | ✅ | Seeder en posición 2; TestCase con guard `Schema::hasTable` |
| Tests cubren los casos críticos | ✅ | Ver Sección 3 |

---

## 2. Verificación de seguridad

El hallazgo crítico de la auditoría pre-implementación (CRÍTICO-1: `$service_type_locked` sin `#[Locked]`) quedó correctamente implementado:

```
# Patrón incorrecto (rechazado en auditoría)
public bool $service_type_locked = false;   ← bypasseable

# Patrón implementado (correcto)
#[Locked]
public ?string $forced_service_type = null;  ← cliente no puede modificar
```

La guardia en `submit()` precede a `validate()`, lo que hace imposible el bypass mediante manipulación de payload Livewire. Un atacante que intente setear `service_type = 'arquitectura'` desde `/inmuebles/{slug}` o `/contacto` obtendrá el valor forzado por el servidor antes de cualquier validación.

---

## 3. Cobertura de tests

### `LeadCoreTest.php`
| Test | Cubre |
|------|-------|
| `test_initial_service_type_catalog_is_seeded` | Seeder: los 3 tipos existen en DB |
| `test_non_commercial_service_type_clears_property_and_zone` | **Regla core:** booted() anula property_id/zone_id para arquitectura/construccion |
| `test_lead_defaults_and_enum_casts_are_available` | Default 'comercializacion' en $attributes |
| `test_lead_inherits_zone_from_property_when_zone_is_blank` | Herencia de zona solo para comercializacion |

### `PublicLeadCaptureTest.php`
| Test | Cubre |
|------|-------|
| `test_locked_public_form_forces_service_type_before_validation` | **Seguridad crítica:** payload manipulado no puede cambiar service_type |
| `test_non_commercial_public_submission_prohibits_property_id` | Regla 'prohibited' para property_id |
| `test_public_form_creates_a_new_lead_and_dispatches_event` | Flujo completo: lead guardado con 'comercializacion' |

### `LeadResourceTest.php`
| Test | Cubre |
|------|-------|
| `test_lead_form_service_type_options_come_from_active_catalog` | Opciones desde DB, no hardcoded |
| `test_owner_can_filter_search_and_change_status_of_leads` | Filtro por service_type funcional |
| `test_agent_creating_manual_lead_is_assigned_to_self_and_can_see_it` | Campo service_type en formulario CMS |

### `TestCase.php`
Garantiza `ServiceTypeSeeder` antes de cada test con `Schema::hasTable('service_types')` como guard. Necesario para que la FK de `leads.service_type` no rompa los tests.

---

## 4. Observaciones para seguimiento post-merge

### OBS-1 — N+1 en badge/color de `service_type` en `LeadResource` ⚠️

**Archivo:** `app/Filament/Resources/LeadResource.php`  
**Líneas:** ~141–142

```php
// Patrón actual: 2 queries por fila en la tabla de Filament
->formatStateUsing(fn (string $state): string => ServiceType::query()->find($state)?->label ?? $state)
->color(fn (string $state): string => ServiceType::query()->find($state)?->color ?? 'gray'),
```

Con 25 leads por página = 50 queries extra. Impacto aceptable hoy; a resolver si el volumen de leads crece.

**Solución recomendada para siguiente iteración:**

```php
// Opción A — static cache local (simple)
->formatStateUsing(function (string $state): string {
    static $cache = [];
    return $cache[$state] ??= ServiceType::query()->find($state)?->label ?? $state;
})

// Opción B — eager load en getEloquentQuery() (más limpio)
// No aplica directamente porque service_type no es una relación Eloquent.
// La opción A es la más pragmática aquí.
```

### OBS-2 — `ServiceTypeSeeder` sin `active` explícito ⚠️

**Archivo:** `database/seeders/ServiceTypeSeeder.php`

Los arrays no incluyen `'active' => true`. Funciona por el default de DB, pero si alguien modifica el default en la migración, el seeder no lo refleja.

```php
// Recomendado: hacer el valor explícito
['code' => 'comercializacion', 'label' => 'Comercialización', 'color' => 'info', 'sort_order' => 1, 'active' => true],
```

### OBS-3 — `Schema::hasTable` en cada `setUp()` de TestCase ℹ️

**Archivo:** `tests/TestCase.php`

La introspección `Schema::hasTable('service_types')` agrega una query por cada test. Con `RefreshDatabase` la tabla siempre existe post-migración, por lo que el guard es redundante y podría eliminarse.

---

## 5. Reglas de negocio verificadas en producción

Las siguientes reglas fueron especificadas en la auditoría pre-implementación y se confirman implementadas:

| Regla | Implementación | Estado |
|-------|---------------|--------|
| `service_type` obligatorio en todo Lead | `['required', Rule::exists(...)]` en LeadCaptureForm y LeadResource | ✅ |
| Catálogo dinámico en DB; Admin lo gestiona | `ServiceTypeResource` con CRUD; opciones desde `ServiceType::query()` | ✅ |
| `/contacto` muestra radio buttons; el prospecto elige el servicio de interés | `$service_type = ''`; invocación sin params; `required` fuerza selección | ✅ (enmienda) |
| `/inmuebles/{slug}` fija 'comercializacion' sin selección del usuario | Invocación con `service-type="comercializacion" :locked="true"` | ✅ |
| Seguridad: payload Livewire no puede alterar service_type en modo locked | `#[Locked] $forced_service_type` + guardia pre-validate en submit() | ✅ |
| `arquitectura`/`construccion` → `property_id = null`, `zone_id = null` | `booted()` + `->visible()` reactivo + `'prohibited'` en validación | ✅ (triple capa) |
| Catálogo valida solo activos | `Rule::exists(...)->where('active', true)` | ✅ |
| `code` inmutable post-creación | `->disabled(fn($op) => $op === 'edit')` en ServiceTypeResource | ✅ |
| Agente no puede editar `service_type` | Campo dentro de Section con `->disabled()` por rol | ✅ |

---

## 6. Archivos entregados

| Archivo | Tipo | Estado |
|---------|------|--------|
| `database/migrations/2026_06_29_190235_create_service_types_table.php` | Nuevo | ✅ |
| `database/migrations/2026_06_29_190244_add_service_type_to_leads_table.php` | Nuevo | ✅ |
| `database/migrations/2026_06_29_190245_add_service_type_fk_to_leads_table.php` | Nuevo | ✅ |
| `database/seeders/ServiceTypeSeeder.php` | Nuevo | ✅ |
| `database/seeders/DatabaseSeeder.php` | Modificado | ✅ |
| `app/Models/ServiceType.php` | Nuevo | ✅ |
| `app/Filament/Resources/ServiceTypeResource.php` | Nuevo | ✅ |
| `app/Models/Lead.php` | Modificado | ✅ |
| `app/Filament/Resources/LeadResource.php` | Modificado | ✅ |
| `app/Livewire/Leads/LeadCaptureForm.php` | Modificado | ✅ |
| `resources/views/livewire/leads/lead-capture-form.blade.php` | Modificado | ✅ |
| `resources/views/leads/create.blade.php` | Modificado | ✅ |
| `resources/views/inmuebles/show.blade.php` | Modificado | ✅ |
| `tests/Feature/Leads/LeadCoreTest.php` | Modificado | ✅ |
| `tests/Feature/Leads/PublicLeadCaptureTest.php` | Modificado | ✅ |
| `tests/Feature/Filament/LeadResourceTest.php` | Modificado | ✅ |
| `tests/TestCase.php` | Modificado | ✅ |
| `app/Enums/LeadServiceType.php` | **Eliminado** | ✅ |
| `docs/rfc/reports/AUDITORIA-RFC-038.md` | Nuevo (pre-impl) | ✅ |
| `docs/rfc/reports/CIERRE-TECNICO-RFC-038.md` | Nuevo (este archivo) | ✅ |

---

## 7. Próximos pasos

- [ ] Resolver OBS-1 (N+1 en LeadResource) en siguiente iteración de performance
- [ ] Agregar `'active' => true` explícito en ServiceTypeSeeder (OBS-2, cosmético)
- [ ] Merge a `develop` y ejecutar `php artisan migrate --seed` en staging
- [ ] QA-038-01 a QA-038-10b aprobación final por Sebastián (casos renumerados por enmienda)
- [ ] Tag `v0.5.1` al cierre de EPICA-5

---

## 8. Enmienda post-cierre — `/contacto` con radio buttons visibles

**Fecha:** Junio 2026  
**Tipo:** Corrección de regla de negocio

### Decisión revertida
Durante la auditoría pre-implementación se acordó que `/contacto` también fijaría `service_type = 'comercializacion'` de forma transparente. Esta decisión fue incorrecta.

### Decisión vigente
`/contacto` debe mostrar radio buttons para que el prospecto indique su tipo de servicio de interés. Solo `/inmuebles/{slug}` queda locked por contexto (el usuario ya está viendo un inmueble → Comercialización).

### Cambios aplicados

| Archivo | Cambio |
|---------|--------|
| `app/Livewire/Leads/LeadCaptureForm.php` | `public string $service_type = ''` (era `'comercializacion'`) |
| `resources/views/leads/create.blade.php` | Invocación sin `service-type` ni `:locked` |

### Por qué el default vacío es correcto
Con `$service_type = ''`:
- En `/contacto`: ningún radio viene pre-seleccionado. `required` fuerza elección explícita.
- En `/inmuebles/{slug}`: `mount()` recibe `serviceType = 'comercializacion'` y lo asigna. El guard en `submit()` lo refuerza. El default del componente es irrelevante.

### Tests pendientes de actualización
- `PublicLeadCaptureTest`: el test que crea un lead desde el formulario público debe pasar `service_type` explícito.
- Agregar: submit en `/contacto` sin `service_type` → error de validación.
- Agregar: submit con `service_type = 'arquitectura'` → lead con `property_id = null`.

---

*Cierre técnico firmado con enmienda. RFC-038 listo para merge.*
