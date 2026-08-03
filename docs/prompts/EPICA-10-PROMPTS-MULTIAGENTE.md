# Épica 10 — Contratos de Intermediación — Documento de Prompts Multiagente

**Proyecto:** New Hauz — Plataforma Inmobiliaria Monolítica
**Épica actual:** Épica 10 — Contratos de Intermediación (Autorización de Promoción y Venta)
**RFCs cubiertos:** RFC-063 → RFC-070
**Stack:** Laravel 13.x + PHP 8.3 · PostgreSQL + PostGIS · Filament v3 · Livewire 3 · Tailwind CSS · `spatie/laravel-medialibrary` · `spatie/laravel-permission` · `barryvdh/laravel-dompdf` (o equivalente) · generación de QR
**Roster de este pipeline:** solo **Claude** y **Codex** (sin agente auditor externo intermedio) — distinto del patrón de 3 agentes usado en Épicas 1-9.
**Rama base:** `develop`
**Rama de trabajo:** `feature/epica-10-contratos-intermediacion`

---

## 0. Cómo usar este documento

Este documento orquesta la Épica 10 con **dos agentes en cadena**: Claude asume doble rol (arquitecto y codificador), Codex asume doble rol (auditor y cierre técnico). Cada prompt es **autónomo y copiable**: incluye su propio bloque de encabezado con la referencia del proyecto y el seguimiento de fases previas, de modo que el agente que lo reciba tenga contexto completo sin depender de la conversación anterior.

### Roster de agentes

| Agente | Rol | Responsabilidad |
| :---- | :---- | :---- |
| **Claude** | Arquitecto + Codificador | Diseña la épica a partir de RFC-063→070, corrige el diseño tras auditoría, e implementa el código por lotes |
| **Codex** | Auditor + Cierre técnico | Audita diseño e implementación (evidencia ejecutada, no solo lectura), reconcilia hallazgos y cierra técnicamente la épica |

Codex audita SIN reimplementar: su valor es la mirada independiente y escéptica sobre lo que Claude diseñó/codificó, con verificación real en terminal/BD, no por intuición. Claude no se audita a sí mismo entre P1 y P4 — pasa siempre por Codex antes de avanzar.

### Flujo de la cadena

```
P1 ─ Claude   →  Diseño técnico              →  docs/epicas/epica-10-contratos-intermediacion.md
P2 ─ Codex    →  Auditoría de diseño          →  docs/audits/epica-10-auditoria-diseno.md
P3 ─ Claude   →  Corrección de diseño         →  docs/epicas/epica-10-contratos-intermediacion.md (actualizado)
P4 ─ Claude   →  Implementación por lotes     →  código fuente + tests (rama feature/epica-10-contratos-intermediacion)
P5 ─ Codex    →  Auditoría de implementación  →  docs/audits/epica-10-auditoria-implementacion.md
P6 ─ Codex    →  Cierre técnico               →  docs/cierres/epica-10-cierre-tecnico.md
```

**Regla de avance:** ningún prompt se ejecuta hasta que el anterior cierra su Definition of Done. Si P2 arroja `Aprobado con observaciones` o `Rechazado`, P3 es obligatorio antes de P4. Si P5 arroja hallazgos críticos, P4 reabre el lote afectado antes de pasar a P6.

---

## 1. Bloque de encabezado de referencia (común a todos los prompts)

Este bloque se incrusta al inicio de **cada** prompt.

```
═══════════════════════════════════════════════════════════════
PROYECTO: New Hauz — Plataforma Inmobiliaria (monolito Laravel)
ÉPICA EN CURSO: Épica 10 — Contratos de Intermediación (RFC-063 → RFC-070)
RAMA BASE: develop   ·   RAMA DE TRABAJO: feature/epica-10-contratos-intermediacion
───────────────────────────────────────────────────────────────
CONTRATOS PREVIOS QUE ESTA ÉPICA CONSUME (verificar en código, no asumir):
  · RFC-011 Modelo Usuario — el agente que genera el contrato es un User
    existente con rol agente/admin/owner (spatie/laravel-permission).
  · RFC-006/RFC-012 Roles y Permisos — matriz base owner/admin/agente.
  · RFC-007 Media Library — para adjuntar identificación oficial (evidencia)
    y el PDF final del contrato.
  · RFC-019 Modelo Inmueble (Épica 4) — el contrato NO referencia Property;
    los datos del inmueble a promover viven únicos dentro del contrato. No
    crear FK a properties.
  · RFC-044 Tracking WhatsApp / RFC-052 Integraciones Externas — envío del
    enlace/QR por WhatsApp en paralelo al email.
  · RFC-053 Notificaciones Avanzadas — canal de notificaciones a cliente,
    agente y administrador.
  · RFC-054 Automatizaciones — jobs de expiración, recordatorios, vencimiento
    y retención de 2 años.
  · RFC-057 Auditoría y Trazabilidad — registro de cada evento del contrato
    con actor, IP y fecha/hora (interno y del formulario público).
  · RFC-058 Preparación Multisucursal — el folio debe ser único GLOBAL, no
    por sucursal.
───────────────────────────────────────────────────────────────
REGLA DE ORO — NO TOCAR ÉPICAS ANTERIORES:
  Toda extensión es ADITIVA. No se modifican migraciones existentes de User,
  Property, Media ni Zone. El contrato de intermediación es una entidad
  independiente del catálogo de Property: no la referencia, no la crea al
  firmarse. Cualquier tabla, policy o permiso nuevo debe declararse explíci-
  tamente en el diseño antes de implementarse.
═══════════════════════════════════════════════════════════════
```

