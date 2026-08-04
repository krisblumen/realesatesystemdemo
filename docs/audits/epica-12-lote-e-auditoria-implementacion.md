# Auditoría de implementación — Épica 12, Lote E

- **Proyecto:** New Hauz — CMS inmobiliario
- **Lote auditado:** E — Contenido editable de páginas institucionales (RFC-075)
- **Rama:** `feature/epica-12-content-manager`
- **Commit auditado:** `90168b6` — `fix(epica-12): C-E4/M-E4 — short-circuit de media y registry en el inventario`
- **Fecha:** 2026-07-23
- **Auditor:** Codex, auditor de implementación independiente
- **Diseño:** `GATE DE DISEÑO: APROBADO` en `docs/audits/epica-12-reauditoria-diseno.md`
- **Última reauditoría:** `90168b6`; se verificó el nuevo commit contra PostgreSQL real.

## 1. Veredicto

## **APROBADO — reauditoría vigente de `90168b6`**

El commit `90168b6` cerró C-E4 y M-E4: el schema ahora corta antes de cualquier consulta de elegibilidad cuando el payload es inválido, y `generated_from_ids` reaplica el registry canónico.

La ejecución independiente confirmó los cierres en PostgreSQL real, suite completa, write path, publisher, snapshot, registry y regresiones públicas. La evidencia vigente está en la sección **“Reauditoría final del Lote E — commit `90168b6`”** al final de este documento.

## 2. Evidencia verificada en código real

### 2.1 Verificaciones base obligatorias

Ejecutadas en la rama indicada, usando PostgreSQL real (`DB_DATABASE=inmo_test`):

| Verificación | Resultado |
| --- | --- |
| `composer validate --strict` | **OK**; `composer.json is valid` |
| `composer install --no-interaction --prefer-dist` | **OK**; lock instalable, nada por instalar/actualizar/eliminar; autoload generado |
| `DB_DATABASE=inmo_test php artisan migrate:fresh --env=testing --force --seed` | **OK**; migraciones y seed de páginas/secciones limpios |
| Tests focales E (`FrontendPageAccessTest`, `FrontendPageEngineTest`, `FrontendPagePublishConcurrencyTest`, `FrontendPageContractTest`, `FrontendPagePublishUiTest`) | **24 passed, 115 assertions** |
| `DB_DATABASE=inmo_test php artisan test --no-coverage` | **767 passed, 3073 assertions**, `duration_ms=394693` |
| `./vendor/bin/pint --test` | **OK** |
| `npm run build` | **OK**; solo warnings no bloqueantes de Tailwind/Browserslist |
| `git diff --check` | **OK** |

### 2.2 Migración, PostgreSQL y archivos protegidos

`migrate:fresh --seed` completó contra `inmo_test` sin errores y creó las cinco páginas canónicas y sus secciones.

La consulta real de índices confirmó los constraints parciales de secciones:

```text
frontend_sections_page_section_key_active_unique
  ON frontend_sections (frontend_page_id, section_key)
  WHERE (deleted_at IS NULL)
frontend_sections_page_sort_order_active_unique
  ON frontend_sections (frontend_page_id, sort_order)
  WHERE (deleted_at IS NULL)
```

El diff desde el gate del Lote D (`47727a4..628e1b3`) no toca migraciones ni modelos protegidos de `User`, `Property`, `Project`, `Zone`, `Media` o `ServiceType`. `git diff --check` no reportó errores.

### 2.3 Schema por tipo y write path, ejecutados en vivo

Se invocó `FrontendSectionSchema` directamente contra la aplicación conectada a `inmo_test`:

