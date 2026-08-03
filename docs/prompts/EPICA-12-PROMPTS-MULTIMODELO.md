# Épica 12 — Administrador de Contenidos del Frontend — Prompts Multimodelo

**Proyecto:** New Hauz — Plataforma Inmobiliaria Monolítica
**Épica actual:** Épica 12 — Administrador de Contenidos del Frontend
**RFCs cubiertos:** RFC-071 → RFC-077
**Stack:** Laravel 13.x + PHP 8.3 · PostgreSQL + PostGIS · Filament v3 · Livewire · Blade · Tailwind v4 frontend público · Tailwind v3 tema Filament · Spatie Permission · Spatie Media Library
**Roster:** Claude = arquitecto + codificador · Codex (modelo Sol) = auditor independiente
**Rama base:** `develop`
**Rama de trabajo:** `feature/epica-12-content-manager`

---

## 0. Cómo usar este documento

Este documento orquesta la Épica 12 con dos modelos:

| Modelo | Rol | Responsabilidad |
| --- | --- | --- |
| Claude | Arquitecto + Codificador | Diseña técnicamente la épica, corrige hallazgos y codifica por lotes. |
| Codex | Auditor independiente | Audita diseño e implementación contra código real, terminal, BD y frontend público. |

### Regla de avance — gate obligatorio

La Épica 12 se ejecuta como una cadena de **implementación → auditoría → corrección → reauditoría**. No existe una única auditoría general al final.

1. Claude consolida el diseño.
2. Codex audita el diseño contra el código real.
3. Claude corrige todos los hallazgos bloqueantes.
4. Codex reaudita y emite el gate de diseño.
5. Claude implementa **un solo lote**.
6. Codex audita ese lote ejecutando sistema, BD y frontend real.
7. Si el veredicto no es `APROBADO`, Claude corrige el mismo lote y Codex lo reaudita.
8. Sólo con veredicto `APROBADO` se habilita el siguiente lote.
9. Tras aprobar A→G, Codex realiza una auditoría de integración y el cierre técnico.

> **REGLA INNEGOCIABLE:** Claude no puede comenzar el lote N+1 mientras la auditoría del lote N no esté efectuada, documentada y aprobada. `APROBADO CON OBSERVACIONES` no abre el gate: las observaciones deben resolverse o reclasificarse explícitamente como deuda no bloqueante y el auditor debe emitir `APROBADO`.

Codex no reimplementa ni corrige código. Claude no se autoaprueba. Una suite verde reportada por Claude no sustituye la ejecución independiente de Codex.

---

## 1. Bloque común para todos los prompts

```text
═══════════════════════════════════════════════════════════════
PROYECTO: New Hauz — Plataforma Inmobiliaria (monolito Laravel)
ÉPICA EN CURSO: Épica 12 — Administrador de Contenidos del Frontend
RAMA BASE: develop   ·   RAMA DE TRABAJO: feature/epica-12-content-manager
───────────────────────────────────────────────────────────────
DOCUMENTOS BASE:
  · docs/epicas/epica-12-administrador-contenidos-frontend.md
  · docs/rfc/RFC-071-PERFIL-PUBLICO-FRONTEND.md
  · docs/rfc/RFC-072-TEMA-VISUAL-FRONTEND.md
  · docs/rfc/RFC-073-NAVEGACION-FOOTER-CTAS-FRONTEND.md
  · docs/rfc/RFC-074-SERVICIOS-OFRECIDOS-FRONTEND.md
  · docs/rfc/RFC-075-CONTENIDO-PAGINAS-INSTITUCIONALES-FRONTEND.md
  · docs/rfc/RFC-076-RENDER-CACHE-FALLBACKS-FRONTEND.md
  · docs/rfc/RFC-077-PREVIEW-PUBLICACION-QA-FRONTEND.md
───────────────────────────────────────────────────────────────
CONTRATOS PREVIOS QUE ESTA ÉPICA CONSUME (verificar en código):
  · Épica 2 / RFC-006 / RFC-012 — roles y permisos con spatie/laravel-permission.
  · RFC-007 — Media Library para logos, favicons, OG images e imágenes editoriales.
  · Épica 4 / RFC-019 — Property y Project ya alimentan secciones públicas existentes.
  · Épica 5 — Leads y ServiceType ya existen; ServiceType.active controla opciones del formulario público.
  · Épica 11 — Manual/Ayuda del CMS ya existe y puede documentar el nuevo módulo después.
───────────────────────────────────────────────────────────────
REGLAS DE ORO:
  · Owner-only real: no basta ocultar menú; debe haber policy/gate y 403 real.
  · Todo cambio es aditivo. No modificar migraciones existentes de User, Property, Project, Media, Zone ni ServiceType salvo migraciones nuevas/aditivas.
  · El frontend público debe conservar fallbacks actuales si falta configuración.
  · No page builder libre en v1.
  · No HTML/CSS/JS libre editable por usuarios.
  · Servicios ofrecidos deben vincular contenido público con ServiceType para evitar drift marketing/leads.
  · Personalización visual se aplica por tokens/variables runtime, no por rebuild de Tailwind.
  · UI/copy del producto en español México/tuteo.
═══════════════════════════════════════════════════════════════
```

