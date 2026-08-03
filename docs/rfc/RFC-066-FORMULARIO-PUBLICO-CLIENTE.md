# RFC-066 Formulario Público del Cliente

## Objetivo
Implementar la vista pública, sin necesidad de cuenta en el sistema, donde el cliente completa los datos pendientes, revisa el clausulado del contrato y decide firmar o rechazar.

## Épica
EPICA-10 Contratos de Intermediación

## Responsable
Por asignar

## Alcance
- Vista pública accesible únicamente vía el token de un solo uso generado en RFC-064 (QR o enlace).
- Aviso de privacidad con aceptación explícita, previo a capturar datos personales e identificación oficial.
- Formulario para completar/confirmar datos del cliente y de la identificación oficial (anverso y reverso), si no fueron cargados por el agente.
- Presentación del clausulado del contrato, armado dinámicamente según exclusividad (con/sin) y tipo de operación (venta/renta/renta con opción a compra) — una sola plantilla base con variantes de texto.
- Acciones finales: **Firmar** (pasa a RFC-067) o **Rechazar** (con motivo opcional).
- Diseño mobile-first, ya que el acceso principal es vía QR desde el celular del cliente.

## Comportamiento por estado
- Primera apertura del enlace: el contrato pasa a estado **Leído/Visto** (si no lo estaba ya).
- Token expirado o ya usado: se muestra un mensaje indicando que el enlace ya no es válido, sin exponer datos del contrato.
- Contrato ya **Cancelado**: se muestra mensaje de contrato no disponible.

## Validaciones
- No se puede avanzar a la firma sin haber aceptado el aviso de privacidad.
- No se puede avanzar a la firma sin haber completado los campos obligatorios definidos en RFC-063.
- El rechazo permite capturar un motivo en texto libre (opcional).

## Definition of Done
Formulario público operativo, accesible solo con token válido de un solo uso, que permite completar datos, revisar el clausulado dinámico y decidir firmar o rechazar.