```json
{
  "audience_missing_audience_items": ["El campo «audience_outcomes.audience_items» es obligatorio."],
  "audience_missing_result_items": ["El campo «audience_outcomes.result.items» es obligatorio."],
  "feature_missing_items": ["El campo «feature_sequence.items» es obligatorio."],
  "feature_no_media_alt": ["El campo «feature_sequence.items[0].media_id» es obligatorio."],
  "feature_missing_layout": [],
  "team_media_no_alt": ["«team.members[0]» tiene una imagen sin texto alternativo; agrega «alt» o marca «decorative»."],
  "gallery_media_no_alt": ["«gallery.items[0]» tiene una imagen sin texto alternativo; agrega «alt» o marca «decorative»."],
  "audience_valid": [],
  "gallery_valid": []
}
```

El caso `feature_missing_layout` devuelve `[]` para este payload:

```json
{
  "items": [
    {"title": "A", "media_id": "u", "alt": "A"}
  ]
}
```

La misma frontera de persistencia rechazó en vivo un `audience_outcomes` con `result.items` ausente: la excepción fue `ValidationException`, el payload quedó sin cambios y `draft_revision` no se incrementó.

### 2.4 Registry y publicación

Se insertó una fila temporal `home/rogue_live/hero` directamente en PostgreSQL y se publicó con un owner temporal. El resultado fue:

```json
{
  "write_path": {
    "rejected": true,
    "payload_unchanged": true,
    "revision_unchanged": true
  },
  "rogue_in_snapshot": false
}
```

Esto confirma que el cierre anterior de **C-E2** es real: `FrontendPageContentService` y `FrontendPagePublisher` sí invocan la frontera canónica, y la fila no canónica no llega al snapshot. La fila y el owner temporales fueron eliminados al finalizar la prueba.

### 2.5 Forma real del snapshot

La publicación real produjo:

```json
{
  "snapshot_top_keys": ["seo", "sections", "is_enabled"],
  "snapshot_section_keys": ["type", "payload", "sort_order", "section_key"],
  "has_generated_from_ids": false,
  "has_section_is_enabled": false
}
```

La implementación actual construye cada entrada en `app/Services/Frontend/FrontendPagePublisher.php:79-84` sin `is_enabled`; además, omite las secciones deshabilitadas antes de construir el arreglo (`:53-56`). La épica define en `docs/epicas/epica-12-administrador-contenidos-frontend.md:384` y `:714` un snapshot completo con `sections[*].is_enabled` y `generated_from_ids`.

### 2.6 HTTP y DOM de regresión

Se levantó temporalmente el servidor en `127.0.0.1:8001`. Las rutas públicas existentes respondieron:

| Ruta | HTTP | Título |
| --- | --- | --- |
| `/` | `200 OK` | `New Hauz · Real Estate en Querétaro` |
| `/nosotros` | `200 OK` | `Nosotros · New Hauz` |
| `/servicios` | `200 OK` | `Servicios · New Hauz` |
| `/inversionistas` | `200 OK` | `Inversionistas · New Hauz · New Hauz` |
| `/contacto` | `200 OK` | `Contacto · New Hauz` |

El parseo DOM real de `/inversionistas` produjo `{h1: 1, main: 1, scripts: 2, html_errors: false}`. No aparecieron `Whoops`, `Server Error`, `Rogue`, `draft-only`, `services_home` ni `Contenido de páginas` en las respuestas. El cutover de las cinco vistas públicas sigue correctamente diferido al Lote F.

`php artisan route:list --path=admin/frontend` mostró las cinco rutas esperadas del módulo.

## 3. Hallazgos críticos

### C-E1-R3 — `feature_sequence.layout` queda opcional

