# Reauditoría de diseño — DEMO multi-inquilino

**Proyecto:** realestatesystemDemo
**Fecha:** 2026-08-03
**Alcance:** reauditar el diseño después de los commits de corrección hasta `16ba41b`, contra documentación, código real y `vendor/`.
**Resultado:** reporte vigente; reemplaza el veredicto anterior de este mismo archivo.

## Evidencia verificada en código real

- `.env.testing` usa la base correcta de tests: `DB_DATABASE=demo_test` y `DB_PASSWORD=` (`.env.testing:36-41`). `phpunit.xml` también fuerza `DB_DATABASE=demo_test` (`phpunit.xml:29-30`).
- `pgrep -fl "artisan test"` no pudo listar procesos en este entorno: `sysmon request failed with error: sysmond service not found`. No se corrió la suite.
- La app confía en todos los proxies: `trustProxies(at: '*')` (`bootstrap/app.php:24-27`). Laravel incluye `HEADER_X_FORWARDED_HOST` entre los encabezados confiados (`vendor/laravel/framework/src/Illuminate/Http/Middleware/TrustProxies.php:22-27`). El diseño ya lo trata como requisito de despliegue, no como tarea olvidada (`docs/deployment/DEMO-MULTI-INQUILINO.md:110-126`, `:145`).
- El orden de middleware se puede controlar: la app ya usa `prependToPriorityList` (`bootstrap/app.php:29-32`) y Laravel expone ese mecanismo (`vendor/laravel/framework/src/Illuminate/Foundation/Configuration/Middleware.php:425`).
- Sesión, caché y cola permiten fijar conexión: `SESSION_CONNECTION` (`config/session.php:76`), `DB_CACHE_CONNECTION` (`config/cache.php:42-46`) y `DB_QUEUE_CONNECTION` (`config/queue.php:38-44`).
- Las claves actuales del frontend siguen sin inquilino: `FrontendPageContentService.php:46`, `FrontendSettingsService.php:54`, `FrontendServicesService.php:51`, `FrontendThemeService.php:48`, `FrontendNavigationService.php:51`.
- Media Library escribe en `public` por defecto (`config/media-library.php:36`) y permite reemplazar `path_generator` / `custom_path_generators` (`config/media-library.php:144`, `:154`).
- `RefreshDatabase` puede acotarse por conexión mediante `$connectionsToTransact` (`vendor/laravel/framework/src/Illuminate/Foundation/Testing/RefreshDatabase.php:174-178`).
- El glob de migraciones no es recursivo: `Migrator::getMigrationFiles()` usa `$path.'/*_*.php'` (`vendor/laravel/framework/src/Illuminate/Database/Migrations/Migrator.php:578-582`).
- Filament expone `Panel::domain()` (`vendor/filament/filament/src/Panel/Concerns/HasRoutes.php:50`), pero el diseño decide no usarlo para no duplicar la frontera de subdominio (`docs/epicas/epica-demo-multi-inquilino.md:129-136`).
- Hay 28 modelos en `app/Models`; los modelos existentes no fijan `$connection`, por lo que seguirán la conexión por defecto cuando el tenant la cambie. Los cuatro `scopeVisibleTo` actuales están en `Property`, `Lead`, `PropertyOwner` y `ContratoIntermediacion`.
- La media publicada quedó aceptada como pública por diseño: el alcance alto habla de **separación** de archivos, no confidencialidad (`docs/rfc/EPICA-DEMO-MULTI-INQUILINO.md:56-64`), y RFC-14 explicita que `/storage` no pasa por Laravel ni sesión (`docs/rfcdemo/RFC-14-ENTORNO-CERRADO.md:92-118`).
- RFC-05 ya no ordena cola en fase 1: la regla dice que el comando de invitación ejecuta el alta síncrona y que fase 2 la encola (`docs/rfcdemo/RFC-05-ALTA-DE-INQUILINO.md:96-103`). RFC-13 mantiene la misma decisión (`docs/rfcdemo/RFC-13-INVITACION.md:1-10`, `:45-57`).
- `fallido` quedó modelado como terminal para el ciclo, pero no para la limpieza: RFC alto (`docs/rfc/EPICA-DEMO-MULTI-INQUILINO.md:92-100`), RFC-01 (`docs/rfcdemo/RFC-01-BASE-CENTRAL-Y-MODELO-DE-INQUILINO.md:59-75`) y Lote C (`docs/epicas/epica-demo-lotes-b-c-diseno.md:172-180`).
- El rollback de `CONNECTION LIMIT 0` ya tiene contrato y test: abortar debe restaurar el límite normal (`docs/rfcdemo/RFC-09-EXPIRACION-Y-BORRADO.md:63-75`, `:97-100`).

