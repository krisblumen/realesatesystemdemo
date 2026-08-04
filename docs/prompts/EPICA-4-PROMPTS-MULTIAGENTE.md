# Épica 4 — Inmuebles — Documento de Prompts Multiagente

**Proyecto:** New Hauz — Plataforma Inmobiliaria Monolítica **Épica actual:** Épica 4 — Núcleo Inmobiliario (Inmuebles) **RFCs cubiertos:** RFC-019 → RFC-024 **Stack:** Laravel 13.x \+ PHP 8.3 · PostgreSQL 18 \+ PostGIS 3.6 · Filament v3 · Livewire 3 · Tailwind CSS · `spatie/laravel-medialibrary` · `spatie/laravel-permission` **Arquitecto responsable:** Kristian **QA:** Sebastián **Revisión de arquitectura:** Edgar **Rama base:** `develop` **Rama de trabajo:** `feature/epica-4-inmuebles`

---

## 0\. Cómo usar este documento

Este documento orquesta la Épica 4 con tres agentes que trabajan en cadena. Cada prompt es **autónomo y copiable**: incluye su propio bloque de encabezado con la referencia del proyecto y el seguimiento de la fase anterior, de modo que el agente que lo reciba tenga el contexto completo sin depender de la conversación previa.

### Roster de agentes

| Agente | Rol | Responsabilidad |
| :---- | :---- | :---- |
| **Claude** | Arquitecto / Diseño e Implementación de correcciones | Genera el diseño técnico de la épica y aplica las correcciones de la auditoría de diseño |
| **Gemini CLI** | Auditor | Audita diseño e implementación; emite veredicto y hallazgos; persiste evidencia en `docs/audits/` y en engram |
| **Codex** | Programación | Implementa el código por lotes incrementales A→E siguiendo el diseño aprobado |

### Flujo de la cadena

```
P1 ─ Claude        →  Diseño técnico          →  docs/epicas/epica-4-inmuebles.md
P2 ─ Gemini CLI    →  Auditoría de diseño     →  docs/audits/epica-4-auditoria-diseno.md  + engram
P3 ─ Claude        →  Corrección de diseño    →  docs/epicas/epica-4-inmuebles.md (actualizado)
P4 ─ Codex         →  Implementación por lotes →  código fuente + tests
P5 ─ Gemini CLI    →  Auditoría de implementación → docs/audits/epica-4-auditoria-implementacion.md + engram
```

**Regla de avance:** ningún prompt se ejecuta hasta que el anterior cierra su Definition of Done. La auditoría de diseño (P2) puede arrojar `Aprobado con observaciones`, en cuyo caso P3 es obligatorio antes de P4.

---

## 1\. Bloque de encabezado de referencia (común a todos los prompts)

Este bloque se incrusta al inicio de **cada** prompt. Da al agente la trazabilidad del proyecto y el estado de las fases previas para que sus decisiones respeten los contratos ya cerrados.

