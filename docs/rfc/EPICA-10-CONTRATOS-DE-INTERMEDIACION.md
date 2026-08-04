# EPICA-10 Contratos de Intermediación (Autorización de Promoción y Venta)

## Objetivo
Permitir que los agentes generen, desde el backend, contratos digitales de intermediación (autorización de promoción, venta o renta) que el cliente/propietario llena, revisa y firma de forma remota desde su propio dispositivo, quedando el documento registrado y trazable dentro del sistema.

## Responsable
Por asignar

## Épica
Este documento corresponde a **EPICA-10 CONTRATOS Y AUTORIZACIONES**, que agrupa los RFCs RFC-063 a RFC-070 (ver "RFCs incluidos"). No encaja del todo en EPICA-8 (Escalamiento) ni en EPICA-9 (Operación de Campo) porque es un proceso operativo core distinto: la firma legal de la relación cliente–inmobiliaria.

## Contexto
Hoy la autorización de promoción/venta se firma en papel o por fuera del sistema. El objetivo es digitalizar ese contrato: el agente captura los datos previos, el sistema genera un folio único y un QR/enlace, el cliente lo abre desde su celular o correo, llena lo que falte, revisa el clausulado y firma. El documento firmado queda registrado con estatus y evidencia de firma.

Es importante distinguir el rol del **cliente** en este contrato: es el propietario/oferente del inmueble que autoriza a la inmobiliaria a promoverlo (mandato de intermediación), no el comprador o arrendatario final. Esto debe quedar explícito en el modelo de datos y en el propio contrato.

## Actores
- **Agente/Asesor**: genera el contrato y captura los datos iniciales del inmueble y del cliente.
- **Cliente (propietario/oferente)**: recibe el enlace/QR, completa datos faltantes, revisa y firma.
- **Administrador/Owner**: supervisa, puede cancelar o dar seguimiento a todos los contratos.
- **Sistema**: genera folio, QR, controla estados, envía notificaciones, produce el PDF final.

## Alcance
- Formulario interno (backend/Filament) para que el agente capture los datos previos y genere el contrato.
- Generación de folio único alfanumérico de 8 dígitos por contrato.
- Generación de QR y enlace público único asociado al folio.
- Envío del enlace al cliente (email y WhatsApp, reutilizando RFC-044).
- Formulario público (sin login) para que el cliente complete datos, revise el clausulado y firme.
- Captura de firma electrónica simple en el dispositivo del cliente.
- Generación del documento final (PDF) con los datos y la evidencia de firma.
- Registro de estatus del contrato durante todo el ciclo de vida.
- Panel interno para consultar, filtrar y dar seguimiento a los contratos generados.

## Fuera de alcance (fase 1)
- Firma electrónica avanzada certificada ante un Prestador de Servicios de Certificación (PSC) — ver sección de validez legal.
- Pagos o cobro de comisiones (eso corresponde a un módulo financiero aparte).
- Generación automática del inmueble en el catálogo público a partir del contrato.

## Flujo del proceso
1. El agente abre "Nuevo contrato de intermediación" desde el backend.
2. Captura los datos del cliente y del inmueble a promover (ver siguiente sección).
3. El sistema genera el folio de 8 dígitos, crea el registro del contrato en estatus **Generado**, y genera el QR/enlace único.
4. El sistema envía el enlace al cliente por **email y WhatsApp** simultáneamente; el estatus pasa a **Enviado**.
5. El cliente abre el enlace o escanea el QR desde su dispositivo. Al abrirlo por primera vez el estatus pasa a **Leído/Visto**.
6. El cliente completa los campos pendientes (si los hay), revisa el clausulado del contrato y decide firmar o rechazar.
7. Si firma: se captura la firma, se genera el PDF final con evidencia, el estatus pasa a **Firmado**, y se notifica al agente/administrador.
8. Si rechaza: el estatus pasa a **Rechazado**, con posibilidad de capturar un motivo, y se notifica al agente.
9. Si el cliente no responde dentro de un plazo definido, el contrato pasa automáticamente a **Expirado** (ver Automatizaciones).
10. En cualquier momento antes de la firma, el agente/administrador puede **Cancelar** el contrato manualmente.
11. Si el contrato queda en **Rechazado** o **Expirado**, el agente puede reenviarlo: se conserva el **mismo folio** y se emite un nuevo acceso de un solo uso; el estatus regresa a **Enviado**.

