# Auditoría de diseño — Épica 11 Ayuda / Manual del CMS

## Evidencia verificada en código real

Artefactos SDD leídos, en orden:

- `openspec/changes/epica-11-ayuda-cms/proposal.md`
- `openspec/changes/epica-11-ayuda-cms/specs/help-manual/spec.md`
- `openspec/changes/epica-11-ayuda-cms/design.md`
- `openspec/changes/epica-11-ayuda-cms/tasks.md`

Código real / terminal verificados:

- `app/Filament/Pages/Ayuda.php` — registro de página, registry, `visibleSections()`, `currentSection()`, `Str::markdown()`.
- `resources/views/filament/pages/ayuda.blade.php` — render del índice y HTML convertido.
- `app/Providers/Filament/AdminPanelProvider.php` — `discoverPages()` y grupos de navegación reales.
- Resources y páginas usadas como gates: `PropertyResource`, `LeadResource`, `ZoneResource`, `PropertyOwnerResource`, `ProjectResource`, `ContratoIntermediacionResource`, `LonaBatchResource`, `LonaRequestResource`, `LonaEvidenceResource`, `FeatureResource`, `ProjectTypeResource`, `ServiceTypeResource`, `UserResource`, `AgentDashboard`, `AgentLonas`.
- Policies reales: `PropertyPolicy`, `LeadPolicy`, `FeaturePolicy`, `LonaBatchPolicy`, `LonaRequestPolicy`, `ContratoIntermediacionPolicy`.
- `database/seeders/PermissionSeeder.php` — matriz rol → permiso real.
- `tests/Feature/Filament/AyudaPageTest.php` — cobertura propuesta/actual de matriz, bypass por `?seccion=` y archivo faltante.
- `package.json`, `composer.lock`, `resources/css/filament/admin/tailwind.config.js` — dependencias Markdown/Tailwind.

Comandos ejecutados:

```bash
php artisan route:list --path=ayuda
./vendor/bin/pint --test app/Filament/Pages/Ayuda.php tests/Feature/Filament/AyudaPageTest.php
DB_DATABASE=inmo_test php artisan test --filter=AyudaPageTest
DB_DATABASE=inmo_test php artisan test
DB_DATABASE=inmo_test php artisan test /tmp/NavOrderSmokeTest.php --env=testing --debug
```

Resultados relevantes:

```text
GET|HEAD admin/ayuda filament.admin.pages.ayuda › App\Filament\Pages\…
{"tool":"pint","result":"passed"}
{"tool":"phpunit","result":"passed","tests":14,"passed":14,"assertions":72,"duration_ms":1896}
{"tool":"phpunit","result":"passed","tests":491,"passed":491,"assertions":1821,"duration_ms":415207}
```

Smoke test temporal de orden real del menú renderizado:

```json
NAV_POS={"Panel":[28105],"Operación":[28372,55382,...],"Lonas":[31502,32055,...],"Configuración":[33275,...],"Seguridad":[35063,...],"Ayuda":[444,26195,55253]}
SNIP_55249="...<span ...> Ayuda </span> ... </ul></li> <li x-data=\"{ label: 'Operación' }\" data-group-label=\"Operación\"..."
```

Interpretación: en el sidebar renderizado, el ítem real de navegación `Ayuda` aparece antes del grupo `Operación`, no al fondo después de `Seguridad`.

---

## 1. Veredicto

**APROBADO CON CORRECCIONES.**

El núcleo de seguridad/autorización del diseño está bien: delega en `Resource::canViewAny()` / `Page::canAccess()` reales, no duplica roles, y el acceso directo por `?seccion=` se resuelve contra `visibleSections()` ya filtrado. Markdown también está correctamente acotado: archivo desde registry, no desde query string; `html_input=escape`; `allow_unsafe_links=false`; placeholder sin path en archivo faltante.

No lo apruebo limpio porque hay dos correcciones de diseño/testabilidad antes de cerrar P1: la promesa de ubicación “al fondo del nav” es falsa con Filament real si la página queda sin grupo, y el test de archivo faltante está diseñado de forma frágil porque depende de que una sección real siga sin `.md`, mientras el mismo plan exige crear ese archivo después.

---

## 2. Hallazgos críticos (C-n)

No detecté hallazgos críticos.

### Controles críticos que sí están bien

- **CONFIRMADO — Gate real, sin duplicar roles.** `design.md:19-48` exige llamar `Resource::canViewAny()` o `Page::canAccess()`. El código real de recursos confirma heterogeneidad: Policy (`PropertyResource.php:444-447`), permiso nombrado (`ZoneResource.php:321-324`, `UserResource.php:265-268`), rol inline (`ProjectTypeResource.php:101-104`, `ServiceTypeResource.php:100-103`). La estrategia es correcta.
- **CONFIRMADO — Re-check servidor para `?seccion=`.** `design.md:63-69` especifica resolver solo desde `visibleSections()`. La implementación actual lo hace en `Ayuda.php:77-95`.
- **CONFIRMADO — No path traversal desde query string.** `Ayuda.php:83-90` compara `seccion` contra keys visibles y pasa a `renderMarkdown()` solo el `file` del registry. El path se arma con `resource_path("help/{$file}.md")` en `Ayuda.php:98-104`; `file` no viene del usuario.
- **CONFIRMADO — Markdown seguro.** `design.md:315-319` exige escape de HTML. `Ayuda.php:108-111` usa `Str::markdown(..., ['html_input' => 'escape', 'allow_unsafe_links' => false])`.

