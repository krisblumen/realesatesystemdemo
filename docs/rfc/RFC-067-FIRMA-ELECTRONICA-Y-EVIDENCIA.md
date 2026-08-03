# RFC-067 Firma Electrónica y Evidencia

## Objetivo
Capturar la firma electrónica simple del cliente junto con evidencia reforzada, para respaldar la validez del contrato sin depender de un proveedor certificado externo (NOM-151).

## Épica
EPICA-10 Contratos de Intermediación

## Responsable
Por asignar

## Alcance
- Captura de la firma en un canvas (trazo con el dedo/mouse) desde el formulario público (RFC-066).
- Captura de evidencia adicional en el momento de la firma.
- Invalidación del token de acceso de un solo uso al completarse la firma o el rechazo.
- Registro de la evidencia asociada al contrato (RFC-063).

## Evidencia capturada
- Imagen del trazo de la firma.
- Dirección IP del cliente.
- User-agent / dispositivo.
- Fecha y hora del servidor (no del dispositivo del cliente, para evitar manipulación).
- Hash del documento final (calculado una vez generado el PDF, ver RFC-068) para garantizar su integridad posterior.

## Reglas
- No se agrega verificación adicional por código de un solo uso (OTP); el token de acceso de un solo uso (RFC-064) es la única barrera antes de la firma.
- Esta es una **firma electrónica simple** con evidencia reforzada: válida como principio de prueba, con fuerza probatoria menor a una firma certificada.
- Si en el futuro se requiere fuerza probatoria plena equivalente a firma autógrafa, se evaluará la integración con un proveedor certificado bajo NOM-151 (Mifiel, Weetrust, DocuSign, etc.) como fase posterior — no forma parte de este RFC.
- De parte de la inmobiliaria no hay firma manual de un representante: el documento se valida con el sello digital del sistema (RFC-068).

## Flujo
1. El cliente traza su firma en el formulario público.
2. Al confirmar, el sistema captura la evidencia (IP, user-agent, timestamp).
3. Se dispara la generación del PDF final (RFC-068), incluyendo la firma y la evidencia.
4. El contrato pasa a estado **Firmado** y el token de acceso queda invalidado.
5. Si el cliente rechaza en vez de firmar, se registra el motivo (opcional) y el contrato pasa a **Rechazado**, también invalidando el token.

## Definition of Done
Firma capturada junto con toda la evidencia definida, asociada al contrato, disparando la generación del documento final y el cambio de estatus correspondiente.