```
═══════════════════════════════════════════════════════════════
PROYECTO: New Hauz — Plataforma Inmobiliaria (monolito Laravel)
ÉPICA EN CURSO: Épica 4 — Inmuebles (RFC-019 → RFC-024)
RAMA BASE: develop   ·   RAMA DE TRABAJO: feature/epica-4-inmuebles
ARQUITECTO: Kristian   ·   QA: Sebastián   ·   REVISIÓN: Edgar
───────────────────────────────────────────────────────────────
SEGUIMIENTO DE FASES (contratos consumidos por esta épica):

  ✅ Épica 1 — Fundación Técnica (RFC-001→RFC-010) — APROBADA
     · Laravel 13.x + PHP 8.3
     · PostgreSQL 18 + PostGIS 3.6 (extensión activa)
     · Filament v3 en /admin
     · Livewire 3 + Tailwind
     · spatie/laravel-medialibrary (tabla media operativa)
     · spatie/laravel-permission (roles owner/admin/agente)

  ✅ Épica 2 — Usuarios y Seguridad (RFC-011→RFC-014) — APROBADA
     · Modelo User extendido (SoftDeletes, status, agente)
     · Matriz de permisos cerrada; permiso properties.manage
       asignado a owner, admin y agente
     · UserPolicy como fuente única de autorización
     · Contratos DIFERIDOS en User: properties() y leads()
       (métodos con whereRaw('1=0') a activar en esta épica)

  ✅ Épica 3 — Zonas Comerciales (RFC-015→RFC-018) — APROBADA
     · Modelo Zone (name, slug, municipality, status)
     · CRUD ZoneResource en Filament
     · Asignación agente↔zona (relación N:N)
     · Polígonos PostGIS (geometry, center point, consultas espaciales)
     · Permiso zones.manage para owner y admin

───────────────────────────────────────────────────────────────
CONTRATOS QUE ESTA ÉPICA DEBE ACTIVAR / CONSUMIR:
  · User::properties() — descomentar contrato diferido de Épica 2
  · Property.zone_id  → Zone (Épica 3)
  · Property.agent_id → User con rol agente (Épica 2/3)
  · Media Library para galería (Épica 1)
  · Permiso properties.manage (Épica 2) en Policy y Resource
═══════════════════════════════════════════════════════════════
```

---

## 2\. Alcance de la Épica 4 (resumen normativo para los agentes)

| RFC | Nombre | Núcleo técnico | DoD |
| :---- | :---- | :---- | :---- |
| RFC-019 | Modelo Inmueble | Modelo `Property`, migración, relaciones con `Zone` y agente (`User`) | Modelo operativo con migración y relaciones |
| RFC-020 | Galería de Imágenes | Media Library: imagen principal obligatoria, galería múltiple, orden, eliminación | Galería funcional desde Filament |
| RFC-021 | Características Dinámicas | Atributos flexibles (alberca, jardín, roof garden, seguridad), catálogos configurables | Características persistentes y configurables |
| RFC-022 | Estados Comerciales | Máquina de estados: Borrador → Publicado → Pausado → Vendido → Rentado; solo Publicado es público | Estados funcionando y aplicados en visibilidad |
| RFC-023 | Generación de Slug | Slug automático, único, actualización controlada (`/juriquilla-casa-con-alberca`) | Slugs únicos y estables |
| RFC-024 | SEO Básico | Meta Title, Meta Description, Canonical, Open Graph por inmueble | Metadatos personalizables y expuestos |

**Entregables comprometidos:** `PropertyResource` (Filament), gestión de inmuebles, integración Media Library, SEO básico. **Cobertura QA:** QA-026 → QA-040. **Definition of Done de la épica:** catálogo inmobiliario completamente funcional, con estados respetados, galería operativa, slug y SEO listos para el frontend público (Épica 6).

---

# PROMPT 1 — Diseño Técnico de la Épica (Agente: Claude)

Objetivo: producir el documento de diseño `docs/epicas/epica-4-inmuebles.md` con el mismo rigor y estructura que el diseño aprobado de la Épica 2\.

