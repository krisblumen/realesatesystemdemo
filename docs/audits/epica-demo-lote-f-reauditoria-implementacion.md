# Reauditoría de implementación — Épica DEMO, Lote F

**Proyecto:** realestatesystemDemo  
**Fecha:** 2026-08-04  
**Commit auditado:** `6c17f1c` — `style(tenancy): nombre legible para el test del orden de borrado`  
**Diff auditado:** `a1c4a52..6c17f1c`  
**Auditoría previa:** `docs/audits/epica-demo-lote-f-auditoria-implementacion.md`  
**Diseño/RFC auditados:** `docs/epicas/epica-demo-lotes-d-e-f-diseno.md`, `docs/rfcdemo/RFC-09-EXPIRACION-Y-BORRADO.md`, `docs/rfcdemo/RFC-12-PADRON-DEL-OPERADOR.md`  
**Tests auditados:** `tests/Feature/Tenancy/BorradoDeInquilinoTest.php`, `tests/Feature/Tenancy/CicloDeVidaTest.php`

## Evidencia verificada en código real

- El diff del lote F y sus correcciones toca comandos, servicio de borrado, migración central, scheduler, documentación de despliegue y tests: `git diff --name-status a1c4a52..HEAD`.
- `BorraInquilinos::borrar()` valida la base, cierra la puerta con `CONNECTION LIMIT 0`, termina sesiones, ejecuta `DROP DATABASE`, borra `tenants/{slug}/` y conserva la fila (`app/Tenancy/BorraInquilinos.php:27-68`).
- La última red rechaza central/default/template y sólo acepta el prefijo de pruebas en `testing` (`app/Tenancy/BorraInquilinos.php:106-137`).
- `demo:borrar` barre `paraBarrer()`, permite `--slug`, guarda `motivo_falla` e incrementa `intentos_de_borrado` cuando falla (`app/Console/Commands/BorrarInquilinos.php:25-52`).
- `demo:expirar` separa expiración de borrado y permite vencer hoy con `--slug` (`app/Console/Commands/ExpirarInquilinos.php:21-46`).
- `demo:padron` consulta sólo `Tenant` central, lista intentos y no muestra email (`app/Console/Commands/PadronDeInquilinos.php:29-61`).
- La tabla central tiene `intentos_de_borrado` (`database/migrations/central/2026_08_03_000000_create_tenants_table.php:59-63`).
- Scheduler separa expiración y borrado (`routes/console.php:38-39`).
- Antes de suite completa se ejecutó `pgrep -fl "artisan test"`: sin procesos concurrentes.
- Tests focales: `php artisan test tests/Feature/Tenancy/BorradoDeInquilinoTest.php tests/Feature/Tenancy/CicloDeVidaTest.php --no-coverage` → **15 tests, 40 assertions, PASS**.
- Suite completa: `php artisan test --no-coverage` → **1653 tests, 7795 assertions, PASS**.
- Mutación verificada: quitando `$this->cerrarLaPuerta($tenant)` de `BorraInquilinos::borrar()`, `test_the_door_is_closed_first_then_sessions_are_terminated_then_the_drop` falla con “Falta cerrar la puerta…” → el crítico previo C-1 quedó protegido.
- Mutación verificada: agregando en `demo:padron` una PDO efímera contra la base del tenant, `test_the_registry_never_connects_to_a_tenant_database` falla porque `pg_stat_database.sessions` cambia → el crítico previo C-2 quedó protegido.
- Mutación verificada: aceptando siempre `tenancy.prefijo_pruebas`, no sólo en `testing`, los tests focales del lote siguen verdes: **15 tests, 40 assertions, PASS**.

## Veredicto

❌ **No está listo para mezclar como implementación completa del Lote F contra los RFC vigentes.**

El núcleo de borrado mejoró de verdad. Los dos críticos de la auditoría anterior ya no son falsos positivos: ahora las mutaciones que reabrían la carrera del `DROP DATABASE` y la fuga por conexión efímera del padrón rompen tests concretos. También quedaron implementados el contador de reintentos, el vencimiento manual por `--slug` y el guard de prefijo de pruebas acotado a `testing`.

Lo que todavía no cierra no es estilo: RFC-12 sigue prometiendo acciones de operador registradas con quién y cuándo, y RFC-09 dice que el padrón ofrece abortar un borrado ya cerrado. El código actual deja esas garantías como comandos sin actor o como método interno sin camino operativo. Si el equipo decide que esa parte pasa a fase 2, el RFC debe decirlo explícitamente. Si sigue en alcance, falta implementación y test.

## Hallazgos críticos

No encontré hallazgos críticos abiertos en el código actual del núcleo de expiración/borrado. Los dos críticos de la auditoría previa quedaron cerrados por tests que fallan ante la mutación correspondiente.

## Hallazgos medios

### M-1 — Las acciones del operador siguen sin registro de quién y cuándo

