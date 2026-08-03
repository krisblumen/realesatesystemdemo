# Prompts multiagente — Épica 3 — Zonas Comerciales (RFC-015 → RFC-018)

**Proyecto:** New Hauz — Plataforma Monolítica Inmobiliaria **Stack:** Laravel 13.x \+ Filament \+ Livewire \+ Tailwind CSS \+ PostgreSQL \+ **PostGIS** \+ `spatie/laravel-permission` **Rama:** `feature/epica-3-zonas-comerciales` **Tag objetivo:** `v0.3.0-zonas-comerciales` (siguiente minor tras el cierre de la Épica 2\) **Documento de Épica:** `docs/epicas/epica-3-zonas-comerciales.md` **Auditorías:** `docs/audits/` \+ registro en **engram** (memoria del proyecto) **Responsable de arquitectura:** Kristian · **Arquitectura de apoyo:** Edgar · **QA:** Sebastián **Restricción global:** proyecto monolítico independiente. No microservicios, no auth externo, no orquestación externa. Se reutiliza todo lo entregado en las Épicas 1 y 2; nada se reescribe.

---

## Contexto de continuidad

Esta épica llega **después de dos fases ya implementadas y APROBADAS**, mergeadas en `main`/`develop`:

```
FASE 1 — Fundación Técnica (RFC-001 → RFC-010) — IMPLEMENTADA Y APROBADA  ·  tag v0.1.x

RFC-001  — Laravel 13.x          RFC-006  — spatie/laravel-permission (HasRoles)
RFC-002  — PostgreSQL            RFC-007  — Media Library
RFC-003  — PostGIS habilitado    RFC-008  — Ambientes + .env.example
RFC-004  — Filament /admin       RFC-009  — Git Flow
RFC-005  — Livewire              RFC-010  — Docker local

FASE 2 / ÉPICA 2 — Usuarios y Seguridad (RFC-011 → RFC-014) — IMPLEMENTADA Y APROBADA  ·  tag v0.2.0

RFC-011  — Modelo Usuario (User extendido: status, soft delete, last_login_at)
RFC-012  — Roles y Permisos (owner/admin/agente + matriz de permisos)
RFC-013  — CRUD Usuarios (Filament UserResource)
RFC-014  — Suspensión y Reactivación (bloqueo de login + auditoría)
```

### Seguimiento de la fase anterior (Épica 2\)

Esta épica **consume directamente los contratos cerrados por la Épica 2**:

```
- Rol `agente` operativo: los usuarios con rol agente son los que se asignarán a zonas (RFC-017).
- Permiso `zones.manage` YA sembrado en la matriz de la Épica 2 (owner y admin). Esta épica
  lo CONSUME para autorizar el CRUD y la asignación; no redefine la matriz.
- Policies/Gates de la Épica 2 son la fuente de autorización backend; ZonePolicy seguirá el mismo patrón.
- Soft delete y estados (status) como convención establecida; Zone reutiliza el mismo enfoque.
- Contrato diferido pendiente: la relación Zona ↔ Inmuebles queda DIFERIDA porque el modelo
  Property pertenece a la Épica 4. Aquí sólo se declara el contrato, no se crea la tabla properties.
```

**Qué hace esta épica:** construir la **administración geográfica de zonas comerciales** del estado de Querétaro sobre PostgreSQL \+ PostGIS: modelo `Zone`, CRUD administrativo en Filament, asignación de agentes (muchos-a-muchos) y soporte geoespacial de polígonos (delimitación, center point, consultas espaciales).

**RFCs de la épica:**

```
RFC-015  — Modelo Zona            (entidad Zone: name, slug, description, municipality, status)
RFC-016  — CRUD Zonas             (Filament ZoneResource)
RFC-017  — Asignación de Agentes  (zona ↔ agentes, muchos-a-muchos)
RFC-018  — Polígonos PostGIS      (polígonos, center point, consultas espaciales)
```

**Dependencias técnicas (ya satisfechas):** RFC-002 (PostgreSQL), RFC-003 (PostGIS), RFC-004 (Filament), RFC-006 (Spatie Permission), RFC-011/RFC-012 (User \+ roles/permisos).

**Decisiones de arquitectura ya tomadas (documentar, NO reabrir):**

