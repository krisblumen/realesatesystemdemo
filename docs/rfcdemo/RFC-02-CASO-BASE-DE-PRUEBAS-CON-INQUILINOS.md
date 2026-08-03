# RFC-02 Caso base de pruebas con inquilinos reales

## Objetivo

Poder escribir el test que justifica la épica: dos inquilinos publican contenido
distinto y ninguno ve el del otro.

## Épica

EPICA-DEMO. Lote A. Depende de RFC-01.

Cierra el contrato C-9 y el hallazgo crítico C-3 de la auditoría de diseño.

## Responsable

Backend.

## Por qué esto va primero y no al final

El andamiaje de pruebas se deja para el final por costumbre. Acá no se puede: los
otros ocho contratos de la épica se verifican **con** esta pieza. Sin ella, todo
lo que venga después se implementa a ciegas y el problema aparece cuando ya no
hay presupuesto para rehacerlo.

## El choque a resolver

`RefreshDatabase` envuelve cada test en una transacción sobre la conexión por
defecto. Esta épica cambia la conexión por defecto a mitad de la petición, y
además necesita crear bases de verdad — que es exactamente lo que Postgres no
permite dentro de una transacción:

```
ERROR: CREATE DATABASE cannot run inside a transaction block
```

## Alcance

### 1. Transaccionar sólo la central

`RefreshDatabase` permite acotar qué conexiones envuelve. Se limita a `central`.
Las bases de inquilino no se transaccionan: son efímeras y se tiran enteras.

### 2. Conexión de mantenimiento

Una conexión apuntada a la base `postgres`, sin transacción abierta, que es la
única que ejecuta `CREATE DATABASE` y `DROP DATABASE` en los tests. Así el
`CREATE` nunca cae dentro de la transacción del test.

### 3. Caso base `TenantTestCase`

Ofrece un método para levantar un inquilino de prueba y borra todos los que haya
creado al terminar el test, **pase o falle**. El borrado va en el equivalente de
un `finally`: un test que falla no puede dejar bases atrás.

### 4. Plantilla de pruebas

Se construye **una vez para toda la suite**, no por test. Copiarla cuesta 0.2 s;
migrar desde cero cuesta segundos. Con dos inquilinos por test y una decena de
tests de aislamiento, el costo total ronda los cuatro segundos.

Si la plantilla no existe, el caso base **falla con un mensaje que dice qué
comando correr**. No con un error de Postgres sobre una base inexistente, que
manda a depurar el lugar equivocado.

### 5. Barrido de bases de prueba

Una corrida interrumpida deja bases reales en el servidor, y esas no desaparecen
solas. Se nombran con un prefijo reconocible y hay un comando que borra lo que
quedó de corridas anteriores.

> Nota: acá el barrido **sí** hace falta, al revés que con los cerrojos de
> RFC-05. Dos situaciones que suenan parecidas y piden respuestas contrarias.

## Reglas

1. Ningún test de inquilino usa `RefreshDatabase` sobre la conexión del
   inquilino.
2. El nombre de las bases de prueba lleva un prefijo distinto del de producción.
   Un barrido de tests jamás debe poder tocar una base real.
3. El caso base no comparte estado entre tests. Dos tests que corren seguidos no
   heredan inquilinos.

## Definition of Done

- Existe un test que levanta dos inquilinos, escribe en cada uno y verifica que
  el contenido no se cruza.
- Un test que falla a propósito deja el servidor sin bases huérfanas.
- Correr la suite sin la plantilla de pruebas da un mensaje accionable.
- El comando de barrido existe y tiene su propio test de que no toca bases fuera
  del prefijo.
