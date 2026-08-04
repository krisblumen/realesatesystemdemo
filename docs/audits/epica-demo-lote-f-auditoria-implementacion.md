# Auditoría de implementación — Épica DEMO, Lote F

**Proyecto:** realestatesystemDemo  
**Fecha:** 2026-08-04  
**Commit auditado:** `af55567` — `feat(tenancy): lote F — expiración, borrado y padrón`  
**Diff auditado:** `af55567^..af55567`  
**Diseño auditado:** `docs/epicas/epica-demo-lotes-d-e-f-diseno.md`, `docs/rfcdemo/RFC-09-EXPIRACION-Y-BORRADO.md`, `docs/rfcdemo/RFC-12-PADRON-DEL-OPERADOR.md`  
**Tests auditados:** `tests/Feature/Tenancy/BorradoDeInquilinoTest.php`, `tests/Feature/Tenancy/CicloDeVidaTest.php`

## Evidencia verificada en código real

- El lote F agrega `app/Tenancy/BorraInquilinos.php`, tres comandos de consola, scheduler y dos archivos de tests (`git diff --name-status af55567^..af55567`).
- `demo:expirar` sólo marca `activo` vencido como `expirado` y no borra bases (`app/Console/Commands/ExpirarInquilinos.php:25-39`).
- `BorraInquilinos::borrar()` ejecuta el orden diseñado: validar nombre, cerrar puerta, terminar sesiones, `DROP DATABASE`, borrar `tenants/{slug}/`, y marcar `borrado` si el estado permite transición (`app/Tenancy/BorraInquilinos.php:27-68`).
- `abortar()` restaura el límite a `CONNECTION LIMIT -1` (`app/Tenancy/BorraInquilinos.php:90-97`).
- `demo:borrar` barre por `Tenant::paraBarrer()` y guarda `motivo_falla` si algo falla (`app/Console/Commands/BorrarInquilinos.php:25-49`).
- El padrón consulta `Tenant` en central, no muestra email y lista `slug`, estado, fechas, plantilla y motivo (`app/Console/Commands/PadronDeInquilinos.php:29-60`).
- Scheduler separa expiración y borrado (`routes/console.php:26-39`).
- Pruebas focales del lote: `php artisan test tests/Feature/Tenancy/BorradoDeInquilinoTest.php tests/Feature/Tenancy/CicloDeVidaTest.php --no-coverage` → **12 tests, 27 assertions, PASS**.
- Suite completa: `php artisan test --no-coverage` → **1650 tests, 7782 assertions, PASS**.
- Mutación 1: quitando la llamada a `cerrarLaPuerta()` dentro de `BorraInquilinos::borrar()`, `php artisan test tests/Feature/Tenancy/BorradoDeInquilinoTest.php --no-coverage` siguió verde: **7 tests, 12 assertions, PASS**.
- Mutación 2: haciendo que `demo:padron` abra una conexión PDO a la base del primer inquilino y la cierre antes de terminar, `php artisan test --filter=test_the_registry_never_connects_to_a_tenant_database tests/Feature/Tenancy/CicloDeVidaTest.php --no-coverage` siguió verde: **1 test, 2 assertions, PASS**.

## Veredicto

❌ **No está listo para mezclar todavía.**

La implementación hace mucho de lo correcto: la separación marcar/borrar está, el borrado está en el orden correcto, la base se cierra antes de matar sesiones, la fila sobrevive, `fallido` entra al barrido y la suite completa pasa. Eso vale.

Pero el lote F promete dos garantías de producción que los tests no protegen: el orden crítico `CONNECTION LIMIT 0 → terminate → DROP`, y que el padrón no abra bases de inquilino. En ambos casos reintroduje el defecto y los tests siguieron verdes. Un test así no es “cobertura parcial”: es una señal falsa en el tablero.