```
- Zone se crea desde cero (modelo nuevo). Campos: id, name, slug (único), description,
  municipality, status (activa/inactiva), + columnas geoespaciales (RFC-018), timestamps,
  soft delete según convención de la Épica 2.
- Geometría: polígono de delimitación con SRID 4326 (WGS84) + center point derivado.
- Relación Zona ↔ Agentes: muchos-a-muchos (un agente en varias zonas; una zona con varios agentes).
- Relación Zona ↔ Inmuebles: CONTRATO DIFERIDO (modelo Property es de la Épica 4). No crear properties.
- Autorización vía permiso `zones.manage` (ya sembrado en Épica 2) + ZonePolicy.
- Slug único, generado/validado; municipality acotado a municipios de Querétaro.
```

**Reutilizaciones obligatorias (consumir, NO reescribir):**

```
- PostGIS (RFC-003): extensión ya habilitada; usar geometry/geography, NO reinstalar.
- Spatie Permission (RFC-006/012): permiso zones.manage y patrón de policies.
- Filament (RFC-004): panel y patrón de Resource del UserResource (RFC-013) como referencia.
- User + rol agente (Épica 2): origen de los agentes asignables.
- PostgreSQL (RFC-002): migraciones compatibles con PG; índice espacial GIST.
- Git Flow (RFC-009) y .env.example (RFC-008) para cualquier variable nueva (p. ej. token de mapas).
```

---

## Orden de ejecución

```
1.  Claude   → Generar documento técnico de la Épica 3 (diseño consolidado)
2.  Gemini   → Auditar el diseño de la Épica 3  → docs/audits/ + engram
3.  Claude   → Cierre / aprobación del diseño
4.  Codex    → Lote A  RFC-015  Modelo Zona (entidad, migración, slug, relaciones, columnas geo)
5.  Codex    → Lote B  RFC-018  Polígonos PostGIS (geometría, center point, consultas espaciales)
6.  Codex    → Lote C  RFC-016  CRUD Zonas (Filament ZoneResource + edición de polígono)
7.  Codex    → Lote D  RFC-017  Asignación de Agentes (pivote zona↔agente, UI)
8.  Codex    → Lote E  Tests + Docs + Validación
9.  Gemini   → Auditoría completa de implementación → docs/audits/ + engram
10. Codex    → Correcciones post-auditoría
11. Codex    → Validación final
12. Claude   → Cierre técnico de la Épica 3
13. Usuario  → commit, PR, merge, tag v0.3.0-zonas-comerciales
```

Nota de ordenamiento: RFC-018 (PostGIS) se ejecuta antes que RFC-016 (CRUD) porque el ZoneResource necesita que el almacenamiento geoespacial y los value objects de geometría existan para poder capturar/editar el polígono. Es una reordenación deliberada respecto a la numeración, justificada por dependencia técnica.

---

## 1\. Claude — Generar documento técnico de la Épica 3

Actúa como arquitecto técnico senior (Laravel 13 \+ Filament \+ Livewire \+ PostgreSQL \+ PostGIS).

Contexto:

- Proyecto New Hauz, monolito. Épicas 1 y 2 implementadas y aprobadas.  
- PostGIS ya habilitado (RFC-003). User \+ rol agente operativos (Épica 2). Permiso `zones.manage` ya sembrado.  
- Documento destino: `docs/epicas/epica-3-zonas-comerciales.md`.

Objetivo:

Diseñar de forma consolidada la administración geográfica de zonas comerciales: modelo `Zone`, CRUD en Filament, asignación de agentes (muchos-a-muchos) y soporte de polígonos PostGIS (delimitación, center point, consultas espaciales). Implementable por lotes (A→E).

Decisiones de arquitectura ya tomadas (documéntalas, no las reabras):

- Zone nuevo. Campos: id, name, slug (único), description, municipality, status (activa|inactiva), columnas geoespaciales, timestamps, soft delete.  
- Geometría: polígono SRID 4326 (WGS84) \+ center point derivado. Índice espacial GIST.  
- Zona ↔ Agentes: muchos-a-muchos (pivote zona↔usuario, agentes).  
- Zona ↔ Inmuebles: CONTRATO DIFERIDO (Property es de la Épica 4). No crear tabla properties.  
- Autorización con `zones.manage` (ya sembrado) \+ ZonePolicy.  
- municipality acotado a municipios de Querétaro.

