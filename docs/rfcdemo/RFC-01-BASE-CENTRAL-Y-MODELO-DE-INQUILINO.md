# RFC-01 Base central y modelo de inquilino

## Objetivo

Dar al sistema un lugar donde anotar quiénes son los inquilinos, que exista antes
que ellos y sobreviva a su borrado.

## Épica

EPICA-DEMO. Lote A. No depende de nada.

## Responsable

Backend.

## Alcance

- Una conexión `central` en la configuración de base de datos, separada de la
  conexión por defecto.
- Migraciones propias de la central, en su propio directorio, que **no** viajan
  en la plantilla del inquilino.
- Tabla `tenants` y modelo `Tenant`.
- Los estados del inquilino y las transiciones permitidas.

## Por qué una conexión aparte y no la de por defecto

La conexión por defecto va a cambiar en cada petición para apuntar al inquilino
(RFC-06). La central no puede seguir ese movimiento: es la que dice a qué
inquilino apuntar, así que tiene que estar disponible **antes** de resolverlo y
seguir disponible **después**.

Todo lo que viva en la central se consulta nombrando la conexión de forma
explícita. Nunca por herencia de la conexión ambiente.

## Tabla `tenants`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint | |
| `slug` | string(32) único | Lo genera el servidor. Formato en RFC-05 |
| `database` | string(64) único | Nombre real de la base. No se deriva del slug al vuelo |
| `estado` | string | Ver abajo |
| `email` | string | Contacto del visitante |
| `origen_hash` | string(64) nulo | **Fase 2.** Hash con sal del origen, para limitar altas repetidas (RFC-10). Con invitación no se usa: es un dato personal menos que retener |
| `template_version` | string | Con qué plantilla nació |
| `expira_en` | timestamp | |
| `borrado_en` | timestamp nulo | |
| `motivo_falla` | text nulo | Por qué falló el alta o el borrado. La pide RFC-12: sin ella el padrón no tiene qué mostrar |
| `created_at`, `updated_at` | timestamp | |

`database` se guarda y no se recalcula. Si mañana cambia la forma de nombrar las
bases, los inquilinos viejos tienen que seguir encontrando la suya.

`origen_hash` guarda un hash, no la dirección. En fase 1 queda vacío: el demo es
por invitación y no hay altas repetidas que limitar. La columna existe desde el
principio para no migrar después, pero **no se llena hasta que el demo se abra**.
Guardar un dato personal que nadie usa es guardarlo por nada.

## Estados

| Estado | Significado | Va a |
|---|---|---|
| `aprovisionando` | La fila existe, la base no todavía | `activo`, `fallido` |
| `activo` | En uso, dentro de plazo | `expirado` |
| `fallido` | El alta no terminó | terminal |
| `expirado` | Venció; no se puede entrar | `borrado` |
| `borrado` | Base y archivos eliminados | terminal |

Las transiciones pasan por un único método del modelo. Un `estado` asignado a
mano en cualquier otro lugar es un error de implementación, no una alternativa.

## Convivencia con los vecinos del servidor

**Medido en el VPS (`srv650075`), no supuesto:**

| Dato | Valor |
|---|---|
| Disco libre en `/var/lib/postgresql` | 55 GB de 96 GB |
| Peso de un inquilino recién creado | 18 MB |
| `max_connections` | 100 |
| Bases ya existentes en esa instancia | 6 |

El disco no es el límite: 55 GB ÷ 18 MB son unos 3.000 inquilinos. Ni cerca de
apretar.

**El límite real es que la instancia de Postgres está compartida.** En ella
conviven la producción de New Hauz (`inmo_db`), otro proyecto (`museo_textil`) y
el stack de correo completo (`mail_server`, `postfixadmin`, `roundcubemail`). Las
100 conexiones son de todos.

Un demo descontrolado —un bucle, un worker mal configurado, alguien probando de
más— puede dejar sin conexiones a la producción inmobiliaria **y al servidor de
correo**. Ese es el riesgo operativo de esta épica, y no tiene nada que ver con
cuántos inquilinos haya.

Se cierra con dos topes, que son dos sentencias:

1. **Tope de conexiones al rol del demo** (`ALTER ROLE ... CONNECTION LIMIT`).
   El demo no puede pasar de su cuota, pase lo que pase adentro.
2. **Tope de conexiones por base de inquilino** (`ALTER DATABASE ... CONNECTION
   LIMIT`), fijado en el alta. Un inquilino no puede consumir la cuota de los
   demás.

El primero protege a los vecinos del demo. El segundo protege a los inquilinos
entre sí. Los dos hacen falta.

> **Nota sobre las conexiones**, porque es contraintuitivo: no son un costo *por
> inquilino* sino *por petición concurrente*. Laravel abre al empezar la petición
> y cierra al terminar, así que un inquilino dormido no consume ninguna. Con dos
> conexiones por petición —la central y la del inquilino— las 100 dan margen de
> sobra para un demo por invitación. Lo que importa no es cuántos inquilinos hay
> sino cuántas peticiones simultáneas, y que el demo no se pase de su cuota.

## Reglas

1. La fila del inquilino **sobrevive al borrado de su base**. Sirve para medir el
   uso y para que el mismo origen no recicle inquilinos sin límite.
2. Sólo `activo` permite resolver una petición hacia ese inquilino.
3. `expira_en` se fija en el alta, no se calcula al leer.
4. El rol del demo tiene tope de conexiones antes de que exista el primer
   inquilino. No después.
5. La central nunca guarda contenido de ningún inquilino. Si algún día hace
   falta un dato de adentro, se copia explícitamente y se documenta por qué.

## Definition of Done

- Existe la conexión `central` y sus migraciones corren separadas de las del
  inquilino.
- Un test verifica que las migraciones de la central **no** están en la
  plantilla del inquilino, y al revés.
- Un test recorre todas las transiciones válidas y rechaza las inválidas.
- Un test verifica que la fila sobrevive al estado `borrado`.
