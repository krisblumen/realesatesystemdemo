# RFC-069 Estatus, Notificaciones y Automatizaciones

## Objetivo
Gestionar las notificaciones de cada evento del ciclo de vida del contrato y los procesos automáticos que mantienen su estatus actualizado.

## Épica
EPICA-10 Contratos de Intermediación

## Responsable
Por asignar

## Alcance
- Envío del enlace/QR inicial y de reenvíos, por email y WhatsApp simultáneamente (RFC-044).
- Notificaciones a cliente, agente y administrador/owner en cada cambio de estatus.
- Jobs automáticos para expiración, vencimiento y retención de datos.

## Notificaciones
Se apoya en RFC-053 (Notificaciones Avanzadas).
- **Al cliente**: enlace enviado (email + WhatsApp), recordatorio de firma pendiente, copia del contrato firmado (PDF final).
- **Al agente**: cliente vio el contrato, cliente firmó, cliente rechazó, contrato por expirar.
- **Al administrador/owner**: visibilidad general o resumen periódico (opcional), y aviso cuando un contrato entra a la lista de eliminación pendiente (ver Automatizaciones).

## Automatizaciones
Se apoya en RFC-054 (Automatizaciones):
- **Expiración**: job que marca como **Expirado** los contratos sin respuesta después de 72 horas (mismo plazo de expiración del token, RFC-064).
- **Recordatorio**: notificación automática al cliente si no ha firmado antes de que expire el plazo (por ejemplo, a las 48 horas).
- **Vencimiento**: job que marca como **Vencido** un contrato firmado cuya vigencia terminó.
- **Retención y eliminación**: job que, al cumplirse 2 años desde la fecha de firma, mueve el contrato a una lista de eliminación pendiente y notifica al Owner. La eliminación efectiva del expediente (incluida la identificación oficial adjunta) requiere confirmación manual del Owner; no es automática.

## Reenvío
- Aplica sobre contratos en estado Rechazado o Expirado.
- Conserva el mismo folio (RFC-064) y reinicia el ciclo de notificación (enlace enviado por ambos canales, recordatorio, expiración a 72 horas).

## Definition of Done
Notificaciones operando en cada evento del ciclo de vida, y los tres jobs automáticos (expiración, vencimiento, retención) funcionando según lo definido.
