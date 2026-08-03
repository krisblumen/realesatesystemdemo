# Prompts Multiagente — RFC-062 (Control de Lonas Asignadas)

**Proyecto:** New Hauz · Instancia concreta del playbook multiagente (mismo patrón usado en `docs/prompts/PROMPTS-RFC-061.md`) con los valores de RFC-062 ya sustituidos. Copia y pega cada prompt tal cual, en orden.

Valores aplicados:

| Variable | Valor |
| :---- | :---- |
| RFC_ID | RFC-062 |
| TITULO | Control de Lonas Asignadas |
| SLUG | control-lonas-asignadas |
| EPICA | Épica 9 — Operación de Campo (`docs/rfc/EPICA-9-OPERACION-DE-CAMPO.md`), CD-1 cerrada |
| RAMA | feature/control-lonas-asignadas (ya creada desde `develop`) |

**Asignación de roles de este pipeline (distinta del patrón por defecto de RFC-061):** Etapa 4 (Implementación) la ejecuta **Claude**, no Codex. Etapa 6 (Cierre técnico) la ejecuta **Codex**, no Claude (Arquitecto). Etapa 5 (Auditoría de implementación) se mantiene en Antigravity.

Rutas de handoff de este RFC:

```
docs/rfc/RFC-062-CONTROL-LONAS-ASIGNADAS.md
docs/audits/RFC-062-auditoria-diseno.md
docs/audits/RFC-062-auditoria-implementacion.md
docs/cierres/RFC-062-cierre-tecnico.md
Rama: feature/control-lonas-asignadas (desde develop)
```

Tabla de seguimiento (ya está pegada al inicio del RFC):

```
| Etapa | Agente | Estado | Fecha |
|---|---|---|---|
| 1. Generación del RFC          | Claude (Arquitecto)   | ✅ | 2026-07-13 |
| 2. Auditoría de diseño         | Antigravity           | ✅ | 2026-07-13 |
| 3. Aplicación de correcciones  | Claude (Arquitecto)   | ✅ | 2026-07-13 |
| 4. Implementación              | Claude                | ✅ | 2026-07-13 |
| 5. Auditoría de implementación | Antigravity           | ⬜ | — |
| 6. Cierre técnico              | Codex                 | ⬜ | — |
```

**Contexto crítico de este RFC (inyectado en los prompts):** no hay una sola relación dura que auditar (como el `class_exists` de RFC-060/061); el riesgo central es **de negocio y de seguridad**, no de nombres de clase: (1) la regla "no puede solicitar más lonas de un tipo mientras tenga unidades sin colocar" debe cumplirse por tipo (venta/renta independientes); (2) la evidencia fotográfica debe ser técnicamente imposible de subir desde galería, no sólo "sugerida" vía `capture=`; (3) `endroid/qr-code` es una dependencia nueva sin precedente en el repo.

---

## Etapa 1 · Claude (Arquitecto) — Generar el RFC

Ya completada — ver `docs/rfc/RFC-062-CONTROL-LONAS-ASIGNADAS.md`.

---

## Etapa 2 · Antigravity — Auditoría de DISEÑO