Define explícitamente (decisiones técnicas a cerrar en el documento):

```
- geometry vs geography para la columna del polígono, y por qué (precisión vs rendimiento de consultas).
- Cómo manipular PostGIS desde Eloquent: paquete espacial (p. ej. clickbar/laravel-magellan o
  matanyadaev/laravel-eloquent-spatial) vs expresiones SQL crudas. Elegir y justificar; sin sobreingeniería.
- Cómo capturar/editar el polígono en Filament (entrada GeoJSON, widget de mapa Leaflet, o coordenadas):
  Filament NO trae campo PostGIS nativo. Definir la estrategia mínima viable.
- Cómo se calcula y persiste el center point (ST_Centroid) y cuándo se recalcula.
```

El documento debe incluir:

```
1.  Título y estado
2.  Contexto (dependencia de las Épicas 1 y 2; contratos consumidos)
3.  Objetivos y no-objetivos
4.  Alcance funcional y alcance técnico
5.  RFC-015 Modelo Zona: campos, casts, slug, status, soft delete, relaciones (agentes real, inmuebles diferida)
6.  RFC-018 Polígonos PostGIS: tipo de columna, SRID, center point, índice GIST, scopes de consulta espacial
7.  RFC-016 CRUD Zonas: ZoneResource (form con captura de polígono, table, filtros, validaciones, permisos)
8.  RFC-017 Asignación de Agentes: pivote, reglas (agente en varias zonas / zona con varios agentes), UI
9.  Modelo de datos (migraciones nuevas, pivote, columnas geoespaciales, índices)
10. Seguridad: autorización con zones.manage en ZonePolicy (backend, no sólo UI)
11. Estrategia de testing (modelo, slug, geometría/consultas espaciales, CRUD, asignación, regresión)
12. Riesgos técnicos (PostGIS en Filament, migrate:fresh con extensión, validez de polígonos) y decisiones abiertas
13. Criterios de aceptación verificables (mapeados a QA-018…QA-025 de la épica)
14. Plan de implementación por lotes Codex (A→E como en el orden de ejecución)
15. Checklist de cierre técnico
```

Restricciones:

- No reescribir lo entregado en las Épicas 1 y 2\. Consumir sus contratos.  
- No reinstalar PostGIS; ya está habilitado.  
- No inventar la tabla properties: sólo declarar el contrato de relación Zona↔Inmuebles.  
- No introducir dependencias de pago. Tokens de mapas (si aplica) vía .env.example.  
- No sobreingeniería en la edición geoespacial: estrategia mínima viable.

Entrega el documento completo en Markdown, tono técnico, orientado a implementación real.

---

## 2\. Gemini — Auditar el diseño de la Épica 3

Actúa como auditor técnico estricto (Laravel 13 \+ Filament \+ PostgreSQL \+ PostGIS \+ Spatie Permission).

Vas a auditar: `docs/epicas/epica-3-zonas-comerciales.md`

Contexto:

- Proyecto New Hauz, monolito. Épicas 1 y 2 cerradas y aprobadas.  
- Épica 3 \= zonas comerciales: modelo `Zone`, CRUD Filament, asignación de agentes y polígonos PostGIS.

Audita con estos criterios:

```
1.  Modelo Zone completo: name, slug único, description, municipality, status, soft delete.
2.  Columna geoespacial bien definida: tipo (geometry/geography), SRID 4326, índice GIST.
3.  Estrategia clara de PostGIS desde Eloquent (paquete o SQL crudo) coherente y sin sobreingeniería.
4.  Center point derivado correctamente (ST_Centroid) y momento de recálculo definido.
5.  Estrategia viable de captura/edición de polígono en Filament (no asumir un campo PostGIS inexistente).
6.  Relación Zona↔Agentes muchos-a-muchos correcta (pivote, sin duplicados, con FKs a users).
7.  Relación Zona↔Inmuebles tratada como contrato diferido; sin inventar tabla properties.
8.  Autorización con zones.manage aplicada en ZonePolicy (backend), no sólo en la UI.
9.  Migraciones compatibles con PostgreSQL; migrate:fresh conserva la extensión PostGIS.
10. Validez de polígonos contemplada (ST_IsValid / cierre del anillo / SRID correcto).
11. Implementabilidad incremental por lotes (A→E) y criterios de aceptación comprobables.
12. Sobreingeniería y deuda técnica oculta.
13. Cobertura de testing: modelo, slug, consultas espaciales, CRUD, asignación, regresión Épicas 1–2.
```

