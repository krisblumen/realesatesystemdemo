# RFC-11 Registro público y entrega de acceso

> **FASE 2 — no se implementa todavía.** El demo arranca por invitación
> (`RFC-13`), y con invitación esto no hace falta. Queda escrito para cuando el
> demo se abra al público.

## Objetivo

Que un visitante se registre y entre a probar el sistema, sin que un correo
perdido lo deje afuera.

## Épica

EPICA-DEMO. Lote G. Depende de RFC-05, RFC-06, RFC-07 y RFC-08.

Cierra el hallazgo M-3 de la auditoría de diseño.

## Responsable

Backend + Owner del producto.

## Por qué va último

Es la parte que uno querría hacer primero, y es la que menos sentido tiene sola.
Sin los RFC anteriores, un visitante registrado entra a un sistema que se pisa
con el vecino: el registro sería la puerta a un problema, no a un demo.

## Alcance

- Formulario de registro en el host central.
- Pantalla de espera mientras se aprovisiona.
- Entrega del acceso.
- Qué pasa con un inquilino al que nadie entra.

## El formulario

Pide un correo. Nada más.

No pide el subdominio: el `slug` lo genera el servidor (RFC-05), y esa decisión
no es de comodidad — es la primera de las cuatro medidas que impiden que el
nombre de una base sea una puerta de entrada.

Los límites de RFC-10 se comprueban **acá**, antes de encolar.

## La espera

El alta va en cola, así que la respuesta es inmediata y el trabajo ocurre atrás.
La pantalla dice que se está preparando el espacio y se actualiza sola.

Copiar la plantilla cuesta 0.2 s, así que en el caso normal la espera es
imperceptible. La pantalla existe para el caso anormal: cola con trabajo
acumulado, cerrojo tomado, servidor cargado. Si el alta falla, esa pantalla lo
dice y ofrece reintentar; no se queda girando para siempre.

## La entrega del acceso, y por qué no basta el correo

**El acceso se muestra en pantalla.** Usuario, contraseña generada y la dirección
del inquilino, ahí mismo, apenas termina el alta.

El correo va **además**, no en lugar de.

Si la entrega dependiera sólo del correo, cada mensaje que cae en spam, se
demora o rebota es un visitante que quería probar el producto y no pudo, con un
inquilino aprovisionado ocupando lugar. En un demo público eso no es un caso
borde: es la mayor parte del embudo perdido en el último paso.

La contraseña se muestra **una sola vez**, con esa advertencia. Si se pierde, se
regenera desde la misma pantalla mientras el inquilino esté activo.

## El inquilino al que nadie entra

Si nadie inicia sesión en un plazo corto, el inquilino se marca vencido antes que
los demás y se borra. Un espacio aprovisionado y nunca usado ocupa exactamente lo
mismo que uno en uso, y el tope de RFC-10 es el recurso escaso.

Ese plazo corto es distinto del plazo de vida normal y es configurable aparte.

## Reglas

1. El registro no crea nada por su cuenta: valida, encola y responde.
2. La contraseña se genera; no se pide ni se elige.
3. El correo del visitante se guarda sólo mientras haga falta para el límite de
   abuso, y después se vacía (RFC-09).
4. Ningún mensaje de error dice si un correo ya se registró antes. Eso permitiría
   averiguar quién usó el demo.

## Definition of Done

- Un test verifica que el acceso aparece en pantalla aunque el envío del correo
  falle.
- Un test verifica que un alta fallida deja la pantalla en un estado que lo dice.
- Un test verifica que un inquilino sin ningún inicio de sesión se vence antes.
- Un test verifica que el registro no revela si un correo ya existía.