```
Actúa como auditor técnico independiente y escéptico. Auditas el DISEÑO de un RFC
del proyecto New Hauz (Laravel 13 / Filament v3 / PostgreSQL+PostGIS). Todavía NO
hay código: auditas la especificación.

ENTRADA
- RFC a auditar: docs/rfc/RFC-062-CONTROL-LONAS-ASIGNADAS.md
- Dependientes a leer: app/Models/User.php (roles, phone/email), app/Models/Property.php
  (canonical(), scopePublished()), app/Http/Controllers/PropertyPdfController.php (patrón
  de generación de PDF), app/Notifications/LeadAssignedNotification.php (patrón de
  notificación), docs/rfc/RFC-061-zonas-dashboard.md (patrón AgentDashboard/canView()
  por rol), database/seeders/PermissionSeeder.php (convención de permisos).

QUÉ VERIFICAR (confirma contra el código del repo con el terminal, no por intuición)
- CONTRATOS CITADOS: ¿`User::$phone`/`email`, `Property::canonical()`,
  `Property::scopePublished()` existen tal cual el RFC los cita (grep/lectura directa)?
  ¿Hay algún drift entre lo que el RFC asume y el código real?
- REGLA DE BLOQUEO: revisa `LonaEligibilityService::canRequestMore()` (sección 5.3).
  ¿La condición realmente aísla venta de renta? ¿Hay alguna condición de carrera si el
  agente envía dos solicitudes del mismo tipo casi simultáneamente antes de que la
  primera se apruebe? El RFC no lo contempla — decide si es un hallazgo real o aceptable.
- EVIDENCIA SÓLO CÁMARA (sección 5.4): ¿el diseño realmente evita todo `<input type=file>`
  en el flujo de captura? ¿Falta validar tamaño máximo / tipo MIME real del `photoData`
  base64 antes de `addMediaFromBase64` (el RFC no lo especifica)? Un payload gigante o
  no-imagen enviado directamente al método Livewire, ¿está cubierto por alguna validación?
- POLICIES FALTANTES: el RFC lista `LonaUnitPolicy` y `LonaRequestPolicy`, pero
  `LonaBatchResource` (sección 5.7) no tiene una `LonaBatchPolicy` en el árbol de
  archivos. Filament resuelve autorización de Resources vía Policy del modelo — confirma
  si falta y es un hallazgo real.
- DEPENDENCIA NUEVA: confirma que `endroid/qr-code` no genera conflicto con las
  dependencias ya instaladas (`barryvdh/laravel-dompdf`, GD/Imagick de Media Library).
  ¿La alternativa descartada (`simplesoftwareio/simple-qrcode`) tiene algún motivo real
  para preferirse (p. ej. si el proyecto ya usa Imagick vs GD de forma específica)?
- ROL DEL AGENTE ASIGNADO: ¿algo impide que owner/admin asignen un `LonaBatch` a un
  usuario que NO tiene rol `agente`? El RFC no lo restringe explícitamente.
- MEDIA LIBRARY: confirma que `spatie/laravel-medialibrary ^11.23` expone
  `addMediaFromBase64()` y `addMediaFromString()` tal como los usa el RFC (5.4 y 5.5).
- COMPATIBILIDAD POSTGRESQL: nada en las migraciones asume MySQL/SQLite.
- SOBREINGENIERÍA: ¿el modelo de 3 tablas (`LonaBatch`/`LonaUnit`/`LonaRequest`) está
  justificado por el requisito de justificar cada lona individualmente, o se simplifica
  con 2 tablas sin perder esa trazabilidad?

REGLA CLAVE
Usa el terminal para CONFIRMAR (grep de los métodos/columnas citados, revisar la versión
real de spatie/laravel-medialibrary en composer.lock, revisar Policies existentes en
app/Policies/ para ver el patrón de nombres). No apruebes por intuición.

FORMATO DE SALIDA (markdown, guardar en docs/audits/RFC-062-auditoria-diseno.md)
Encabezado · Veredicto (Aprobado | Aprobado con observaciones | Rechazado) · Hallazgos
críticos/medios/menores · Sobreingeniería · Riesgos de implementación · Riesgos de
seguridad (especialmente sobre la garantía "sólo cámara") · Recomendaciones obligatorias ·
opcionales · Evaluación de cada Decisión Diferida (CD-1 a CD-6: ¿cuáles pueden cerrarse
ya por criterio técnico y cuáles siguen siendo de negocio?) · Preguntas abiertas ·
Checklist de corrección para Claude · Checklist de implementación para Codex.

Cada hallazgo: cita archivo/sección, impacto y corrección concreta.
```

---

## Etapa 3 · Claude (Arquitecto) — Aplicar correcciones al RFC

Ya completada — ver el RFC actualizado en `docs/rfc/RFC-062-CONTROL-LONAS-ASIGNADAS.md`, secciones "Registro de Cambios desde la Auditoría" y "Hallazgos No Aplicados / Divergencias con la Auditoría" al final del documento.

Notas de trazabilidad de esta etapa (no un prompt — se aplicó en la misma sesión de arquitectura que generó la auditoría):
- Se aplicaron los 2 hallazgos críticos (C-1 condición de carrera, C-2 falta de `LonaBatchPolicy`), los 3 medios (M-1 confusión de inmueble-QR vs. ubicación-de-colocación, M-2 validación de Base64, M-3 falta de validación de rol/estado) y el menor (Mn-1 tope de cantidad), más la recomendación opcional (forceDelete).
- **Una divergencia deliberada:** no se aceptó el cierre que la auditoría dio a CD-2 (`endroid/qr-code` "cerrada técnicamente") por carecer de evidencia de ejecución real en esa etapa — se mantuvo abierta hasta Lote A.
- **Un hallazgo propio detectado al aplicar la corrección de M-2:** la primera corrección redactada usaba `Rule::make()` para envolver el closure de validación — se verificó contra `vendor/laravel/framework` del proyecto y ese método no existe en `Illuminate\Validation\Rule`. Se corrigió a un closure inline (la forma nativa de Laravel), antes de cerrar la sección.

