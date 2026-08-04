# Reauditoría de diseño — Épica 12.1 Mejora UX del Hero

**Proyecto:** New Hauz — Plataforma inmobiliaria (monolito Laravel)  
**Fecha:** 2026-07-25  
**Auditor:** Codex, auditor independiente  
**Rama auditada:** `feature/epica-12-content-manager`  
**Documento auditado:** `docs/epicas/epica-12-1-mejora-ux-hero.md` (v10)  
**Commit auditado:** `9fdbdbd docs(epica-12): diseño v10 del Hero — propagación a RFC-077/074 y API de resolución publicada`

## 1. Veredicto

### **APROBADO**

La v10 cierra los bloqueantes de la reauditoría v9:

- **C-11:** RFC-077 ahora incorpora el paso de locks de `media` y RFC-074 delimita la estrategia B como histórica/no normativa.
- **M-7:** se define la frontera concreta `PublishedMediaReference::resolvePublished(string $uuid, FrontendPage $page, string $collection = 'images'): ?Media`.
- **M-8:** la estrategia A queda vigente y la estrategia B queda fuera del contrato normativo.
- **Mn-1:** se descarta `section_id` del snapshot y se conserva el formato estable de las revisiones publicadas.

No encontré contradicciones activas entre la fuente única de la épica, RFC-074, RFC-075 y RFC-077. La aprobación es del **diseño**: no implica que P4-A esté implementado ni que los tests de la nueva funcionalidad ya existan.

> **GATE DE DISEÑO 12.1: APROBADO**

**P4-A queda habilitado.**

## 2. Evidencia verificada en código y documentación real

### Comandos ejecutados

| Comando | Resultado |
| --- | --- |
| `composer validate --strict` | ✅ `./composer.json is valid` |
| `./vendor/bin/pint --test` | ✅ Passed |
| `git diff --check` | ✅ Sin errores de whitespace |
| `DB_DATABASE=inmo_test php artisan test tests/Feature/Frontend tests/Unit/Frontend` | ✅ 335 tests, 1,438 assertions, PostgreSQL real |
| `npm run build` | ✅ Vite `v8.0.16` y `build:filament`; solo warning informativo de Browserslist |
| `git show --stat --oneline 9fdbdbd` | ✅ El cambio auditado es documental; no implementa el pipeline de P4-A |
| `rg -n 'No hace falta bloquear|media solo se VALIDA|SIN lock|FrontendMediaReferenceService' docs/epicas docs/rfc` | ⚠️ Devuelve coincidencias históricas y de la propia matriz de auditoría; revisión manual confirmó cero instrucciones normativas activas |

### Contratos del código existente confirmados

- `FrontendSection` usa `SoftDeletes`: `app/Models/FrontendSection.php:21-25`.
- La migración usa índices parciales que permiten recrear una clave después de un soft-delete: `database/migrations/2026_07_24_100100_create_frontend_sections_table.php:33-44`.
- La clase existente es `FrontendMediaReference`: `app/Services/Frontend/FrontendMediaReference.php:22-55`.
- El renderer actual todavía usa `sections()->get()->keyBy('section_key')`: `app/Services/Frontend/FrontendPageRenderer.php:116-121`. La v10 lo identifica como conducta a reemplazar durante P4-A mediante `PublishedMediaReference`; no lo presenta como comportamiento ya implementado.

## 3. Matriz de cierre de hallazgos

| Hallazgo | Estado | Evidencia de cierre |
| --- | --- | --- |
| **C-1 a C-10** | ✅ Resueltos | Contratos y enmiendas conservados en `docs/epicas/epica-12-1-mejora-ux-hero.md` |
| **M-1 a M-2** | ✅ Resueltos | Estados `draft/pending/promoted`, predicados y reconciliación en §7.8 y §14 |
| **M-3** — carrera publisher/job | ✅ Resuelto en diseño | Lock order, lectura bajo lock y pruebas concurrentes en §7.12 y §14 |
| **M-4** — secuencia del publisher | ✅ Resuelto | Pasos 1–9 en `docs/epicas/epica-12-1-mejora-ux-hero.md:377-395` |
| **M-5** — nombre de servicio inexistente | ✅ Resuelto | Contratos activos usan `FrontendMediaReference` y `PublishedMediaReference`; RFC-077:306-307 |
| **M-6** — scope de reconciliación | ✅ Resuelto | Limitado a `FrontendSection` + colección `images`: §7.8 y §14 |
| **C-11** — propagación a RFC-074/RFC-077 | ✅ Resuelto | RFC-077:139-144, 306-307; RFC-074:159-161, 370; estrategia B marcada histórica/no normativa |
| **C-12** — soft-delete + recreación de `section_key` | ✅ Resuelto en diseño | Resolución por `Media.model_id`, owner con `withTrashed()` y prueba específica: §7.11 y §14 |
| **M-7** — API UUID + página | ✅ Resuelto | `PublishedMediaReference::resolvePublished(...)`: §7.8, §7.11 y §14 |
| **M-8** — mezcla de estrategias en RFC-074 | ✅ Resuelto | Estrategia A declarada vigente; estrategia B delimitada como histórica/no normativa |
| **Mn-1** — `section_id` divergente | ✅ Resuelto | Se elimina del snapshot; §7.11 explica que la identidad estable es `Media.model_id` |

