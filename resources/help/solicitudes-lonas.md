# Manual de Solicitudes de lonas

Es la bandeja donde owner/admin revisan y resuelven las solicitudes de lonas adicionales que los
agentes envían desde su página **Mis Lonas**.

## ¿Para qué sirve?

Centraliza el pedido de más lonas: en vez de una entrega directa, el agente solicita y owner/admin
aprueba (definiendo cuántas entregar) o rechaza (con motivo).

Fíjate que **solo las solicitudes Pendientes muestran los botones Aprobar y Rechazar**: una vez
resuelta, la fila queda como registro histórico sin acciones.

{{captura: solicitudes-lonas/listado | Bandeja de solicitudes con las acciones Aprobar y Rechazar}}

## Cómo se usa

1. Cada fila muestra agente, tipo, cantidad solicitada, estado e inmueble sugerido (si el agente
   sugirió uno para el QR).
2. Con una solicitud **Pendiente**, usa **Aprobar**: el sistema te muestra el cupo disponible del
   agente para ese tipo (tope menos lo que ya tiene sin colocar) y proponte por defecto la cantidad
   mínima entre lo solicitado y lo disponible — puedes ajustarla dentro de ese máximo.
3. Si no corresponde, usa **Rechazar** indicando el motivo — queda registrado para el agente.
4. Al aprobar, se genera automáticamente el lote correspondiente en **Lonas asignadas**.

## Campos importantes

- **Estado**: Pendiente → Aprobada/Rechazada. Solo las solicitudes pendientes muestran las acciones
  Aprobar/Rechazar.
- **Cupo disponible**: se recalcula en el momento según cuántas lonas sin colocar tiene el agente de ese
  tipo — no es un número fijo por solicitud.
- **Contador en el menú**: el número junto a "Solicitudes de lonas" indica cuántas están pendientes de
  revisión.

## Preguntas frecuentes

- **La cantidad máxima que puedo aprobar es menor a lo solicitado** — el agente ya tiene lonas sin
  colocar de ese tipo cerca del tope; solo puedes aprobar hasta el cupo disponible, no todo lo pedido.
- **¿Dónde queda el motivo de un rechazo?** — se guarda como parte del evento de la solicitud; el
  agente lo puede consultar como referencia de por qué no se aprobó.
