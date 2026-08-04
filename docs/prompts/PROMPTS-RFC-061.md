# Prompts Multiagente — RFC-061 (Integración de Zonas al Dashboard)

**Proyecto:** New Hauz · **Instancia concreta del playbook** (`docs/workflow/PROMPTS-MULTIAGENTE.md`) con todos los valores de RFC-061 ya sustituidos. Copia y pega cada prompt tal cual.

Valores aplicados:

| Variable | Valor |
| :---- | :---- |
| RFC\_ID | RFC-061 |
| TITULO | Integración de Zonas Comerciales al Dashboard |
| SLUG | zonas-dashboard |
| EPICA | Épica 3 — Zonas Comerciales (integración a dashboard) |
| RAMA | feature/rfc-061-zonas-dashboard |

Rutas de handoff de este RFC:

```
docs/rfc/RFC-061-zonas-dashboard.md
docs/audits/RFC-061-auditoria-diseno.md
docs/audits/RFC-061-auditoria-implementacion.md
docs/cierres/RFC-061-cierre-tecnico.md
Rama: feature/rfc-061-zonas-dashboard (desde develop, efímera)
```

Tabla de seguimiento (pegar al inicio del RFC):

```
| Etapa | Agente | Estado | Fecha |
|---|---|---|---|
| 1. Generación del RFC          | Claude (Arquitecto)   | ✅ | 2026-06-18 |
| 2. Auditoría de diseño         | Antigravity           | ⬜ | — |
| 3. Aplicación de correcciones  | Claude (Agente Impl.) | ⬜ | — |
| 4. Implementación              | Codex                 | ⬜ | — |
| 5. Auditoría de implementación | Antigravity           | ⬜ | — |
| 6. Cierre técnico              | Claude (Arquitecto)   | ⬜ | — |
```

**Contexto crítico de este RFC (inyectado en los prompts):** la página DEBE llamarse exactamente `App\Filament\Pages\AgentDashboard` — es un contrato duro con el hook `class_exists(AgentDashboard::class)` de `Login::getRedirectUrl()` (RFC-060, DIV-3). Y el nombre de la relación `User ↔ Zone` de Épica 3 debe confirmarse, no asumirse.

---

## Etapa 1 · Claude (Arquitecto) — Generar el RFC

Ya completada — ver `docs/rfc/RFC-061-zonas-dashboard.md`. Prompt usado, para trazabilidad:

```
Actúa como arquitecto senior de Laravel en el proyecto New Hauz.

CONTEXTO
- Stack: Laravel 13.x, PHP 8.3, Filament v3, PostgreSQL 18 + PostGIS, Livewire,
  Tailwind, spatie/laravel-permission, spatie/laravel-medialibrary.
- Te adjunto los RFCs y épicas previos relevantes (Épica 3, RFC-060 y su cierre técnico).
  Léelos antes de diseñar.

TAREA
Diseña el documento RFC-061 — Integración de Zonas Comerciales al Dashboard.
Objetivo: Crear App\Filament\Pages\AgentDashboard (activando el hook de redirect de
RFC-060) y exponer las zonas de Épica 3 en el dashboard de Filament — landing del agente
con sus zonas asignadas y widgets de zonas para owner/admin.
Dependencias declaradas: RFC-060 (hook AgentDashboard en Login::getRedirectUrl), Épica 3
(Zone, ZoneResource, relación User↔Zone de RFC-017, PostGIS RFC-018), Épica 2 (roles,
canAccessPanel, UserPolicy), Épica 1 (panel Filament).

REGLAS DE LA CASA (obligatorias)
1. Aditividad: no toques épicas cerradas. Login.php, ZoneResource y Zone NO se modifican.
2. La Policy/servicios son la fuente de verdad de autorización; Filament la consume.
3. Tests sobre PostgreSQL real (RefreshDatabase), nunca SQLite in-memory.
4. Relaciones entre épicas que aún no existen = contratos diferidos documentados con
   su épica de activación. No inventes tablas futuras (p. ej. inmuebles por zona depende
   de Épica 4 — diferir).
5. Antes de "lo que entrega", declara explícitamente "lo que NO entrega".
6. CONTRATO DURO: la página debe ser exactamente App\Filament\Pages\AgentDashboard
   (el hook de RFC-060 la busca por class_exists). Documéntalo como riesgo.

FORMATO DE SALIDA (markdown, mismo orden que RFC-060)
Encabezado · tabla de seguimiento · Objetivo · Contexto y Dependencias (subsección
"Consume de ..." con estado real de cada contrato) · Alcance (entrega / NO entrega) ·
Diseño Técnico (código de referencia, decisiones CERRADA/ABIERTA) · Alcance Técnico
(árbol de archivos + "Archivos que NO se tocan") · Plan por Lotes (A→D con DoD propio) ·
Criterios QA (continúa numeración; tabla + tests it(...)) · Riesgos (R-1...) · Decisiones
Diferidas · Checklist de Cierre · Estimación.

Entrega solo el documento, listo para docs/rfc/RFC-061-zonas-dashboard.md.
```