---

## 2. Alcance de la Épica 10 (resumen normativo para los agentes)

| RFC | Nombre | Núcleo técnico | DoD |
| :---- | :---- | :---- | :---- |
| RFC-063 | Modelo de Contrato de Intermediación | Entidad `ContratoIntermediacion`, migración, relaciones, máquina de estados | Modelo operativo con migración, relaciones y transiciones válidas |
| RFC-064 | Folio y QR | Folio alfanumérico de 8 caracteres único global, generación/validación de colisiones, QR + enlace público, expiración 72h, un solo uso efectivo | Folio y QR generados, sin colisiones, expirables |
| RFC-065 | Formulario Interno de Captura | Formulario en Filament para que el agente capture datos previos y genere el contrato | CRUD funcional en `/admin` respetando permisos |
| RFC-066 | Formulario Público del Cliente | Vista pública (sin login) para completar datos, revisar clausulado dinámico y decidir firmar/rechazar | Formulario público operativo, un solo uso, sin exponer datos de otros contratos |
| RFC-067 | Firma Electrónica y Evidencia | Captura de firma en canvas, evidencia (IP, user-agent, timestamp, hash), sin OTP adicional | Firma capturada con evidencia reforzada persistida |
| RFC-068 | PDF, Sello Digital y Almacenamiento | Generación del PDF final, sello digital SVG con mini-QR de verificación, hash SHA-256, vista pública de verificación por folio | PDF final generado y verificable, sin exponer datos personales en la vista de verificación |
| RFC-069 | Estatus, Notificaciones y Automatizaciones | Jobs de expiración, recordatorios, vencimiento por vigencia, retención de 2 años con lista de eliminación pendiente confirmada por Owner | Automatizaciones activas y notificaciones enviadas por email + WhatsApp |
| RFC-070 | Panel de Seguimiento de Contratos | Panel interno (Filament) para consultar, filtrar y dar seguimiento a los contratos | Panel operativo con filtros por estatus/agente/fecha, respetando restricción de identificaciones solo-Owner |

**Decisiones ya cerradas en el RFC de épica (no reabrir sin justificación):** firma electrónica simple in-house (sin NOM-151 por ahora); sin OTP adicional (el QR/enlace es la única barrera, de un solo uso); el inmueble vive solo dentro del contrato (sin FK a Property); exclusividad y vigencia variables por contrato; solo el cliente firma, la inmobiliaria valida con sello digital; una sola plantilla con clausulado dinámico según exclusividad/tipo de operación; identificación oficial solo como evidencia adjunta, sin repositorio aparte; envío simultáneo email + WhatsApp; reenvío conserva el mismo folio; retención de 2 años con confirmación manual del Owner antes de eliminar; roles: generan Agente/Admin/Owner, cancelan Admin/Owner, ven identificaciones solo Owner.

**Cobertura QA:** QA-151 → QA-180. **Definition of Done de la épica:** ciclo completo del contrato operativo end-to-end (generación → envío → firma/rechazo → PDF con sello → panel de seguimiento), con automatizaciones y retención funcionando, sin tocar el catálogo de Property.

---

# PROMPT 1 — Diseño Técnico de la Épica (Agente: Claude)

Objetivo: producir el documento de diseño `docs/epicas/epica-10-contratos-intermediacion.md`, con el mismo rigor que los diseños aprobados de épicas anteriores (ver `docs/epicas/epica-4-inmuebles.md` como referencia de nivel de detalle).

