# RFC-063 Modelo de Contrato de Intermediación

## Objetivo
Definir la entidad Contrato de Intermediación: sus campos, relaciones y estados, como base de datos sobre la que operan el resto de los RFCs de EPICA-10.

## Épica
EPICA-10 Contratos de Intermediación

## Responsable
Por asignar

## Alcance
- Modelo `ContratoIntermediacion` (o nombre equivalente) y su migración.
- Relación con el agente que lo generó.
- Relación con el historial de accesos (folio/QR, ver RFC-064).
- Relación con la evidencia de firma (ver RFC-067).
- Definición formal de los estados y sus transiciones válidas.

## Campos
**Identificación**
- Folio alfanumérico de 8 caracteres, único a nivel de todo el sistema.
- Fecha de generación.
- Estatus actual.

**Cliente**
- Nombre completo, teléfono, email, dirección.
- Identificación oficial (anverso y reverso) — adjunta como evidencia del contrato, no como documento independiente.

**Inmueble a promover**
- Tipo de inmueble.
- Tipo de operación: venta, renta, o renta con opción a compra.
- Dirección del inmueble.
- Porcentaje de comisión.

Nota: estos datos son propios del contrato; no referencian un registro del catálogo de Property (RFC-019). El contrato no depende de que el inmueble exista previamente en el catálogo.

**Condiciones del contrato**
- Vigencia (fecha inicio / fecha fin), variable por contrato.
- Exclusividad (sí/no), variable por contrato.
- Versión de la plantilla legal utilizada (una sola plantilla; el clausulado se arma dinámicamente según exclusividad y tipo de operación — ver RFC-066).

**Trazabilidad**
- Agente responsable (FK a User).
- Timestamps por evento: enviado, leído, firmado, rechazado, cancelado, expirado, vencido.
- Motivo de rechazo (texto libre, opcional).
- Hash del documento final (una vez firmado, ver RFC-068).

## Relaciones
- `belongsTo` Agente (User) — RFC-011.
- `hasMany` Accesos/tokens del folio (histórico de QR/enlaces emitidos, incluyendo reenvíos) — RFC-064.
- `hasOne` Evidencia de firma (IP, user-agent, timestamp, trazo) — RFC-067.
- `hasOne` Documento final (PDF con sello digital, vía Media Library) — RFC-068.
- `hasMany` Eventos de auditoría (generado, enviado, visto, firmado, rechazado, cancelado, reenviado) — RFC-057.

## Estados
- **Generado**: creado por el agente, aún no enviado.
- **Enviado**: el enlace fue entregado al cliente.
- **Leído/Visto**: el cliente abrió el formulario al menos una vez.
- **Firmado**: el cliente completó y firmó. Estado final positivo.
- **Rechazado**: el cliente decidió no firmar. Puede reenviarse (vuelve a Enviado, mismo folio).
- **Expirado**: se venció el plazo sin respuesta. Puede reenviarse (vuelve a Enviado, mismo folio).
- **Cancelado**: anulado manualmente por Admin/Owner antes de la firma. Estado final.
- **Vencido**: contrato firmado cuya vigencia terminó. Estado final.

## Reglas
- El folio no cambia durante todo el ciclo de vida del contrato, incluyendo reenvíos.
- Un contrato en estado Firmado, Cancelado o Vencido no puede volver a un estado anterior.
- Solo se genera un documento final (PDF) al pasar a Firmado.

## Definition of Done
Modelo, migración y relaciones funcionando, con los 8 estados operativos y sus transiciones validadas.