- **Estado:** **CONFIRMADO por ejecución real**.
- **Evidencia:** `app/Services/Frontend/FrontendSectionSchema.php:47-50` declara `layout` como `?layout`. La ejecución directa de `validate('feature_sequence', ...)` aceptó un item con `media_id` y `alt`, pero sin `layout`.
- **Contrato afectado:** `docs/rfc/RFC-075-CONTENIDO-PAGINAS-INSTITUCIONALES-FRONTEND.md:195` define `feature_sequence` como `items:[{eyebrow,title,body,media_id,alt,layout}]` y exige `layout` dentro de la allowlist; la épica exige schemas cerrados por tipo.
- **Impacto:** el Lote F puede recibir paneles sin variante de layout. Un renderer que dependa de esa variante debe inventar un default, o puede producir una salida no determinista. Esto contradice el contrato cerrado por tipo que el lote acaba de declarar implementado.
- **Corrección obligatoria:** hacer `layout` requerido con el token `layout` (no `?layout`), probar rechazo en schema y write path cuando falte, y mantener pruebas positiva/negativa contra los tres valores allowlisted.

## 4. Hallazgos medios

### M-E3 — Snapshot publicado incompleto respecto de la épica

- **Estado:** **CONFIRMADO por SQL/ejecución y lectura directa del código**.
- **Evidencia:** `app/Services/Frontend/FrontendPagePublisher.php:53-56` descarta secciones deshabilitadas y `:79-84` serializa solo `section_key`, `type`, `sort_order` y `payload`. El snapshot real no tiene `sections[*].is_enabled` ni `generated_from_ids`.
- **Contrato afectado:** `docs/epicas/epica-12-administrador-contenidos-frontend.md:384` y `:714` describen `published_revision` como estado publicable completo, incluyendo `sections[*].is_enabled` y `generated_from_ids`.
- **Impacto:** los consumidores posteriores no pueden distinguir, desde el snapshot, entre una sección ausente y una sección explícitamente deshabilitada; tampoco reciben el inventario de entidades dinámicas que el contrato promete. La implementación actual puede funcionar para el renderer mínimo, pero no cumple la forma estable declarada.
- **Corrección obligatoria:** alinear implementación y contrato antes del Lote F: incluir `is_enabled` por sección y `generated_from_ids` según el diseño, o modificar explícitamente la épica/RFC si se decidió retirar esos campos. Centralizar la forma en un DTO/normalizador compartido y agregar asserts de forma exacta.

## 5. Hallazgos menores

### Mn-E3 — La cobertura nueva no prueba los dos residuos encontrados

`tests/Feature/Frontend/FrontendPageContractTest.php:75-90` prueba listas y media, pero no prueba que falte `feature_sequence.items[*].layout`; `:186-199` solo verifica las claves superiores del snapshot, no `sections[*].is_enabled` ni `generated_from_ids`. Esto permitió que los dos huecos sobrevivieran a la suite.

### Mn-E4 — Divergencia nominal del campo de sección

La épica usa `key` en el ejemplo compacto de `:384`, mientras que el contrato operativo usa `section_key` en `:714` y `:533`, y la implementación serializa `section_key`. No rompe la ejecución actual, pero debe dejarse una sola nomenclatura normativa para evitar consumidores que esperen `key`.

## 6. Reconciliación de hallazgos anteriores

| Hallazgo anterior | Resultado de esta reauditoría |
| --- | --- |
| C-E1 — schema abierto | **RESUELTO**; los casos de claves desconocidas, tipos incorrectos y cardinalidad se rechazan |
| C-E1-R — compuestos requeridos y reglas de hero | **RESUELTO**; `result`, `media_id`, `sort_order` y reglas hero de `alt/decorative` se ejecutan |
| C-E1-R2 — huecos en `audience_outcomes`, `feature_sequence`, `team`, `gallery` | **RESUELTO en lo observado**; listas requeridas y regla universal media/alt están activas |
| C-E2 — registry no aplicado | **RESUELTO**; fila no canónica excluida de edición/publicación en vivo |
| C-E3 — lectura de draft en `page(key)` | **RESUELTO** en el alcance probado; el render conserva snapshot ante cambios draft |
| M-E1 — revisión UI stale | **RESUELTO** por `FrontendPagePublishUiTest` |
| M-E2 — policy permitía create/delete | **RESUELTO**; la policy niega esos caminos |
| Mn-E1 — `type` de 40 vs 30 | **RESUELTO**; migración actual usa 30 |
| Mn-E2 — comentarios obsoletos | **RESUELTO** en los archivos revisados |
| **C-E1-R3** — `feature_sequence.layout` opcional | **ABIERTO; bloquea el gate** |
| **M-E3** — snapshot sin forma completa | **ABIERTO; bloquea el gate** |

