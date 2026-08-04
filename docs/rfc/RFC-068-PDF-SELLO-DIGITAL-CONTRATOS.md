# RFC-068 Generación de PDF, Sello Digital y Almacenamiento

## Objetivo
Generar el documento final (PDF) del contrato firmado, con la evidencia de firma y el sello digital de la inmobiliaria, y almacenarlo de forma segura dentro del expediente del contrato.

## Épica
EPICA-10 Contratos de Intermediación

## Responsable
Por asignar

## Alcance
- Plantilla de PDF única, con clausulado dinámico según exclusividad (con/sin) y tipo de operación (venta/renta/renta con opción a compra).
- Inclusión de los datos capturados, la firma del cliente y la identificación oficial adjunta como evidencia.
- Estampado del sello digital de la inmobiliaria.
- Cálculo y registro del hash SHA-256 del documento final.
- Almacenamiento del PDF vía Media Library (RFC-007), como parte del expediente de ese contrato.
- Vista pública de verificación por folio.

## Sello digital
El diseño gráfico del sello se entrega en formato **SVG** por el equipo, y se integra en el PDF final. El sello codifica:
- Folio del contrato.
- Hash SHA-256 del documento final.
- Fecha y hora de emisión.

El sello visual incluye, además del diseño gráfico, el folio en texto y un mini-QR de verificación que enlaza a la vista pública de verificación (`dominio/verificar/{folio}`).

## Vista pública de verificación
- Accesible por folio, sin necesidad de token del contrato (a diferencia del formulario de llenado).
- Muestra únicamente: folio, estatus (firmado), fecha de firma.
- Permite subir un PDF para comparar su hash contra el guardado en el sistema, como verificación de integridad.
- No expone datos personales del cliente ni del inmueble.

## Almacenamiento
- El PDF final, junto con la identificación oficial usada como evidencia, se guarda dentro del expediente de ese contrato específico (no en un repositorio general de documentos del cliente).
- Acceso restringido: la identificación oficial adjunta solo puede consultarse por el rol Owner (ver RFC-070 para el detalle de permisos de visualización).
- Aplica la política de retención de 2 años definida en RFC-069.

## Definition of Done
PDF generado automáticamente al firmar, con clausulado correcto según exclusividad/operación, sello digital estampado, hash calculado y registrado, y vista pública de verificación funcionando.
