# Manual de Mis Lonas

"Mis Lonas" es la vista personal del agente para administrar las lonas (publicidad física) que tiene
asignadas: pedir más, y registrar la evidencia de dónde las colocó. Solo la ve el rol `agente`.

## ¿Para qué sirve?

Te permite ver el estado de tus lonas (pendientes de colocar o ya colocadas), solicitar más cuando las
necesites y dejar constancia fotográfica de cada instalación.

## Cómo se usa

1. Si tienes una solicitud de lonas pendiente de aprobación, la ves destacada arriba de la tabla.
2. La tabla lista tus lonas con su tipo, estado, dónde se colocó (si corresponde) y fecha. Solo las
   que siguen **Pendientes de colocación** muestran el botón **Registrar evidencia**.

   {{captura: mis-lonas/vista | Mis Lonas con el estado de cada una y los botones para solicitar más}}
3. Para pedir más, usa **Solicitar más Venta** o **Solicitar más Renta** según el tipo que necesites —
   indica cantidad y, opcionalmente, el inmueble sugerido para el QR. El botón se deshabilita con un
   tooltip explicando el motivo si no tienes cupo disponible.
4. Para una lona todavía sin colocar, usa **Registrar evidencia**: sube la foto de la instalación y,
   si aplica, el inmueble y una referencia de ubicación.

## Campos importantes

- **Cupo para solicitar**: hay un máximo de lonas sin colocar por tipo; si lo alcanzaste, tienes que
  colocar (con evidencia) las que ya tienes antes de poder pedir más.
- **Estado de la lona**: Pendiente de colocación → Colocada. Una vez colocada con su evidencia, ya no
  se puede volver a registrar evidencia sobre esa unidad.
- **Solicitud pendiente**: mientras tengas una solicitud sin resolver, no vas a poder generar otra del
  mismo tipo — espera la respuesta de owner/admin en **Solicitudes de lonas**.

## Preguntas frecuentes

- **El botón "Solicitar más" está deshabilitado** — pasa el mouse sobre él para ver el motivo: o ya
  llegaste al tope sin colocar, o tienes una solicitud pendiente sin resolver.
- **Registré la evidencia pero me equivoqué de foto** — contacta a owner/admin; el registro de
  evidencia no tiene edición propia desde esta vista.
- **¿Dónde veo si me aprobaron o rechazaron mi solicitud?** — el widget de solicitudes pendientes
  desaparece cuando se resuelve; si fue rechazada, el motivo queda registrado y puedes consultarlo con
  owner/admin.