```
[INCRUSTAR BLOQUE DE ENCABEZADO DE REFERENCIA — Sección 1]

ROL: Eres el arquitecto senior de Laravel a cargo del diseño técnico de la
Épica 10 — Contratos de Intermediación. No escribes código de producción
todavía: produces el DOCUMENTO DE DISEÑO que tú mismo implementarás después,
una vez que Codex audite y tú corrijas el diseño.

ENTRADA:
  · docs/rfc/EPICA-10-CONTRATOS-DE-INTERMEDIACION.md (alcance general y
    16 decisiones ya tomadas — no las reabras sin justificación técnica)
  · RFC-063 Modelo de Contrato de Intermediación
  · RFC-064 Generación de Folio y QR
  · RFC-065 Formulario Interno de Captura
  · RFC-066 Formulario Público del Cliente
  · RFC-067 Firma Electrónica y Evidencia
  · RFC-068 PDF, Sello Digital y Almacenamiento
  · RFC-069 Estatus, Notificaciones y Automatizaciones
  · RFC-070 Panel de Seguimiento de Contratos
  · Contratos previos citados en el bloque de encabezado (confirma en código
    real con grep/lectura directa, no asumas nombres)

REGLA DE ORO — NO TOCAR ÉPICAS ANTERIORES:
  Toda extensión es ADITIVA. El contrato de intermediación NO tiene FK a
  properties: los datos del inmueble a promover son columnas propias del
  contrato. Documenta esto explícitamente para que no se reintroduzca por
  error durante la implementación.

ENTREGABLE: docs/epicas/epica-10-contratos-intermediacion.md con TODAS estas
secciones:

  1.  Contexto y Dependencias (tabla de contratos consumidos por RFC origen
      y estado real verificado en código).
  2.  Objetivos: qué entrega y qué NO entrega la épica (fuera de alcance fase
      1: firma NOM-151, cobro de comisiones, alta automática en catálogo).
  3.  Alcance Funcional (tabla funcionalidad ↔ actor: agente/cliente/admin-
      owner/sistema).
  4.  Alcance Técnico (árbol de archivos app/, database/, resources/views/
      públicas a crear).
  5.  RFC-063 — Modelo ContratoIntermediacion:
        · Migración completa (folio único, datos cliente, datos inmueble,
          condiciones, trazabilidad de timestamps por evento, motivo de
          rechazo, hash del documento) + índices + softDeletes si aplica.
        · Enums de dominio: EstadoContrato (Generado/Enviado/Leído/Firmado/
          Rechazado/Expirado/Cancelado/Vencido), TipoOperacion (venta/renta/
          renta con opción a compra).
        · Modelo: fillable, casts, relaciones (agente, accesos/tokens,
          evidencia de firma, documento final, eventos de auditoría),
          máquina de estados con transiciones válidas explícitas (diagrama).
  6.  RFC-064 — Folio y QR:
        · Algoritmo de generación del folio de 8 caracteres alfanuméricos,
          único GLOBAL (no por sucursal, RFC-058), con reintento en colisión.
        · Modelo/tabla de accesos (histórico de tokens emitidos por folio,
          incluyendo reenvíos), expiración (72h configurable), regla de un
          solo uso efectivo (invalidado tras firma/rechazo).
        · Librería de generación de QR a usar (confirma si el proyecto ya
          tiene una dependencia instalada antes de proponer una nueva; si es
          nueva, justifícala frente a alternativas).
        · Reenvío: mismo folio, nuevo token de acceso, vuelve a Enviado.
  7.  RFC-065 — Formulario Interno (Filament):
        · Resource/formulario para captura por el agente: datos cliente +
          datos inmueble + condiciones del contrato.
        · Permisos: generar (Agente/Admin/Owner), cancelar (Admin/Owner),
          enviar/reenviar (Agente/Admin/Owner) — Policy como fuente única.
        · Al guardar: dispara generación de folio/QR (RFC-064) y envío
          (RFC-069) o los deja como acciones explícitas — decide y justifica.
  8.  RFC-066 — Formulario Público del Cliente:
        · Ruta pública sin autenticación, resuelta por folio+token, con
          validación de expiración y de uso único.
        · Clausulado dinámico: una sola plantilla, contenido variable según
          exclusividad (sí/no) y tipo de operación (venta/renta/renta con
          opción a compra) — mecanismo de armado (Blade condicional, bloques,
          etc.).
        · Aviso de privacidad con aceptación explícita antes de capturar
          datos personales e identificación oficial.
        · Transición de estatus a Leído/Visto en la primera apertura.
  9.  RFC-067 — Firma Electrónica y Evidencia:
        · Captura de firma en canvas (JS vanilla, sin librerías nuevas salvo
          justificación) y envío del trazo al backend.
        · Evidencia capturada: IP, user-agent, fecha/hora servidor, hash
          SHA-256 del documento final. Persistencia de esta evidencia junto
          al contrato (hasOne Evidencia).
        · Explícito: sin OTP adicional; el acceso de un solo uso por
          QR/enlace es la única barrera.
  10. RFC-068 — PDF, Sello Digital y Almacenamiento:
        · Generación del PDF final (librería a confirmar: dompdf u otra ya
          instalada) con datos del contrato + evidencia de firma.
        · Sello digital: SVG proporcionado por el equipo (usar placeholder
          documentado si aún no existe el archivo real), con folio en texto
          y mini-QR de verificación apuntando a `/verificar/{folio}`.
        · Cálculo y persistencia del hash SHA-256 del PDF final.
        · Vista pública de verificación: folio, estatus, fecha de firma, y
          opción de subir el PDF para comparar hash. Sin datos personales.
        · Almacenamiento vía Media Library (colección dedicada, acceso
          restringido para identificación oficial).
  11. RFC-069 — Estatus, Notificaciones y Automatizaciones:
        · Jobs (Laravel scheduled): expiración sin respuesta, recordatorio
          previo a expiración, marcar Vencido por fin de vigencia, job de
          retención de 2 años → lista de eliminación pendiente con
          confirmación manual del Owner (NO borrado automático).
        · Notificaciones (RFC-053) al cliente (enlace enviado, recordatorio,
          copia de contrato firmado), al agente (visto/firmado/rechazado/por
          expirar), al admin/owner (visibilidad opcional).
        · Canal dual email + WhatsApp (RFC-044) desde el primer envío.
  12. RFC-070 — Panel de Seguimiento de Contratos:
        · Filament Resource/Page de solo consulta con filtros (estatus,
          agente, rango de fechas, tipo de operación).
        · Restricción dura: solo Owner ve identificaciones oficiales
          adjuntas — impleméntalo en Policy, no solo ocultando en la UI.
  13. Modelo de Datos completo (esquema final: contratos_intermediacion,
      accesos/tokens, evidencias_firma, y cualquier tabla auxiliar, con
      índices y FKs).
  14. Seguridad — Mapa de controles: Policy por cada modelo nuevo; ruta
      pública sin sesión NO debe exponer IDs internos ni permitir IDOR sobre
      otros folios; identificación oficial con acceso restringido a Owner
      (ni siquiera Admin) tanto en Policy como en la URL de Media Library.
  15. Estrategia de Testing (Unit: enums, generación de folio sin colisión,
      máquina de estados, cálculo de hash; Feature: flujo completo generación
      → envío → firma/rechazo → PDF, expiración por job, retención con
      confirmación de Owner, restricción de identificaciones; Regresión
      Épicas 1/2/4). RefreshDatabase sobre PostgreSQL de test, sin SQLite.
  16. Riesgos Técnicos (tabla prob/impacto/mitigación). Incluir al menos:
      colisión de folio a escala, condición de carrera en "un solo uso" si
      el cliente abre dos pestañas, fuerza probatoria real de la firma
      simple, tamaño/legibilidad del PDF con evidencia, borrado accidental
      en el job de retención.
  17. Criterios de Aceptación QA-151 → QA-180 (tabla ID/caso/verificación).
  18. Plan de Implementación por Lotes A→H, estrictamente incremental, cada
      lote con su propia DoD y comandos de verificación:
        Lote A — Modelo ContratoIntermediacion + migración + máquina de
                  estados + enums (RFC-063)
        Lote B — Folio, tabla de accesos/tokens y generación de QR (RFC-064)
        Lote C — Formulario interno Filament + permisos + Policy (RFC-065)
        Lote D — Formulario público del cliente + clausulado dinámico +
                  aviso de privacidad (RFC-066)
        Lote E — Firma electrónica + evidencia + hash (RFC-067)
        Lote F — PDF final + sello digital SVG + vista de verificación
                  pública (RFC-068)
        Lote G — Notificaciones (email+WhatsApp) + jobs de expiración,
                  recordatorio, vencimiento y retención de 2 años (RFC-069)
        Lote H — Panel de seguimiento (RFC-070) + Tests (Unit/Feature/
                  Regresión) + cierre
  19. Checklist de Cierre Técnico (estado Pendiente por ítem).
  20. Decisiones Diferidas / Fuera de Alcance (firma NOM-151 certificada,
      cobro de comisiones, alta automática del inmueble en catálogo público).

FORMATO: Markdown, mismo estilo y nivel de detalle que los diseños de
épicas anteriores. Incluye snippets de código PHP reales (migraciones,
enums, modelo, policy, resource, servicio de generación de folio/PDF)
listos para que tú mismo los implementes en el Prompt 4.

DoD del prompt: el documento queda completo, internamente consistente, sin
contradicciones con los contratos de Épicas 1/2/4 ni con las 16 decisiones
ya cerradas en EPICA-10-CONTRATOS-DE-INTERMEDIACION.md, y listo para
auditoría de Codex.
```