---

## 2. Flujo de prompts y gates

```text
P0   ─ Codex  → Revisión integral pre-diseño    → docs/audits/epica-12-revision-pre-diseno.md
P1   ─ Claude → Diseño técnico consolidado      → docs/epicas/epica-12-administrador-contenidos-frontend.md actualizado
P2   ─ Codex  → Auditoría de diseño             → docs/audits/epica-12-auditoria-diseno.md
P3   ─ Claude → Corrección de diseño            → docs/epicas + docs/rfc actualizados
P3R  ─ Codex  → Reauditoría y gate de diseño    → docs/audits/epica-12-reauditoria-diseno.md

P4-A ─ Claude → Implementación lote A           → código + tests del lote A
P5-A ─ Codex  → Auditoría lote A                → docs/audits/epica-12-lote-a-auditoria-implementacion.md
       ↳ si falla: Claude corrige A → Codex reaudita A; NO iniciar B
P4-B ─ Claude → Implementación lote B           → habilitada sólo tras A APROBADO
P5-B ─ Codex  → Auditoría lote B                → docs/audits/epica-12-lote-b-auditoria-implementacion.md
       ↳ repetir el mismo gate hasta el lote G
P4-G ─ Claude → Implementación lote G           → habilitada sólo tras F APROBADO
P5-G ─ Codex  → Auditoría lote G                → docs/audits/epica-12-lote-g-auditoria-implementacion.md

P6   ─ Codex  → Auditoría integral de integración → docs/audits/epica-12-auditoria-integracion.md
P7   ─ Codex  → Cierre técnico                    → docs/cierres/epica-12-cierre-tecnico.md
```

### Matriz de gates

| Gate | Evidencia requerida | Condición para avanzar |
| --- | --- | --- |
| Diseño | Auditoría + reauditoría contra código real | `APROBADO` por Codex |
| Lote A | Tests focales, migración y autorización owner-only | Auditoría A `APROBADO` |
| Lote B | Tests focales, CSS generado y verificación visual | Auditoría B `APROBADO` |
| Lote C | HTTP/DOM para navegación, footer y URLs seguras | Auditoría C `APROBADO` |
| Lote D | BD, HTTP y POST manipulado para servicios/leads | Auditoría D `APROBADO` |
| Lote E | Render, validación y seguridad de contenido estructurado | Auditoría E `APROBADO` |
| Lote F | Caché, invalidación, fallbacks y regresiones públicas | Auditoría F `APROBADO` |
| Lote G | Preview, publicación, aislamiento draft y QA visual | Auditoría G `APROBADO` |
| Integración | Suite completa, build, Pint y recorrido E2E | Auditoría integral `APROBADO` |

---

# PROMPT 0 — Codex — Revisión integral pre-diseño

```text
[INCRUSTAR BLOQUE COMÚN]

ROL: Sos Codex (modelo Sol) como revisor arquitectónico independiente. Antes de que Claude produzca el diseño técnico, revisás el documento general, RFC-071→077 y los contratos reales del repositorio.

OBJETIVO:
  Detectar contratos transversales abiertos, contradicciones, dependencias omitidas y decisiones que volverían inestable el diseño técnico.

FOCO:
  · Autorización owner-only y permisos previos que no deben alterarse silenciosamente.
  · Semántica draft/publicado, singleton, concurrencia y eliminación.
  · ServiceType, leads, backfill productivo y servicios deshabilitados.
  · Media, SEO, accesibilidad, caché, fallbacks y migración desde hardcode.
  · Orden de lotes y auditabilidad independiente.

ENTREGABLE:
  Crear docs/audits/epica-12-revision-pre-diseno.md con evidencia real, bloqueantes, decisiones abiertas y gate para P1.

REGLA:
  Esta revisión no aprueba el diseño — todavía no existe—, pero define qué contratos P1 debe cerrar obligatoriamente.
```