Además, RFC-12 pide datos/acciones del padrón que no existen todavía: conteo de reintentos, acciones del operador y registro de esas acciones. Eso es contrato no implementado, no estilo.

## Hallazgos críticos

### C-1 — El orden crítico del borrado no está protegido por tests

**Qué está mal:** el código actual sí llama a `cerrarLaPuerta()` antes de terminar sesiones, pero el test que debería proteger ese contrato sigue verde si esa llamada desaparece. La prueba cubre “una sesión abierta actual no impide el DROP”, pero no cubre la carrera real del diseño: una nueva conexión entre `pg_terminate_backend` y `DROP DATABASE`.

**Evidencia:**

- Diseño: el orden exige `CONNECTION LIMIT 0` antes de terminar sesiones (`docs/epicas/epica-demo-lotes-d-e-f-diseno.md:166-176`; `docs/rfcdemo/RFC-09-EXPIRACION-Y-BORRADO.md:36-49`).
- Implementación actual correcta: `BorraInquilinos::borrar()` llama a `$this->cerrarLaPuerta($tenant)` antes de `terminarSesiones()` y `DROP DATABASE` (`app/Tenancy/BorraInquilinos.php:31-49`).
- Test que parece cubrirlo: `test_an_open_session_does_not_stop_the_deletion()` abre una PDO, llama a `borrar()` y espera que la base desaparezca (`tests/Feature/Tenancy/BorradoDeInquilinoTest.php:99-118`).
- Test del mecanismo aislado: `test_closing_the_door_actually_refuses_new_connections()` verifica `cerrarLaPuerta()` por separado (`tests/Feature/Tenancy/BorradoDeInquilinoTest.php:120-163`).
- Mutación verificada: reemplacé la llamada a `cerrarLaPuerta()` dentro de `borrar()` por un comentario y corrí `BorradoDeInquilinoTest`: **7/7 tests pasaron**.

**Escenario concreto de falla:** alguien refactoriza `borrar()` y elimina el paso 1 porque “ya terminamos sesiones antes del DROP”. La suite queda verde. En producción, un navegador con pestaña abierta reconecta entre `pg_terminate_backend` y `DROP DATABASE`; Postgres rechaza el DROP porque la base vuelve a tener conexión. El inquilino queda `expirado`, la base sigue viva, y el sistema depende del reintento diario.

### C-2 — El test del padrón no detecta que el comando abra una base de inquilino

**Qué está mal:** RFC-12 dice que la garantía del padrón es estructural: ninguna consulta del padrón nombra ni abre una base de inquilino. El test actual sólo mira `pg_stat_activity` al final. Si el comando abre una conexión, lee y la cierra antes de terminar, el test no lo ve.

**Evidencia:**

- Diseño: el padrón vive en central y no hay conexión abierta a bases de inquilino (`docs/rfcdemo/RFC-12-PADRON-DEL-OPERADOR.md:32-41`).
- Regla explícita: “Ninguna consulta de esta pantalla nombra una base de inquilino” (`docs/rfcdemo/RFC-12-PADRON-DEL-OPERADOR.md:75-84`).
- Implementación actual: `demo:padron` consulta `Tenant::query()` y arma una tabla (`app/Console/Commands/PadronDeInquilinos.php:29-60`).
- Test actual: después de ejecutar `demo:padron`, cuenta sesiones vivas en `pg_stat_activity` para la base del tenant (`tests/Feature/Tenancy/CicloDeVidaTest.php:120-135`).
- Mutación verificada: inserté en `demo:padron` una conexión PDO a `$inquilinos->first()->database`, ejecuté `SELECT 1`, cerré la PDO y corrí sólo ese test. Resultado: **PASS**.

**Escenario concreto de falla:** en una futura versión alguien agrega al padrón “cantidad de propiedades” leyendo cada base de inquilino con una conexión efímera y cerrándola. El test sigue verde porque al final no quedan sesiones. El operador ya está leyendo contenido interno, justo lo que RFC-12 prohíbe.

