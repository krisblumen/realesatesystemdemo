# RFC-12 Padrón del operador

## Objetivo

Que quien mantiene el demo vea qué está pasando, sin poder abrir el contenido de
ningún inquilino.

## Épica

EPICA-DEMO. Transversal. Depende de RFC-01, RFC-06 y RFC-09.

Cierra el hallazgo M-4 de la auditoría de diseño.

## Responsable

Backend.

## Por qué existe este RFC

En el RFC de la épica esto era una frase: "el operador ve el padrón de
inquilinos, no el contenido de ninguno". Una frase no es un contrato. Sin definir
dónde vive esa pantalla, con qué autenticación y **cómo se garantiza
técnicamente** el "no el contenido", la garantía es una intención.

## Alcance

- Pantalla de padrón en el host central.
- Autenticación propia, separada de la de los inquilinos.
- Qué se ve y qué no.
- Acciones del operador.

## Dónde vive y con qué credenciales

En el **host central**, que no resuelve ningún inquilino (RFC-06). Con su propio
juego de credenciales, que no son las de ningún inquilino ni las de ninguna
instalación del producto.

Que viva en el host central no es una decisión de comodidad: es lo que hace que
la garantía sea estructural. En ese host la conexión por defecto **es** la
central. No hay conexión abierta a ninguna base de inquilino desde donde leer
algo de adentro.

## Qué se ve

Del padrón, por inquilino:

- `slug`, estado, cuándo nació, cuándo vence.
- Con qué versión de plantilla nació.
- Si el alta falló, el motivo.
- Si el borrado falló y cuántas veces se reintentó.

Agregados: cuántos inquilinos activos hay contra el tope duro (RFC-10), cuántas
altas por día, cuántas fallaron.

## Qué NO se ve

Nada de adentro de un inquilino. Ni sus inmuebles, ni sus clientes, ni su
contenido publicado, ni sus usuarios más allá de que existen.

No hay "entrar como" ni suplantación. Un demo público es exactamente el lugar
donde esa función se ve mal: el producto que se está vendiendo promete que los
datos de un cliente son de ese cliente.

El correo del visitante se ve sólo mientras exista por el límite de abuso, y
desaparece con él.

## Acciones

- Vencer un inquilino antes de tiempo.
- Reintentar un borrado fallido.
- Cerrar el registro temporalmente, sin desplegar.

Ninguna abre contenido.

**Alcance en fase 1, decidido al implementar.** Existen como comandos:
`demo:expirar --slug` vence hoy, `demo:borrar --slug` reintenta y
`demo:abortar-borrado --slug` deshace un cierre de puerta. Falta cerrar las
invitaciones, y **no hay registro de quién hizo qué**.

Ese registro se difiere a fase 2 A PROPÓSITO, no por olvido. En fase 1 el único
camino es la consola del servidor y quien opera es la misma persona que invita:
«quién» ya está en los accesos del servidor, y una tabla de auditoría que
siempre dice el mismo nombre no informa nada — sólo da sensación de control.
Cuando el padrón sea una pantalla web con más de un operador, el registro
empieza a significar algo y entra junto con ella.

**Y hasta entonces, este RFC no debe prometerlo.** Un contrato que el código no
cumple no es una deuda: es una afirmación falsa, y alguien la va a leer como
garantía.

## Reglas

1. Ninguna consulta de esta pantalla nombra una base de inquilino.
2. La autenticación del operador no comparte tabla con la de los inquilinos.
3. Cerrar el registro no afecta a los inquilinos ya activos.

## Definition of Done

- Un test verifica que ninguna consulta del padrón se conecta a una base de
  inquilino.
- Un test verifica que el operador autenticado no puede alcanzar el panel de
  ningún inquilino.
- Un test verifica que cerrar el registro no interrumpe a los activos.