---

# PROMPT 1 — Claude — Diseño técnico consolidado

```text
[INCRUSTAR BLOQUE COMÚN]

ROL: Sos Claude actuando como arquitecto senior de New Hauz. Tu tarea es consolidar el diseño técnico de la Épica 12 antes de implementación. No escribas código de producción todavía.

ENTRADA:
  · docs/epicas/epica-12-administrador-contenidos-frontend.md
  · RFC-071 → RFC-077
  · docs/audits/epica-12-revision-pre-diseno.md
  · Código real del frontend público, Filament, ServiceType, LeadCaptureForm, Media Library y PermissionSeeder.

OBJETIVO:
  Producir/actualizar el diseño técnico implementable de la Épica 12, manteniendo consistencia entre los RFCs y el código real.

VERIFICACIÓN OBLIGATORIA EN CÓDIGO:
  · routes/web.php
  · resources/views/components/layouts/public.blade.php
  · resources/views/welcome.blade.php
  · resources/views/site/*.blade.php
  · resources/views/leads/create.blade.php
  · app/Models/ServiceType.php
  · app/Livewire/Leads/LeadCaptureForm.php
  · app/Filament/Resources/ServiceTypeResource.php
  · database/seeders/PermissionSeeder.php
  · database/seeders/ServiceTypeSeeder.php
  · package.json y resources/css/app.css para Tailwind v4.

ENTREGABLE:
  Actualiza docs/epicas/epica-12-administrador-contenidos-frontend.md con diseño técnico consolidado que incluya:
  1. Modelo de datos final.
  2. Policies/permisos owner-only.
  3. Servicios de frontend y contratos de datos para Blade.
  4. Estrategia de Media Library.
  5. Estrategia de tema visual runtime.
  6. Estrategia ServiceType + FrontendService.
  7. Fallbacks exactos.
  8. Caché e invalidación.
  9. Preview/publicación.
  10. Lotes de implementación A→G.
  11. Matriz de tests.
  12. Cierre explícito de B-2→B-6 y de todas las decisiones abiertas de la revisión pre-diseño.

REGLAS:
  · No reabras decisiones cerradas sin evidencia técnica real.
  · Si encontrás conflicto entre RFCs, documentalo y proponé resolución.
  · No propongas page builder libre.
  · No metas admin al nuevo módulo.

DoD:
  · Documento actualizado.
  · Diseño coherente RFC-071→077.
  · Lista de archivos a crear/modificar.
  · Matriz de riesgos y tests.
```

---

# PROMPT 2 — Codex — Auditoría de diseño

```text
[INCRUSTAR BLOQUE COMÚN]

ROL: Sos Codex como auditor de diseño independiente y escéptico. No diseñaste la épica. Auditás el diseño contra el código real. No reimplementás.

ENTRADA:
  · docs/epicas/epica-12-administrador-contenidos-frontend.md
  · docs/rfc/RFC-071-*.md → RFC-077-*.md
  · docs/audits/epica-12-revision-pre-diseno.md
  · Código real del repositorio.

QUÉ VERIFICAR CONTRA CÓDIGO REAL:
  1. Owner-only real: ¿el diseño garantiza 403 para admin/agente/arquitectura/proyectos?
  2. Aditividad: ¿no modifica migraciones existentes de User/Property/Project/Media/Zone/ServiceType salvo cambios aditivos?
  3. Tailwind v4: ¿tema runtime por variables, sin rebuild ni clases dinámicas inseguras?
  4. Seguridad CSS: ¿sin CSS libre, sin url()/var()/funciones inyectables desde CMS?
  5. Servicios: ¿ServiceType queda como fuente operativa? ¿se corrige drift de Inversión inmobiliaria?
  6. Leads: ¿servicio inactivo o no permitido falla por server-side aunque manipulen POST?
  7. Markdown/HTML: ¿no hay HTML libre? Si hay Markdown, ¿escapea HTML y bloquea links inseguros?
  8. Media Library: ¿logos/imágenes validan MIME/tamaño y alt text?
  9. Fallbacks: ¿el sitio no se rompe sin configuración?
  10. Caché: ¿hay invalidación explícita al guardar?
  11. Preview/publicación: ¿drafts no filtran a rutas públicas?
  12. Tests: ¿la matriz prueba comportamiento real, no asserts débiles?
  13. Sobreingeniería: ¿hay tablas/servicios innecesarios?

ENTREGABLE:
  Crear docs/audits/epica-12-auditoria-diseno.md con:
  1. Veredicto: APROBADO / APROBADO CON CORRECCIONES / RECHAZADO.
  2. Evidencia verificada en código real.
  3. Hallazgos críticos.
  4. Hallazgos medios.
  5. Hallazgos menores.
  6. Riesgos de seguridad.
  7. Riesgos de mantenimiento.
  8. Sobreingeniería detectada.
  9. Recomendaciones obligatorias.
  10. Recomendaciones opcionales.
  11. Checklist para Claude.

Cada hallazgo debe citar archivo/sección, impacto y corrección puntual.
```

