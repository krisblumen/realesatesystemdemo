# Auditoría de Implementación — Épica 4 — Inmuebles

**Proyecto:** New Hauz — Plataforma Inmobiliaria
**Fecha:** 19 de Junio, 2026
**Auditor:** Gemini CLI
**Rama auditada:** `feature/epica-4-inmuebles` (HEAD `816648a`)
**Documento auditado:** implementación de Lotes A→E contra `docs/epicas/epica-4-inmuebles.md` (diseño aprobado) y contratos de Épicas 1/2/3
**Evidencia ejecutada:** `php artisan test` (suite completa), `./vendor/bin/pint --test`, `./vendor/bin/phpstan analyse --memory-limit=1G`

---

## 1. Veredicto

**⚠️ APROBADO CON OBSERVACIONES**

La implementación de la Épica 4 es **fiel al diseño aprobado** y resuelve correctamente los cuatro hallazgos críticos que la auditoría de diseño había marcado. La suite de pruebas pasa completa sobre PostgreSQL (**132/132, 671 assertions**), con QA-026→QA-051 mapeado 1:1 a tests reales y sin regresiones en Épicas 1/2/3. La autorización vive en `PropertyPolicy` y en el forzado de servidor; no depende de la UI de Filament.

Las observaciones son de **calidad transversal pre-existente**, no defectos de Épica 4: el repositorio no pasa `pint --test` ni `phpstan analyse` en verde por deuda heredada de Épicas anteriores (un snippet de documentación y la tipificación geoespacial de `Zone`). El diseño fijó como DoD del Lote E "Pint y PHPStan sin errores"; ese gate no se cumple a nivel de repo, aunque **ningún archivo de Épica 4 está implicado**. Procede merge una vez que el equipo decida cómo tratar esa deuda (baseline, exclusión del snippet o corrección).

---

## 2. Hallazgos críticos

**Ninguno.** Los cuatro críticos del diseño fueron implementados y verificados:

| Crítico de diseño | Implementación | Evidencia |
| :--- | :--- | :--- |
| 2.1 Invariante de publicación durable | `PropertyStatusService::guardPublish()` exige zona existente + activa + con polígono + portada; `Property::assertPublishedInvariant()` en `PropertyObserver@saving`; `AppServiceProvider` pausa publicados al inactivar/eliminar zona; hook `Media::deleting` impide borrar la última portada de un publicado | `PropertyPublicationTest` (QA-045/046/047), `PropertyStatusServiceTest` |
| 2.2 Forzado de `agent_id` y validación de zona en backend | Trait `EnforcesAgentPropertyOwnership` en `CreateProperty`/`EditProperty` (`mutateFormDataBeforeCreate/Save`): fuerza `agent_id = auth()->id()` e ignora payload; rechaza zona ajena | `PropertyResourceTest::test_agent_payload_forces_self_and_rejects_foreign_zone` (QA-041/042) |
| 2.3 Precedencia agente↔zona | `PropertyPolicy::canManage()` y `Property::scopeVisibleTo()` codifican el mismo predicado: `agent_id` manda; la zona solo da acceso a inmuebles sin responsable | `PropertyPolicyTest`, `PropertyScopesTest` (QA-039/044/048) |
| 2.4 Única puerta de estado | `status` y `slug` fuera de `$fillable`; `PropertyStatusService::transition()` con `DB::transaction` + `lockForUpdate` y asignación directa | `PropertyCoreTest::test_status_and_slug_are_not_mass_assignable` (QA-043), `PropertySlugConcurrencyTest` (QA-050) |

---

## 3. Hallazgos medios

### 3.1 `phpstan analyse` no está en verde a nivel de repositorio (deuda pre-existente)

Con `--memory-limit=1G`, PHPStan reporta **19 errores**, **ninguno en código de Épica 4**. Se concentran en archivos previos:

- `app/Models/Zone.php` — `Access to an undefined property Zone::$polygon` (×5), `Unsafe call to private uniqueSlug()`, comparación estricta siempre falsa (geometría de Épica 3).
- `app/Filament/Resources/ZoneResource/RelationManagers/AgentsRelationManager.php` — `argument.type` (Épica 3).
- `database/seeders/OwnerSeeder.php` — `env()` fuera de config + nullsafe redundante (Épica 1/2).
- `tests/Feature/Zones/*`, `tests/Feature/Filament/ZoneResourceTest.php` — accesos a `$polygon`/`$center_point` (Épica 3).

