# Cierre técnico — RFC-062 Control de Lonas Asignadas

**Proyecto:** New Hauz  
**Épica:** 9 — Operación de Campo  
**Rama:** `feature/control-lonas-asignadas`  
**Fecha:** 2026-07-13  
**Estado:** ✅ Implementado, auditado y técnicamente cerrado

> Nota de trazabilidad: al momento del cierre, la implementación sigue en el working tree de la rama y no existe un commit propio del RFC encima de `develop` (`HEAD 589ecba`). Por eso los cierres se citan por archivo y test; el commit quedará asignado cuando se cree el commit convencional de cierre/feature.

## Veredicto

✅ **RFC-062 queda cerrado técnicamente.** Las correcciones de la auditoría de diseño y de implementación están reconciliadas; los contratos estables quedan documentados para futuras épicas/RFCs.

La garantía “sólo cámara” queda explícitamente limitada a UI: no hay selector de archivo y la captura normal usa `getUserMedia`, pero no es prueba forense contra cámara virtual ni payload manipulado.

## Verificación final ejecutada

| Comando | Resultado |
| --- | --- |
| `DB_DATABASE=inmo_test php artisan test` | ✅ `375 passed`, `1476 assertions`, PostgreSQL real |
| `./vendor/bin/pint --test ...` sobre archivos Épica 9 | ✅ `pint passed` |
| `composer validate --strict` | ✅ `./composer.json is valid` |
| `composer show endroid/qr-code --locked` | ✅ `endroid/qr-code 6.0.9`, lock en sync |

## Reconciliación de hallazgos

### Auditoría de diseño

| Hallazgo | Estado | Cierre / evidencia |
| --- | --- | --- |
| C-1 — carrera en solicitudes pendientes | ✅ Resuelto | Índice parcial `lona_requests_agent_tipo_pendiente_unique` en `database/migrations/2026_07_13_000001_create_lona_requests_table.php`; traducción de colisión en `app/Services/Lonas/LonaRequestService.php`; tests en `tests/Feature/Lonas/LonaSchemaTest.php` y `LonaEligibilityTest.php`. |
| C-2 — falta `LonaBatchPolicy` | ✅ Resuelto | `app/Policies/LonaBatchPolicy.php`; registro explícito en `app/Providers/AppServiceProvider.php`. |
| M-1 — inmueble QR vs colocación real | ✅ Resuelto | `app/Services/Lonas/LonaBatchApprovalService.php` crea `LonaUnit` sin `property_id`; `app/Livewire/Lonas/CapturePlacementEvidence.php` fija inmueble/ubicación recién al colocar; test `test_grant_does_not_copy_qr_property_into_unit_property`. |
| M-2 — validación base64 defectuosa | ✅ Resuelto | `CapturePlacementEvidence::rules()` usa closure con prefijos `data:image/jpeg;base64,` / `data:image/png;base64,`, `max:7000000` y `addMediaFromBase64(..., 'image/jpeg', 'image/png')`; tests en `LonaEvidenceCaptureTest.php`. |
| M-3 — `grant()` sin rol/estado | ✅ Resuelto | `LonaBatchApprovalService::grant()` exige rol `agente` + `isActive()`; tests `test_grant_rejects_a_user_without_agente_role` y `test_grant_rejects_a_suspended_agent`. |
| Mn-1 — tope 50 | ✅ Resuelto | Forms Filament con `maxValue(50)` y servicios con invariante `1..50`; tests `test_submit_rejects_cantidad_over_the_maximum` y `test_grant_rejects_cantidad_over_the_maximum`. |
| Op-1 — `forceDelete=false` | ✅ Resuelto | `forceDelete()` retorna `false` en `LonaBatchPolicy`, `LonaRequestPolicy`, `LonaUnitPolicy`; cubierto en tests de policy/schema. |

### Auditoría de implementación

| Hallazgo | Estado | Cierre / evidencia |
| --- | --- | --- |
| M-IMP-1 — `property_id` manipulable | ✅ Resuelto | `LonaRequestService::submit()` valida que el inmueble sugerido sea publicado y pertenezca al agente; tests negativos en `LonaEligibilityTest.php` y bypass vía widget en `LonaResourcesTest.php`. |
| Mn-IMP-1 — `grant()` exige inmueble publicado | ✅ Resuelto | `LonaBatchApprovalService::grant()` rechaza propiedad no publicada; decisión I-8 documenta que admin puede elegir cualquier inmueble publicado del sistema; test `test_grant_rejects_an_unpublished_qr_property`. |
| Recomendación — `cantidad <= 50` en servicio | ✅ Resuelto | Constante `MAX_CANTIDAD = 50` en `LonaRequestService` y `LonaBatchApprovalService`; tests de máximo en ambos servicios. |
| Mn-IMP-2 — `ZoneSeeder` Polygon/MultiPolygon | ↪ Diferido fuera de alcance | No pertenece a RFC-062 ni toca Lonas. Queda como tarea separada de seed geográfico / Épica de zonas. Referencias: `docs/audits/RFC-062-auditoria-implementacion.md`, `docs/prompts/PROMPTS-RFC-062.md`; destino técnico sugerido: fix dedicado de `database/seeders/ZoneSeeder.php`. |

## Decisiones diferidas