Entrega en Markdown:

```
# Auditoría de diseño — Épica 3 — Zonas Comerciales

## Veredicto (Aprobado / Aprobado con observaciones / Rechazado)
## Hallazgos críticos
## Hallazgos medios
## Hallazgos menores
## Sobreingeniería detectada
## Riesgos de implementación
## Riesgos geoespaciales (PostGIS)
## Recomendaciones obligatorias
## Recomendaciones opcionales
## Preguntas abiertas
## Checklist de corrección para Claude
## Checklist de implementación para Codex
```

Persistencia obligatoria del resultado:

- Guarda el informe completo en `docs/audits/epica-3-auditoria-diseno.md`.  
- Registra en **engram** (memoria del proyecto) un resumen estructurado con: veredicto, hallazgos críticos, riesgos geoespaciales, correcciones obligatorias, fecha y ruta del archivo, bajo la clave `audit:epica-3:diseno`, para que los demás agentes consulten el estado.

No reescribas el documento completo. Audita, cuestiona y corrige el rumbo.

---

## 3\. Claude — Cierre / aprobación del diseño

Actúa como arquitecto técnico senior responsable del cierre del diseño.

Contexto:

- Proyecto New Hauz. Documento: `docs/epicas/epica-3-zonas-comerciales.md`.  
- El diseño fue auditado por Gemini (ver `docs/audits/epica-3-auditoria-diseno.md` y la entrada `audit:epica-3:diseno` en engram).

Tareas:

```
1. Leer el documento de la épica y la auditoría de Gemini (archivo + engram).
2. Aplicar únicamente las observaciones válidas.
3. Marcar decisiones cerradas y decisiones diferidas.
4. Confirmar: Zone con slug único y soft delete; columna geoespacial SRID 4326 + índice GIST;
   estrategia PostGIS-en-Eloquent elegida; estrategia de edición de polígono en Filament definida;
   pivote Zona↔Agentes; relación Zona↔Inmuebles como contrato diferido; autorización en ZonePolicy.
5. Confirmar que los criterios de aceptación son verificables y mapean a QA-018…QA-025.
6. Confirmar que el plan por lotes (A→E) es incremental.
```

Entrega:

- Documento de la épica corregido y completo.  
- Sección final "Cierre técnico del diseño".  
- Lista de cambios aplicados desde la auditoría.  
- Lista de puntos diferidos / fuera de alcance.  
- Estado final: "Aprobado para implementación".

---

## 4\. Codex — Lote A: RFC-015 Modelo Zona (entidad, migración, slug, relaciones, columnas geo)

Actúa como desarrollador senior Laravel.

Contexto:

- Proyecto New Hauz. Rama `feature/epica-3-zonas-comerciales`. Épica 3 aprobada.  
- PostGIS habilitado (RFC-003). User \+ rol agente operativos (Épica 2).

Objetivo:

Crear la entidad `Zone` con todos sus campos, slug, estado, soft delete y las columnas geoespaciales declaradas (la lógica espacial va en el Lote B). Sin UI todavía.

Tareas:

```
1. Migración create_zones: id, name, slug (único), description (nullable), municipality,
   status (default 'activa'; activa|inactiva), columna geoespacial para el polígono
   (geometry/geography SRID 4326 según diseño) y center point, soft delete (deleted_at), timestamps.
   Añadir índice espacial GIST sobre la columna del polígono.
2. Modelo Zone: fillable/casts; SoftDeletes; generación/validación de slug único; cast de status
   (enum/constantes); helpers isActive()/isInactive() si aporta.
3. Relaciones:
   - Zone → agentes (belongsToMany hacia User): declarar (pivote se materializa en Lote D).
   - Zone → properties (hasMany): declarar como CONTRATO diferido (modelo Property es de la Épica 4;
     método presente con comentario, sin migrar tabla properties).
4. Factory de Zone (sin geometría compleja aún; placeholder válido o nullable controlado).
5. NO implementar consultas espaciales (Lote B). NO construir el CRUD (Lote C). NO el pivote (Lote D).
```