## Etapa 4 · Claude — Implementación por Lotes

✅ Completada. Claude implementó los Lotes A–E directamente. Resumen:
- **Lote A** — enums (`LonaUnitStatus`, `LonaRequestStatus`; reusa `OperationType`), 3 migraciones (con índice único parcial), modelos, permisos, 3 policies. Test `LonaSchemaTest` (9).
- **Lote B** — `CapturePlacementEvidence` (Livewire, cámara en vivo, sin `<input file>`) + vista. Test `LonaEvidenceCaptureTest` (9).
- **Lote C** — `LonaEligibilityService`, `LonaRequestService`, `LonaBatchApprovalService` (PDF+QR endroid v6, CD-2 cerrada), 3 notificaciones, vista PDF 90×120cm. Tests `LonaEligibilityTest` (7) + `LonaRequestApprovalTest` (8).
- **Lote D** — `LonaBatchResource`, `LonaRequestResource` (aprobar/rechazar), página `AgentLonas` + `AgentLonaUnitsWidget`. Test `LonaResourcesTest` (6).
- **Lote E** — regresión: suite completa verde (368 tests; se actualizó `PermissionSeederTest` por los 2 permisos nuevos).

Verificación visual en navegador (proof adjunto en la sesión): login owner → formulario de asignación → lista de lotes con PDF descargable; login agente → "Mis Lonas" con las unidades y el modal de captura (cámara solicitada, sin selector de archivos).

## Etapa 5 · Antigravity — Auditoría de IMPLEMENTACIÓN

```
Actúa como auditor de implementación independiente en New Hauz. Auditas CÓDIGO YA
ESCRITO (RFC-062, Épica 9 — Control de Lonas Asignadas). Tu valor está en EJECUTAR
el sistema real, no en leer el diff. Tienes terminal y navegador: úsalos.

ENTRADA
- RFC implementado: docs/rfc/RFC-062-CONTROL-LONAS-ASIGNADAS.md (Lotes A–E, decisiones I-1..I-7)
- Rama: feature/control-lonas-asignadas

VERIFICACIÓN EN VIVO (OBLIGATORIA)
1. composer install / validate en sync; php artisan migrate:fresh corre limpio contra inmo_test,
   incluido el índice único parcial lona_requests_agent_tipo_pendiente_unique.
2. php artisan test — toda la suite verde sobre PostgreSQL real (no SQLite).
3. Índice único parcial: intenta insertar dos LonaRequest 'pendiente' del mismo agente+tipo
   por SQL directo → debe fallar; una de tipo distinto → debe pasar.
4. Evidencia SÓLO cámara: confirma que la vista capture-placement-evidence NO contiene
   ningún <input type=file>; que getUserMedia es la única fuente; y que photoData valida
   prefijo MIME (jpeg/png) y tamaño máximo.
5. M-1: tras aprobar un lote CON inmueble para el QR, verifica que las LonaUnit creadas
   tienen property_id NULL (el inmueble del QR NO se copia a la unidad).
6. M-3: grant() a un usuario sin rol agente o suspendido → ValidationException.
7. PDF+QR: aprueba una solicitud con inmueble publicado → el batch tiene media en
   'diseno-pdf'; abre el PDF y confirma el QR apunta a canonical() del inmueble.
8. Aislamiento por rol (en vivo): agente NO accede a /admin/lona-batches ni /admin/lona-requests
   (403); owner/admin sí. Agente accede a /admin/mis-lonas; no-agente 403.
9. Botón "Solicitar más" del agente deshabilitado si tiene unidades pendientes de ese tipo.

QUÉ MÁS REVISAR
- Coincidencia implementación ↔ RFC lote por lote y las decisiones I-1..I-7.
- Regresiones sobre Épica 2 (permisos/roles), Épica 4 (Property) y RFC-061.
- ¿Se tocó por error algún archivo de la lista "Archivos que NO se tocan"?
- Condición de carrera del índice parcial con soft-deletes (deleted_at IS NULL).

FORMATO DE SALIDA (markdown, guardar en docs/audits/RFC-062-auditoria-implementacion.md)
Encabezado + Veredicto · Hallazgos críticos/medios/menores (con evidencia: SQL, HTTP,
captura del DOM, PDF) · Regresiones · Riesgos de seguridad (foco en la garantía "sólo
cámara") · Tests faltantes · Correcciones obligatorias · recomendadas · Checklist final
antes de merge. Actualiza la tabla de seguimiento: etapa 5 en ✅.
```