```
[INCRUSTAR BLOQUE DE ENCABEZADO DE REFERENCIA — Sección 1]

ROL: Eres el arquitecto senior de Laravel a cargo del diseño técnico de la
Épica 4 — Inmuebles. No escribes código de producción todavía: produces el
DOCUMENTO DE DISEÑO que Codex implementará después de la auditoría.

ENTRADA:
  · RFC-019 Modelo Inmueble
  · RFC-020 Galería de Imágenes
  · RFC-021 Características Dinámicas
  · RFC-022 Estados Comerciales
  · RFC-023 Generación de Slug
  · RFC-024 SEO Básico
  · EPICA-4-INMUEBLES.md (descripción general)
  · Contratos cerrados de Épicas 1, 2 y 3 (ver encabezado)

REGLA DE ORO — NO TOCAR ÉPICAS ANTERIORES:
  Toda extensión es ADITIVA. No se modifican migraciones existentes de User,
  Zone ni media. La relación User::properties() se ACTIVA descomentando el
  contrato diferido de la Épica 2 (reemplazar el whereRaw('1=0') por el
  hasMany real a Property). Documenta ese cambio explícitamente.

ENTREGABLE: docs/epicas/epica-4-inmuebles.md con TODAS estas secciones:

  1.  Contexto y Dependencias (tabla de contratos consumidos por RFC origen
      y estado, igual que Épica 2).
  2.  Objetivos: qué entrega y qué NO entrega la épica (delimitar frontera
      con Épica 6 Frontend Público y Épica 7 Comercialización).
  3.  Alcance Funcional (tabla funcionalidad ↔ actor: owner/admin/agente).
  4.  Alcance Técnico (árbol de archivos app/ y database/ a crear/alterar).
  5.  RFC-019 — Modelo Property:
        · Decisiones de arquitectura cerradas (tabla).
        · Enums de dominio: OperationType (venta/renta),
          PropertyType (casa/departamento/terreno/local/oficina...),
          PropertyStatus (estados comerciales del RFC-022).
        · Migración create_properties_table con todos los campos del RFC-019
          (title, slug, description, operation_type, property_type, price,
          bedrooms, bathrooms, parking_spaces, land_area, construction_area,
          status) + zone_id (FK→zones, nullOnDelete) + agent_id
          (FK→users, nullOnDelete) + campos SEO (meta_title, meta_description,
          canonical_url) + timestamps + softDeletes.
        · Modelo Property: fillable, casts (enums + decimales), relaciones
          zone(), agent(), HasMedia (InteractsWithMedia), scopes
          (published, byZone, byOperation), helpers (isPublished).
        · Activación del contrato diferido User::properties().
  6.  RFC-020 — Galería de Imágenes:
        · Collections de Media Library: 'gallery' y conversión de 'cover'.
        · Regla: imagen principal OBLIGATORIA antes de publicar.
        · Orden de imágenes (order_column), eliminación, límites de tamaño
          y mime types permitidos. Conversiones (thumb, web) con Spatie.
  7.  RFC-021 — Características Dinámicas:
        · Decisión de modelado: tabla catálogo `features` + pivote
          `property_feature` (N:N) frente a JSON. Justifica la elección
          (recomendado: catálogo + pivote para filtrar en Épica 6/7).
        · Seeder de features base (alberca, jardín, roof garden, seguridad,
          elevador, etc.). CRUD de catálogo en Filament.
  8.  RFC-022 — Estados Comerciales:
        · Máquina de estados con transiciones permitidas y diagrama ASCII.
        · Regla dura: solo PUBLICADO es visible públicamente
          (scope published() consumido por Épica 6).
        · Validación: no se puede publicar sin imagen principal (cruce con
          RFC-020) ni sin zona asignada.
  9.  RFC-023 — Generación de Slug:
        · Estrategia de generación (zona + tipo + título), unicidad con
          sufijo incremental, actualización controlada (no romper URLs
          indexadas: regenerar solo bajo confirmación).
        · Índice único en slug.
  10. RFC-024 — SEO Básico:
        · Campos meta_title, meta_description, canonical, Open Graph.
        · Fallbacks automáticos (si no hay meta_title → título; si no hay
          meta_description → resumen de description).
        · Cómo lo consumirá el frontend (contrato hacia Épica 6).
  11. Modelo de Datos (esquema final de properties, features,
      property_feature, con índices y FKs).
  12. Seguridad — Mapa de controles (PropertyPolicy basada en permiso
      properties.manage; reglas: agente solo gestiona inmuebles de SUS
      zonas/asignados; owner/admin gestionan todos). Policy es fuente única
      de verdad, no la UI.
  13. PropertyResource en Filament: form (secciones: datos, ubicación/zona,
      precios, características, galería, SEO, estado), table (columnas,
      badges de estado con color, filtros por zona/operación/tipo/estado),
      acciones (publicar/pausar/marcar vendido-rentado controladas por Policy).
  14. Estrategia de Testing (Unit: enums, slug, scopes; Feature: CRUD por rol,
      transiciones de estado, publicación bloqueada sin imagen principal,
      galería, SEO fallbacks; Regresión Épicas 1/2/3). Usar RefreshDatabase
      sobre PostgreSQL de test, sin SQLite.
  15. Riesgos Técnicos (tabla prob/impacto/mitigación). Incluir al menos:
      slug colisión, borrado de zona con inmuebles, agente reasignado,
      media huérfana, performance de filtros sin índice.
  16. Criterios de Aceptación QA-026 → QA-040 (tabla ID/caso/verificación).
  17. Plan de Implementación por Lotes A→E, estrictamente incremental, cada
      lote con su propia DoD y comandos de verificación:
        Lote A — Enums + Migración + Modelo Property + activación
                  contrato User::properties()
        Lote B — Características dinámicas (features, pivote, seeder, catálogo)
        Lote C — Estados comerciales + slug + reglas de publicación
        Lote D — Galería Media Library + SEO + PropertyResource Filament
        Lote E — Tests (Unit/Feature/Regresión) + cierre
  18. Checklist de Cierre Técnico (estado Pendiente por ítem).
  19. Decisiones Diferidas / Fuera de Alcance (p.ej. mapa Google Maps →
      Épica 7 RFC-043; SEO avanzado/schema.org → Épica 7 RFC-045;
      destacados → Épica 7 RFC-041).

FORMATO: Markdown, mismo estilo y nivel de detalle que el diseño de la
Épica 2. Incluye snippets de código PHP reales (migraciones, enums, modelo,
policy, resource) listos para que Codex los implemente.

DoD del prompt: el documento queda completo, internamente consistente, sin
contradicciones con los contratos de Épicas 1/2/3, y listo para auditoría.
```