---

# PROMPT 2 — Auditoría de Diseño (Agente: Codex)

Objetivo: auditar el diseño producido en P1 con mirada independiente y escéptica, verificando contra el código real del repositorio, no por intuición.

```
[INCRUSTAR BLOQUE DE ENCABEZADO DE REFERENCIA — Sección 1]

ROL: Eres el auditor técnico (Codex). Auditas el DISEÑO de la Épica 10
contenido en docs/epicas/epica-10-contratos-intermediacion.md. Todavía NO
hay código de producción: evalúas la especificación. No reimplementas ni
rediseñas: tu valor es la mirada independiente.

DOCUMENTO AUDITADO: docs/epicas/epica-10-contratos-intermediacion.md

QUÉ VERIFICAR (confirma contra el código del repo con el terminal — grep,
lectura directa de composer.json/composer.lock, artisan route:list — no
apruebes por intuición):
  1.  Aditividad real: ¿el diseño NO crea FK a properties? ¿NO modifica
      migraciones de User/Property/Zone/Media existentes?
  2.  Folio único global: ¿la estrategia de generación + reintento en
      colisión es correcta a nivel de concurrencia (dos agentes generando
      folio casi simultáneamente)? ¿el índice único en BD respalda la
      unicidad, no solo la validación en PHP?
  3.  Un solo uso efectivo del enlace/QR: ¿el diseño realmente invalida el
      acceso tras firma/rechazo? ¿hay condición de carrera si el cliente
      abre el enlace en dos pestañas y envía en ambas casi al mismo tiempo?
  4.  Máquina de estados: ¿todas las transiciones documentadas en RFC-063 y
      en el diseño coinciden? ¿hay transiciones inválidas posibles (p.ej.
      Firmado → Enviado) que el diseño no bloquea explícitamente?
  5.  Dependencia de librería de QR/PDF: ¿confirma si ya existe una
      dependencia instalada en composer.lock antes de proponer una nueva?
      ¿la nueva dependencia (si aplica) genera conflicto real?
  6.  Seguridad del formulario público: ¿evita IDOR (folio adivinable,
      exposición de datos de otro contrato)? ¿el token de acceso es
      suficientemente aleatorio y no derivable del folio?
  7.  Identificación oficial: ¿la Policy restringe realmente a Owner (ni
      Admin) tanto el acceso al modelo como la URL de Media Library, no solo
      la UI de Filament?
  8.  Sello digital y hash: ¿el hash se calcula sobre el documento final
      correcto (post-sello, no pre-sello)? ¿la vista pública de verificación
      evita filtrar datos personales?
  9.  Automatizaciones de retención: ¿el job de 2 años realmente NO borra
      sin confirmación del Owner? ¿existe el estado intermedio "lista de
      eliminación pendiente" como el RFC-069/EPICA-10 lo exige?
  10. Notificaciones: ¿reutiliza correctamente RFC-053/RFC-044 sin reinventar
      un canal propio?
  11. Cobertura de tests y trazabilidad QA-151→QA-180.
  12. Sobreingeniería / tablas o servicios innecesarios / contratos diferidos
      mal documentados.

ENTREGABLE OBLIGATORIO — generar el archivo:
    docs/audits/epica-10-auditoria-diseno.md

  con esta estructura:
    · Encabezado: Proyecto, Fecha, Auditor (Codex), Documento auditado.
    1.  Veredicto (Aprobado / Aprobado con observaciones / Rechazado)
    2.  Hallazgos críticos
    3.  Hallazgos medios
    4.  Hallazgos menores
    5.  Sobreingeniería detectada
    6.  Riesgos de implementación
    7.  Riesgos de seguridad (foco: IDOR del formulario público, acceso a
        identificaciones oficiales, condición de carrera del "un solo uso")
    8.  Recomendaciones obligatorias
    9.  Recomendaciones opcionales
    10. Evaluación de cada decisión ya cerrada en EPICA-10 (¿alguna requiere
        reabrirse por hallazgo técnico real? — el default es NO reabrir)
    11. Preguntas abiertas
    12. Checklist de corrección para Claude (Prompt 3)

Cada hallazgo: cita archivo/sección del diseño, impacto y corrección
concreta. Usa el terminal para confirmar cualquier afirmación sobre el
código existente (contratos previos, dependencias) antes de reportarla como
hallazgo.

DoD del prompt: archivo docs/audits/epica-10-auditoria-diseno.md creado con
veredicto emitido.
```