Criterios de aceptación:

```
- Migración corre limpia en PostgreSQL; la columna geoespacial y el índice GIST se crean.
- migrate:fresh conserva la extensión PostGIS (no se elimina por error).
- Zone tiene slug único, status, SoftDeletes y relaciones declaradas.
- Tests existentes (Épicas 1 y 2) siguen pasando.
```

Entrega: archivos modificados, resumen técnico, comandos ejecutados, riesgos. Commit `feat: ...`.

---

## 5\. Codex — Lote B: RFC-018 Polígonos PostGIS (geometría, center point, consultas espaciales)

Actúa como desarrollador senior Laravel \+ PostGIS.

Contexto:

- Proyecto New Hauz. Rama `feature/epica-3-zonas-comerciales`. Épica 3 aprobada.  
- Existe el modelo Zone con columnas geoespaciales (Lote A). PostGIS habilitado.

Objetivo:

Implementar la capa geoespacial: persistencia y lectura del polígono, cálculo del center point y scopes de consulta espacial, usando la estrategia PostGIS-en-Eloquent definida en el diseño.

Tareas:

```
1. Integrar el manejo de geometría según el diseño (paquete espacial elegido o expresiones SQL crudas).
   Casts/value objects para leer y escribir el polígono como GeoJSON o WKT con SRID 4326.
2. Validación de polígonos: SRID correcto, anillo cerrado, ST_IsValid; rechazar geometrías inválidas.
3. Center point: calcular con ST_Centroid al guardar/actualizar el polígono y persistirlo.
4. Scopes/consultas espaciales mínimas necesarias para los casos de uso del RFC-018:
   - contiene un punto (ST_Contains / ST_Within) — base para "identificar inmuebles por zona".
   - (preparar la firma del scope que la Épica 4 consumirá con Property, sin implementar Property).
5. Aprovechar el índice GIST en las consultas.
6. NO construir el CRUD (Lote C). NO el pivote de agentes (Lote D).
```

Criterios de aceptación:

```
- Se puede guardar y recuperar un polígono SRID 4326 sin corrupción de datos.
- Geometrías inválidas son rechazadas con un error claro.
- El center point se calcula y persiste correctamente.
- Una consulta "¿este punto cae dentro de la zona?" devuelve el resultado correcto usando el índice.
- Tests existentes siguen pasando.
```

Entrega: archivos modificados, estrategia PostGIS confirmada, ejemplos de consulta, comandos, riesgos. Commit `feat: ...`.

---

## 6\. Codex — Lote C: RFC-016 CRUD Zonas (Filament ZoneResource \+ edición de polígono)

Actúa como desarrollador senior Laravel \+ Filament.

Contexto:

- Proyecto New Hauz. Rama `feature/epica-3-zonas-comerciales`. Épica 3 aprobada.  
- Existen el modelo (Lote A) y la capa geoespacial (Lote B). Filament operativo (RFC-004).

Objetivo:

Administrar zonas desde el panel Filament, incluyendo la captura/edición del polígono según la estrategia mínima viable definida, respetando permisos y soft delete.

Tareas:

```
1. ZoneResource con form: name, slug (auto desde name, editable y único), description,
   municipality (select de municipios de Querétaro), status, y captura del polígono
   (GeoJSON, widget de mapa o coordenadas, según diseño).
2. Tabla: columnas clave (name, municipality, status, nº de agentes si aplica), búsqueda y filtros
   (por municipality y por status). Desactivar zona = status inactiva o soft delete con restore.
3. Validaciones: name obligatorio, slug único, polígono válido (reutilizar validación del Lote B).
4. Autorización: el Resource y sus acciones respetan ZonePolicy / permiso zones.manage.
   Sólo owner/admin gestionan zonas (según matriz de la Épica 2).
5. Mostrar el center point o un preview del polígono si aporta claridad, sin sobreingeniería.
6. NO implementar la asignación de agentes como acción funcional (va en Lote D).
```

Criterios de aceptación:

```
- CRUD completo operativo desde /admin con validaciones activas.
- El polígono se captura, guarda, recarga y edita sin pérdida ni corrupción.
- Filtros por municipality y status funcionan.
- Las acciones respetan zones.manage (backend, no sólo UI).
- Tests existentes siguen pasando.
```