---

# PROMPT 2 — Auditoría de Diseño (Agente: Gemini CLI)

Objetivo: auditar el diseño producido en P1, emitir veredicto y hallazgos, y **persistir la evidencia** en `docs/audits/` y en engram.

```
[INCRUSTAR BLOQUE DE ENCABEZADO DE REFERENCIA — Sección 1]

ROL: Eres el agente auditor (Gemini CLI). Auditas el DISEÑO de la Épica 4
contenido en docs/epicas/epica-4-inmuebles.md. No implementas código:
evalúas solidez técnica, consistencia con los contratos de las Épicas 1/2/3
y riesgos antes de autorizar la implementación.

DOCUMENTO AUDITADO: docs/epicas/epica-4-inmuebles.md

CRITERIOS DE AUDITORÍA:
  1.  Respeto a contratos previos: ¿la épica es aditiva? ¿activa correctamente
      User::properties() sin romper la Épica 2? ¿usa zone_id→Zone (Épica 3)
      con la estrategia de borrado correcta (nullOnDelete)?
  2.  Integridad del modelo Property: campos, tipos, enums, índices, FKs.
  3.  Máquina de estados comerciales: transiciones completas y seguras;
      regla "solo Publicado es público" verificable; bloqueo de publicación
      sin imagen principal y sin zona.
  4.  Slug: unicidad garantizada, colisiones resueltas, índice único,
      política de actualización que no rompe URLs indexadas.
  5.  Galería: imagen principal obligatoria, orden, límites, conversiones,
      manejo de media huérfana en borrado.
  6.  Características dinámicas: justificación del modelado (catálogo+pivote
      vs JSON), capacidad de filtrado para Épicas 6/7.
  7.  SEO: fallbacks y contrato hacia el frontend.
  8.  Seguridad: PropertyPolicy como fuente única; regla agente↔zonas;
      consistencia con permiso properties.manage.
  9.  Cobertura de tests y trazabilidad QA-026→QA-040.
  10. Sobreingeniería / código muerto / contratos diferidos mal documentados.

ENTREGABLE OBLIGATORIO — generar el archivo:
    docs/audits/epica-4-auditoria-diseno.md

  con esta estructura (idéntica a la auditoría de diseño de la Épica 2):
    · Encabezado: Proyecto, Fecha, Auditor (Gemini CLI), Documento auditado.
    1.  Veredicto (Aprobado / Aprobado con observaciones / Rechazado)
    2.  Hallazgos críticos
    3.  Hallazgos medios
    4.  Hallazgos menores
    5.  Sobreingeniería detectada
    6.  Riesgos de implementación
    7.  Riesgos de seguridad
    8.  Recomendaciones obligatorias
    9.  Recomendaciones opcionales
    10. Preguntas abiertas
    11. Checklist de corrección para Claude (agente de implementación)
    12. Checklist de implementación para Codex (agente de programación)

PERSISTENCIA EN ENGRAM (obligatorio):
  Tras escribir el archivo, guarda en engram un registro de la auditoría con:
    · clave / título: "auditoria-diseno-epica-4-inmuebles"
    · proyecto: New Hauz
    · épica: 4 — Inmuebles
    · fase: diseño
    · veredicto emitido
    · lista de hallazgos críticos y medios (resumen)
    · ruta del artefacto: docs/audits/epica-4-auditoria-diseno.md
    · fecha de auditoría
  El objetivo es que engram conserve la trazabilidad de decisiones para
  futuras épicas y auditorías. Confirma explícitamente que el registro quedó
  almacenado en engram.

DoD del prompt: archivo docs/audits/epica-4-auditoria-diseno.md creado +
veredicto emitido + registro persistido y confirmado en engram.
```

