# Manual de Leads

Un lead es un prospecto que mostró interés en un inmueble o en un servicio (llamada, formulario web,
WhatsApp). Esta sección es la bandeja donde se gestiona su seguimiento comercial.

## ¿Para qué sirve?

Centraliza todos los contactos entrantes para que ningún prospecto se pierda: quién lo generó, en qué
estado está, quién lo está atendiendo y qué inmueble o servicio le interesa.

En el listado ves de un golpe el origen, el servicio y el estado de cada lead. Los **leads nuevos sin
atender aparecen resaltados**, y el contador rojo junto a "Leads" en el menú te indica cuántos hay.

{{captura: leads/listado | Listado de leads con el nuevo resaltado y el contador en el menú}}

## Cómo se usa

1. Al crear o revisar un lead, el bloque **Datos del prospecto** (nombre, email, teléfono, origen,
   mensaje) es editable al alta por el agente; una vez creado, solo owner/admin pueden corregir esos
   datos de contacto — el agente sigue trabajándolo desde **Gestión**.

   {{captura: leads/form-datos | Sección Datos del prospecto del formulario}}

2. En **Gestión**, elige el **Tipo de servicio** (comercialización, renta de lonas, etc. — catálogo
   configurable en Tipos de servicio). Si el servicio es "comercialización", se habilita elegir el
   **Inmueble** de interés.

   {{captura: leads/form-gestion | Sección Gestión del formulario}}
3. Usa la acción **Cambiar estado** en la tabla para mover el lead por su ciclo de vida — el formulario
   solo ofrece las transiciones válidas desde el estado actual, no todas.
4. Owner/admin pueden **Reasignar** un lead puntual, o usar **Reasignar leads de un agente** para mover
   en bloque todos los leads abiertos de un agente que se va de vacaciones o deja el equipo, dejando
   registrado el motivo.

### Seguimiento: los comentarios del lead

Una vez creado el lead, **hasta abajo de su pantalla de detalle** encuentras el bloque
**Seguimiento**. Ahí puedes seguir agregando comentarios durante toda la vida del prospecto: cada
llamada, cada mensaje, lo que se acordó. Es la bitácora del trato con ese cliente.

Usa el botón **Agregar comentario** y escribe el avance. El sistema guarda solo la **fecha** y el
**autor**, así que después se puede reconstruir quién hizo qué y cuándo.

{{captura: leads/seguimiento | Bloque Seguimiento con los comentarios del lead}}

Tres cosas que conviene saber:

- Los comentarios **no se pueden editar**. Son historial: si algo cambió, agrega un comentario nuevo
  en vez de corregir el anterior.
- Solo el rol `owner` puede **borrar** un comentario.
- Un lead **cerrado** (ganado o perdido) ya no admite comentarios nuevos: el botón desaparece.

## Campos importantes

- **Estado**: Nuevo → Contactado → En seguimiento → Cerrado ganado / Cerrado perdido. Cada cambio de
  estado se hace con la acción "Cambiar estado", nunca editando el campo libremente.
- **Origen**: de dónde vino el lead (web, landing, ficha de inmueble, manual, teléfono) — útil para medir
  qué canal capta mejor.
- **Fila resaltada**: los leads nuevos sin atender se destacan visualmente en la tabla para que no pasen
  desapercibidos.
- **Los leads no se eliminan**: son historial comercial para auditoría; no existe acción de borrado.

## Preguntas frecuentes

- **¿Por qué no puedo editar el email de un lead que no creé yo?** — solo owner/admin corrigen los datos
  de contacto originales una vez creado el lead, para no perder trazabilidad del registro fuente.
- **No veo el selector de inmueble en Gestión** — solo aparece cuando el Tipo de servicio elegido es
  "comercialización"; otros servicios no lo necesitan.
- **¿Cómo veo el número de leads sin asignar?** — el contador rojo junto a "Leads" en el menú lo indica
  para owner/admin; el agente ve ahí su propia cantidad de leads abiertos.