## Datos a capturar

### Datos del cliente
- Nombre completo
- Teléfono
- Email
- Dirección
- Identificación oficial (foto anverso y reverso)

Nota: la identificación se usa únicamente como evidencia de la firma y queda adjunta a ese contrato específico; no se conserva en un repositorio separado de documentos del cliente.

### Datos del inmueble a promover
- Tipo de inmueble
- Tipo de operación (venta / renta / renta con opción a compra)
- Dirección del inmueble
- Porcentaje de comisión

Nota: el inmueble se captura como datos propios del contrato, no como referencia a un registro existente del catálogo de Property. El contrato no depende de que el inmueble ya esté dado de alta, y su firma tampoco crea automáticamente un registro en el catálogo.

### Datos propios del contrato
- Folio (generado por el sistema)
- Fecha de generación
- Vigencia del contrato (fecha inicio / fecha fin) — variable, definida por el agente en cada contrato
- Exclusividad (sí/no) — variable por contrato; cambia el clausulado legal aplicable
- Agente responsable que lo generó
- Versión de la plantilla legal utilizada (una sola plantilla; el clausulado se ajusta dinámicamente según exclusividad y tipo de operación)
- Estatus actual

## Identificador único y QR
- Folio alfanumérico de 8 caracteres, único a nivel de todo el sistema (no solo por sucursal, pensando en RFC-058 Preparación Multisucursal).
- El sistema debe validar colisiones al generar el folio y reintentar si ya existe.
- El QR y el enlace apuntan a una vista pública asociada al folio, sin necesidad de que el cliente tenga cuenta en el sistema.
- El enlace debe tener una **expiración** (por ejemplo 72 horas) y ser de **un solo uso efectivo** una vez firmado o rechazado.
- El QR/enlace debe poder **imprimirse** (por ejemplo, para entregarlo en papel cuando el agente está frente al cliente) y queda registrado de forma permanente en el historial/auditoría del contrato.
- Para el llenado del formulario, el acceso es de **un solo uso**: una vez que el cliente completa y envía (firma o rechaza), ese acceso queda invalidado. No se agrega un código de un solo uso (OTP) adicional; el QR/enlace es la única barrera.
- Si el contrato se reenvía por rechazo o expiración, se conserva el **mismo folio** y se emite un nuevo acceso de un solo uso para ese folio.

## Estados del contrato
- **Generado**: creado por el agente, aún no enviado.
- **Enviado**: el enlace fue entregado al cliente.
- **Leído/Visto**: el cliente abrió el formulario al menos una vez.
- **Firmado**: el cliente completó y firmó.
- **Rechazado**: el cliente decidió no firmar.
- **Expirado**: se venció el plazo para firmar sin respuesta.
- **Cancelado**: anulado manualmente por el agente/administrador antes de la firma.
- **Vencido**: el contrato ya firmado llegó al final de su vigencia (si aplica exclusividad con fecha límite).

## Seguridad y validez de la firma electrónica
**Decisión:** firma electrónica simple con evidencia reforzada, desarrollada in-house. Sin integración con proveedor certificado NOM-151 por ahora (queda como posible fase futura).

- La firma se captura en un canvas (trazo con el dedo/mouse) en el dispositivo del cliente. Es una **firma electrónica simple**: válida como principio de prueba, con fuerza probatoria menor a una firma certificada.
- El sistema debe capturar evidencia adicional en el momento de la firma: IP, user-agent/dispositivo, fecha y hora del servidor, y un hash del documento final para garantizar su integridad posterior.
- No se agrega verificación por código de un solo uso (OTP): el acceso de un solo uso por QR/enlace es la única barrera (ver "Identificador único y QR").
- De parte de la inmobiliaria no hay firma manual de un representante: el documento final se valida con un **sello digital único** generado por el sistema (ver siguiente sección).
- Si a futuro se requiere fuerza probatoria plena equivalente a firma autógrafa (por ejemplo para litigios), existen proveedores certificados en México bajo NOM-151 (Mifiel, Weetrust, DocuSign, etc.) que podrían evaluarse como integración posterior.