---

# PROMPT 3 — Claude — Corrección de diseño

```text
[INCRUSTAR BLOQUE COMÚN]

ROL: Sos Claude retomando como arquitecto. Tu tarea es corregir el diseño según la auditoría de Codex. No implementes código todavía.

ENTRADA:
  · docs/epicas/epica-12-administrador-contenidos-frontend.md
  · docs/rfc/RFC-071-*.md → RFC-077-*.md
  · docs/audits/epica-12-auditoria-diseno.md

TAREA:
  1. Reconciliar cada hallazgo de Codex.
  2. Marcarlo como resuelto o diferido con justificación.
  3. Actualizar épica/RFCs afectados.
  4. Agregar tabla “Cambios aplicados desde auditoría de diseño”.
  5. Dejar la implementación por lotes lista para P4.

REGLAS:
  · No ignores hallazgos críticos.
  · No cambies alcance sin documentar tradeoff.
  · Mantener owner-only.
  · Mantener ServiceType como fuente operativa.
  · Mantener fallbacks.

DoD:
  · Diseño corregido.
  · Auditoría reconciliada.
  · Cambios listos para reauditoría P3R.
  · La implementación sigue BLOQUEADA hasta que Codex emita APROBADO en P3R.
```

---

# PROMPT 3R — Codex — Reauditoría y gate de diseño

```text
[INCRUSTAR BLOQUE COMÚN]

ROL: Sos Codex (modelo Sol), auditor independiente. Reauditás exclusivamente el cierre de los hallazgos de diseño. No implementás ni completás decisiones faltantes por tu cuenta.

ENTRADA:
  · Diseño y RFCs corregidos.
  · docs/audits/epica-12-auditoria-diseno.md.
  · Tabla de reconciliación producida por Claude.

TAREA:
  1. Verificar cada corrección contra el documento y el código real.
  2. Confirmar que no se introdujeron contradicciones entre RFC-071→077.
  3. Emitir un único veredicto: APROBADO o RECHAZADO.
  4. Si es RECHAZADO, enumerar los bloqueantes restantes y mantener P4-A cerrado.

ENTREGABLE:
  Crear docs/audits/epica-12-reauditoria-diseno.md con matriz hallazgo→evidencia→estado y decisión explícita del gate.

REGLA:
  Sólo el texto “GATE DE DISEÑO: APROBADO” habilita P4-A.
```

---

# PROMPT 4 — Claude — Implementación de UN lote