---

# PROMPT 3 — Corrección de Diseño (Agente: Claude)

Objetivo: aplicar al documento de diseño las correcciones obligatorias de la auditoría P2.

```
[INCRUSTAR BLOQUE DE ENCABEZADO DE REFERENCIA — Sección 1]

ROL: Eres el arquitecto (Claude). Recibes la auditoría de diseño y aplicas
las correcciones al documento docs/epicas/epica-4-inmuebles.md.

ENTRADA:
  · docs/epicas/epica-4-inmuebles.md (diseño original)
  · docs/audits/epica-4-auditoria-diseno.md (hallazgos de Gemini CLI)
  · Registro de la auditoría en engram (consultar para contexto)

INSTRUCCIONES:
  1.  Aplica TODAS las recomendaciones obligatorias de la sección 8 y la
      checklist 11 de la auditoría.
  2.  Para cada hallazgo no aplicado, documenta la razón en una sección
      "Registro de Cambios desde la Auditoría" (igual que hizo la Épica 2),
      distinguiendo hallazgos aplicados de hallazgos rechazados con
      justificación técnica.
  3.  Añade los criterios QA adicionales que la auditoría haya solicitado
      (p.ej. QA post-auditoría).
  4.  Cierra el documento con una sección "Cierre Técnico del Diseño":
      confirmaciones de arquitectura + veredicto final
      "APROBADO PARA IMPLEMENTACIÓN".
  5.  No introduzcas regresiones sobre los contratos de Épicas 1/2/3.

DoD del prompt: documento actualizado, hallazgos obligatorios resueltos,
registro de cambios trazable, veredicto final de diseño aprobado. Listo para
que Codex inicie el Lote A.
```

---

# PROMPT 4 — Implementación por Lotes (Agente: Codex)

Objetivo: implementar el código de la Épica 4 siguiendo el diseño aprobado, lote por lote, sin avanzar hasta cerrar la DoD de cada lote.