---

## 3. Hallazgos medios (M-n)

### M-1 — CONFIRMADO — “Sin grupo + navigationSort=99” no coloca Ayuda al fondo del sidebar

- **Evidencia de diseño:**
  - `proposal.md:77-80` propone Ayuda “sin grupo, al fondo de la nav”.
  - `spec.md:13-17` exige “no navigation group” y “placed at the bottom of the sidebar”.
  - `design.md:10` y `design.md:239-240` afirman que `navigationSort = 99` + sin grupo empuja Ayuda abajo.
- **Evidencia de código real:**
  - `AdminPanelProvider.php:81-97` usa `discoverPages()` y define grupos explícitos: `Operación`, `Lonas`, `Configuración`, `Seguridad`; no hay grupo Ayuda.
  - `Ayuda.php:33-36` define página sin grupo y `navigationSort = 99`.
- **Evidencia de ejecución:** smoke test renderizando `/admin/ayuda` como owner mostró el item de sidebar `Ayuda` inmediatamente antes del grupo `Operación` (`SNIP_55249`), no después de `Seguridad`.
- **Por qué es problema:** el diseño promete una UX verificable (“al fondo”) que la configuración real de Filament no cumple. Si se implementa tal cual, los tests o el smoke manual pueden pasar por `assertSee('Ayuda')`, pero la ubicación final queda mal.
- **Corrección puntual:** decidir una de dos, explícitamente:
  1. Si “al fondo” importa más que “sin grupo”: crear grupo `Ayuda` y agregarlo al final de `navigationGroups()` después de `Seguridad`.
  2. Si “sin grupo” importa más: cambiar spec/diseño/tests para aceptar la posición real de Filament para páginas sin grupo, sin prometer “al fondo”.

### M-2 — CONFIRMADO — El test de “archivo faltante” queda frágil contra el propio plan de contenido

- **Evidencia de diseño/tasks:**
  - `tasks.md:99-102` pide probar archivo faltante con una sección cuyo `.md` esté ausente o un escenario test-only.
  - `tasks.md:142` luego exige crear `resources/help/zonas.md`.
  - `spec.md:84-90` exige que cada sección tenga su `.md` no vacío al delivery.
- **Evidencia de implementación actual:** `AyudaPageTest.php:130-136` usa `?seccion=zonas` para esperar el placeholder de archivo faltante. Hoy pasa porque `find resources/help` muestra solo `resources/help/inmuebles.md`; pero cuando Work Unit 4 cree `zonas.md`, el test dejará de probar “missing file” y probablemente fallará.
- **Por qué es problema:** el diseño invita a una prueba que depende del estado incompleto del repo. Eso es test frágil, no contrato estable. Justo lo que queremos evitar: asserts que pasan por accidente durante una etapa y se rompen cuando se completa el DoD.
- **Corrección puntual:** especificar que el caso missing-file se pruebe sin depender de una sección real incompleta. Opciones aceptables:
  - Mockear `File::exists()` para devolver `false` sobre un file del registry.
  - Extraer el renderizador a método/clase testeable y probar `renderMarkdown('archivo-inexistente')` sin atravesar una sección real.
  - Inyectar un registry test-only controlado. Si se elige esto, `sectionRegistry()` no debería ser `private static`, porque `design.md:203` lo hace difícil de reemplazar en test.

---

## 4. Hallazgos menores (Mn-n)

### Mn-1 — CONFIRMADO — Conteo y alcance de secciones inconsistente entre spec, design y tasks

- **Evidencia:**
  - `spec.md:51-54` lista 13 secciones resource-backed para admin/owner.
  - `spec.md:95-96` dice “12 total”.
  - `design.md:264-266` dice “17 sections total = 2 always-visible + 13 resources + 2 agent pages”.
  - `tasks.md:64-74` también habla de 17 entries.
- **Por qué es problema:** no rompe la arquitectura, pero sí confunde al implementador y a QA. En una épica de manual, el conteo ES el contrato de cobertura.
- **Corrección puntual:** normalizar todos los artefactos a una sola frase: “17 entradas de ayuda: 2 generales, 13 resource-backed, 2 páginas de agente. Admin/owner ven las 13 resource-backed + generales; agente ve las permitidas por resources + Mi Zona/Mis Lonas + generales”.

### Mn-2 — CONFIRMADO — Falta exigir un assertion del aviso suave en bypass por `?seccion=`

- **Evidencia:**
  - `design.md:290-293` muestra el mensaje “Esa sección no está disponible para tu cuenta”.
  - `tasks.md:114-116` pide wirear el soft notice.
  - `AyudaPageTest.php:120-128` solo valida `assertDontSee('Manual de Usuarios')` y que el índice muestra Inmuebles; no exige ver el aviso.