## 7. Regresiones detectadas

- **No se detectaron regresiones funcionales en la suite:** 767/767 tests pasan sobre PostgreSQL.
- **No se detectaron regresiones de contratos protegidos:** el diff desde `47727a4` no toca User, Property, Project, Zone, Media ni ServiceType.
- Las cinco rutas públicas existentes siguen en `200 OK`, con títulos correctos y sin errores HTML; el DOM de `/inversionistas` conserva un `h1` y un `main`.
- El cutover de render público sigue fuera del Lote E, conforme a `docs/epicas/epica-12-administrador-contenidos-frontend.md:1555`; no se reporta como falla que las vistas aún no consuman el CMS.

## 8. Riesgos de seguridad

- **Integridad de contenido:** el registry ya es fail-closed en los caminos probados; la fila `rogue_live` no llegó al snapshot.
- **HTML/JS:** el schema mantiene rechazo de `<`/`>` y la validación de CTA seguro; no se observó HTML ejecutable en las respuestas públicas.
- **Media:** las referencias siguen pasando por `FrontendMediaReference`; la reauditoría no observó borrado físico ni regresión en colecciones protegidas.
- **Riesgo pendiente:** un `feature_sequence` sin layout no es, por sí solo, un bypass de autorización, pero deja al futuro renderer una decisión implícita sobre una variante visual. Si el renderer usa un fallback inseguro o no determinista, el problema se convierte en integridad y mantenimiento del contenido.
- **Snapshot incompleto:** no expone draft en la prueba actual, pero la forma incompleta fuerza a los consumidores del Lote F a reconstruir estado fuera del snapshot, debilitando la garantía de publicación atómica.

## 9. Riesgos de mantenimiento

- El contrato de snapshot no está centralizado entre publisher, read model y documentación; actualmente el publisher no materializa todos los campos normativos.
- La prueba de contrato verifica presencia de claves superiores, pero no la forma profunda de cada sección.
- La divergencia `key`/`section_key` puede generar adaptadores incompatibles entre lotes.
- La no exigencia de `layout` permite payloads que el Lote F deberá normalizar si el hallazgo no se corrige ahora.

## 10. Tests faltantes

1. Rechazo de `feature_sequence.items[*]` sin `layout` en schema y `FrontendPageContentService`.
2. Aceptación de cada layout allowlisted y rechazo de cualquier variante desconocida.
3. Assert exacto de `published_revision.sections[*]`, incluyendo `is_enabled`, `section_key`/`key` según el contrato final, `type`, `sort_order` y `payload`.
4. Assert de `generated_from_ids` si el campo permanece normativo.
5. Publicación con una sección deshabilitada: verificar la representación que el contrato estable define, no solo que se omita silenciosamente.
6. Prueba de consumidor que lea el snapshot sin consultar columnas draft.

## 11. Correcciones obligatorias

- [ ] **C-E1-R3:** hacer obligatorio `feature_sequence.items[*].layout` y probarlo en schema + write path + publish.
- [ ] **M-E3:** cerrar la forma normativa del snapshot: incluir `sections[*].is_enabled` y `generated_from_ids`, o corregir explícitamente la épica/RFC antes de implementar F. No dejar la divergencia implícita.
- [ ] Añadir las pruebas de forma profunda indicadas en la sección 10 y repetir la suite focal, suite completa, migración, Pint y build.

## 12. Correcciones recomendadas