Entrega: archivos modificados, resumen técnico, descripción visual del Resource y de la edición de polígono, comandos, riesgos. Commit `feat: ...`.

---

## 7\. Codex — Lote D: RFC-017 Asignación de Agentes (pivote zona↔agente, UI)

Actúa como desarrollador senior Laravel \+ Filament.

Contexto:

- Proyecto New Hauz. Rama `feature/epica-3-zonas-comerciales`. Épica 3 aprobada.  
- Existen modelo (A), capa geoespacial (B) y CRUD (C). User \+ rol agente operativos (Épica 2).

Objetivo:

Permitir asignar, reasignar y consultar agentes por zona mediante una relación muchos-a-muchos, con su UI en Filament y autorización correcta.

Tareas:

```
1. Migración del pivote zone_user (o nombre acordado): zone_id, user_id, FKs a zones y users,
   índice único compuesto para evitar duplicados, timestamps si aporta.
2. Materializar la relación belongsToMany en Zone (y la inversa en User si conviene),
   filtrando que sólo usuarios con rol `agente` sean asignables.
3. UI en Filament: gestor de relación (RelationManager) o campo de asignación en ZoneResource
   para asignar/reasignar/quitar agentes y consultar las asignaciones de una zona.
4. Reglas: un agente puede pertenecer a varias zonas; una zona puede tener varios agentes.
   Evitar duplicados (índice único). Respetar zones.manage para modificar asignaciones.
5. Consultar asignaciones: listar agentes de una zona y, si aporta, zonas de un agente.
6. NO ampliar a lógica de distribución de leads (eso es Épica 5).
```

Criterios de aceptación:

```
- Se asignan y reasignan agentes a zonas sin duplicados (índice único efectivo).
- Sólo usuarios con rol agente son asignables.
- Las asignaciones se consultan desde el panel.
- Modificar asignaciones respeta zones.manage.
- Tests existentes siguen pasando.
```

Entrega: archivos modificados, resumen técnico, descripción de la UI de asignación, comandos, riesgos. Commit `feat: ...`.

---

## 8\. Codex — Lote E: Tests \+ Docs \+ Validación

Actúa como desarrollador senior Laravel especializado en testing (incluye geoespacial).

Contexto:

- Proyecto New Hauz. Rama `feature/epica-3-zonas-comerciales`. Épica 3 aprobada.

Objetivo:

Cobertura real y documentación de la administración de zonas. Mapear los tests a la matriz QA de la épica (QA-018…QA-025) y a la regresión de Épicas 1 y 2\.

Tareas:

```
1. Tests de modelo: campos, slug único, status, SoftDeletes, relaciones (agentes real, inmuebles diferida).
2. Tests geoespaciales: guardar/recuperar polígono SRID 4326; rechazo de geometría inválida;
   cálculo del center point; consulta "punto dentro de zona" con resultado correcto.
3. Tests de CRUD: crear/editar/consultar/desactivar zona; validaciones (name, slug único, polígono);
   permisos zones.manage respetados.
4. Tests de asignación de agentes: asignar/reasignar/quitar; sin duplicados; sólo agentes asignables;
   consulta de asignaciones; autorización.
5. Tests de regresión: Épica 1 (Filament /admin, PostGIS activo) y Épica 2 (login roles, CRUD usuarios,
   suspensión) siguen funcionando.
6. Factories: Zone con polígono válido de ejemplo y estado; pivote zona↔agente.
7. Documentación: docs/modulos/zonas-comerciales.md (modelo, columnas geoespaciales y SRID,
   estrategia PostGIS-en-Eloquent, edición de polígono en Filament, center point, asignación de agentes,
   consultas espaciales, integración con RFC-003/004/006/012, decisiones diferidas: relación Inmuebles).
8. Ejecutar: php artisan test, ./vendor/bin/pint, ./vendor/bin/phpstan analyse (si aplica), npm run build.
```

Criterios de aceptación:

```
- Tests nuevos pasan; suite verde; Pint/PHPStan limpios; build ok.
- Cobertura real (no cosmética) de modelo, geometría/consultas, CRUD, asignación y regresión.
- Todos los casos QA-018…QA-025 quedan cubiertos por al menos un test.
- Documentación fiel a lo implementado, con la estrategia geoespacial explicada.
```