El PHPDoc de los contratos activados (`User::properties()`, `Zone::properties()` → `HasMany<Property, $this>`) **sí es aceptado por Larastan**: el objetivo concreto de la corrección 8.6 del diseño está cumplido. **Impacto:** el DoD del Lote E ("PHPStan 0 errores") no se cumple por deuda heredada. **Recomendación:** introducir baseline de PHPStan o corregir la tipificación geoespacial de `Zone` en una tarea de saneamiento aparte; no bloquea la corrección de Épica 4.

### 3.2 `pint --test` falla a nivel de repositorio por snippet de documentación

`./vendor/bin/pint --test` falla con un único error en `docs/files-login-design/AdminPanelProvider.snippet.php` (parse error de un fragmento PHP parcial de la RFC-059, no es código ejecutable). **Todo el código fuente de Épica 4 pasa Pint limpio** (verificado ejecutando Pint sobre los 12 archivos de la épica → `passed`). **Impacto:** el gate "Pint sin errores" del DoD no se cumple por un artefacto documental heredado. **Recomendación:** excluir `docs/**` del `pint.json` o renombrar el snippet a `.php.txt`.

---

## 4. Hallazgos menores

### 4.1 `RestoreAction` degrada con `->before()` en lugar de `->after()`
El diseño (§13.4) especificó degradar publicado→borrador en el callback `->after()`; la implementación lo hace en `->before()` (tanto en la table action como en el header action de `EditProperty`). Es **funcionalmente equivalente y seguro** (fija `status = Borrador` antes de retirar el `deleted_at`, evitando que el registro reaparezca publicado un instante). Cubierto por QA-049. No requiere cambio.

### 4.2 Comprobación de polígono vía `polygonAsGeoJson()` (divergencia justificada)
`guardPublish()` y `assertPublishedInvariant()` evalúan el polígono con `$zone->polygonAsGeoJson() === null` en lugar del literal `$zone->polygon === null` del diseño. Es **más correcto**: `Zone::$polygon` almacena EWKT y el accesor ejecuta `ST_AsGeoJSON`, evitando falsos positivos. Divergencia positiva; no es defecto.

### 4.3 Ruta de creación "cruda" sin reintento de slug
`PropertyObserver@creating` genera el slug con `PropertySlugGenerator::generate()` (sin reintento), mientras la creación vía Filament usa `handleRecordCreation()` → `persist()` (con `retry(3)`). Una creación cruda concurrente (`Property::create()` en código futuro) caería en el índice único sin reintento automático. La **integridad se mantiene** (el índice único es la garantía final; no hay duplicados), solo se pierde el reintento transparente en esa ruta. `PropertySlugConcurrencyTest` cubre `persist()`. Menor.

---

## 5. Regresiones detectadas

**Ninguna.** La suite completa pasa **132/132 (671 assertions)** sobre PostgreSQL, incluyendo:

- `Epica123RegressionTest` — panel `/admin`, permiso `properties.manage`, y resolución real de `Zone::properties()` / `User::properties()` contra la tabla `properties`.
- Suites previas intactas: `Auth` (login RFC-060), `Dashboard` (RFC-061), `Zones` (Épica 3), `UserResource`/`Filament` (Épica 2).
- Activación de contratos: cambio limitado al cuerpo + PHPDoc de `properties()` en `User`/`Zone`; ningún consumidor previo roto.

---

## 6. Riesgos de seguridad

Sin hallazgos abiertos. Controles verificados:

- **Autorización backend-first:** `PropertyPolicy` es la fuente de verdad; `delete`/`restore` exigen `properties.manage` **y** owner/admin (coherente con Épica 2). `forceDelete` siempre `false`.
- **No confianza en la UI:** ocultar `agent_id` se complementa con `EnforcesAgentPropertyOwnership`, que fuerza el responsable y valida la zona en servidor. Un payload manipulado de `agent_id`/`zone_id` es neutralizado (QA-041/042).
- **Aislamiento horizontal:** `scopeVisibleTo()` + `canManage()` impiden que un agente vea o gestione inmuebles de otro agente en zona compartida (QA-044/048).
- **Sin bypass de estado:** `status` no es mass-assignable; toda transición pasa por el servicio transaccional (QA-043).

---

## 7. Riesgos de mantenimiento