---

# PROMPT 3 — Corrección de Diseño (Agente: Claude)

Objetivo: aplicar al documento de diseño las correcciones obligatorias de la auditoría P2.

```
[INCRUSTAR BLOQUE DE ENCABEZADO DE REFERENCIA — Sección 1]

ROL: Eres el arquitecto (Claude). Recibes la auditoría de Codex y aplicas
las correcciones al documento docs/epicas/epica-10-contratos-intermediacion.md.

ENTRADA:
  · docs/epicas/epica-10-contratos-intermediacion.md (diseño original)
  · docs/audits/epica-10-auditoria-diseno.md (hallazgos de Codex)

INSTRUCCIONES:
  1.  Aplica TODAS las recomendaciones obligatorias y la checklist de
      corrección de la auditoría.
  2.  Para cada hallazgo que NO apliques, documenta la razón técnica en una
      sección "Registro de Cambios desde la Auditoría", distinguiendo
      hallazgos aplicados de hallazgos rechazados con justificación.
  3.  Si la auditoría propone reabrir alguna de las 16 decisiones ya
      cerradas en EPICA-10-CONTRATOS-DE-INTERMEDIACION.md, evalúa con rigor:
      solo se reabre si hay un hallazgo técnico real (no de negocio); si es
      de negocio, queda registrado como pregunta para el responsable de la
      épica, no se decide unilateralmente.
  4.  Añade los criterios QA adicionales que la auditoría haya solicitado.
  5.  Cierra el documento con una sección "Cierre Técnico del Diseño":
      confirmaciones de arquitectura + veredicto final
      "APROBADO PARA IMPLEMENTACIÓN".
  6.  No introduzcas regresiones sobre los contratos de Épicas 1/2/4.

DoD del prompt: documento actualizado, hallazgos obligatorios resueltos,
registro de cambios trazable, veredicto final de diseño aprobado. Listo
para iniciar el Lote A de implementación.
```

---

# PROMPT 4 — Implementación por Lotes (Agente: Claude)

Objetivo: implementar el código de la Épica 10 siguiendo el propio diseño aprobado, lote por lote (A→H), sin avanzar hasta cerrar la DoD de cada lote.

