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
| `origen_hash` | string(64) | Hash con sal del origen. Ver RFC-10 |
| `template_version` | string | Con qué plantilla nació |
| `expira_en` | timestamp | |
| `borrado_en` | timestamp nulo | |
| `created_at`, `updated_at` | timestamp | |

`database` se guarda y no se recalcula. Si mañana cambia la forma de nombrar las
bases, los inquilinos viejos tienen que seguir encontrando la suya.

`origen_hash` guarda un hash, no la dirección. Sirve para limitar altas repetidas
sin retener un dato personal más tiempo del necesario.

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

## Reglas

1. La fila del inquilino **sobrevive al borrado de su base**. Sirve para medir el
   uso y para que el mismo origen no recicle inquilinos sin límite.
2. Sólo `activo` permite resolver una petición hacia ese inquilino.
3. `expira_en` se fija en el alta, no se calcula al leer.
4. La central nunca guarda contenido de ningún inquilino. Si algún día hace
   falta un dato de adentro, se copia explícitamente y se documenta por qué.

## Definition of Done

- Existe la conexión `central` y sus migraciones corren separadas de las del
  inquilino.
- Un test verifica que las migraciones de la central **no** están en la
  plantilla del inquilino, y al revés.
- Un test recorre todas las transiciones válidas y rechaza las inválidas.
- Un test verifica que la fila sobrevive al estado `borrado`.