- Centralizar el snapshot en una clase/DTO o normalizador único consumido por publisher y renderer.
- Mantener un test de drift que compare el seed de páginas con `config/frontend-sections.php`.
- Convertir la matriz de payloads por tipo en tests data-driven para que cada campo obligatorio quede cubierto.
- Resolver en un solo documento la nomenclatura `key` vs `section_key` y propagarla a RFC, épica, tests y código.

## 13. Decisión explícita del gate

**GATE LOTE E: RECHAZADO.**

El Lote F queda cerrado. Claude debe corregir únicamente C-E1-R3 y M-E3 en el Lote E; Codex debe repetir esta auditoría con evidencia nueva. No se habilita el siguiente lote por la suite verde mientras el snapshot y el schema sigan divergentes del contrato escrito.

---

# Reauditoría posterior — sin cambios de implementación

**Fecha:** 2026-07-23 · **HEAD:** `628e1b3` · **Resultado:** el código auditado es idéntico a la reauditoría anterior.

## Evidencia nueva de ejecución

La rama no contiene commits posteriores a `628e1b3` (`git log 628e1b3..HEAD` no produjo salida). La única modificación local adicional ajena a producción es `.atl/skill-registry.md`; el informe de auditoría es el único archivo de esta auditoría que se actualiza. No se encontraron cambios nuevos en `FrontendSectionSchema`, `FrontendPagePublisher`, sus tests ni en los RFC/épica que cerraran los dos hallazgos pendientes.

Se repitieron todas las verificaciones obligatorias:

| Verificación | Resultado actual |
| --- | --- |
| `composer validate --strict` + `composer install --no-interaction --prefer-dist` | **OK**; lock sincronizado, nada por instalar/actualizar/eliminar |
| `DB_DATABASE=inmo_test php artisan migrate:fresh --env=testing --force --seed` | **OK** en PostgreSQL real |
| Tests focales del Lote E | **26 passed, 126 assertions** |
| `DB_DATABASE=inmo_test php artisan test --no-coverage` | **767 passed, 3073 assertions**, `duration_ms=490998` |
| `./vendor/bin/pint --test` | **OK** |
| `npm run build` | **OK**; warnings no bloqueantes de Tailwind/Browserslist |
| `git diff --check` | **OK** |

## Reproducción actual de los bloqueantes

El schema continúa aceptando el panel sin layout:

```json
{
  "feature_missing_layout": [],
  "feature_invalid_layout": [
    "El layout «carousel_3d» de «feature_sequence.items[0].layout» no está permitido."
  ],
  "feature_valid_layout": [],
  "audience_missing_result_items": [
    "El campo «audience_outcomes.result.items» es obligatorio."
  ],
  "team_media_no_alt": [
    "«team.members[0]» tiene una imagen sin texto alternativo; agrega «alt» o marca «decorative»."
  ],
  "gallery_media_no_alt": [
    "«gallery.items[0]» tiene una imagen sin texto alternativo; agrega «alt» o marca «decorative»."
  ]
}
```

La prueba directa del write path sigue rechazando el `audience_outcomes` incompleto sin persistirlo ni incrementar `draft_revision`:

```json
{
  "rejected": true,
  "payload_unchanged": true,
  "revision_unchanged": true
}
```

La forma del snapshot publicado sigue siendo:

```json
{
  "snapshot_top_keys": ["seo", "sections", "is_enabled"],
  "snapshot_section_keys": ["type", "payload", "sort_order", "section_key"],
  "has_generated_from_ids": false,
  "has_section_is_enabled": false,
  "rogue_in_snapshot": false
}
```

Las cinco rutas públicas volvieron a responder `200 OK`; el parseo DOM de cada una conservó `h1=1`, `main=1` y `html_errors=false`. No se observaron regresiones en los contratos protegidos.

## Decisión de esta reauditoría

No existe evidencia de que las correcciones declaradas estén presentes en la rama auditada. Los dos hallazgos anteriores permanecen confirmados y obligatorios:

- **C-E1-R3 abierto:** `feature_sequence.items[*].layout` sigue declarado `?layout`.
- **M-E3 abierto:** el snapshot sigue sin `sections[*].is_enabled` y `generated_from_ids`.

**GATE LOTE E: RECHAZADO.**

No se habilita el Lote F. La próxima reauditoría requiere un commit o cambios verificables que modifiquen esas dos conductas, no únicamente nuevos tests o una actualización del reporte.

---

# Reauditoría del Lote E — commit `671b382`

**Fecha:** 2026-07-23 · **Resultado:** C-E1-R3 y M-E3 quedan cerrados en la conducta observada, pero la implementación introduce dos bloqueantes nuevos.

## Evidencia base ejecutada

| Verificación | Resultado |
| --- | --- |
| `composer validate --strict` + `composer install --no-interaction --prefer-dist` | **OK**; lock sincronizado, nada por instalar/actualizar/eliminar |
| `DB_DATABASE=inmo_test php artisan migrate:fresh --env=testing --force --seed` | **OK** en PostgreSQL real |
| Tests focales del Lote E | **29 passed, 138 assertions** |
| `DB_DATABASE=inmo_test php artisan test --no-coverage` | **770 passed, 3085 assertions**, `duration_ms=423753` |
| `./vendor/bin/pint --test` | **OK** |
| `npm run build` | **OK**; warnings no bloqueantes de Tailwind/Browserslist |
| `git diff --check` | **OK** |
| `php artisan route:list --path=admin/frontend` | **5 rutas** del módulo |

No se tocaron modelos ni migraciones protegidos de `User`, `Property`, `Project`, `Zone`, `Media` o `ServiceType` desde el gate del Lote D.

## Cierres confirmados

- **C-E1-R3:** `FrontendSectionSchema.php:50` ahora usa `layout` requerido. En vivo, un panel sin layout devuelve error; las tres variantes allowlisted pasan.
- **M-E3:** el snapshot real conserva secciones deshabilitadas con `is_enabled=false`, incluye `generated_from_ids` y unifica `section_key`.
- El write path rechaza la composición incompleta de `audience_outcomes` sin modificar payload ni `draft_revision`.
- Las cinco rutas públicas siguen en `200 OK`; el DOM conserva `h1=1`, `main=1` y no presenta errores HTML.

## 3. Hallazgos críticos nuevos

### C-E4 — El write/publish path todavía consulta media aunque el schema ya tenga errores

- **Estado:** **CONFIRMADO por ejecución real**.
- **Evidencia:** `app/Services/Frontend/FrontendPageContentService.php:121-129` y `:161-169` ejecutan `FrontendMediaReference::isEligible()` antes de comprobar `$errors !== []`. `app/Services/Frontend/FrontendPagePublisher.php:69-79` repite el mismo orden.
- **Prueba directa:** el schema aislado rechaza `media_id="not-a-uuid"`, pero `updateSectionPayload()` produce `Illuminate\\Database\\QueryException` con PostgreSQL `SQLSTATE[22P02]` al consultar la columna UUID. Resultado adicional: payload y `draft_revision` permanecen sin cambios, pero la solicitud falla como excepción de base de datos, no como `ValidationException` controlada.

```json
{
  "exception": {
    "class": "Illuminate\\Database\\QueryException",
    "sqlstate": "22P02",
    "detail": "invalid input syntax for type uuid: not-a-uuid"
  },
  "payload_unchanged": true,
  "revision_unchanged": true
}
```

- **Impacto:** un payload de media malformado puede generar 500 en vez de una respuesta de validación fail-closed. El comentario del commit afirma que el schema rechaza la referencia antes de tocar la base, pero el write path no respeta esa frontera.
- **Corrección obligatoria:** si el schema devuelve errores, no ejecutar ninguna consulta de elegibilidad; aplicar el mismo short-circuit en `updateSectionPayload`, `saveSectionDraft` y `publish`. Agregar pruebas de write y publish con UUID malformado que exijan `ValidationException`, cero queries a `media` y cero cambios de payload/revisión.