## Sello digital de la inmobiliaria
En lugar de una firma bilateral, el documento final lleva un **sello digital único** generado por el sistema en representación de la inmobiliaria. El diseño gráfico se proporciona en formato **SVG** por el equipo, para integrarse en el PDF final.

**Propuesta de contenido y verificación:**
- El sello codifica: folio del contrato, hash SHA-256 del PDF final (documento + evidencia de firma) y fecha/hora de emisión.
- Al estampar el sello, el sistema calcula el hash del documento final y lo guarda en el registro del contrato junto con el folio.
- El sello visual (SVG) incluye, además del diseño gráfico, el folio en texto y un mini-QR de verificación que enlaza a una vista pública (por ejemplo `dominio/verificar/{folio}`).
- Esa vista pública muestra únicamente: folio, estatus (firmado), fecha de firma, y una opción para verificar la integridad del documento (el visitante sube el PDF y el sistema compara el hash calculado contra el guardado). No se exponen datos personales del cliente ni del inmueble.
- Este mecanismo no sustituye una firma certificada NOM-151, pero permite comprobar que un PDF específico corresponde a un contrato real del sistema y que no ha sido alterado después de firmado.

## Protección de datos personales
- El formulario público debe mostrar un aviso de privacidad y requerir aceptación explícita antes de capturar datos personales e identificación oficial.
- **Decisión:** la identificación oficial no se conserva como documento independiente ni en un repositorio de datos del cliente; se usa únicamente como evidencia de la firma y queda adjunta al expediente de ese contrato específico.
- Las fotos de identificación son datos sensibles: deben almacenarse con acceso restringido (solo roles autorizados) y no exponerse en URLs públicas sin control, aunque vivan dentro del expediente del contrato y no en un repositorio aparte.
- **Decisión de retención**: el expediente del contrato, incluida la identificación adjunta como evidencia, se conserva **2 años** a partir de la fecha de firma.
- **Procedimiento de eliminación**: un job automático identifica los contratos que cumplen los 2 años y los mueve a una **lista de eliminación pendiente**; el **Owner** debe revisar y confirmar cada eliminación antes de que se ejecute (no es una eliminación automática sin supervisión).

## Notificaciones
Se apoya en RFC-053 (Notificaciones Avanzadas). **Decisión:** el enlace/QR se envía por ambos canales desde el primer momento: email y WhatsApp (RFC-044). Eventos a notificar:
- Al cliente: enlace enviado, recordatorio de firma pendiente, copia del contrato firmado.
- Al agente: cliente vio el contrato, cliente firmó, cliente rechazó, contrato por expirar.
- Al administrador/owner: visibilidad general o resumen periódico (opcional).

## Auditoría y trazabilidad
Se apoya en RFC-057 (Auditoría y Trazabilidad). Debe quedar registro de cada evento del contrato (generado, enviado, visto, firmado, rechazado, cancelado, reenviado) con actor, IP y fecha/hora, tanto de acciones internas (agente) como del cliente en el formulario público.

## Permisos y roles
Se apoya en RFC-006/RFC-012 (Spatie Permission, Roles y Permisos).

- **Generar contratos**: Agente, Admin y Owner.
- **Cancelar contratos**: Admin y Owner.
- **Enviar/reenviar contratos**: Agente, Admin y Owner.
- **Ver identificaciones oficiales adjuntas**: solo Owner.

## Automatizaciones
Se apoya en RFC-054 (Automatizaciones):
- Job que marca como **Expirado** los contratos sin respuesta después del plazo definido.
- Recordatorios automáticos al cliente si no ha firmado después de X horas.
- Job que marca como **Vencido** un contrato firmado cuya vigencia terminó (si aplica exclusividad con fecha límite).
- Job que, al cumplirse 2 años desde la firma, mueve el contrato a una **lista de eliminación pendiente** y notifica al Owner para su confirmación antes de eliminar el expediente.