**Qué está mal:** RFC-12 dice que las acciones del operador quedan registradas con actor y timestamp. La implementación actual agrega acciones por consola (`demo:expirar --slug`, `demo:borrar --slug`), pero no registra quién las ejecutó ni cuándo como evento auditable del demo. El `updated_at` del tenant no reemplaza un registro de acción: no distingue comando, actor, motivo ni origen.

**Evidencia:**

- RFC-12 incluye “Acciones del operador” en alcance (`docs/rfcdemo/RFC-12-PADRON-DEL-OPERADOR.md:25-31`).
- RFC-12 exige: “Todas quedan registradas con quién y cuándo” (`docs/rfcdemo/RFC-12-PADRON-DEL-OPERADOR.md:67-73`).
- El diseño de detalle repite que las acciones quedan registradas (`docs/epicas/epica-demo-lotes-d-e-f-diseno.md:193-207`).
- `demo:expirar --slug` cambia estado, pero sólo imprime “Expirado” y no escribe evento de operador (`app/Console/Commands/ExpirarInquilinos.php:25-46`).
- `demo:borrar --slug` registra sólo fallas técnicas en `motivo_falla`/`intentos_de_borrado`; no registra acción, actor ni timestamp de operador (`app/Console/Commands/BorrarInquilinos.php:25-52`).
- La migración central del padrón no tiene tabla ni columnas de auditoría de acciones de operador (`database/migrations/central/2026_08_03_000000_create_tenants_table.php:25-65`).

**Escenario concreto de falla:** dos personas con acceso operativo comparten mantenimiento del demo. Una ejecuta `php artisan demo:expirar --slug=cliente-demo` por un pedido de soporte. Al día siguiente el cliente reclama que su demo fue cortado antes de vencer. El sistema sólo muestra el tenant `expirado`; no puede responder quién lo cortó, cuándo se decidió ni qué acción exacta se ejecutó.

### M-2 — El aborto de un borrado cerrado existe como método, pero no como acción operable del padrón

**Qué está mal:** RFC-09 declara que abortar un borrado restaura `CONNECTION LIMIT -1` y que el padrón ofrece esa acción junto a reintentar borrado. El servicio implementa `abortar()`, y el test cubre el método aislado, pero no hay comando, ruta ni acción del padrón que permita ejecutarlo sobre un inquilino real.

**Evidencia:**

- RFC-09: si el operador aborta tras cerrar la puerta, debe restaurarse el límite de conexiones (`docs/rfcdemo/RFC-09-EXPIRACION-Y-BORRADO.md:63-74`).
- `BorraInquilinos::abortar()` existe y ejecuta `ALTER DATABASE ... CONNECTION LIMIT -1` (`app/Tenancy/BorraInquilinos.php:90-97`).
- El test sólo llama al método directamente (`tests/Feature/Tenancy/BorradoDeInquilinoTest.php:212-225`).
- Los comandos disponibles del lote son `demo:expirar`, `demo:borrar` y `demo:padron`; ninguno expone abortar (`app/Console/Commands/ExpirarInquilinos.php:21`, `app/Console/Commands/BorrarInquilinos.php:21`, `app/Console/Commands/PadronDeInquilinos.php:25`).
- El scheduler sólo ejecuta expiración y borrado (`routes/console.php:38-39`).

**Escenario concreto de falla:** `demo:borrar --slug=cliente-demo` muere después de `CONNECTION LIMIT 0` y antes de `DROP DATABASE` —por ejemplo, `SIGKILL` del proceso o reinicio del VPS. La base queda existente pero no conectable. Si soporte decide no borrar todavía por un reclamo del cliente, no hay acción operativa documentada para restaurar el acceso; alguien tiene que entrar por SQL/tinker y ejecutar a mano lo que el diseño quería ofrecer desde el padrón.

## Hallazgos menores

No encontré hallazgos menores de implementación que cumplan la regla de falla demostrable. Lo que sigue son gaps de tests y riesgos operativos.

## Hallazgos sobre tests

### T-1 — El guard productivo del prefijo de pruebas no está protegido por tests

**Qué está mal:** el código actual acota correctamente `tenancy.prefijo_pruebas` a `testing`, pero no hay test que falle si esa protección se revierte. La auditoría anterior ya marcó este punto como menor; el fix existe, pero el contrato quedó abierto en CI.

**Evidencia:**

- Implementación correcta actual: `app()->environment('testing') ? config('tenancy.prefijo_pruebas') : null` (`app/Tenancy/BorraInquilinos.php:125-128`).
- Búsqueda en tests: sólo se usa `config('tenancy.prefijo_pruebas')` para crear bases de prueba en `BorradoDeInquilinoTest` y `CicloDeVidaTest`; no hay test de rechazo fuera de `testing`.
- Mutación verificada: cambié temporalmente esa línea para aceptar siempre `tenancy.prefijo_pruebas`; los tests focales del lote siguieron verdes: **15 tests, 40 assertions, PASS**.

