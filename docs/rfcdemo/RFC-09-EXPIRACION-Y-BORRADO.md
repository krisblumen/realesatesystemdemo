# RFC-09 Expiración y borrado

## Objetivo

Que un inquilino vencido deje de existir sin dejar bases, archivos ni filas
huérfanas, y sin que una pestaña abierta lo impida.

## Épica

EPICA-DEMO. Lote F. Depende de RFC-05 y RFC-06.

Cierra el contrato C-7 y el hallazgo M-1 de la auditoría de diseño.

## Responsable

Backend.

## Alcance

- Tarea programada que marca vencidos.
- Trabajo que borra un inquilino: base y archivos.
- El orden de las operaciones, que no es negociable.

## Dos etapas, no una

**Marcar** y **borrar** son dos cosas separadas y a propósito.

Marcar `expirado` corta el acceso de inmediato y es barato. Borrar es caro,
irreversible y puede fallar. Separarlas permite que el corte de acceso sea
inmediato y confiable aunque el borrado se demore o haya que reintentarlo.

Entre una y otra queda una ventana en la que el inquilino ya no entra pero sus
datos existen. Esa ventana es deliberada: da margen para atender un reclamo antes
de que sea imposible.

## El orden del borrado

1. **Cerrar la puerta**: `ALTER DATABASE ... CONNECTION LIMIT 0`. Una sola
   sentencia, sin enumerar roles, y se deshace igual de fácil si el borrado se
   aborta.
2. **Terminar** las sesiones vivas.
3. **Borrar** la base.
4. **Borrar** `tenants/{slug}/` del disco.
5. Marcar `borrado`, conservando la fila.

**El paso 1 es la corrección de M-1.** Terminar las sesiones sin cerrar antes
deja una ventana: el navegador del visitante reintenta, se reconecta, y el
borrado de la base falla. En un demo con pestañas abiertas eso no es raro, es lo
normal.

**El orden 3 antes que 4 también importa.** Si se borran los archivos primero y
el borrado de la base falla, queda un inquilino vivo con las imágenes rotas — un
estado peor que no haber empezado.

## La fila sobrevive

Se conserva con estado `borrado`. Sirve para medir el uso del demo y para que el
mismo origen no recicle inquilinos sin límite (RFC-10).

Se conserva el `origen_hash`, no el origen. El correo se conserva sólo el tiempo
que haga falta para el límite de abuso, y después se vacía.

## Abortar un borrado ya empezado

El paso 1 se puede deshacer, y hace falta un camino explícito para hacerlo. Si
el operador cierra la puerta y después aborta —por un reclamo del inquilino, por
ejemplo— la base queda con `CONNECTION LIMIT 0` y **nadie puede entrar aunque el
inquilino se reactive**.

No es el camino feliz, pero es el que deja el sistema en un estado que parece
sano y no lo está: el padrón muestra el inquilino, y el inquilino no abre.

Contrato: la acción de abortar restaura el límite de conexiones al valor normal,
y el padrón (RFC-12) la ofrece junto a «reintentar borrado».

## Reintentos

El borrado es reintentable: cada paso comprueba si ya está hecho antes de
hacerlo. Un borrado a medias no puede dejar el sistema en un estado que sólo se
arregle a mano.

Si falla varias veces, el inquilino queda visible para el operador (RFC-12) con
su motivo de falla. No se reintenta para siempre en silencio.

## Reglas

1. Marcar y borrar nunca corren en la misma tarea.
2. El borrado sólo alcanza bases con el prefijo de inquilino. Jamás puede tocar
   la central ni una plantilla.
3. Ningún borrado corre en el request.

## Definition of Done

- Un test borra un inquilino con una sesión abierta contra su base, y no falla.
- Un test verifica que después del borrado no queda base, ni archivos, y sí queda
  la fila.
- Un test verifica que un borrado interrumpido a mitad se puede reintentar y
  termina bien.
- Un test verifica que abortar tras cerrar la puerta deja la base **conectable**
  otra vez.
- Un test verifica que el borrado no puede nombrar la central ni una plantilla.