## Hallazgos medios

### M-1 — El padrón no implementa “cuántas veces se reintentó” un borrado fallido

**Qué está mal:** RFC-12 pide mostrar si el borrado falló y cuántas veces se reintentó. La tabla central no tiene contador, `demo:borrar` no incrementa nada, y `demo:padron` no muestra ese dato.

**Evidencia:**

- Diseño: el padrón muestra “si el borrado falló y cuántas veces se reintentó” (`docs/rfcdemo/RFC-12-PADRON-DEL-OPERADOR.md:43-53`).
- Diseño: si falla varias veces, queda visible y no se reintenta para siempre en silencio (`docs/rfcdemo/RFC-09-EXPIRACION-Y-BORRADO.md:76-83`).
- Migración central: tiene `borrado_en` y `motivo_falla`, pero no contador de intentos de borrado (`database/migrations/central/2026_08_03_000000_create_tenants_table.php:50-57`).
- Comando de borrado: al fallar sólo guarda `motivo_falla` recortado (`app/Console/Commands/BorrarInquilinos.php:38-43`).
- Padrón: columnas `Slug`, `Estado`, `Nació`, `Vence`, `Plantilla`, `Motivo de falla`; no hay reintentos (`app/Console/Commands/PadronDeInquilinos.php:42-53`).

**Escenario concreto de falla:** un tenant expirado falla al borrar por permisos durante tres noches. El operador corre `demo:padron` y ve un `motivo_falla`, pero no puede distinguir “falló una vez recién” de “lleva tres reintentos y requiere intervención”. El cron seguirá ejecutando `demo:borrar` cada día sin que el padrón muestre la escalada que el RFC pidió.

### M-2 — Las acciones del operador de RFC-12 no están implementadas ni auditadas

**Qué está mal:** RFC-12 define acciones del operador: vencer antes de tiempo, reintentar borrado fallido y cerrar el registro temporalmente, todas registradas con quién y cuándo. El lote implementa listados y permite reintentar borrado por `demo:borrar --slug`, pero no hay comando/acción para vencer antes de tiempo, no hay cierre de registro, y no hay registro de acciones.

**Evidencia:**

- Diseño de detalle: acciones “vencer antes de tiempo, reintentar un borrado, cerrar las invitaciones” y todas registradas (`docs/epicas/epica-demo-lotes-d-e-f-diseno.md:193-207`).
- RFC-12: acciones del operador y registro con quién/cuándo (`docs/rfcdemo/RFC-12-PADRON-DEL-OPERADOR.md:67-73`).
- `demo:expirar` no acepta `--slug` ni acción manual; sólo barre vencidos por fecha (`app/Console/Commands/ExpirarInquilinos.php:21-39`).
- `demo:padron` sólo tiene `--estado` (`app/Console/Commands/PadronDeInquilinos.php:25-34`).
- No hay migración/tabla de auditoría de acciones del operador en el diff del lote; la tabla `tenants` no tiene campos de auditoría de acción (`database/migrations/central/2026_08_03_000000_create_tenants_table.php:25-60`).

**Escenario concreto de falla:** un invitado reporta que cargó datos reales y pide cortar el demo hoy. El operador no tiene acción de “vencer ahora” ni rastro auditable; termina editando la fila a mano en la base central. El tenant deja de entrar, pero no queda evidencia de quién lo venció ni cuándo.

## Hallazgos menores

### Mn-1 — La red de borrado acepta el prefijo de pruebas también en runtime normal

**Qué está mal:** `BorraInquilinos` permite borrar bases con `tenancy.prefijo_pruebas` además del prefijo real de inquilino. Eso es necesario para los tests actuales, pero no está acotado a `testing`. En producción amplía la superficie de DDL permitida por la red de seguridad.

**Evidencia:**

