# RFC-03 Colas ancladas a la central

## Objetivo

Que la cola funcione antes de que exista el inquilino, y que ningún trabajo
escriba en la base equivocada.

## Épica

EPICA-DEMO. Lote A. Depende de RFC-01.

Cierra el contrato C-4 del diseño.

## Responsable

Backend.

## Alcance

- Fijar la conexión de la cola a `central`, de forma explícita.
- Un middleware de trabajo que resuelve la conexión del inquilino al empezar y la
  restaura al terminar.

## Por qué la cola vive en la central

Por una razón elemental: **el trabajo que crea la base del inquilino corre cuando
esa base todavía no existe.** Si la cola viviera adentro del inquilino, el alta
no tendría dónde encolarse.

La conexión de la cola se declara explícitamente. No se hereda de la conexión por
defecto, porque esa cambia en cada petición.

## El modo de falla más silencioso de la épica

El worker es un proceso largo que atiende inquilinos distintos uno detrás del
otro. Si un trabajo deja la conexión por defecto apuntando al inquilino A, el
siguiente trabajo —que puede ser de B, o de la central— la hereda.

**Ese error no da excepción.** Escribe, y escribe bien, en la base equivocada. Se
descubre cuando alguien nota datos ajenos, que puede ser semanas después.

## Por qué middleware y no convención

El diseño podría decir "cada trabajo resuelve y restaura su conexión". Eso es
disciplina, y la primera regla de oro de la épica dice que la disciplina no es
protección.

Se implementa como **middleware de trabajo obligatorio**: el trabajo declara de
qué inquilino es y el middleware hace el resto. Un trabajo nuevo que se olvide de
declararlo no debe correr con la conexión ambiente: debe fallar.

## Reglas

1. Todo trabajo que toque datos de un inquilino guarda su `slug`, no el objeto
   ni la conexión.
2. El middleware restaura la conexión anterior en un `finally`. Un trabajo que
   lanza excepción no puede dejar la conexión movida.
3. Un trabajo sin inquilino declarado corre contra `central` y no puede acceder a
   ninguna base de inquilino.
4. La cola nunca se configura por variable de entorno que pueda quedar apuntando
   a otro lado. Es explícita en configuración.

## Definition of Done

- Un test encola un trabajo del inquilino A y otro del B, los procesa en el mismo
  worker y verifica que cada uno escribió en su base.
- Un test verifica que un trabajo que lanza excepción deja la conexión como
  estaba.
- Un test verifica que un trabajo sin inquilino declarado no alcanza datos de
  ningún inquilino.