## Veredicto

✅ **El diseño está listo para implementar, con deuda menor de documentación de índice.**

Los bloqueantes de la reauditoría anterior fueron corregidos de verdad, no maquillados: la media pública está asumida como tradeoff, el alta de fase 1 es síncrona, `fallido` entra al barrido de bases a medias, el borrado tiene rollback explícito, y `.env.testing` ya no apunta a `inmo_test`.

Lo que queda no cambia la arquitectura ni bloquea implementación. Pero hay que limpiarlo porque un repo con señales cruzadas obliga al próximo implementador a hacer arqueología. Y eso, en una épica de aislamiento, es pedirle disciplina humana a un sistema que justamente dice que la disciplina no alcanza.

## Estado de hallazgos anteriores

| Hallazgo anterior | Estado actual | Evidencia |
|---|---|---|
| `.env.testing` apuntaba a `inmo_test` | **Cerrado** | `.env.testing:36-41`; `phpunit.xml:29-30`. |
| Documentos altos prometían cierre fuerte de archivos | **Cerrado por opción A** | `docs/rfc/EPICA-DEMO-MULTI-INQUILINO.md:56-64`; `docs/rfcdemo/RFC-14-ENTORNO-CERRADO.md:92-118`. |
| RFC-05 decía que fase 1 iba por cola | **Cerrado** | `docs/rfcdemo/RFC-05-ALTA-DE-INQUILINO.md:96-103`. |
| `fallido` parecía terminal absoluto | **Cerrado** | `docs/rfcdemo/RFC-01-BASE-CENTRAL-Y-MODELO-DE-INQUILINO.md:59-75`; `docs/epicas/epica-demo-lotes-b-c-diseno.md:172-180`. |
| Auditoría anterior confundía estado histórico con estado vigente | **Cerrado** | `docs/audits/auditoria-demo-multi-inquilino.md:3-7` marca el documento como superado. |
| RFC-13 sugería que no hacía falta cola/worker | **Cerrado** | `docs/rfcdemo/RFC-13-INVITACION.md:5-10`, `:30-33`. |
| `CONNECTION LIMIT 0` no tenía camino de vuelta | **Cerrado** | `docs/rfcdemo/RFC-09-EXPIRACION-Y-BORRADO.md:63-75`, `:97-100`. |

## Hallazgos críticos

No quedan hallazgos críticos abiertos.

Lo crítico era que el diseño prometiera una cosa y ejecutara otra: tests contra base prohibida, media “cerrada” servida por `/storage`, alta síncrona documentada como cola, limpieza incapaz de recoger `fallido`. Esa capa ya está alineada.

## Hallazgos medios

No quedan hallazgos medios abiertos.

La decisión de aceptar media publicada pública es incómoda, pero ahora está escrita como contrato. No es una vulnerabilidad accidental: es un límite conocido, con aviso obligatorio en el comando de invitación (`docs/rfcdemo/RFC-13-INVITACION.md:52-57`) y revisión si el demo se abre (`docs/rfcdemo/RFC-14-ENTORNO-CERRADO.md:117-118`).

## Hallazgos menores

### Mn-1 — El índice de RFC todavía dice que falta la auditoría de diseño fresca

**Qué está mal:** `docs/rfcdemo/README.md` conserva una señal operativa vieja: dice que falta la auditoría con contexto fresco, aunque ya existe la auditoría inicial, la reauditoría, y este reporte deja el gate listo para implementar.

**Evidencia:**