```
[INCRUSTAR BLOQUE DE ENCABEZADO DE REFERENCIA — Sección 1]

ROL: Eres el agente de programación (Codex). Implementas la Épica 4
exactamente como especifica el diseño aprobado. No rediseñas: ejecutas.

ENTRADA:
  · docs/epicas/epica-4-inmuebles.md (diseño APROBADO PARA IMPLEMENTACIÓN)
  · docs/audits/epica-4-auditoria-diseno.md (para checklist de Codex)

REGLAS DURAS:
  · Trabaja en la rama feature/epica-4-inmuebles desde develop.
  · Implementación ADITIVA: no modificas migraciones de User/Zone/media.
  · La activación de User::properties() reemplaza SOLO el contrato diferido
    (whereRaw('1=0')) por el hasMany real a Property.
  · La PropertyPolicy es la única fuente de autorización; la UI de Filament
    la consume, no la reemplaza.
  · Cada migración debe ser compatible con PostgreSQL (no asumir SQLite).
  · Ejecuta la suite de tests al cerrar cada lote.

PLAN POR LOTES (orden estricto, cada uno con su DoD):

  LOTE A — Modelo Property y contrato
    · Enums OperationType, PropertyType, PropertyStatus.
    · Migración create_properties_table (+ zone_id, agent_id, SEO,
      softDeletes, índices, slug único).
    · Modelo Property (fillable, casts, relaciones, HasMedia, scopes).
    · Activar User::properties().
    · PropertyFactory.
    DoD: migra sin error; Property::factory() persiste; relaciones zone/agent
    resuelven; User::first()->properties() ya no usa 1=0.

  LOTE B — Características Dinámicas
    · Modelo Feature, tabla features, pivote property_feature.
    · FeatureSeeder (alberca, jardín, roof garden, seguridad, ...).
    · CRUD de catálogo de features en Filament.
    DoD: features sembradas; asociación N:N con Property operativa.

  LOTE C — Estados Comerciales y Slug
    · Máquina de estados con transiciones validadas.
    · Generación de slug única y estable; índice único.
    · Reglas de publicación (imagen principal + zona obligatorias).
    DoD: transiciones respetan el diseño; no se publica sin requisitos;
    slugs únicos verificados.

  LOTE D — Galería, SEO y PropertyResource
    · Collections Media Library (gallery + cover), conversiones, orden.
    · Campos y fallbacks SEO.
    · PropertyResource completo (form por secciones, table, filtros,
      acciones publicar/pausar/vender/rentar por Policy).
    DoD: CRUD completo en /admin; galería funcional; SEO con fallbacks;
    acciones controladas por permiso properties.manage.

  LOTE E — Tests y cierre
    · Unit (enums, slug, scopes), Feature (CRUD por rol, estados, publicación
      bloqueada, galería, SEO), Regresión Épicas 1/2/3.
    DoD: php artisan test en verde; QA-026→QA-040 cubiertos; sin regresiones.

ENTREGABLE: código fuente + tests por lote, con commits siguiendo la
convención del proyecto (feat:/fix:/test:/refactor:) y PR vinculado a la
épica al cerrar el Lote E.

DoD del prompt: los cinco lotes cerrados, suite completa en verde, sin
regresiones de épicas previas, listo para auditoría de implementación.
```

---

# PROMPT 5 — Auditoría de Implementación (Agente: Gemini CLI)

Objetivo: auditar el código implementado en P4, emitir veredicto y **persistir la evidencia** en `docs/audits/` y en engram.