## 4. Hallazgos medios nuevos

### M-E4 — `generated_from_ids` acepta una sección dinámica fuera del registry

- **Estado:** **CONFIRMADO por ejecución real**.
- **Evidencia:** `FrontendPagePublisher.php:121-138` calcula `generatedFromIds($sections)` sobre todas las filas bloqueadas y no vuelve a comprobar `isCanonicalSection()`. La frontera de `:60` excluye la fila no canónica de `published_revision.sections`, pero no del inventario dinámico.
- **Prueba directa:** se insertó `home/rogue_dynamic/featured_properties` por SQL y se publicó. Resultado:

```json
{
  "rogue_in_sections": false,
  "rogue_in_generated_from_ids": true,
  "disabled_partner_present": true,
  "disabled_partner_flag": false,
  "has_generated_from_ids": true,
  "dynamic_keys": [
    "featured_properties",
    "opportunity_properties",
    "featured_projects",
    "rogue_dynamic"
  ],
  "section_shape": ["type", "payload", "is_enabled", "sort_order", "section_key"]
}
```

- **Impacto:** el registry no es completamente fail-closed: una fila no canónica puede contaminar metadata del snapshot aunque no aparezca como sección renderizable. El Lote F o cualquier consumidor que confíe en `generated_from_ids` puede procesar una clave que el registry rechazó.
- **Corrección obligatoria:** construir `generated_from_ids` únicamente con la colección canónica filtrada, o revalidar `page/key/type` dentro de `generatedFromIds()`. Agregar una prueba directa con sección dinámica rogue que exija ausencia tanto en `sections` como en `generated_from_ids`.

## Reconciliación actualizada

| Hallazgo | Estado en esta reauditoría |
| --- | --- |
| C-E1-R3 — `layout` opcional | **RESUELTO** y probado en schema/write path |
| M-E3 — snapshot incompleto | **RESUELTO** y probado con sección deshabilitada + inventario dinámico |
| **C-E4 — schema inválido aún llega a query UUID** | **ABIERTO; bloquea el gate** |
| **M-E4 — registry contamina `generated_from_ids`** | **ABIERTO; bloquea el gate** |

## Riesgos y tests faltantes

- El riesgo inmediato es un 500 por entrada editorial malformada, aunque no persista datos.
- El inventario dinámico debe tratarse como parte del boundary canónico, no como metadata de confianza.
- Faltan pruebas de short-circuit en los tres caminos (`updateSectionPayload`, `saveSectionDraft`, `publish`) y de exclusión de rogue dinámico del inventario.

## Correcciones obligatorias

- [ ] **C-E4:** no ejecutar elegibilidad de media cuando el schema ya tiene errores; devolver `ValidationException` controlada.
- [ ] **M-E4:** filtrar `generated_from_ids` por registry canónico antes de serializarlo.
- [ ] Repetir migración, tests focales, suite completa, Pint, build y probes de PostgreSQL después de corregir.

## Decisión de esta reauditoría

**GATE LOTE E: RECHAZADO.**

C-E1-R3 y M-E3 ya no bloquean. El Lote F sigue cerrado por C-E4 y M-E4 hasta que ambos comportamientos sean corregidos y demostrados en ejecución real.

---

# Reauditoría final del Lote E — commit `90168b6`

**Fecha:** 2026-07-23 · **Resultado:** C-E4 y M-E4 quedan cerrados en ejecución real. No quedan correcciones obligatorias para el Lote E.

## Evidencia base ejecutada