```
[INCRUSTAR BLOQUE DE ENCABEZADO DE REFERENCIA — Sección 1]

ROL: Eres el mismo arquitecto (Claude), ahora en rol de codificador.
Implementas la Épica 10 exactamente como especifica tu propio diseño ya
aprobado tras la auditoría de Codex. No rediseñas: ejecutas. Si durante la
implementación descubres que el diseño es inviable en algún punto, PÁRATE,
documenta la desviación y decide antes de continuar (no improvises en
silencio).

ENTRADA:
  · docs/epicas/epica-10-contratos-intermediacion.md (diseño APROBADO PARA
    IMPLEMENTACIÓN, con el "Registro de Cambios desde la Auditoría")
  · docs/audits/epica-10-auditoria-diseno.md (para contexto de hallazgos)

REGLAS DURAS:
  · Trabaja en la rama feature/epica-10-contratos-intermediacion desde
    develop (sincronizada: git pull origin develop && git rebase develop).
  · Implementación ADITIVA: no modificas migraciones de User/Property/Zone/
    Media. El contrato de intermediación NO tiene FK a properties.
  · Las Policies son la única fuente de autorización; Filament y las rutas
    públicas las consumen, no las reemplazan.
  · La restricción "identificaciones oficiales solo Owner" se implementa en
    Policy Y en el control de acceso a Media Library, no solo ocultando en
    la UI.
  · Cada migración debe ser compatible con PostgreSQL (no asumir SQLite).
  · Ejecuta la suite de tests al cerrar cada lote. Commits atómicos con la
    convención del proyecto (feat:/fix:/test:/refactor:).

PLAN POR LOTES (orden estricto, cada uno con su DoD — usa el detalle exacto
del Plan de Implementación del diseño aprobado, sección correspondiente):

  LOTE A — Modelo ContratoIntermediacion (RFC-063)
    · Enums EstadoContrato, TipoOperacion. Migración completa. Modelo con
      fillable, casts, relaciones, máquina de estados con transiciones
      validadas (método guardado que rechaza transiciones inválidas).
    DoD: migra sin error; factory persiste; transiciones inválidas lanzan
    excepción; transiciones válidas actualizan estatus y timestamp.

  LOTE B — Folio y QR (RFC-064)
    · Servicio de generación de folio (8 caracteres, único global, retry en
      colisión con índice único en BD). Tabla de accesos/tokens con
      expiración (72h) y flag de uso. Generación de QR.
    DoD: folio único verificado con test de colisión forzada; token expira
    correctamente; reenvío conserva folio y emite nuevo token.

  LOTE C — Formulario Interno Filament (RFC-065)
    · Resource/formulario de captura para el agente. Policy
      (generar: agente/admin/owner; cancelar: admin/owner; enviar/reenviar:
      agente/admin/owner).
    DoD: CRUD funcional en /admin respetando permisos; acción de generar
    dispara folio+QR del Lote B.

  LOTE D — Formulario Público del Cliente (RFC-066)
    · Ruta pública resuelta por folio+token, validando expiración y uso
      único. Clausulado dinámico (una plantilla, contenido condicional por
      exclusividad/tipo de operación). Aviso de privacidad con aceptación
      explícita.
    DoD: acceso vía token válido funciona; token expirado o usado devuelve
    error claro; clausulado cambia según exclusividad y tipo de operación;
    primera apertura marca Leído/Visto.

  LOTE E — Firma Electrónica y Evidencia (RFC-067)
    · Captura de firma en canvas (JS vanilla). Endpoint que persiste trazo +
      evidencia (IP, user-agent, timestamp servidor).
    DoD: firma se persiste con evidencia completa; sin OTP adicional; el
    acceso queda invalidado tras el envío (firma o rechazo).

  LOTE F — PDF, Sello Digital y Verificación (RFC-068)
    · Generación del PDF final con datos + evidencia. Sello digital (usa
      placeholder documentado si el SVG real del equipo aún no está
      disponible). Hash SHA-256 del documento final persistido. Vista
      pública de verificación por folio.
    DoD: PDF se genera y adjunta vía Media Library; hash persistido
    coincide con el recalculado; vista de verificación no expone datos
    personales; sube-y-compara hash funciona.

  LOTE G — Notificaciones y Automatizaciones (RFC-069)
    · Notificaciones (RFC-053) a cliente/agente/admin por email + WhatsApp
      (RFC-044). Jobs programados: expiración, recordatorio, vencimiento por
      vigencia, retención de 2 años → lista de eliminación pendiente con
      confirmación manual del Owner.
    DoD: notificaciones se disparan en los eventos correctos; job de
    expiración marca Expirado sin respuesta; job de retención NO borra sin
    confirmación explícita del Owner (verificar con test que el borrado
    automático NO ocurre).

  LOTE H — Panel de Seguimiento y Cierre (RFC-070)
    · Resource/Page de solo consulta con filtros (estatus, agente, fecha,
      tipo de operación). Restricción dura: identificaciones solo Owner.
    · Tests: Unit (enums, folio, máquina de estados, hash), Feature (flujo
      completo generación→envío→firma/rechazo→PDF, expiración por job,
      retención, restricción de identificaciones), Regresión Épicas 1/2/4.
    DoD: php artisan test en verde; QA-151→QA-180 cubiertos; sin
    regresiones; panel operativo con filtros y restricción verificada.

ENTREGABLE: código fuente + tests por lote, commits atómicos, listo para
auditoría de implementación de Codex.

DoD del prompt: los ocho lotes cerrados, suite completa en verde, sin
regresiones de épicas previas, listo para Prompt 5.
```