---

## Etapa 2 · Antigravity — Auditoría de DISEÑO

```
Actúa como auditor técnico independiente y escéptico. Auditas el DISEÑO de un RFC
del proyecto New Hauz (Laravel 13 / Filament v3 / PostgreSQL+PostGIS). Todavía NO
hay código: auditas la especificación.

ENTRADA
- RFC a auditar: docs/rfc/RFC-061-zonas-dashboard.md
- Dependientes a leer: RFC-060 y su cierre técnico (el hook de getRedirectUrl), Épica 3
  (RFC-015 Zone, RFC-016 ZoneResource, RFC-017 asignación de agentes, RFC-018 PostGIS),
  Épica 2 (roles, canAccessPanel, UserPolicy).

QUÉ VERIFICAR (confirma contra el código del repo con el terminal)
- CONTRATO RFC-060: confirma en app/Filament/Pages/Auth/Login.php que getRedirectUrl()
  busca App\Filament\Pages\AgentDashboard por class_exists. ¿El RFC usa exactamente ese
  nombre/namespace? Cualquier desviación deja el hook inerte.
- RELACIÓN User↔Zone: confirma el nombre REAL en el código de Épica 3
  (grep "function zones", "belongsToMany", revisar Zone::agents()). ¿El RFC asume bien?
  ¿Hace falta añadir la inversa User::zones()?
- Aislamiento de widgets por rol: ¿cada widget define canView()? ¿se filtra alguno entre
  el dashboard por defecto y el AgentDashboard?
- Permiso del agente sobre ZoneResource: ¿la matriz de Épica 2 le da lectura de zonas?
  Si no, el enlace "Ver" del widget produce 403.
- Dependencia de Épica 4: ¿el RFC evita correctamente "inmuebles por zona" hasta que
  exista Property? ¿guarda con class_exists/hasTable si lo adelanta?
- Auto-descubrimiento de Filament: confirma que Pages y Widgets se descubren sin tocar
  AdminPanelProvider.php.
- Compatibilidad PostgreSQL (no asumir MySQL).

REGLA CLAVE
Usa el terminal para CONFIRMAR (grep de la relación, class_exists del hook,
php artisan route:list). No apruebes por intuición.

FORMATO DE SALIDA (markdown, guardar en docs/audits/RFC-061-auditoria-diseno.md)
Encabezado · Veredicto (Aprobado | Aprobado con observaciones | Rechazado) · Hallazgos
críticos/medios/menores · Sobreingeniería · Riesgos de implementación · Riesgos de
seguridad · Recomendaciones obligatorias · opcionales · Preguntas abiertas · Checklist
de corrección para Claude · Checklist de implementación para Codex.

Cada hallazgo: cita archivo/sección, impacto y corrección concreta.
```

---