- `docs/rfcdemo/README.md:89-94`: “Falta la auditoría de diseño con contexto fresco”.
- `docs/audits/auditoria-demo-multi-inquilino.md:3-7`: la primera auditoría ya está marcada como superada por esta reauditoría.
- `docs/audits/reauditoria-demo-multi-inquilino.md`: este documento es el estado vigente.

**Escenario concreto de falla:** el implementador empieza por `docs/rfcdemo/README.md`, lee “falta la auditoría”, y frena el lote A o dispara otra auditoría innecesaria. No rompe producción, pero sí rompe el flujo de implementación y puede reabrir discusiones ya cerradas.

**Corrección segura:** cambiar esa línea por “Auditoría fresca completada; estado vigente en `docs/audits/reauditoria-demo-multi-inquilino.md`”.

### Mn-2 — La auditoría autocontenida de la épica sigue diciendo que falta una pasada fresca

**Qué está mal:** `docs/audits/epica-demo-auditoria-diseno.md` fue útil como autoauditoría, pero su advertencia inicial quedó vieja después de la auditoría independiente.

**Evidencia:**

- `docs/audits/epica-demo-auditoria-diseno.md:1-6`: dice que falta una pasada con contexto fresco.
- `docs/rfcdemo/README.md:11`: el índice enlaza esa auditoría como documento de referencia.

**Escenario concreto de falla:** alguien abre el documento enlazado desde el índice, ve “falta una pasada con contexto fresco” y concluye que el diseño todavía no pasó el gate, aunque la reauditoría vigente sí lo pasó.

**Corrección segura:** agregar un aviso arriba, igual que en la primera auditoría externa: “Documento histórico; la pasada fresca vigente está en `docs/audits/reauditoria-demo-multi-inquilino.md`”.

## Sobreingeniería detectada

No veo sobreingeniería nueva.

Las decisiones que parecen pesadas —base por inquilino, conexión central, `TenantTestCase`, prefijo de media, prefijo de caché aunque la DB ya aísle— responden a riesgos reales y verificados. La única pieza que puede parecer excesiva, mantener `path_generator` de media aunque el sitio esté cerrado, no es seguridad extra: evita colisión física de archivos porque los IDs de media se repiten por base.

## Riesgos de implementación

1. **`trustProxies(at: '*')` no puede llegar al primer invitado.** El diseño lo documenta como requisito de despliegue, pero sigue siendo código real hoy (`bootstrap/app.php:27`). Si el origen queda alcanzable sin CloudPanel, `X-Forwarded-Host` puede elegir tenant.
2. **La suite no se corrió en esta reauditoría.** Además, `pgrep` no pudo verificar procesos concurrentes en este entorno. Antes de ejecutar suite real hay que resolver esa verificación o hacerla fuera del sandbox.
3. **La separación de roles debe probarse antes de marcar tenants activos.** El contrato de despliegue exige que `demo_provisioner` cree y transfiera ownership a `demo_app` (`docs/deployment/DEMO-MULTI-INQUILINO.md:84-103`). Si se implementa “rápido” con un solo rol, se destruye la protección del DDL.
4. **Los trabajos de cola deben restaurar conexión al terminar.** El diseño lo sabe (`docs/epicas/epica-demo-multi-inquilino.md:186-196`), pero es una de esas fallas que no explota: escribe en la base equivocada.

## Riesgos de seguridad

1. **La media publicada es pública.** Ya no es hallazgo porque está aceptado, pero sigue siendo el riesgo más fácil de explicar mal. La invitación debe imprimir el aviso; no alcanza con que el operador “se acuerde”.
2. **Host spoofing si se confía cualquier proxy.** El diseño lo tiene bien ubicado como hardening de despliegue. Hay que verificar firewall/proxy real antes del primer tenant.
3. **DDL con nombres interpolados.** El diseño tiene las defensas correctas: slug generado por servidor, regex cerrada, prefijo fijo, quoting y rol separado. La implementación no puede recortar ninguna.

## Preguntas pendientes

No quedan preguntas bloqueantes para empezar el lote A.

La única decisión de producto que conviene no olvidar es futura: si el demo pasa de invitación a público, RFC-14 ya dice que se revisa la decisión de media pública (`docs/rfcdemo/RFC-14-ENTORNO-CERRADO.md:117-118`).