---

# PROMPT 5 — Auditoría de Implementación (Agente: Codex)

Objetivo: auditar el código implementado en P4 ejecutando el sistema real (terminal + BD, y navegador si es posible), no solo leyendo el diff.

```
[INCRUSTAR BLOQUE DE ENCABEZADO DE REFERENCIA — Sección 1]

ROL: Eres el auditor de implementación (Codex). Auditas CÓDIGO YA ESCRITO de
la Épica 10 (Lotes A→H) contra el diseño aprobado y los contratos de
Épicas 1/2/4. Tu valor está en EJECUTAR el sistema real, no en leer el diff.

ENTRADA:
  · Rama feature/epica-10-contratos-intermediacion
  · docs/epicas/epica-10-contratos-intermediacion.md (diseño aprobado)
  · docs/audits/epica-10-auditoria-diseno.md (hallazgos de la fase de diseño)

VERIFICACIÓN EN VIVO (OBLIGATORIA):
  1.  composer install / validate en sync; php artisan migrate:fresh corre
      limpio contra la BD de test (PostgreSQL real, no SQLite).
  2.  php artisan test — toda la suite verde.
  3.  Folio: intenta forzar una colisión (inserta directo en BD un folio y
      genera otro) → el sistema reintenta y no falla. Índice único real en
      la tabla, confirmado por SQL directo (\d en psql o equivalente).
  4.  Un solo uso: firma o rechaza un contrato desde el formulario público
      y verifica que reutilizar el mismo enlace/token ya NO permite enviar
      de nuevo (HTTP real, no solo revisión de código).
  5.  Máquina de estados: intenta forzar por código/tinker una transición
      inválida (p.ej. Firmado → Enviado) → debe rechazarse.
  6.  IDOR: intenta acceder al formulario público de otro folio con un token
      inválido o ajeno → debe fallar con error claro, sin filtrar datos.
  7.  Identificación oficial: confirma en vivo que un usuario Admin (no
      Owner) NO puede ver la identificación adjunta (403 real, no solo UI
      oculta); Owner sí puede.
  8.  Firma y evidencia: firma un contrato de prueba end-to-end y confirma
      que IP, user-agent, timestamp y hash quedan persistidos correctamente.
  9.  PDF y sello: genera el PDF final de un contrato firmado, confirma el
      hash SHA-256 persistido coincide con el recalculado del archivo, y que
      la vista pública de verificación NO expone datos personales.
  10. Automatizaciones: ejecuta manualmente (o simula fecha) el job de
      expiración y el de retención de 2 años → confirma que el de retención
      mueve a "lista de eliminación pendiente" SIN borrar, y que requiere
      confirmación explícita del Owner para eliminar.
  11. Notificaciones: confirma que los eventos correctos disparan
      notificación (email y/o WhatsApp) sin duplicados.
  12. Regresión: Épicas 1/2/4 (Property, roles, Media Library) siguen
      funcionando sin alteración.

QUÉ MÁS REVISAR:
  · Fidelidad implementación ↔ diseño aprobado, lote por lote.
  · ¿Se tocó por error alguna migración o archivo de Property/User/Zone que
    no debía tocarse?
  · Higiene de repo: caché/artefactos versionados, dependencias nuevas
    reflejadas en composer.lock.
  · Código muerto, comentarios obsoletos, contratos diferidos mal
    etiquetados.

ENTREGABLE OBLIGATORIO — generar el archivo:
    docs/audits/epica-10-auditoria-implementacion.md

  con esta estructura:
    · Encabezado: Proyecto, Fecha, Auditor (Codex), rama auditada.
    1.  Veredicto (Aprobado / Aprobado con observaciones / Rechazado)
    2.  Hallazgos críticos (con evidencia: SQL, HTTP, captura del DOM, PDF)
    3.  Hallazgos medios
    4.  Hallazgos menores
    5.  Regresiones detectadas
    6.  Riesgos de seguridad (foco: IDOR, acceso a identificaciones, "un
        solo uso" real)
    7.  Riesgos de mantenimiento
    8.  Tests faltantes
    9.  Correcciones obligatorias (para reabrir el lote correspondiente si
        el veredicto no es Aprobado)
    10. Correcciones recomendadas
    11. Checklist final antes de merge

DoD del prompt: archivo docs/audits/epica-10-auditoria-implementacion.md
creado + veredicto emitido con evidencia real de ejecución, no solo lectura
de código.
```

---

# PROMPT 6 — Cierre Técnico (Agente: Codex)

Objetivo: reconciliar ambas auditorías, verificar el estado final por cuenta propia, y documentar los contratos estables que la Épica 10 deja disponibles para el resto del sistema.

