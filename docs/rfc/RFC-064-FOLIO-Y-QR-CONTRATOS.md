# RFC-064 Generación de Folio y QR

## Objetivo
Generar, para cada contrato, un folio único de 8 caracteres alfanuméricos y un QR/enlace de acceso público, de un solo uso para el llenado, imprimible y con historial permanente.

## Épica
EPICA-10 Contratos de Intermediación

## Responsable
Por asignar

## Alcance
- Generación del folio al crear el contrato (RFC-063).
- Generación de un token de acceso separado del folio, asociado al enlace/QR.
- Generación de la imagen QR apuntando a la vista pública del formulario (RFC-066).
- Vista/versión imprimible del QR y del folio.
- Endpoint de reenvío que emite un nuevo token para el mismo folio.

## Folio
- 8 caracteres alfanuméricos.
- Único a nivel de todo el sistema, no por sucursal (pensando en RFC-058 Preparación Multisucursal).
- Al generarse, el sistema valida colisión contra folios existentes y reintenta si ya existe.
- El folio no cambia durante todo el ciclo de vida del contrato, incluyendo reenvíos.

## Token de acceso (QR/enlace)
- Es distinto del folio: el folio identifica al contrato de forma permanente; el token es lo que efectivamente da acceso al formulario y es lo que se invalida o renueva.
- Expira a las 72 horas de emitido (configurable).
- Es de **un solo uso**: al completarse el llenado (firma o rechazo), el token queda invalidado.
- No requiere verificación adicional (sin OTP): el token es la única barrera de acceso, según lo definido en EPICA-10.
- Cada token generado (inicial o de reenvío) queda registrado en el historial del contrato, incluso después de invalidado.

## QR y enlace
- El QR codifica la URL pública del formulario (`dominio/contrato/{token}` o equivalente).
- El QR y el enlace se entregan al cliente por email y WhatsApp (ver RFC-069) y también deben poder mostrarse/descargarse en una vista imprimible desde el panel interno, para entrega en papel cuando el agente está frente al cliente.
- La vista imprimible incluye folio y QR; queda asociada permanentemente al historial del contrato aunque el token asociado ya haya expirado o se haya usado.

## Reenvío
- Aplica cuando el contrato está en estado Rechazado o Expirado.
- Se conserva el mismo folio.
- Se genera un nuevo token de acceso (el anterior queda invalidado si no lo estaba ya) y un nuevo QR/enlace.
- El contrato regresa a estado Enviado.
- Cada reenvío queda registrado en el historial de accesos del contrato.

## Definition of Done
Folio único generado sin colisiones, QR/enlace de un solo uso funcionando con expiración, vista imprimible disponible, y reenvío operando sobre el mismo folio.