**Escenario concreto de falla:** una refactorización vuelve a aceptar `demo_probe_t_` en runtime normal. CI queda verde. En producción, una fila central mal cargada con `database=demo_probe_t_manual` pasa la última red de borrado aunque no sea una base de inquilino de producción.

### T-2 — No hay test que pruebe el registro auditable de acciones del operador

**Qué está mal:** como no existe implementación de auditoría de acciones, tampoco existe test que cierre el contrato de RFC-12 “quién y cuándo”. Los tests actuales verifican que `--slug` expire y que el contador de fallos se vea, pero no que una acción de operador deje evento auditable.

**Evidencia:**

- `test_the_operator_can_expire_a_tenant_today_without_waiting_for_its_date` sólo verifica cambio de estado del tenant pedido y no actor/evento (`tests/Feature/Tenancy/CicloDeVidaTest.php:123-135`).
- `test_a_deletion_that_keeps_failing_shows_how_many_times` sólo verifica contador y salida del padrón (`tests/Feature/Tenancy/CicloDeVidaTest.php:137-154`).
- RFC-12 exige registro de acciones con quién y cuándo (`docs/rfcdemo/RFC-12-PADRON-DEL-OPERADOR.md:67-73`).

**Escenario concreto de falla:** se agrega una tabla de auditoría más adelante, pero `demo:expirar --slug` no la usa. La suite del lote seguiría verde porque sólo observa el estado final del tenant.

## Sobreingeniería detectada

No detecté sobreingeniería nueva. Separar expiración, borrado, padrón y servicio de borrado sigue teniendo sentido: el diseño necesita responsabilidades distintas y reintentos seguros.

## Riesgos de implementación

1. **Fase operativa partida entre RFC y código.** El código comenta que el padrón web llega en fase 2 (`app/Console/Commands/PadronDeInquilinos.php:20-21`), pero RFC-12 sigue describiendo pantalla, autenticación propia y acciones auditadas (`docs/rfcdemo/RFC-12-PADRON-DEL-OPERADOR.md:25-41`). Esa contradicción va a producir discusiones de alcance en QA.
2. **Borrado programado sin `withoutOverlapping()`.** `demo:borrar` corre diario sin cerrojo de scheduler (`routes/console.php:39`). Si alguien ejecuta un retry manual cerca de las 03:30, dos procesos pueden operar sobre el mismo tenant. No vi corrupción de datos crítica en el flujo feliz, pero los contadores de falla se actualizan con `valor_actual + 1` desde el modelo cargado (`app/Console/Commands/BorrarInquilinos.php:42-45`), así que pueden subcontar en carreras.
3. **Aborto de emergencia no está operacionalizado.** El método existe, pero depende de que alguien sepa invocarlo por código si queda una base con `CONNECTION LIMIT 0`.

## Riesgos de seguridad

1. **Trazabilidad operativa insuficiente.** En un sistema que promete aislamiento por cliente, una acción manual de operador sin actor/timestamp no es sólo observabilidad pobre: impide investigar cortes o borrados prematuros.
2. **Guard de DDL sin test productivo.** El código actual está bien, pero la regresión que permitiría borrar prefijos de prueba fuera de `testing` queda verde.
3. **Padrón de contenido interno protegido correctamente por test.** Este riesgo estaba abierto en la auditoría anterior; ahora `pg_stat_database.sessions` detecta conexiones efímeras y cierra la fuga futura más probable.

## Qué está bien resuelto

- El orden `CONNECTION LIMIT 0` → `pg_terminate_backend` → `DROP DATABASE` está implementado y ahora protegido por test de orden (`app/Tenancy/BorraInquilinos.php:31-49`; `tests/Feature/Tenancy/BorradoDeInquilinoTest.php:165-197`).
- El padrón no abre bases de inquilino y el test falla si alguien agrega una conexión efímera (`app/Console/Commands/PadronDeInquilinos.php:29-61`; `tests/Feature/Tenancy/CicloDeVidaTest.php:168-190`).
- El contador de reintentos de borrado está en schema, comando y padrón (`database/migrations/central/2026_08_03_000000_create_tenants_table.php:59-63`; `app/Console/Commands/BorrarInquilinos.php:42-45`; `app/Console/Commands/PadronDeInquilinos.php:42-55`).
- `demo:expirar --slug` cubre el caso operativo “cortar hoy” sin `UPDATE` manual (`app/Console/Commands/ExpirarInquilinos.php:21-46`; `tests/Feature/Tenancy/CicloDeVidaTest.php:123-135`).
- La suite completa pasa contra el árbol restaurado: **1653 tests, 7795 assertions**.

## Preguntas abiertas

1. ¿La pantalla web/autenticación propia del operador de RFC-12 quedó oficialmente fuera de fase 1? El código lo dice en comentario, pero el RFC no.
2. ¿El registro “con quién y cuándo” se quiere implementar ahora, o se descopa a fase 2? Si se descopa, el RFC debe cambiar para no dejar un contrato falso.