## Etapa 3 · Claude (Agente de Implementación) — Aplicar correcciones al RFC

```
Actúa como arquitecto de New Hauz aplicando los hallazgos de una auditoría a un RFC.

ENTRADA
- RFC original: docs/rfc/RFC-061-zonas-dashboard.md
- Auditoría de diseño: docs/audits/RFC-061-auditoria-diseno.md

TAREA
Aplica hallazgos críticos, medios y recomendaciones obligatorias. Para cada hallazgo que
NO apliques, justifícalo técnicamente. En particular: cierra el nombre real de la relación
User↔Zone (R-1) y confirma el nombre exacto de la clase AgentDashboard (R-2) con base en
lo que la auditoría haya verificado en el código.

REGLAS
- Conserva formato y numeración del RFC. Las correcciones son ediciones, no un doc nuevo.
- Decisiones de negocio (UX del AgentDashboard, permiso de zonas del agente) → a
  "Decisiones Diferidas" con destino, si no pueden cerrarse por criterio técnico.
- Mantén QA sincronizado con los hallazgos.

FORMATO DE SALIDA
1. RFC corregido completo (encabezado → ✅ listo para implementación).
2. "Registro de Cambios desde la Auditoría" (# | Hallazgo | Tipo | Cambio | Sección).
3. "Hallazgos no aplicados" con su razón.

Actualiza la tabla de seguimiento: etapas 2 y 3 en ✅.
```

---

## Etapa 4 · Codex — Implementación por Lotes

```
Actúa como ingeniero de implementación en New Hauz (Laravel 13 / Filament v3 /
PostgreSQL+PostGIS). Implementas un RFC ya auditado y cerrado, EXACTAMENTE como
está especificado.

ENTRADA
- RFC cerrado: docs/rfc/RFC-061-zonas-dashboard.md (Plan por Lotes A→D)
- Rama de trabajo: feature/rfc-061-zonas-dashboard (desde develop sincronizada)

REGLAS
- LOTE POR LOTE en orden; no empieces uno sin cumplir el DoD del anterior.
- No te desvíes del diseño. Si algo no estaba previsto, PÁRATE y propón opciones.
- CONTRATO DURO: la clase debe ser exactamente App\Filament\Pages\AgentDashboard. Si la
  nombras distinto, el redirect del agente (RFC-060) no se activa y NO da error visible.
- Aditividad estricta: NO toques Login.php, AdminPanelProvider.php, ZoneResource, Zone.
  Filament auto-descubre Pages y Widgets — no registres nada en el panel provider.
- Lote A primero: confirma por tinker la relación User↔Zone real antes de codificar los
  widgets. Si falta la inversa User::zones(), añádela (aditivo) ajustando la tabla pivote
  real de Épica 3.
- Tras cada lote: ejecuta su verificación y los tests relevantes. Commits atómicos.

PRE-VUELO (antes del Lote A)
- git pull origin develop && git rebase develop
- composer install && composer validate
- npm install && npm run build
- Si tocas composer.json, regenera composer.lock y commitéalo.

FORMATO DE SALIDA
Por lote: archivos creados/modificados, salida de verificación, confirmación de DoD.
Al final: estado de la suite completa y lista de commits. Si un DoD falla, repórtalo.

Actualiza la tabla de seguimiento: etapa 4 en ✅ cuando todos los lotes pasen su DoD.
```

---

## Etapa 5 · Antigravity — Auditoría de IMPLEMENTACIÓN (ejecutando la app)