Entrega: archivos test/doc, mapeo test→QA, comandos, resultado de pruebas, pendientes. Commit `test: ...` / `docs: ...`.

---

## 9\. Gemini — Auditoría completa de implementación

Actúa como auditor técnico estricto para una implementación Laravel 13 \+ Filament \+ PostGIS sobre PostgreSQL.

Contexto:

- Proyecto New Hauz. Rama `feature/epica-3-zonas-comerciales`. Épica 3 implementada (Lotes A→E).

Audita la implementación completa:

```
1.  Modelo Zone correcto: slug único, status, SoftDeletes; relaciones (agentes real, inmuebles diferida).
2.  Columna geoespacial con SRID 4326 e índice GIST realmente creado y usado en consultas.
3.  Estrategia PostGIS-en-Eloquent coherente; sin corrupción al guardar/leer polígonos.
4.  Validez de polígonos aplicada (SRID, anillo cerrado, ST_IsValid); geometrías inválidas rechazadas.
5.  Center point calculado con ST_Centroid y persistido correctamente.
6.  CRUD: validaciones reales; edición de polígono funcional; soft delete/desactivación correctos.
7.  Asignación de agentes muchos-a-muchos sin duplicados; sólo agentes asignables.
8.  Autorización zones.manage en ZonePolicy (backend), no sólo UI.
9.  Relación Zona↔Inmuebles como contrato diferido; sin tabla properties inventada.
10. migrate:fresh en entorno limpio conserva PostGIS; migraciones compatibles con PostgreSQL.
11. Tests reales (no cosméticos) cubriendo QA-018…QA-025 y regresión. Sobreingeniería. Regresiones.
```

Verifica especialmente:

```
- Que un polígono guardado y recargado conserve coordenadas y SRID (sin pérdida ni reproyección errónea).
- Que la consulta "punto dentro de zona" sea correcta y aproveche el índice GIST (no scan completo).
- Que NINGÚN usuario sin rol agente pueda quedar asignado a una zona.
- Que el permiso zones.manage no quede sólo en la capa visual sin policy/gate detrás.
- Que no se haya roto nada de las Épicas 1 y 2.
```

Entrega en Markdown:

```
# Auditoría de implementación — Épica 3 — Zonas Comerciales

## Veredicto (Aprobado / Aprobado con correcciones / Rechazado)
## Hallazgos críticos
## Hallazgos medios
## Hallazgos menores
## Regresiones detectadas
## Riesgos geoespaciales (PostGIS)
## Riesgos de seguridad
## Riesgos de mantenimiento
## Tests faltantes
## Correcciones obligatorias para Codex
## Correcciones recomendadas
## Checklist final antes de merge
```

Persistencia obligatoria del resultado:

- Guarda el informe completo en `docs/audits/epica-3-auditoria-implementacion.md`.  
- Registra en **engram** un resumen estructurado con: veredicto, hallazgos críticos, riesgos geoespaciales y de seguridad, correcciones obligatorias, fecha y ruta del archivo, bajo la clave `audit:epica-3:implementacion`, para trazabilidad entre agentes y para el cierre técnico.

---

## 10\. Codex — Correcciones post-auditoría

Actúa como desarrollador senior Laravel \+ PostGIS.

Contexto:

- Proyecto New Hauz. Rama `feature/epica-3-zonas-comerciales`. Épica 3\.  
- Existe la auditoría de Gemini (ver `docs/audits/epica-3-auditoria-implementacion.md` y la clave `audit:epica-3:implementacion` en engram).

Tareas:

```
1. Leer la auditoría (archivo + engram).
2. Clasificar hallazgos: críticos, medios, menores, diferidos.
3. Corregir críticos y medios. Corregir menores sólo si son seguros.
4. Priorizar riesgos geoespaciales (validez/SRID/center point) y de seguridad (zones.manage, agentes asignables).
5. Actualizar/añadir tests cuando aplique.
6. Ejecutar la suite relevante.
7. No agregar alcance nuevo. No romper las Épicas 1 y 2. No reabrir decisiones aprobadas
   salvo bug claro. No inventar la tabla properties.
```

Entrega: correcciones aplicadas, hallazgos diferidos con razón, archivos modificados, comandos, resultado de tests, estado final recomendado. Commit `fix: ...`.

---

## 11\. Codex — Validación final