| Decisión | Estado final |
| --- | --- |
| CD-1 — ubicación épica | ✅ Cerrada: Épica 9 — Operación de Campo (`docs/rfc/EPICA-9-OPERACION-DE-CAMPO.md`). |
| CD-2 — QR package | ✅ Cerrada: `endroid/qr-code ^6.0`, lock actual `6.0.9`; API v6 usada en `LonaBatchApprovalService`. |
| CD-3 — GPS | ↪ Sigue abierta y correctamente diferida; no forma parte de RFC-062. |
| CD-4 — rango QA | ✅ Cerrada: QA-151 a QA-167. |
| CD-5 — máximo lote/solicitud | ✅ Cerrada: `1..50` en forms y servicios. |
| CD-6 — tamaño físico lona | ✅ Cerrada: 90×120cm, `setPaper([0, 0, 2551, 3402])`. |
| R-1 — arte gráfico final | ✅ Cerrada (post-cierre, 2026-07-13): Diseño entregó `public/images/brand/fondo_lonas.jpg` (2551×3402px, 1:1 con la página) y `public/images/brand/Logo_lonas.svg`; `resources/views/pdf/lona-design.blade.php` reescrito con el layout real (decisión I-9). Ya no es un placeholder. |
| R-2 — sólo cámara | ✅ Documentado como riesgo residual aceptado: garantía de UI, no forense. |

## Contratos estables

### Servicios

| Servicio | Contrato estable |
| --- | --- |
| `LonaEligibilityService::canRequestMore(User $agent, OperationType $type): bool` | Retorna `false` si el agente tiene unidades `pendiente_colocacion` de ese tipo o una solicitud `pendiente` del mismo tipo; venta/renta son independientes. |
| `LonaRequestService::submit(User $agent, OperationType $type, int $cantidad, ?Property $property = null): LonaRequest` | Crea solicitud pendiente, notifica owner/admin, valida `cantidad` 1..50, valida propiedad sugerida publicada y propia del agente, y traduce colisiones del índice parcial a `ValidationException`. |
| `LonaBatchApprovalService::grant(User $agent, OperationType $type, int $cantidad, User $authorizedBy, ?Property $property = null, ?LonaRequest $request = null): LonaBatch` | Crea lote, N unidades pendientes y PDF `diseno-pdf`; valida agente activo con rol `agente`, `cantidad` 1..50 y propiedad QR publicada. No copia `property_id` del QR a las unidades. |
| `LonaBatchApprovalService::reject(LonaRequest $request, User $reviewedBy, string $motivo): LonaRequest` | Marca solicitud como rechazada, guarda reviewer/fecha/motivo y notifica al agente. |

### Permisos

| Permiso | Consumidor |
| --- | --- |
| `lonas.manage` | Owner/admin: recursos Filament de lotes y solicitudes. |
| `lonas.place` | Agente: página `Mis Lonas`, solicitud de reposición y registro de evidencia propia. |

### Modelos y media

| Modelo | Contrato estable |
| --- | --- |
| `LonaBatch` | Lote autorizado; media collection single-file `diseno-pdf`. |
| `LonaUnit` | Unidad física individual; media collection single-file `evidencia`; `property_id` representa ubicación real de colocación, no inmueble del QR. |
| `LonaRequest` | Solicitud del agente; estados `pendiente`, `aprobada`, `rechazada`; soft deletes. |

### Base de datos

| Contrato | Detalle |
| --- | --- |
| Índice parcial | `lona_requests_agent_tipo_pendiente_unique` en `(agent_id, operation_type)` con `WHERE estado = 'pendiente' AND deleted_at IS NULL`. |
| Tipo venta/renta | Se reutiliza `App\Enums\OperationType`; no existe `LonaTipo` separado. |
| Policies | `LonaBatchPolicy`, `LonaRequestPolicy`, `LonaUnitPolicy` registradas explícitamente en `AppServiceProvider` vía `Gate::policy()`. |

## Divergencias diseño ↔ implementación

| Divergencia | Estado |
| --- | --- |
| El diseño inicial mencionaba `LonaTipo`; implementación usa `OperationType` (I-1). | ✅ Estabilizado. |
| Orden de migraciones final es requests → batches → units por FK (I-2). | ✅ Estabilizado. |
| `endroid/qr-code` terminó en v6, no en el supuesto original v5. | ✅ Estabilizado y verificado contra lock. |
| Inmueble QR en `grant()` puede ser cualquier inmueble publicado, no necesariamente del agente receptor (I-8). | ✅ Estabilizado como decisión de negocio/admin. |
| GPS y arte final siguen fuera del alcance. | ↪ Deuda/diferido explícito. |

## Checklist de merge final

- [x] RFC estado ✅ IMPLEMENTADO.
- [x] Etapa 6 de seguimiento marcada ✅.
- [x] Ambas auditorías reconciliadas.
- [x] Tests completos verdes sobre PostgreSQL real.
- [x] Pint limpio en archivos Épica 9.
- [x] Composer validado y lock con `endroid/qr-code 6.0.9`.
- [x] Contratos estables documentados.
- [x] Riesgo “sólo cámara” documentado como garantía UI, no forense.
- [ ] Crear commit convencional del RFC.
- [ ] Abrir PR hacia `develop`.
- [ ] Crear/trackear tarea separada para `ZoneSeeder` Polygon/MultiPolygon si QA lo exige para seed completo.