- **Por qué es problema:** la seguridad está cubierta por el `assertDontSee`, pero la UX especificada puede desaparecer sin que el test falle.
- **Corrección puntual:** agregar `assertSee('Esa sección no está disponible para tu cuenta.')` al test de sección prohibida.

---

## 5. Sobreingeniería detectada

No veo sobreingeniería grave.

- El registry de 17 secciones parece grande, pero está justificado porque los gates son heterogéneos y el manual debe ser role-aware.
- No hay DB, búsqueda, UI editable ni dependencia nueva de Markdown: bien acotado para v1.
- El único costo relevante es redactar 17 Markdown. Está acotado en `tasks.md:123-157` como trabajo paralelo de contenido, no mezclado con lógica.

---

## 6. Riesgos de implementación

1. **Orden de navegación** — confirmado como riesgo real: el diseño no produce “al fondo” si Ayuda queda sin grupo.
2. **Drift del registry al agregar Resources nuevos** — reconocido en `design.md:441-442`. Aceptable para v1, pero conviene crear un test de cobertura cuando el panel siga creciendo.
3. **Tests falsos positivos por labels globales** — el diseño lo conoce y el test actual mejora usando `seccion=<key>` (`AyudaPageTest.php:155-168`). Bien.
4. **Content completeness** — hoy solo existe `resources/help/inmuebles.md`; no es falla de diseño si Work Unit 4 está pendiente, pero el DoD debe bloquear merge hasta tener los 17 `.md` no vacíos.

---

## 7. Riesgos de seguridad

### IDOR / acceso directo por `?seccion=`

**Estado:** bien diseñado.

- `design.md:63-69` especifica re-check cerrado por defecto.
- `Ayuda.php:77-95` resuelve solo desde `visibleSections()`.
- Test actual cubre agente pidiendo `?seccion=usuarios` (`AyudaPageTest.php:120-128`).

### Markdown / XSS / links inseguros

**Estado:** bien diseñado.

- `design.md:315-319` exige `html_input=escape`.
- `Ayuda.php:108-111` aplica `html_input=escape` y `allow_unsafe_links=false`.
- `composer.lock` confirma `league/commonmark` vía Laravel, sin dependencia nueva.

### Path traversal

**Estado:** bien diseñado.

- El query param es una key; no se concatena directo al filesystem.
- `renderMarkdown()` recibe solo `file` del registry visible.

---

## 8. Recomendaciones obligatorias

1. Corregir la decisión de navegación: o grupo `Ayuda` al final, o cambiar el contrato para no prometer “al fondo”.
2. Rediseñar el test de archivo faltante para que no dependa de que falte `zonas.md` u otra sección real que el DoD exige crear.
3. Normalizar el conteo de secciones en `spec.md`, `design.md` y `tasks.md`.

---

## 9. Recomendaciones opcionales

1. Agregar un test futuro que compare Resources descubiertos por Filament contra keys del registry, marcado como protección anti-drift.
2. Agregar `assertSee` del mensaje “Esa sección no está disponible…” en el test de bypass por `?seccion=`.
3. Documentar en el manual de deploy que cambios de copy Markdown no requieren migraciones ni seeders; solo deploy + build si cambia CSS.

---

## 10. Evaluación de decisiones cerradas en la Épica 11

- **Sin búsqueda:** bien justificado. Para v1, índice + deep links alcanza; evita complejidad de UX y búsqueda client/server.
- **Sin DB:** bien justificado. El contenido es documentación versionada, no dato operativo.
- **Sin UI editable:** bien justificado. Evita permisos, auditoría y sanitización extra; contenido git-only es suficiente para manual interno.
- **Sin backfill a listas:** bien justificado. El manual profundo y los `SectionHeader` cortos resuelven problemas distintos.
- **Sin capa `lang/`:** consistente con el proyecto; copy inline en español México.
- **Typography plugin:** aceptable porque ya existe en `package.json`; no introduce dependencia nueva.
- **Registry en `Ayuda.php`:** aceptable para v1, pero ajustar testabilidad del missing-file si se mantiene `private static`.

Ningún no-goal requiere reabrirse por hallazgo técnico real.

---

## 11. Preguntas abiertas

1. ¿Preferimos “Ayuda sin grupo” aunque quede antes de los grupos, o “Ayuda al fondo” aunque sea en un grupo propio?

---

## 12. Checklist de corrección para el implementador

- [ ] Alinear navegación: cambiar spec/diseño o implementar grupo `Ayuda` al final.
- [ ] Actualizar tests para verificar el orden real elegido del menú.
- [ ] Rehacer el test de missing-file sin depender de una sección real ausente.
- [ ] Alinear conteo: 17 entradas totales; explicar cuáles ve cada rol.
- [ ] Agregar assert del aviso “sección no disponible”.
- [ ] Antes de merge: crear los 17 `resources/help/*.md` no vacíos.
- [ ] Antes de merge: `./vendor/bin/pint --test` limpio.
- [ ] Antes de merge: `DB_DATABASE=inmo_test php artisan test` verde.
