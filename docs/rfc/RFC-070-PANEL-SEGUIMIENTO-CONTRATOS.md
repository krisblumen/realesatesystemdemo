# RFC-070 Panel de Seguimiento de Contratos

## Objetivo
Implementar el panel interno (Filament) donde Agente, Admin y Owner consultan, filtran y dan seguimiento a los contratos de intermediación generados.

## Épica
EPICA-10 Contratos de Intermediación

## Responsable
Por asignar

## Alcance
- Listado de contratos con folio, cliente, inmueble, tipo de operación, estatus y fechas clave.
- Filtros por estatus, agente, tipo de operación y exclusividad.
- Vista de detalle por contrato, con historial completo de auditoría (generado, enviado, visto, firmado/rechazado, cancelado, reenviado) — RFC-057.
- Acciones disponibles según permisos: reenviar, cancelar, descargar PDF firmado, ver QR/folio imprimible.
- Acceso restringido a la identificación oficial adjunta.

## Permisos
Se apoya en RFC-006/RFC-012 (Spatie Permission).
- **Ver el listado y detalle general**: Agente (solo sus propios contratos), Admin y Owner (todos).
- **Cancelar contratos**: Admin y Owner.
- **Enviar/reenviar contratos**: Agente, Admin y Owner.
- **Ver identificaciones oficiales adjuntas**: solo Owner.

## Indicadores sugeridos
- Contratos por estatus (generados, enviados, firmados, rechazados, expirados, vencidos).
- Contratos próximos a expirar (sin respuesta del cliente).
- Contratos próximos a la revisión de retención (2 años desde la firma).

## Definition of Done
Panel operativo con listado, filtros, detalle con historial de auditoría, y acciones (reenviar, cancelar, descargar) respetando los permisos definidos.