| Verificación | Resultado |
| --- | --- |
| `composer validate --strict` + `composer install --no-interaction --prefer-dist` | **OK**; lock sincronizado, nada por instalar/actualizar/eliminar |
| `DB_DATABASE=inmo_test php artisan migrate:fresh --env=testing --force --seed` | **OK** en PostgreSQL real |
| Tests focales del Lote E | **31 passed, 142 assertions** |
| `DB_DATABASE=inmo_test php artisan test --no-coverage` | **772 passed, 3089 assertions**, `duration_ms=351522` |
| `./vendor/bin/pint --test` | **OK** |
| `npm run build` | **OK**; warnings no bloqueantes de Tailwind/Browserslist |
| `git diff --check` | **OK** |
| `php artisan route:list --path=admin/frontend` | **5 rutas** del módulo |

No se detectaron cambios en modelos o migraciones protegidos de `User`, `Property`, `Project`, `Zone`, `Media` o `ServiceType` desde el gate del Lote D.

## Verificación funcional en vivo

### Schema y short-circuit de media

El schema rechazó un `feature_sequence` sin `layout` y un `gallery` con UUID inválido; la variante `split_media_end` pasó. Al invocar el write path con `media_id="not-a-uuid"`, el resultado fue:

```json
{
  "exception": {
    "class": "Illuminate\\Validation\\ValidationException",
    "is_validation": true,
    "sqlstate": null
  },
  "payload_unchanged": true,
  "revision_unchanged": true
}
```

La misma prueba sobre `FrontendPagePublisher` también devolvió `ValidationException`, restauró el payload y no produjo `QueryException` PostgreSQL.

### Registry y snapshot

Se insertó directamente `home/rogue_dynamic/featured_properties`, se publicó y se restauró la base al finalizar. El resultado fue:

```json
{
  "rogue_in_sections": false,
  "rogue_in_generated_from_ids": false,
  "disabled_partner_present": true,
  "disabled_partner_flag": false,
  "dynamic_keys": [
    "featured_properties",
    "opportunity_properties",
    "featured_projects"
  ],
  "section_shape": ["type", "payload", "is_enabled", "sort_order", "section_key"]
}
```

Esto confirma que el snapshot conserva una sección canónica deshabilitada, registra `is_enabled` por sección y no permite que una fila dinámica rogue contamine ni `sections` ni `generated_from_ids`.

### HTTP y DOM

Las cinco rutas públicas respondieron `200 OK` con sus títulos esperados. El parseo DOM real devolvió `h1=1`, `main=1` y `html_errors=false` para `/`, `/nosotros`, `/servicios`, `/inversionistas` y `/contacto`. No se observaron `Whoops`, `Server Error` ni datos de las sondas.

## Reconciliación final

| Hallazgo | Estado |
| --- | --- |
| C-E1 — schema abierto | **RESUELTO** |
| C-E1-R / C-E1-R2 — compuestos, media y accesibilidad | **RESUELTO** |
| C-E1-R3 — `feature_sequence.layout` requerido | **RESUELTO** |
| C-E2 — registry aplicado a edición/publicación | **RESUELTO** |
| C-E3 — aislamiento draft/published | **RESUELTO** |
| C-E4 — media inválida sin crash | **RESUELTO** |
| M-E1 / M-E2 — UI stale y policy de creación | **RESUELTO** |
| M-E3 — snapshot completo | **RESUELTO** |
| M-E4 — registry aplicado a `generated_from_ids` | **RESUELTO** |
| Mn-E1 / Mn-E2 / Mn-E4 — DDL, comentarios y nomenclatura | **RESUELTO** |

## Observaciones no bloqueantes

- Mantener pruebas de contrato profundo para cada nueva sección que se agregue al registry.
- Conservar el test de drift entre `config/frontend-sections.php`, seed y snapshot.
- En el Lote F deberá verificarse que el renderer omita las secciones cuyo `is_enabled` sea `false`, ya que ahora viajan explícitamente en el snapshot.

## Decisión final del gate

**GATE LOTE E: APROBADO.**

El Lote F queda habilitado.
