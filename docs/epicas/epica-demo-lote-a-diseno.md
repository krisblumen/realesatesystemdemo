# Épica DEMO — Lote A, diseño de detalle

> Diseño, sin código. Cubre RFC-01, RFC-02 y RFC-03.
>
> Épica: `docs/epicas/epica-demo-multi-inquilino.md`.
> RFC: `docs/rfcdemo/`.

## Qué resuelve el lote A

Las tres piezas de las que cuelga todo lo demás: dónde se anota quién es cada
inquilino, cómo se prueba que dos inquilinos no se pisan, y cómo un trabajo en
segundo plano no escribe en la base equivocada.

Ninguna de las tres se ve desde afuera. Y sin las tres, lo que venga después se
implementa a ciegas.

## Evidencia verificada del framework

Todo lo que sigue se comprobó en `vendor/`, no se supone.

- `config/queue.php:40` — la cola acepta una conexión fija (`'connection'`).
- `config/session.php:76` — la sesión acepta una conexión fija.
- `config/cache.php:44` — el caché acepta una conexión fija; si es nula, usa la
  por defecto.
- `RefreshDatabase::connectionsToTransact()` (`RefreshDatabase.php:174-179`) lee
  la propiedad `$connectionsToTransact` si existe, y si no usa la conexión por
  defecto. Acotar a `central` es declarar la propiedad.
- `Migrator::getMigrationFiles()` (`Migrator.php:581`) usa `glob($path.'/*_*.php')`,
  que **no es recursivo**. Un subdirectorio `central/` no se cuela en las
  migraciones del inquilino.

## 1. Las conexiones

### La decisión que ordena todo

**La conexión por defecto es la del inquilino, y se apunta en cada petición.**

Los 28 modelos existentes usan la conexión por defecto. No tocarlos es el punto:
`Property`, `Lead`, `FrontendPage` y los demás quedan exactamente como están, y
el aislamiento ocurre por debajo.

| Conexión | `database` | Quién la usa |
|---|---|---|
| `tenant` | se apunta en cada petición | **La por defecto.** Todos los modelos existentes |
| `central` | fija, `demo_central` | El modelo `Tenant`, la cola, y el host central |
| `maintenance` | fija, `postgres` | Sólo DDL. Nunca dentro de una petición |

**Los nombres, sin ambigüedad** (hallazgo M-2 de la auditoría). Hoy el repo usa
`demo_db` para desarrollo de un solo inquilino y `demo_test` para la suite. Al
entrar la multi-inquilinidad:

| Nombre | Qué es | Cuándo |
|---|---|---|
| `demo_db` | La base de desarrollo actual, de un solo inquilino | **Deja de usarse.** Sirve de origen para construir la primera plantilla |
| `demo_central` | El padrón, la cola y las sesiones del host central | Nueva |
| `demo_template_vN` | La plantilla vigente | Nueva |
| `demo_t_{slug}` | Un inquilino | Una por alta |
| `demo_test` | La suite | Se mantiene |

No conviven `demo_db` y `demo_central` como si fueran lo mismo: son cosas
distintas y la confusión hace que el alta escriba en una base y el resolver lea
de otra.

`maintenance` existe por una razón concreta: `CREATE DATABASE` no corre dentro de
una transacción, y necesita una conexión que no esté apuntando a la base que se
va a crear ni a una envuelta por un test.

### El valor inicial de `tenant.database`, que no es nulo

Dejarlo nulo sería un error silencioso: **Postgres, con nombre de base vacío,
conecta a una base con el nombre del usuario.** Una consulta antes de resolver el
inquilino no fallaría — escribiría en otro lado.

Arranca apuntando a un nombre deliberadamente inexistente,
`demo_sin_resolver`. Así cualquier consulta previa a la resolución muere con
«database does not exist», que es exactamente lo que uno quiere leer.

Esto convierte una regla de disciplina —«el host central no toca datos de
inquilino»— en algo que el motor hace cumplir solo.