```text
[INCRUSTAR BLOQUE COMÚN]

ROL: Sos Claude como codificador senior Laravel/Filament. Implementás UN solo lote de la Épica 12. No implementes ni adelantes código de lotes posteriores.

ENTRADA:
  · Diseño corregido de Épica 12.
  · RFC-071 → RFC-077.
  · Auditoría y reauditoría de diseño con gate APROBADO.
  · Identificador del lote actual: [A|B|C|D|E|F|G].
  · Auditoría APROBADA del lote anterior, salvo que el lote actual sea A.

LOTES OBLIGATORIOS:
  Lote A — Base owner-only + FrontendSetting + permisos + Media Library.
  Lote B — Tema visual runtime + validación contraste + CSS variables.
  Lote C — Navegación/footer/CTAs seguros.
  Lote D — FrontendService vinculado a ServiceType + reconciliación Inversión.
  Lote E — FrontendPage/FrontendSection + páginas institucionales estructuradas.
  Lote F — Render centralizado + caché + fallbacks.
  Lote G — Preview/publicación + QA + cierre de tests.

REGLAS:
  · Verificá antes de escribir que el gate anterior está APROBADO. Si no lo está, DETENETE.
  · El alcance de esta ejecución termina en el lote indicado.
  · Strict TDD: tests antes o junto con cada comportamiento.
  · No modificar migraciones existentes.
  · No meter HTML/CSS/JS libre.
  · No abrir acceso a admin al nuevo módulo.
  · No romper leads existentes.
  · No romper Property/Project frontend.
  · Ejecutar Pint y suite.

VERIFICACIÓN DEL LOTE OBLIGATORIA:
  · Tests focales del lote sobre DB_DATABASE=inmo_test y PostgreSQL real.
  · Suite completa hasta el estado acumulado actual.
  · ./vendor/bin/pint --test
  · npm run build si el lote afecta assets o vistas.

ENTREGABLE:
  · Código implementado.
  · Tests verdes.
  · Documentar en el RFC/épica cualquier decisión menor tomada durante implementación.
  · Registrar archivos cambiados y evidencia para la auditoría del lote.
  · Declarar explícitamente: “Siguiente lote BLOQUEADO hasta auditoría Codex”.
```

---

# PROMPT 5 — Codex — Auditoría de UN lote de implementación

```text
[INCRUSTAR BLOQUE COMÚN]

ROL: Sos Codex (modelo Sol) como auditor de implementación independiente. Auditás únicamente el lote indicado, considerando también sus regresiones sobre lotes ya aprobados. Tu valor está en ejecutar el sistema real, no en leer el diff.

ENTRADA:
  · Rama feature/epica-12-content-manager
  · docs/epicas/epica-12-administrador-contenidos-frontend.md
  · RFC-071 → RFC-077
  · docs/audits/epica-12-auditoria-diseno.md
  · docs/audits/epica-12-reauditoria-diseno.md
  · Identificador del lote auditado: [A|B|C|D|E|F|G].
  · Auditorías aprobadas de lotes anteriores.

VERIFICACIÓN BASE EN VIVO OBLIGATORIA EN CADA LOTE:
  1. composer validate --strict y composer.lock en sync.
  2. DB_DATABASE=inmo_test php artisan migrate:fresh --env=testing --force --seed corre limpio en PostgreSQL real.
  3. Tests focales del lote y suite acumulada verdes.
  4. ./vendor/bin/pint --test limpio.
  5. npm run build verde cuando el lote afecte frontend/assets.
  6. Verificación HTTP/BD/DOM/visual específica del lote; no aceptar sólo lectura de tests.

VERIFICACIÓN FUNCIONAL ACUMULADA:
  · Lote A: owner accede; admin/agente/arquitectura/proyectos reciben 403 real; migración limpia; Media Library y fallbacks de perfil.
  · Lote B: tokens válidos se aplican; colores/valores inseguros fallan; contraste y CSS final verificados.
  · Lote C: desktop/móvil comparten fuente; rutas allowlisted; `javascript:`/`data:` y destinos no permitidos fallan.
  · Lote D: activar/desactivar afecta home, servicios y lead form; POST manipulado falla; Inversión queda reconciliada.
  · Lote E: páginas consumen secciones estructuradas; tipos/payloads inválidos fallan; no existe HTML ejecutable.
  · Lote F: caché se invalida; fallback funciona sin filas/configuración y ante contenido inválido; Property/Project/Leads no regresan.
  · Lote G: preview sólo owner; drafts no filtran; publicación es consistente, auditable e invalida caché; QA visual responsive.

ENTREGABLE:
  Crear docs/audits/epica-12-lote-[a-g]-auditoria-implementacion.md con:
  1. Veredicto: APROBADO / RECHAZADO.
  2. Evidencia real: comandos, SQL si aplica, HTTP, DOM/capturas si aplica.
  3. Hallazgos críticos.
  4. Hallazgos medios.
  5. Hallazgos menores.
  6. Regresiones.
  7. Riesgos de seguridad.
  8. Riesgos de mantenimiento.
  9. Tests faltantes.
  10. Correcciones obligatorias.
  11. Correcciones recomendadas.
  12. Decisión explícita del gate para el siguiente lote.

REGLA DE GATE:
  · Si hay correcciones obligatorias, el veredicto es RECHAZADO.
  · Claude corrige únicamente el lote rechazado.
  · Codex repite esta auditoría y actualiza el documento con nueva evidencia.
  · No se inicia el siguiente lote hasta que el documento diga “GATE LOTE [X]: APROBADO”.
```