Actúa como responsable de validación técnica final.

Contexto:

- Proyecto New Hauz. Rama `feature/epica-3-zonas-comerciales`. Épica 3\.  
- Ya se aplicaron correcciones post-auditoría.

Ejecuta o verifica:

```
1.  php artisan test
2.  ./vendor/bin/pint
3.  ./vendor/bin/phpstan analyse  (si está configurado)
4.  npm run build
5.  Migraciones en entorno limpio (migrate:fresh --seed) sobre PostgreSQL; PostGIS conservado.
6.  CRUD de zonas operativo en /admin con validaciones y edición de polígono.
7.  Polígono SRID 4326 guardado/recargado sin pérdida; center point correcto.
8.  Consulta "punto dentro de zona" correcta y con uso del índice GIST.
9.  Asignación de agentes sin duplicados; sólo agentes asignables; autorización efectiva.
10. Casos QA-018…QA-025 verificados; regresión de Épicas 1 y 2 OK.
11. Sin dependencias de pago ni reinstalación de PostGIS.
```

Entrega:

```
# Validación final — Épica 3 — Zonas Comerciales

## Resultado general (Aprobado / Aprobado con observaciones / No aprobado)
## Comandos ejecutados
## Resultado de pruebas
## Validaciones manuales (incluye geoespaciales)
## Riesgos restantes
## Pendientes fuera de alcance
## Recomendación (Listo para cierre técnico / Requiere correcciones / No mergear)
```

---

## 12\. Claude — Cierre técnico de la Épica 3

Actúa como arquitecto técnico responsable del cierre.

Contexto:

- Proyecto New Hauz. Rama `feature/epica-3-zonas-comerciales`. Épica 3\.  
- Existen auditoría de Gemini (archivo \+ engram), correcciones de Codex y validación final.

Tareas:

```
1. Revisar el documento de la épica, las auditorías (docs/audits/ + engram), las correcciones
   y la validación final.
2. Emitir el cierre técnico.
3. Identificar deuda técnica aceptada y pendientes fuera de alcance.
4. Confirmar si la rama está lista para commit, PR, merge y tag.
```

Entrega:

```
# Cierre técnico — Épica 3 — Zonas Comerciales

## Estado final
## Alcance implementado (RFC-015 → RFC-018)
## Decisiones técnicas cerradas
   - Modelo Zone con slug único, status y SoftDeletes
   - Columna geoespacial SRID 4326 + índice GIST + center point (ST_Centroid)
   - Estrategia PostGIS-en-Eloquent elegida (paquete/SQL crudo)
   - Edición de polígono en Filament (estrategia mínima viable)
   - Asignación de agentes muchos-a-muchos (sólo rol agente)
   - Autorización con zones.manage en ZonePolicy (backend, no sólo UI)
   - Relación Zona↔Inmuebles como contrato diferido
## Validaciones realizadas (incluye geoespaciales)
## Tests confirmados (mapeo QA-018…QA-025)
## Integración con Épicas previas (RFC-003 PostGIS, RFC-004 Filament, RFC-006/012 permisos, Épica 2 agentes)
## Seguimiento de fase: confirmación de que Épicas 1 y 2 no sufrieron regresión
## Deuda técnica aceptada
## Pendientes fuera de alcance (módulo Inmuebles/Property — Épica 4; distribución de leads — Épica 5)
## Riesgos residuales
## Recomendación final
## Checklist para Kristian / Edgar
   - revisar diff
   - ejecutar tests
   - commit
   - push
   - PR a develop
   - revisión (QA: Sebastián)
   - merge
   - tag v0.3.0-zonas-comerciales
```

---

## Después de cerrar la Épica 3

La capa de zonas queda lista para que la **Épica 4 — Inmuebles** materialice la relación hoy diferida **Zona ↔ Inmuebles**: el modelo `Property` (Épica 4\) consumirá la geometría de las zonas y el scope espacial preparado en el Lote B para "identificar inmuebles por zona". La **Épica 5 — Leads** consumirá la asignación de agentes por zona para la distribución automática de prospectos. Ninguno de esos dominios se mezcla con esta épica: cada uno define su propio modelo y migraciones cuando llegue su RFC. El permiso `zones.manage` y el soporte PostGIS entregado aquí son la base que esos módulos reutilizarán.  
