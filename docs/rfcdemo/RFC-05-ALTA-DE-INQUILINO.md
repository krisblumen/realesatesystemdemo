# RFC-05 Alta de inquilino

## Objetivo

Crear el espacio aislado de un visitante en segundos, sin que dos altas
simultáneas se estorben y sin que el nombre de una base sea una puerta de
entrada.

## Épica

EPICA-DEMO. Lote C. Depende de RFC-01, RFC-03 y RFC-04.

Cierra los contratos C-5, C-2 y C-8.

## Responsable

Backend.

## Alcance

- Generación y validación del `slug`.
- Trabajo en cola que copia la plantilla y crea el usuario del inquilino.
- Serialización de la copia.
- Rol de base de datos separado para el aprovisionamiento.

## 1. El `slug` y el nombre de la base

`CREATE DATABASE` es DDL, y **Postgres no acepta parámetros enlazados para
identificadores**. El nombre se interpola en la sentencia sí o sí.

Este es el único punto del sistema donde una falla no significa "un inquilino ve
a otro" sino "se pierden todos". Cuatro medidas, las cuatro obligatorias:

1. **El `slug` lo genera el servidor.** El visitante no lo elige ni lo sugiere.
   No existe campo en el formulario que llegue hasta acá.
2. **Formato cerrado**: `^[a-z][a-z0-9]{7,31}$`, validado **inmediatamente antes**
   de componer la sentencia. Pegado al uso peligroso, no sólo al crear la fila:
   si la validación queda lejos, alguien agrega un segundo camino que no pasa por
   ella.
3. **Prefijo fijo**: la base es `demo_t_{slug}`. Queda entre 14 y 38 bytes, bajo
   el límite de 63 de Postgres, y no puede coincidir con palabra reservada.
4. **El rol que crea bases no es el rol de las peticiones.** Aunque las tres
   medidas anteriores fallaran, el rol con el que corre el panel no puede
   ejecutar `CREATE DATABASE` ni `DROP DATABASE`.

Como red final, el identificador se cita con las reglas de Postgres en vez de
concatenarse a mano. Es redundante con el punto 2 y va igual.

## 2. Serialización de la copia

Postgres rechaza copiar una plantilla que tenga cualquier conexión encima. Dos
altas al mismo tiempo hacen fallar a la segunda.

**El cerrojo transaccional no es opción**: `pg_advisory_xact_lock` exige una
transacción y `CREATE DATABASE cannot run inside a transaction block`.

Queda el cerrojo de sesión, con dos precauciones:

- Se toma con `pg_try_advisory_lock` **en un bucle acotado**, no con la variante
  que espera sin límite. Si algo dejó el cerrojo tomado, el alta tiene que fallar
  con un mensaje; si espera para siempre, el síntoma es lentitud sin causa, que
  es lo último que alguien va a mirar.
- Se suelta **en un `finally`**. `pg_advisory_lock` se ata a la sesión, y la
  sesión de un worker no se cierra entre trabajos: una excepción entre tomar y
  soltar congela todas las altas siguientes mientras el worker siga vivo.

**No hay barrido de cerrojos huérfanos, y es deliberado.** Si el worker muere, su
sesión se cierra y Postgres suelta el cerrojo solo. El único caso real es el del
trabajo que falla con el worker vivo, y ese lo cubre el `finally`.

## 3. Pasos del trabajo

1. Tomar el cerrojo (bucle acotado).
2. `CREATE DATABASE demo_t_{slug} TEMPLATE demo_template_vN`.
3. Soltar el cerrojo, en `finally`.
4. **Desde acá nada toca la plantilla y puede correr en paralelo.**
5. Conectarse al inquilino y crear su usuario `owner` con contraseña generada.
6. Marcar `activo` y entregar el acceso (RFC-11).

Si algo falla entre 1 y 5: estado `fallido`, y una limpieza borra la base a
medias si llegó a crearse.

## 4. El usuario del inquilino

Nace con rol `owner` **dentro de su inquilino**. No hay rol por encima de
`owner`: los roles dicen qué puede hacer alguien, el aislamiento dice qué datos
existen para él, y son ejes perpendiculares.

La contraseña se genera, no se pide. Su entrega es RFC-11.

## Reglas

1. Ninguna parte del alta corre en el request. Todo va en cola.
2. La copia es lo único serializado.
3. El rol de aprovisionamiento no atiende peticiones nunca.

## Definition of Done

- Un test de dos altas concurrentes produce dos inquilinos, no un error.
- Un test verifica que un trabajo que falla después de tomar el cerrojo no
  bloquea el siguiente.
- Un test verifica que un `slug` fuera de formato es rechazado antes de tocar la
  base.
- Una alerta avisa si un cerrojo de esta clase lleva tomado más que una copia con
  holgura.
