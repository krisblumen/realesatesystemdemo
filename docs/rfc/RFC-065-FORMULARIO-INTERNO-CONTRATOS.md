# RFC-065 Formulario Interno de Captura

## Objetivo
Implementar, en el panel administrativo (Filament), la pantalla que permite a Agente, Admin y Owner generar un nuevo contrato de intermediación.

## Épica
EPICA-10 Contratos de Intermediación

## Responsable
Por asignar

## Alcance
- Formulario "Nuevo contrato de intermediación" dentro de Filament (RFC-004).
- Captura de datos del cliente e inmueble a promover (ver RFC-063 para el detalle de campos).
- Selección de tipo de operación (venta / renta / renta con opción a compra) y exclusividad (sí/no).
- Captura de vigencia (fecha inicio / fin) y porcentaje de comisión.
- Al guardar: dispara la generación de folio y QR (RFC-064) y el envío inicial por email y WhatsApp (RFC-069).
- Listado propio de "mis contratos generados" para el agente (complementario al panel general de RFC-070).

## Permisos
Se apoya en RFC-006/RFC-012 (Spatie Permission).
- **Generar contratos**: Agente, Admin y Owner.
- Los campos de identificación oficial del cliente se cargan en este formulario o quedan pendientes para que el propio cliente los suba en el formulario público (RFC-066) — a definir en implementación, según si el agente ya cuenta con las fotos al momento de generar el contrato.

## Validaciones
- Campos obligatorios mínimos para generar el folio: nombre y contacto del cliente (teléfono o email), tipo de inmueble, tipo de operación, dirección del inmueble, porcentaje de comisión.
- El resto de los datos (identificación oficial, dirección completa del cliente) puede completarse por el propio cliente en el formulario público si el agente no los captura de entrada.

## Definition of Done
Formulario operativo en Filament, restringido a los roles definidos, que genera el contrato, el folio, el QR y dispara el envío inicial.