```
Actúa como auditor de implementación independiente en New Hauz. Auditas CÓDIGO YA
ESCRITO. Tu valor está en EJECUTAR el sistema real, no en leer el diff. Tienes
terminal y navegador: úsalos.

ENTRADA
- RFC implementado: docs/rfc/RFC-061-zonas-dashboard.md
- Rama: feature/rfc-061-zonas-dashboard (o el merge en develop)

VERIFICACIÓN EN VIVO (OBLIGATORIA)
1. Clon/working tree limpio: composer install sin faltantes; composer validate en sync.
2. npm run build sin errores de Vite.
3. php artisan about sin errores; la app arranca.
4. CONTRATO RFC-060 en vivo: login como AGENTE → debe aterrizar en /admin/mi-zona
   (no en /admin). Esto prueba que class_exists(AgentDashboard::class) ahora es true y el
   hook se activó. Login como owner/admin → siguen en /admin (regresión RFC-060).
5. AgentDashboard:
   - agente con zonas → ve sus zonas listadas;
   - agente sin zonas → estado vacío con mensaje guía (no error);
   - no-agente que navega directo a /admin/mi-zona → bloqueado por canAccess().
6. Dashboard principal: owner/admin ven ZonesOverviewWidget con conteos correctos;
   agente NO lo ve. Verifica el aislamiento en AMBAS direcciones.
7. php artisan test — toda la suite, incluida regresión de RFC-060 (redirect) y Épica 3
   (ZoneResource CRUD).
8. class_exists() de AgentDashboard, AgentZonesWidget, ZonesOverviewWidget.

QUÉ MÁS REVISAR
- ¿Coincide la implementación con el RFC, lote por lote y DoD por DoD?
- Regresiones sobre RFC-060 (login/redirect), Épica 3 (zonas) y Épica 2 (roles).
- ¿Algún widget se filtra a un rol que no debe (canView mal puesto)?
- ¿Se tocó por error Login.php, AdminPanelProvider.php, ZoneResource o Zone? No deberían.
- Higiene de repo: caché/artefactos versionados, ramas mergeadas sin borrar.

FORMATO DE SALIDA (markdown, guardar en docs/audits/RFC-061-auditoria-implementacion.md)
Encabezado + Veredicto · Hallazgos críticos/medios/menores (con evidencia: HTTP, captura
del DOM, traza) · Regresiones · Riesgos de seguridad · Tests faltantes · Correcciones
obligatorias para Codex · recomendadas · Checklist final antes de merge.

Adjunta como artefactos la evidencia de ejecución (logs, respuestas HTTP, capturas del
redirect del agente). Actualiza la tabla de seguimiento: etapa 5 en ✅.
```

---

## Etapa 6 · Claude (Arquitecto) — Cierre técnico

```
Actúa como arquitecto senior de New Hauz cerrando técnicamente un RFC tras su
implementación y auditoría.

ENTRADA
- RFC: docs/rfc/RFC-061-zonas-dashboard.md
- Auditoría de diseño: docs/audits/RFC-061-auditoria-diseno.md
- Auditoría de implementación: docs/audits/RFC-061-auditoria-implementacion.md

TAREA
1. Reconcilia: cada hallazgo de ambas auditorías resuelto o justificadamente diferido.
2. Confirma el cierre de CD-1 de RFC-060 (AgentDashboard creado y hook activo). Verifica
   la vigencia de las decisiones diferidas (D-1 inmuebles/Épica 4, D-2 métricas/Épica 7,
   D-3 mapa/Épica 7).
3. Documenta el contrato estable para épicas siguientes: nombre real de User::zones(),
   AgentDashboard::getUrl() como punto de entrada del agente, y los canView() de los
   widgets. Anota cualquier divergencia diseño↔implementación.
4. Marca el RFC como ✅ IMPLEMENTADO y actualiza la tabla de seguimiento (etapa 6 ✅).

FORMATO DE SALIDA
- RFC actualizado (estado ✅ IMPLEMENTADO).
- docs/cierres/RFC-061-cierre-tecnico.md: veredicto, hallazgos resueltos vs diferidos,
  divergencias documentadas, y la lista de contratos que las épicas siguientes
  (4, 6, 7) pueden consumir con seguridad.
```

---

*Instancia de RFC-061 generada el 2026-06-18 a partir de `docs/workflow/PROMPTS-MULTIAGENTE.md`.*  