### Los dos modos del middleware

| Host | Conexión por defecto | Sesión y caché caen en |
|---|---|---|
| `{slug}.demo.…` | `tenant`, apuntada a su base | La base del inquilino |
| central, sin slug | `central` | La central |

Sesión y caché no se configuran aparte: usan la conexión por defecto, así que
siguen el mismo movimiento. **Un mecanismo resuelve tres aislamientos.**

Consecuencia: la tabla `sessions` y la de caché tienen que existir en la central
**y** en la plantilla del inquilino. Las dos migran.

### La cola es la excepción, y se fija explícitamente

Un worker no tiene `Host`. La cola se fija a `central` por configuración
(`DB_QUEUE_CONNECTION=central`), y no se hereda de nada.

Además, el trabajo que crea la base de un inquilino corre cuando esa base todavía
no existe: la cola no podría vivir adentro aunque quisiéramos.

## 2. Migraciones separadas

```
database/migrations/           → el inquilino (las que ya existen, sin tocar)
database/migrations/central/   → la central
```

Se corren apuntando la conexión y la ruta:

```
php artisan migrate --database=central --path=database/migrations/central
```

Que el subdirectorio no se cuele en `php artisan migrate` está verificado arriba,
y **hay que dejarlo probado**: si un día alguien cambia la ruta y las migraciones
de la central entran en la plantilla, cada inquilino nace con una tabla
`tenants` propia y el padrón deja de significar nada. El síntoma aparecería lejos
de la causa.

## 3. La tabla `tenants`

Vive sólo en la central.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint | |
| `slug` | string(32) | único. Lo genera el servidor; formato en RFC-05 |
| `database` | string(64) | único. Nombre real; no se recalcula |
| `estado` | string(20) | índice. Ver abajo |
| `email` | string(180) | |
| `origen_hash` | string(64) nulo | **Fase 2.** En fase 1 queda vacío |
| `template_version` | string(20) | Con qué plantilla nació |
| `expira_en` | timestamp | índice, para la tarea de expiración |
| `borrado_en` | timestamp nulo | |
| `motivo_falla` | text nulo | Por qué falló el alta o el borrado |
| `created_at`, `updated_at` | timestamp | |

`database` se guarda y no se deriva del slug al leer. Si mañana cambia la forma
de nombrar las bases, los inquilinos viejos tienen que seguir encontrando la
suya.

`motivo_falla` no estaba en RFC-01 y lo pide RFC-12: el padrón muestra por qué
falló un alta o un borrado. Sin la columna, esa pantalla no tiene qué mostrar.

## 4. El modelo `Tenant` y sus estados

Declara su conexión de forma explícita:

```
protected $connection = 'central';
```

No la hereda. Un modelo de la central que dependa de cuál sea la conexión por
defecto funcionaría en el host central y fallaría en el de un inquilino.

Los estados van en un enum respaldado por string, con un **único** método de
transición que valida el paso. Un `estado` asignado en cualquier otro lugar es un
error de implementación, no una alternativa.

```
aprovisionando → activo | fallido
activo         → expirado
expirado       → borrado
fallido        → (terminal)
borrado        → (terminal)
```

Sólo `activo` resuelve una petición. Y `expirado` y «no existe» devuelven lo
mismo hacia afuera, para no permitir enumerar inquilinos.

## 5. El middleware de trabajo (RFC-03)

El modo de falla que cubre no da excepción: si un trabajo deja la conexión por
defecto apuntando al inquilino A, el siguiente la hereda y **escribe bien, en la
base equivocada**.

Contrato:

- El trabajo declara de qué inquilino es guardando su `slug`. No el modelo, no
  la conexión: el slug.
- El middleware apunta la conexión al empezar y **restaura en `finally`**.
- Restaurar significa volver a `demo_sin_resolver`, no a la del trabajo
  anterior.