## Etapa 6 · Codex — Cierre técnico

```
Actúa como ingeniero senior de New Hauz cerrando técnicamente un RFC tras su
implementación y sus dos auditorías (diseño e implementación). No reimplementás:
reconciliás, verificás el estado final y documentás los contratos estables.

ENTRADA
- RFC: docs/rfc/RFC-062-CONTROL-LONAS-ASIGNADAS.md (Épica 9 — Operación de Campo)
- Auditoría de diseño: docs/audits/RFC-062-auditoria-diseno.md
- Auditoría de implementación: docs/audits/RFC-062-auditoria-implementacion.md
- Épica: docs/rfc/EPICA-9-OPERACION-DE-CAMPO.md
- Rama: feature/control-lonas-asignadas (desde develop)

TAREA
1. Reconcilia cada hallazgo de AMBAS auditorías: marcá resuelto o justificadamente
   diferido, citando el archivo/commit donde se cerró.
   - Diseño: C-1 (carrera solicitudes), C-2 (LonaBatchPolicy), M-1 (inmueble QR vs
     colocación), M-2 (validación base64), M-3 (rol/estado en grant), Mn-1 (tope 50),
     Op-1 (forceDelete=false).
   - Implementación: M-IMP-1 (property_id manipulable → validado en el servicio),
     Mn-IMP-1 (grant exige inmueble publicado), recomendación cantidad≤50 en servicio.
     Mn-IMP-2 (ZoneSeeder Polygon/MultiPolygon) queda FUERA de alcance — confirmá que
     sigue fuera y que hay una tarea separada para ello.
2. Verificá el estado final por vos mismo (no confíes en el reporte):
   - `DB_DATABASE=inmo_test php artisan test` → toda la suite verde sobre PostgreSQL real.
   - `./vendor/bin/pint --test` limpio en los archivos de la Épica 9.
   - `composer validate --strict` y `composer.lock` en sync (endroid/qr-code ^6.0 presente).
3. Confirmá el cierre de las decisiones diferidas: CD-1 (Épica 9), CD-2 (endroid v6),
   CD-4 (QA-151..167), CD-5 (max 50), CD-6 (90×120cm). Verificá que siguen ABIERTAS y
   correctamente diferidas: CD-3 (GPS) y R-1 (arte gráfico final de la lona).
4. Documentá los CONTRATOS ESTABLES que otras épicas/RFCs pueden consumir con seguridad:
   - Servicios: `LonaEligibilityService::canRequestMore(User, OperationType): bool`,
     `LonaRequestService::submit(...)`, `LonaBatchApprovalService::grant(...)` / `reject(...)`.
   - Permisos: `lonas.manage` (owner/admin), `lonas.place` (agente).
   - Modelos/colecciones media: `LonaBatch` ('diseno-pdf'), `LonaUnit` ('evidencia').
   - Índice único parcial `lona_requests_agent_tipo_pendiente_unique`.
   - Reutilización de `OperationType` (I-1) y registro de policies en AppServiceProvider (I-3).
   Anotá cualquier divergencia diseño↔implementación que quede como deuda.
5. Marcá el RFC como ✅ IMPLEMENTADO y actualizá la tabla de seguimiento (etapa 6 ✅).

REGLAS
- Aditividad: no cambies comportamiento en esta etapa; sólo reconciliación y documentación.
  Si encontrás un hallazgo NUEVO real, no lo parchees a escondidas: documentalo como
  hallazgo abierto con severidad y destino.
- "Sólo cámara" es garantía de UI, no forense (R-2): dejalo explícito en el cierre.

FORMATO DE SALIDA
- RFC actualizado (estado ✅ IMPLEMENTADO, tabla de seguimiento etapa 6 ✅).
- docs/cierres/RFC-062-cierre-tecnico.md con: veredicto, tabla de hallazgos resueltos vs
  diferidos (ambas auditorías), resultado de la verificación (test/pint/composer),
  contratos estables para épicas siguientes, decisiones diferidas vigentes (CD-3, R-1,
  Mn-IMP-2), y checklist de merge final.
```

---

*Instancia de RFC-062 generada el 2026-07-13 a partir del patrón de `docs/prompts/PROMPTS-RFC-061.md`.*