- **Deuda de análisis estático heredada (medio 3.1/3.2):** mientras el repo no pase Pint/PHPStan en verde, el CI no puede usar esos gates como señal binaria; conviene baseline + tarea de saneamiento de la geometría de `Zone`.
- **Listeners de modelo en `AppServiceProvider::boot()`:** la propagación `Zone::updated/deleted` y el hook `Media::deleting` viven como closures en el provider. Funciona y está probado, pero a medida que crezcan conviene extraerlos a observers/listeners dedicados para testabilidad y legibilidad.
- **Conversiones `nonQueued`:** ambas conversiones (`thumb`, `web`) son síncronas; correcto para el invariante de `og:image`, pero con volumen alto de subidas conviene reevaluar una cola garantizada.

---

## 8. Tests faltantes

Cobertura **completa** de QA-026→QA-051 (ver `docs/qa/EPICA-4-TEST-MAPPING.md`). Sugerencias opcionales, no bloqueantes:

- Test explícito del hook `Media::deleting` cuando el inmueble **no** está publicado (debe permitir borrar portada sin error) — hoy se cubre indirectamente.
- Test de creación cruda concurrente (`Property::create()` fuera de Filament) para documentar el comportamiento descrito en 4.3.

---

## 9. Correcciones obligatorias para Codex

1. **Restaurar el gate de calidad del repo** (elegir una vía y dejar `pint --test` y `phpstan analyse` en verde para que el DoD del Lote E sea válido):
   - Excluir `docs/**` de Pint (o renombrar `docs/files-login-design/AdminPanelProvider.snippet.php`).
   - Añadir baseline de PHPStan **o** sanear la tipificación geoespacial de `Zone` (declarar `polygon`/`center_point` como propiedades tipadas / casts) — en tarea separada, sin tocar el comportamiento.

> No hay correcciones obligatorias sobre el **comportamiento** de Épica 4: los críticos y medios funcionales del diseño están resueltos.

---

## 10. Correcciones recomendadas

1. Unificar la generación de slug para que la ruta "cruda" también reintente (mover el `retry` al ciclo de guardado o documentar que la creación canónica es vía `persist()`).
2. Extraer los closures de `AppServiceProvider` (propagación de zona, `Media::deleting`) a listeners/observers dedicados.
3. Añadir los dos tests opcionales de la sección 8.

---

## 11. Checklist final antes de merge

| # | Ítem | Estado |
| :--- | :--- | :---: |
| 1 | Enums `OperationType`/`PropertyType`/`PropertyStatus` fieles al diseño | ✅ |
| 2 | Migración `properties` con FKs `nullOnDelete`, CHECKs (enum + numéricos), índices | ✅ |
| 3 | `status`/`slug` fuera de `$fillable`; transición solo vía servicio (tx + lock) | ✅ |
| 4 | `User::properties()` / `Zone::properties()` activados (cuerpo + PHPDoc) sin romper Épicas 2/3 | ✅ |
| 5 | Invariante de publicación durable (cover + zona activa con polígono) sostenido en edición y baja de zona | ✅ |
| 6 | `PropertyPolicy` backend-first + forzado de `agent_id`/zona en servidor | ✅ |
| 7 | Precedencia agente↔zona alineada Policy ≡ scope (`visibleTo`) | ✅ |
| 8 | Slug único con índice en BD, reintento concurrencia, no se regenera al editar | ✅ |
| 9 | Galería: portada `singleFile` obligatoria para publicar, galería ordenable, conversiones `thumb`/`web` | ✅ |
| 10 | Catálogo `features` + pivote con índice inverso + seeder convergente | ✅ |
| 11 | Suite completa verde sobre PostgreSQL (132/132) + QA-026→051 mapeado | ✅ |
| 12 | Regresión Épicas 1/2/3 en verde | ✅ |
| 13 | `pint --test` (repo) en verde | ⚠️ Falla por snippet doc pre-existente (código Épica 4 limpio) |
| 14 | `phpstan analyse` (repo) en verde | ⚠️ 19 errores pre-existentes (Épica 3/seeders); Épica 4 limpio |

**Resultado:** 12/14 ✅, 2 ⚠️ por deuda transversal pre-existente. La funcionalidad de Épica 4 está **lista para merge**; los dos ⚠️ se resuelven con la corrección obligatoria 9.1 (saneamiento de gates), que no afecta el comportamiento de la épica.

---

*Fin del reporte de auditoría de implementación — Épica 4 Inmuebles.*