## Relación con módulos existentes
- **RFC-011 Modelo Usuario**: el agente que genera el contrato es un User existente.
- **RFC-019 Modelo Inmueble / RFC-022 Estados Comerciales**: el contrato es independiente del catálogo de Property; no requiere que el inmueble exista previamente ni lo da de alta automáticamente al firmarse.
- **RFC-007 Media Library**: para almacenar la identificación (como evidencia dentro del expediente del contrato) y el PDF final.
- **RFC-052 Integraciones Externas / RFC-044 Tracking WhatsApp**: para el envío del enlace por WhatsApp, en paralelo al email.
- **RFC-058 Preparación Multisucursal**: el folio debe ser único globalmente, no por oficina.

## Decisiones tomadas
1. **Validez de la firma**: firma electrónica simple con evidencia reforzada, in-house. Sin integración con proveedor certificado NOM-151 por ahora.
2. **Verificación del firmante**: sin OTP adicional. El QR/enlace es la única barrera; es de un solo uso para el llenado, imprimible, y queda registrado de forma permanente en el historial del contrato.
3. **Relación contrato–inmueble**: el inmueble no necesita existir previamente en el catálogo, y la firma del contrato no lo da de alta automáticamente. El dato del inmueble vive únicamente dentro del contrato.
4. **Exclusividad y vigencia**: ambas son variables, definidas por el agente al generar cada contrato.
5. **Firma bilateral**: solo firma el cliente. De parte de la inmobiliaria el documento se valida con un **sello digital único** generado por el sistema, no con firma manual de un representante.
6. **Tipos de operación**: venta, renta, o renta con opción a compra — cada uno con su propio clausulado/plantilla.
7. **Identificación oficial**: se usa solo como evidencia adjunta a ese contrato; no se conserva como documento independiente en un repositorio de cliente.
8. **Canal de envío**: email y WhatsApp simultáneamente desde el primer envío.
9. **Reenvío**: si el contrato es rechazado o expira, se reenvía conservando el **mismo folio** (no se genera un contrato nuevo).
10. **Retención de datos**: el expediente del contrato, incluida la identificación adjunta como evidencia, se conserva **2 años** a partir de la fecha de firma.
11. **Sello digital**: el diseño gráfico se proporciona en formato **SVG**, para integrarse en el PDF final.
12. **Plantilla legal**: existe **una sola plantilla**; el clausulado varía dinámicamente según exclusividad (con/sin) y tipo de operación (venta/renta/renta con opción a compra), sin necesidad de documentos separados por combinación.
13. **Roles**: generan contratos Agente, Admin y Owner; cancelan Admin y Owner; envían/reenvían Agente, Admin y Owner.
14. **Contenido y verificación del sello digital**: codifica folio, hash SHA-256 del PDF final y fecha/hora de emisión; incluye un mini-QR que enlaza a una vista pública de verificación por folio (ver "Sello digital de la inmobiliaria").
15. **Consulta de identificaciones oficiales**: restringida solo a Owner.
16. **Eliminación al cumplir retención**: job automático que mueve los contratos a una lista de eliminación pendiente, con confirmación manual del Owner antes de borrar.

## Puntos que aún quedan abiertos
Ninguno por ahora. El módulo está listo para desglosarse en los RFCs individuales (RFC-063 a RFC-070) y pasar a diseño técnico detallado.

## RFCs incluidos (desglose)
Dado el tamaño del módulo, se sugiere dividirlo en RFCs individuales, siguiendo el patrón usado en el resto del proyecto:

- RFC-063 Modelo de Contrato de Intermediación (entidad, relaciones, estados)
- RFC-064 Generación de Folio y QR
- RFC-065 Formulario Interno de Captura (backend/Filament)
- RFC-066 Formulario Público del Cliente (llenado y revisión)
- RFC-067 Firma Electrónica y Evidencia
- RFC-068 Generación de PDF, Sello Digital y Almacenamiento
- RFC-069 Estatus, Notificaciones y Automatizaciones (expiración, recordatorios, retención de 2 años)
- RFC-070 Panel de Seguimiento de Contratos

## Casos QA
QA-151 a QA-180.

## Definition of Done
Documento de alcance validado, con todos los puntos abiertos resueltos. Listo para desglosarse en los RFCs individuales (RFC-063 a RFC-070) e iniciar el diseño técnico detallado.