```
[INCRUSTAR BLOQUE DE ENCABEZADO DE REFERENCIA — Sección 1]

ROL: Eres el mismo agente (Codex), ahora cerrando técnicamente la Épica 10
tras su implementación y ambas auditorías. No reimplementas: reconcilias,
verificas el estado final y documentas los contratos estables.

ENTRADA:
  · docs/epicas/epica-10-contratos-intermediacion.md (diseño aprobado)
  · docs/audits/epica-10-auditoria-diseno.md
  · docs/audits/epica-10-auditoria-implementacion.md
  · docs/rfc/EPICA-10-CONTRATOS-DE-INTERMEDIACION.md
  · Rama feature/epica-10-contratos-intermediacion

TAREA:
  1.  Reconcilia cada hallazgo de AMBAS auditorías: marca resuelto o
      justificadamente diferido, citando el archivo/commit donde se cerró.
      Si algún hallazgo crítico de la auditoría de implementación (P5) sigue
      abierto, el cierre NO puede declararse Aprobado — repórtalo como
      bloqueante y detén el cierre.
  2.  Verifica el estado final por vos mismo (no confíes en el reporte de
      P4/P5):
      · php artisan test (BD PostgreSQL real) → suite completa verde.
      · ./vendor/bin/pint --test limpio en los archivos de la Épica 10.
      · composer validate --strict y composer.lock en sync.
  3.  Confirma el cierre de las 16 decisiones de EPICA-10-CONTRATOS-DE-
      INTERMEDIACION.md: cuáles quedaron implementadas tal cual, cuáles se
      ajustaron durante diseño/implementación (con justificación) y cuáles
      siguen legítimamente diferidas (NOM-151, cobro de comisiones, alta
      automática en catálogo).
  4.  Documenta los CONTRATOS ESTABLES que otras épicas/RFCs pueden consumir
      con seguridad, por ejemplo:
      · Modelo `ContratoIntermediacion` y su máquina de estados.
      · Servicio de generación de folio único global (reutilizable si
        futuras épicas necesitan folios similares).
      · Permisos nuevos (p.ej. `contratos.manage`, `contratos.ver-
        identificacion`) y a qué roles quedaron asignados.
      · Colecciones de Media Library usadas (identificación, PDF final).
      · Vista pública de verificación `/verificar/{folio}` como contrato de
        URL estable.
      Anota cualquier divergencia diseño↔implementación que quede como deuda
      técnica explícita.
  5.  Marca la épica como ✅ IMPLEMENTADA (o el estado que corresponda) y dejá
      trazabilidad de las 8 RFCs (RFC-063→070) como implementadas o con su
      estado real.

REGLAS:
  · Aditividad: no cambies comportamiento en esta etapa; solo reconciliación
    y documentación. Si encontrás un hallazgo NUEVO real durante el cierre,
    no lo parchees a escondidas: documentalo como hallazgo abierto con
    severidad y destino (reabrir lote correspondiente).
  · "Firma electrónica simple in-house" es una garantía de trazabilidad, no
    de fuerza probatoria plena (NOM-151) — dejalo explícito en el cierre.

FORMATO DE SALIDA:
  · docs/cierres/epica-10-cierre-tecnico.md con: veredicto final, tabla de
    hallazgos resueltos vs diferidos (ambas auditorías), resultado de la
    verificación (test/pint/composer), contratos estables para épicas
    siguientes, decisiones diferidas vigentes, y checklist de merge final.

DoD del prompt: archivo docs/cierres/epica-10-cierre-tecnico.md creado con
veredicto y checklist de merge. Si el veredicto es Aprobado, la Épica 10
queda lista para merge a develop.
```

---

## 3. Convención de artefactos y persistencia

| Artefacto | Ruta | Generado por |
| :---- | :---- | :---- |
| RFCs de la épica | `docs/rfc/RFC-063` → `RFC-070` | Ya existentes |
| Diseño técnico | `docs/epicas/epica-10-contratos-intermediacion.md` | Claude (P1, P3) |
| Auditoría de diseño | `docs/audits/epica-10-auditoria-diseno.md` | Codex (P2) |
| Código + tests | rama `feature/epica-10-contratos-intermediacion` | Claude (P4) |
| Auditoría de implementación | `docs/audits/epica-10-auditoria-implementacion.md` | Codex (P5) |
| Cierre técnico | `docs/cierres/epica-10-cierre-tecnico.md` | Codex (P6) |

---

## 4. Checklist de orquestación de la Épica 10

| Paso | Prompt | Agente | Salida | Estado |
| :---- | :---- | :---- | :---- | :---- |
| 1 | P1 | Claude | Diseño técnico | Pendiente |
| 2 | P2 | Codex | Auditoría de diseño | Pendiente |
| 3 | P3 | Claude | Diseño corregido y aprobado | Pendiente |
| 4 | P4 | Claude | Implementación Lotes A→H | Pendiente |
| 5 | P5 | Codex | Auditoría de implementación | Pendiente |
| 6 | P6 | Codex | Cierre técnico | Pendiente |
| 7 | — | Responsable de la épica | Merge a `develop` | Pendiente |

---

*Documento de orquestación multiagente — Épica 10 — Contratos de Intermediación · New Hauz*
*Rama de destino: `feature/epica-10-contratos-intermediacion` desde `develop`*