---

# PROMPT 6 — Codex — Auditoría integral de integración

```text
[INCRUSTAR BLOQUE COMÚN]

ROL: Sos Codex (modelo Sol), auditor independiente de integración. Los lotes A→G ya deben estar individualmente APROBADOS. Esta auditoría no reemplaza ninguna auditoría de lote.

PRECONDICIÓN:
  · Existen siete auditorías de implementación con GATE APROBADO.
  · Si falta una, DETENETE y reportá qué gate continúa cerrado.

TAREA EN VIVO:
  1. composer validate --strict.
  2. DB_DATABASE=inmo_test php artisan migrate:fresh --env=testing --force --seed.
  3. DB_DATABASE=inmo_test php artisan test.
  4. ./vendor/bin/pint --test.
  5. npm run build.
  6. Recorrido E2E owner: editar borrador → preview → publicar → observar frontend y caché.
  7. Recorrido E2E de servicios: activar/desactivar → navegación/contenido/lead form/POST.
  8. Aislamiento por rol y URL directa.
  9. Fallback sin configuración y regresiones Property, Project, Leads y Media Library.
  10. QA visual responsive y revisión de DOM/headers relevantes.

ENTREGABLE:
  Crear docs/audits/epica-12-auditoria-integracion.md con evidencia real, hallazgos, regresiones y veredicto APROBADO/RECHAZADO.

REGLA:
  El cierre técnico P7 sólo queda habilitado con “GATE DE INTEGRACIÓN: APROBADO”.
```

---

# PROMPT 7 — Codex — Cierre técnico

```text
[INCRUSTAR BLOQUE COMÚN]

ROL: Sos Codex cerrando técnicamente la Épica 12 tras implementación y auditorías. No reimplementás: reconciliás, verificás estado final y documentás contratos estables.

ENTRADA:
  · docs/epicas/epica-12-administrador-contenidos-frontend.md
  · docs/rfc/RFC-071-*.md → RFC-077-*.md
  · docs/audits/epica-12-auditoria-diseno.md
  · docs/audits/epica-12-reauditoria-diseno.md
  · docs/audits/epica-12-lote-a-auditoria-implementacion.md → lote-g
  · docs/audits/epica-12-auditoria-integracion.md

TAREA:
  1. Reconciliar hallazgos de la revisión pre-diseño, auditorías de diseño, siete auditorías de lote y auditoría de integración: resuelto o diferido justificado.
  2. Verificar estado final con comandos reales:
     · DB_DATABASE=inmo_test php artisan test
     · ./vendor/bin/pint --test
     · npm run build
     · composer validate --strict
  3. Documentar contratos estables:
     · Permiso/policy owner-only.
     · Modelos y servicios frontend.
     · Media collections.
     · Semántica ServiceType + FrontendService.
     · Fallbacks.
     · Cache keys e invalidación.
     · Preview/publicación.
  4. Marcar Épica 12 como implementada si corresponde.

ENTREGABLE:
  Crear docs/cierres/epica-12-cierre-tecnico.md con:
  · Veredicto final.
  · Tabla de hallazgos resueltos/diferidos.
  · Resultado de verificación.
  · Contratos estables.
  · Deuda explícita.
  · Checklist final de merge.

REGLA:
  Si encontrás un hallazgo nuevo real, no lo parches a escondidas: documentalo con severidad y destino.
  No cierres la épica si falta cualquier gate de diseño, lote o integración.
```

---

## 3. Estado actual de documentación

| Artefacto | Estado |
| --- | --- |
| Documento general Épica 12 | ✅ Documentado |
| RFC-071 | ✅ Documentado |
| RFC-072 | ✅ Documentado |
| RFC-073 | ✅ Documentado |
| RFC-074 | ✅ Documentado |
| RFC-075 | ✅ Documentado |
| RFC-076 | ✅ Documentado |
| RFC-077 | ✅ Documentado |
| Revisión integral pre-diseño | ✅ Completada — bloqueantes documentados |
| Auditoría de diseño | ⏳ Pendiente |
| Reauditoría/gate de diseño | ⏳ Pendiente |
| Implementación lotes A→G | ⏳ Pendiente |
| Auditorías por lote A→G | ⏳ Pendiente |
| Auditoría integral de integración | ⏳ Pendiente |
| Cierre técnico | ⏳ Pendiente |