- Un trabajo que no declara inquilino corre contra `central` y no puede alcanzar
  ninguna base de inquilino.

Diferencia con la petición web, que importa: en una petición nada se conectó
todavía cuando corre el middleware. En un worker la conexión **ya está abierta**
de un trabajo anterior, así que apuntar la configuración no alcanza — hay que
descartar la conexión viva antes de reabrirla.

## 6. El caso base de pruebas (RFC-02)

### Las tres piezas

1. **Transaccionar sólo la central**: declarar `$connectionsToTransact = ['central']`.
   Verificado que el framework lo respeta.
2. **La conexión `maintenance`** ejecuta `CREATE DATABASE` y `DROP DATABASE`, sin
   transacción abierta encima.
3. **`TenantTestCase`** ofrece un método para levantar un inquilino y los borra a
   todos al terminar, **pase o falle el test**.

### La plantilla de pruebas

Se construye una vez para toda la suite. Copiarla cuesta 0.2 s; migrar desde cero
cuesta segundos.

Si no existe, el caso base falla con un mensaje que **dice qué comando correr**.
Un error de Postgres sobre una base inexistente manda a depurar el lugar
equivocado.

### El barrido

Una corrida interrumpida deja bases reales que no desaparecen solas. Prefijo
reconocible y distinto del de producción, y un comando que borra lo que quedó.

> Contraste deliberado con el cerrojo de RFC-05, que **no** lleva barrido: ahí,
> si el proceso muere, Postgres suelta solo. Acá no: las bases quedan. Dos
> situaciones que suenan iguales y piden respuestas contrarias.

## 7. Archivos del lote

| Archivo | Qué es |
|---|---|
| `config/tenancy.php` | Plantilla vigente, dominio base, prefijos, plazo por defecto |
| `config/database.php` | Conexiones `tenant`, `central`, `maintenance` |
| `database/migrations/central/…_create_tenants_table.php` | |
| `app/Models/Tenant.php` | |
| `app/Enums/TenantEstado.php` | |
| `app/Jobs/Middleware/UsaConexionDeInquilino.php` | |
| `app/Console/Commands/DemoBuildTestTemplate.php` | |
| `app/Console/Commands/DemoSweepTestDatabases.php` | |
| `tests/TenantTestCase.php` | |

## 8. Orden dentro del lote

1. Conexiones y `config/tenancy.php`.
2. Migración de la central y modelo `Tenant` con su enum.
3. **`TenantTestCase` y el comando de plantilla de pruebas.** Acá, no al final:
   lo que sigue ya se verifica con esto.
4. Cola fijada a la central y middleware de trabajo, con su test de dos trabajos
   de inquilinos distintos en el mismo worker.

## 9. Tests que cierran el lote

| Test | Qué protege |
|---|---|
| Las migraciones de la central no aparecen en las del inquilino, ni al revés | Que el padrón no se replique en cada inquilino |
| Una consulta a un modelo de inquilino sin resolver falla, y falla ruidosamente | El centinela `demo_sin_resolver` |
| El modelo `Tenant` responde igual en modo central y en modo inquilino | La conexión explícita |
| Todas las transiciones válidas pasan y las inválidas se rechazan | La máquina de estados |
| Dos trabajos seguidos de inquilinos distintos escriben cada uno en su base | El modo de falla silencioso |
| Un trabajo que lanza excepción deja la conexión en el centinela | El `finally` |
| Un test de inquilino que falla no deja bases huérfanas | El caso base |
| El barrido no toca bases fuera de su prefijo | Que un comando de tests no pueda rozar producción |

## 10. Lo que NO entra en el lote A

La resolución por subdominio (RFC-06) es del lote D. Acá se diseña **cómo** se
apunta la conexión, no **quién** decide a qué inquilino.

Para los tests eso no es un problema: el caso base apunta la conexión de forma
directa, sin `Host`. Lo que se prueba en el lote A es que apuntar funciona y que
nada se hereda entre trabajos.