## 4. Hallazgos críticos

**Ninguno.**

El lock order vigente quedó propagado: mutaciones draft sin lock de `media`; publicación con promoción en el orden `page → sections(id ASC) → media(uuid ASC)`. RFC-077 incorpora el diff `added/removed`, el merge bajo lock y el dispatch `afterCommit`.

## 5. Hallazgos medios

**Ninguno bloqueante.**

La implementación futura debe respetar literalmente la frontera `PublishedMediaReference`; esto es una condición de implementación derivada del diseño aprobado, no una deficiencia abierta del diseño.

## 6. Hallazgos menores

### Mn-1 — El comando de coherencia es deliberadamente amplio

**Estado:** **CONFIRMADO, no bloqueante.**

El `rg` normativo también encuentra texto en:

- la tabla de respuesta a auditorías de la propia épica 12.1;
- explicaciones históricas o tachadas de `docs/epicas/epica-12-administrador-contenidos-frontend.md`;
- el bloque histórico explícito de RFC-074;
- registros históricos de §18.

La revisión manual de todas las coincidencias confirmó que no son instrucciones activas contradictorias. La corrección v10 es suficiente para el gate, pero el criterio sería más fiable si en P4-A se automatizara sobre bloques normativos extraídos explícitamente, en lugar de exigir cero coincidencias textuales en todo el árbol.

## 7. Riesgos de seguridad y consistencia

| Riesgo | Estado en el diseño v10 | Control que debe conservar P4-A |
| --- | --- | --- |
| Draft servido públicamente | ✅ Cerrado | Disco privado, policy real, 404 uniforme y render solo `promoted` |
| UUID malformado llega a PostgreSQL | ✅ Cerrado | Validación antes de la query en la frontera compartida |
| Carrera publisher/job | ✅ Cerrado en diseño | Locks `page → sections → media`, predicado atómico y pruebas concurrentes |
| Soft-delete rompe media publicada | ✅ Cerrado en diseño | Resolución por `Media.model_id` y propietarios con `withTrashed()` |
| Renderer duplica autorización | ✅ Cerrado en diseño | Única vía `PublishedMediaReference::resolvePublished()` |
| Reconciliación toca media ajena | ✅ Cerrado | Scope `FrontendSection/images` |
| Borrado físico accidental | ✅ Cerrado | Sin `forceDelete`, `singleFile`, `onlyKeepLatest` ni eliminación física en v1 |

## 8. Riesgos de implementación y mantenimiento

1. El código actual aún no implementa `PublishedMediaReference`; P4-A debe crear la API y evitar queries ad-hoc en el renderer.
2. La suite ejecutada en esta reauditoría valida el baseline existente, no los nuevos escenarios de promoción, soft-delete y concurrencia. Esos escenarios son DoD de P4-A.
3. `FrontendService.image` continúa fuera del pipeline de promoción de 12.1; no debe recibir accidentalmente el lock/reconciliación de `FrontendSection.images`.
4. El bloque histórico de RFC-074 debe permanecer claramente no normativo para no reabrir la estrategia B durante implementación.

## 9. Recomendaciones obligatorias para P4-A

No quedan correcciones obligatorias de diseño para abrir P4-A. La implementación debe cumplir, como mínimo:

- usar `PublishedMediaReference::resolvePublished()` como única frontera de resolución pública;
- validar UUID antes de consultar PostgreSQL;
- respetar el lock order completo en publisher y job;
- conservar snapshot sin `section_id`;
- probar owner soft-deleted, recreación de `section_key`, página/colección ajena y carrera concurrente;
- mantener RFC-074 histórico fuera del contrato activo.

## 10. Recomendaciones opcionales

- Reemplazar el `rg` amplio por un check que ignore automáticamente bloques históricos y tablas de auditoría.
- Registrar en observabilidad la revisión autorizante sin almacenar contenido editorial ni datos sensibles.
- Mantener `report-unreferenced` como observabilidad; no convertirlo en borrado físico.

## 11. Checklist de entrada a P4-A

- [x] C-11 cerrado en RFC-074 y RFC-077.
- [x] M-7 cerrado con API concreta.
- [x] M-8 cerrado y estrategia B marcada histórica/no normativa.
- [x] `section_id` retirado del snapshot.
- [x] Lock order único documentado.
- [x] Resolución por `Media.model_id`, no por `keyBy('section_key')`.
- [x] `withTrashed()` limitado a resolver propietarios publicados, sin relajar pertenencia a página/colección.
- [x] Fallbacks, estados, no-borrado físico y pruebas requeridas permanecen en el DoD.
- [x] Verificación independiente ejecutada contra PostgreSQL real, Pint y build.

## 12. Decisión explícita del gate

> **GATE DE DISEÑO 12.1: APROBADO**

La documentación v10 queda habilitada para P4-A. El siguiente gate debe ser de implementación y deberá demostrar en el sistema real los escenarios de seguridad, media, fallback y concurrencia definidos en el DoD; no se consideran cubiertos solo por la aprobación de este documento.