```
[INCRUSTAR BLOQUE DE ENCABEZADO DE REFERENCIA — Sección 1]

ROL: Eres el agente auditor (Gemini CLI). Auditas la IMPLEMENTACIÓN de la
Épica 4 (Lotes A→E) contra el diseño aprobado y los contratos de Épicas 1/2/3.

DOCUMENTO / CÓDIGO AUDITADO:
  · Rama feature/epica-4-inmuebles
  · docs/epicas/epica-4-inmuebles.md (diseño aprobado, como referencia)

CRITERIOS DE AUDITORÍA DE IMPLEMENTACIÓN:
  1.  Fidelidad al diseño: enums, migración, modelo, policy y resource
      coinciden con lo aprobado.
  2.  Activación correcta de User::properties() sin romper Épica 2.
  3.  Integridad referencial: zone_id/agent_id con nullOnDelete; sin pérdida
      de inmuebles al borrar zonas o reasignar agentes.
  4.  Máquina de estados: transiciones reales seguras; "solo Publicado es
      público" verificado en código; publicación bloqueada sin imagen
      principal y sin zona.
  5.  Slug: unicidad real, índice único en BD, no rompe URLs al actualizar.
  6.  Galería: imagen principal obligatoria, orden, conversiones, sin media
      huérfana en borrado.
  7.  Seguridad: PropertyPolicy es la fuente de autorización; agente solo
      gestiona sus inmuebles; backend no depende de la UI de Filament.
  8.  Tests: cobertura real de QA-026→QA-040; regresión de Épicas 1/2/3 en
      verde; uso de PostgreSQL de test.
  9.  Calidad: sin código muerto, comentarios obsoletos ni contratos
      diferidos mal etiquetados.

ENTREGABLE OBLIGATORIO — generar el archivo:
    docs/audits/epica-4-auditoria-implementacion.md

  con esta estructura (idéntica a la auditoría de implementación de Épica 2):
    · Encabezado: Proyecto, Fecha, Auditor (Gemini CLI), Documento auditado.
    1.  Veredicto (Aprobado / Aprobado con observaciones / Rechazado)
    2.  Hallazgos críticos
    3.  Hallazgos medios
    4.  Hallazgos menores
    5.  Regresiones detectadas
    6.  Riesgos de seguridad
    7.  Riesgos de mantenimiento
    8.  Tests faltantes
    9.  Correcciones obligatorias para Codex
    10. Correcciones recomendadas
    11. Checklist final antes de merge

PERSISTENCIA EN ENGRAM (obligatorio):
  Tras escribir el archivo, guarda en engram un registro con:
    · clave / título: "auditoria-implementacion-epica-4-inmuebles"
    · proyecto: New Hauz
    · épica: 4 — Inmuebles
    · fase: implementación
    · veredicto emitido
    · hallazgos críticos/medios (resumen) y regresiones
    · estado del checklist final antes de merge
    · ruta del artefacto: docs/audits/epica-4-auditoria-implementacion.md
    · fecha de auditoría
  Confirma explícitamente que el registro quedó almacenado en engram.

DoD del prompt: archivo docs/audits/epica-4-auditoria-implementacion.md
creado + veredicto emitido + registro persistido y confirmado en engram.
Si el veredicto es Aprobado y el checklist final está completo, la Épica 4
queda lista para merge a develop.
```

---

## 3\. Convención de artefactos y persistencia

| Artefacto | Ruta | Generado por | Persiste en engram |
| :---- | :---- | :---- | :---- |
| Diseño técnico | `docs/epicas/epica-4-inmuebles.md` | Claude (P1, P3) | — |
| Auditoría de diseño | `docs/audits/epica-4-auditoria-diseno.md` | Gemini CLI (P2) | ✅ `auditoria-diseno-epica-4-inmuebles` |
| Código \+ tests | rama `feature/epica-4-inmuebles` | Codex (P4) | — |
| Auditoría de implementación | `docs/audits/epica-4-auditoria-implementacion.md` | Gemini CLI (P5) | ✅ `auditoria-implementacion-epica-4-inmuebles` |

**Nota sobre engram:** cada auditoría deja su huella en engram con proyecto, épica, fase, veredicto, hallazgos y ruta del artefacto. Esto permite que las épicas siguientes (5 Leads, 6 Frontend, 7 Comercialización) consulten el historial de decisiones del núcleo inmobiliario sin releer todos los documentos.

---

## 4\. Checklist de orquestación de la Épica 4

| Paso | Prompt | Agente | Salida | Estado |
| :---- | :---- | :---- | :---- | :---- |
| 1 | P1 | Claude | Diseño técnico | Pendiente |
| 2 | P2 | Gemini CLI | Auditoría de diseño \+ engram | Pendiente |
| 3 | P3 | Claude | Diseño corregido y aprobado | Pendiente |
| 4 | P4 | Codex | Implementación Lotes A→E | Pendiente |
| 5 | P5 | Gemini CLI | Auditoría de implementación \+ engram | Pendiente |
| 6 | — | Sebastián (QA) | Validación QA-026→QA-040 | Pendiente |
| 7 | — | Edgar/Kristian | Merge a `develop` | Pendiente |

---

*Documento de orquestación multiagente — Épica 4 — Inmuebles · New Hauz* *Rama de destino: `feature/epica-4-inmuebles` desde `develop`*  