- Config por defecto: `prefijo_pruebas => demo_probe_t_` (`config/tenancy.php:20-23`).
- Borrador acepta ambos prefijos sin mirar ambiente (`app/Tenancy/BorraInquilinos.php:118-129`).
- RFC-09 dice que el borrado sólo alcanza bases con el prefijo de inquilino y nunca central ni plantilla (`docs/rfcdemo/RFC-09-EXPIRACION-Y-BORRADO.md:85-90`).

**Escenario concreto de falla:** una fila errónea del padrón central queda con `database=demo_probe_t_manual` y estado `expirado` en un servidor compartido donde existe esa base temporal. `demo:borrar` la considera válida y la borra, aunque no es una base de inquilino de producción. No toca `inmo_db`, pero sí demuestra que la última red permite más de lo que el contrato dice.

## Hallazgos sobre tests

### T-1 — `test_an_open_session_does_not_stop_the_deletion` no protege la carrera que dice proteger

**Evidencia:** con la llamada a `cerrarLaPuerta()` removida de `BorraInquilinos::borrar()`, `BorradoDeInquilinoTest` completo siguió pasando: **7 tests, 12 assertions, PASS**.

**Por qué importa:** el test deja una falsa sensación de seguridad sobre M-1. Protege “matar una sesión ya abierta”; no protege “impedir una reconexión entre matar y dropear”. El segundo es el defecto de producción.

### T-2 — `test_the_registry_never_connects_to_a_tenant_database` sólo detecta conexiones vivas al final

**Evidencia:** con una mutación que abre una PDO a la base del tenant dentro de `demo:padron`, hace `SELECT 1` y cierra la conexión, el test siguió pasando: **1 test, 2 assertions, PASS**.

**Por qué importa:** el contrato de RFC-12 es “no abrir bases de inquilino”, no “no dejar conexiones colgadas”. El test actual permite lecturas fugaces de contenido interno.

## Sobreingeniería detectada

No veo sobreingeniería relevante en el lote F. Separar `ExpirarInquilinos`, `BorrarInquilinos` y `PadronDeInquilinos` es correcto: son tres responsabilidades distintas y el diseño justamente pide que marcar y borrar no sean una sola operación.

## Riesgos de implementación

1. **El core safety depende de tests que hoy no fallan ante regresiones.** El código actual está bien, pero una refactorización puede romper el orden del borrado sin que CI lo vea.
2. **El padrón puede crecer hacia contenido interno sin que el test lo detecte.** Hay que instrumentar la conexión, no mirar sesiones al final.
3. **Operación manual sin auditoría.** Mientras no existan acciones registradas, cualquier vencimiento manual real tenderá a hacerse por SQL directo.
4. **Reintentos invisibles.** Sin contador, el operador no puede distinguir fallo aislado de fallo persistente.

## Riesgos de seguridad

1. **Aislamiento por padrón:** el riesgo no es el código actual, es que el test permite una fuga futura. Para este sistema, eso es grave: el producto promete que los datos de un cliente son de ese cliente.
2. **DDL demasiado permisivo:** aceptar prefijo de pruebas en runtime normal amplía la lista de bases que el rol de mantenimiento puede borrar si una fila central queda mal.
3. **Superusuario en despliegue:** el lote encontró y documentó correctamente que `CONNECTION LIMIT` no aplica a superusuarios (`docs/deployment/DEMO-MULTI-INQUILINO.md:84-103`). Ese checklist es obligatorio antes del primer tenant.

## Qué está bien resuelto

- El orden real de `BorraInquilinos::borrar()` está bien escrito hoy.
- La base se borra antes que los archivos.
- `fallido` entra al barrido sin transicionar a `borrado`.
- `abortar()` restaura `CONNECTION LIMIT -1` y tiene test.
- La suite completa está verde: **1650 tests, 7782 assertions**.
- El hallazgo de despliegue sobre superusuarios está bien incorporado al checklist.
