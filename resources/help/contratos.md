# Manual de Contratos de intermediación

Un contrato de intermediación es el documento que autoriza a la inmobiliaria a promover un inmueble en
nombre del propietario. Se genera, se envía a firmar y sigue un flujo de estados hasta su cierre.

## ¿Para qué sirve?

Formaliza digitalmente la relación con el propietario: datos del cliente, del inmueble a promover,
condiciones (vigencia, comisión, exclusividad) y la firma electrónica del cliente.

En el listado ves el folio, el estado de cada contrato y las acciones disponibles. Fíjate que **las
acciones cambian según el estado**: un contrato ya firmado solo ofrece "Ver", mientras que uno enviado
o leído todavía se puede cancelar.

{{captura: contratos/listado | Listado de contratos con sus estados y acciones}}

## Cómo se usa

1. Al crear un contrato, completa **Cliente** (nombre, teléfono, email, dirección — todos obligatorios),
   **Inmueble a promover** (tipo, operación, dirección, precio/renta autorizado y comisión) y
   **Condiciones** (vigencia de inicio/fin y si es exclusivo).

   {{captura: contratos/form-cliente | Sección Cliente del formulario de contrato}}

   {{captura: contratos/form-condiciones | Sección Condiciones con vigencia y exclusividad}}
2. Usa la acción **Enviar** para mandarle el contrato al cliente para su firma electrónica.
3. Si el cliente no firmó a tiempo o rechazó, usa **Reenviar** (mantiene el mismo folio) para darle otra
   oportunidad.
4. Si ya no aplica, usa **Cancelar** — requiere confirmación y queda registrado como evento del
   contrato.
5. Entra al detalle (**Ver**) para revisar folio, estado, condiciones y, una vez firmado, comparar la
   identificación del firmante con la firma registrada (solo owner, con permiso específico).

   {{captura: contratos/detalle | Detalle de un contrato firmado con folio, estado y vigencia}}

   El precio autorizado se muestra también **en letra**, como corresponde a un documento legal. Más
   abajo, si el contrato ya fue firmado y tienes el permiso, aparece la identificación del firmante
   para cotejarla.

### Cómo llega el contrato al cliente

Crear el contrato **no se lo envía a nadie**: queda guardado en estado *Generado*, visible solo para
ustedes. Para que el cliente lo reciba tienes que entrar al listado y presionar la acción **Enviar**
en la fila de ese contrato.

El botón está **al final de la fila**, del lado derecho. Fíjate en la imagen: solo el contrato en
estado *Generado* muestra **Enviar**; los que ya se enviaron o se leyeron ofrecen únicamente Cancelar
y Ver, y los firmados solo Ver.

{{captura: contratos/enviar | Fila de un contrato Generado mostrando el botón Enviar}}

Al presionar Enviar, el sistema manda un **correo a la dirección de email que cargaste en el
contrato**, con un enlace personal para revisarlo y firmarlo. El cliente no necesita cuenta ni
contraseña: entra a su correo, abre el enlace, lee el contrato y ahí mismo lo **firma** o lo
**rechaza** desde su celular o computadora.

Dos cosas importantes de ese enlace:

- **Dura 72 horas.** Si el cliente no lo abre a tiempo, el contrato pasa a *Expirado* y hay que usar
  **Reenviar** (conserva el mismo folio).
- **Es de un solo uso.** Una vez que el cliente firma o rechaza, el enlace deja de funcionar.

### Las etapas del contrato

Cada contrato avanza por estos estados, y el listado te muestra en cuál está:

| Estado | Qué significa |
| --- | --- |
| **Generado** | Recién creado. Todavía no se le envió nada al cliente. |
| **Enviado** | Se presionó Enviar y el correo salió a la dirección del cliente. |
| **Leído / Visto** | El cliente abrió el enlace y está viendo el contrato. |
| **Firmado** | El cliente firmó. Es el cierre exitoso del circuito. |
| **Rechazado** | El cliente abrió el contrato y decidió no firmarlo. |
| **Expirado** | Pasaron las 72 horas sin que el cliente abriera o resolviera el enlace. |
| **Cancelado** | Alguien del equipo canceló el contrato desde el panel. |
| **Vencido** | Un contrato firmado cuya vigencia ya terminó. |

Los estados **Expirado** y **Vencido** los marca el sistema solo, sin que nadie los toque. Desde
*Rechazado* o *Expirado* puedes volver a intentarlo con **Reenviar**.

## Campos importantes

- **Folio**: identificador único del contrato, copiable con un clic; se mantiene igual aunque se
  reenvíe.
- **Estado**: Generado → Enviado → Leído → Firmado, o Rechazado/Expirado/Cancelado/Vencido según el
  caso. El contrato no se edita una vez generado — solo se opera con las acciones Enviar/Reenviar/
  Cancelar.
- **Exclusividad**: marca si el propietario se compromete a trabajar solo con esta inmobiliaria durante
  la vigencia.
- **Confirmar eliminación**: purga la identificación y firma del cliente y archiva el expediente; el
  PDF y su huella (hash) se conservan para verificación posterior. Es irreversible.

## Preguntas frecuentes

- **¿Por qué no puedo editar un contrato ya generado?** — por diseño: los contratos son documentos
  legales una vez creados; los cambios se hacen mediante las acciones de estado, no editando campos.
- **El cliente dice que no le llegó el contrato** — usa **Reenviar**; conserva el mismo folio y genera
  un nuevo evento de envío.
- **No veo la identificación del firmante** — solo se muestra tras la firma y solo para quien tiene el
  permiso específico de verla (típicamente owner).
