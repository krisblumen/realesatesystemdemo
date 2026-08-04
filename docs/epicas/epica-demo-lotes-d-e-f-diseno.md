# Épica DEMO — Lotes D, E y F, diseño de detalle

> Diseño, sin código. Cubre RFC-06, RFC-14 (lote D), RFC-07, RFC-08 (lote E),
> RFC-09 y RFC-12 (lote F).
>
> Depende de: `epica-demo-lote-a-diseno.md`, `epica-demo-lotes-b-c-diseno.md`.

## Evidencia verificada

- `bootstrap/app.php:27` — `trustProxies(at: '*')`. La app corre detrás del
  proxy de CloudPanel.
- `TrustProxies.php:22-27` — los encabezados confiados **incluyen
  `HEADER_X_FORWARDED_HOST`**.
- `bootstrap/app.php:29` — `prependToPriorityList` ya se usa en esta app, así que
  el mecanismo para ordenar middleware existe y está entendido.
- `config/media-library.php:144` y `:154` — `path_generator` y
  `custom_path_generators` son configurables.
- Los cinco servicios de caché arman su clave **cada uno por su cuenta**
  (`FrontendSettingsService.php:54`, `FrontendThemeService.php:48`, y los otros
  tres). **No existe un punto común**: hay que crearlo.

---

# Lote D — Resolución y cierre

## D.1 El hallazgo: hoy el `Host` lo elige el cliente

Toda la decisión de RFC-06 se apoya en esta frase:

> El `Host` llega antes de que corra una sola línea nuestra, así que ningún error
> de la aplicación puede confundir de quién es una petición.

**Eso hoy no es cierto en este despliegue.** La app confía en todos los proxies
(`at: '*'`) y entre los encabezados confiados está `X-Forwarded-Host`. Quien
pueda alcanzar el origen directamente —sin pasar por CloudPanel— manda el
encabezado que quiera y elige a qué inquilino resuelve.

**Alcance real, sin dramatizar**: resolver a un inquilino no da acceso. Siguen
haciendo falta credenciales, y la sesión se lee de la base de ese inquilino, así
que una sesión de A no autentica en B. El daño directo es acotado.

Pero convierte una frontera dura en una blanda, que es exactamente lo que
elegimos evitar cuando descartamos la sesión. Y deja de ser acotado en cuanto se
combine con cualquier error futuro.

**Corrección, en despliegue y no en código**: confiar sólo en la dirección del
proxy en vez de en todos. CloudPanel corre en el mismo host, así que en la
práctica es la dirección local.

> **Esto también aplica al New Hauz de producción, que corre el mismo
> `bootstrap/app.php`.** Ahí no hay inquilinos, pero un `Host` elegible por el
> cliente afecta la generación de URL — enlaces de recuperación de contraseña,
> por ejemplo. Depende de si el origen es alcanzable sin pasar por el proxy;
> vale confirmarlo con el firewall. No es parte de esta épica, pero salió de
> ella.

## D.2 El orden del middleware

`ResolveTenant` corre **antes** de `StartSession`. La sesión vive en la base de
datos: sin ese orden se leería de la equivocada.

Esta app ya usa `prependToPriorityList` (`bootstrap/app.php:29`), así que el
mecanismo está a mano y entendido.

El orden es frágil frente a cualquier paquete que se agregue después y se
registre antes. **Se prueba el orden, no sólo el comportamiento**: un test que
lea la lista de prioridad y verifique que `ResolveTenant` está antes de
`StartSession`, y que falle si alguien lo mueve.

## D.3 Del `Host` al inquilino

1. Tomar el host y quitarle el dominio base de `config/tenancy.php`.
2. Si no queda nada, es el host central: modo central.
3. Si queda algo, validarlo contra el mismo formato del slug —el de RFC-05, sin
   caracteres confundibles— **antes** de consultar. Un host que no cumple el
   formato ni siquiera llega a la base.
4. Buscar en `central` un inquilino `activo` con ese slug.
5. Apuntar la conexión por defecto a su base.

Un slug inexistente, uno expirado y uno malformado devuelven **lo mismo**. Que
difieran permitiría enumerar inquilinos probando subdominios.

## D.4 El modo central

Conexión por defecto: `central`. Sesión y caché caen ahí por el mismo movimiento.

Sirve el padrón del operador (RFC-12) y nada más: en fase 1 no hay registro
público. **El host central no tiene ninguna página anónima.**

## D.5 El cierre (RFC-14)

Las rutas públicas del inquilino pasan a exigir sesión. Es middleware: no se
tocan rutas ni controladores, así que el sitio que ve el inquilino es
exactamente el que vería un visitante, con el mismo render y el mismo caché.

La vista previa del CMS ya está cerrada por su propio controlador —exige `owner`
con `frontend.manage` antes de mirar qué página se pidió— y no necesita nada.

## D.6 Las excepciones, que son el punto

| Ruta | ¿Abierta? | Por qué |
|---|---|---|
| `/contrato/{token}` y sus posteos | **Sí** | El control es el token: de un solo uso y con límite de frecuencia. Es una de las funciones que el demo quiere lucir, y un cliente firma sin tener cuenta |
| `/contacto` (captura de leads) | No | El inquilino prueba el flujo como él mismo |
| `/sitemap.xml` | Deshabilitada | Un demo no se indexa |
| Todo el resto | No | |

Regla: **toda excepción se justifica en esta tabla.** Una ruta abierta que no
figure acá es un error, no una decisión que alguien no anotó.

Además, todo el demo responde `noindex, nofollow`. Es redundante con el cierre y
va igual: una ruta que alguien abra por error mañana no debería además terminar
indexada.

---

# Lote E — Aislamiento de caché y archivos

## E.1 Caché: el punto único no existe, hay que crearlo

Los cinco servicios arman su clave por su cuenta, con `sprintf` inline. **Cinco
lugares son cinco oportunidades de olvidarse**, y el sexto servicio que alguien
escriba el año que viene no va a acordarse de ninguno.

El lote E introduce un compositor de claves por el que pasan las cinco, y les
antepone el inquilino: `t:{slug}:frontend:g3:page:home:v2`. En modo central no
hay prefijo, porque no hay inquilino.

Hoy es redundante: el caché usa la conexión por defecto, así que su tabla ya vive
en la base del inquilino. Va igual, y el motivo está en RFC-07: el día que
alguien mueva el caché a Redis por rendimiento, el aislamiento por base
desaparece de golpe y las claves son lo único que queda en pie.

**Cómo se prueba que el prefijo hace el trabajo y no la base**: correr el test de
aislamiento con el caché apuntado a un almacén compartido. Si pasa así, el
prefijo funciona.

## E.2 Archivos: generador de rutas propio

La librería numera desde 1 en cada base. Dos inquilinos suben su primera imagen y
los dos escriben en `1/`. El disco es uno solo.

`config/media-library.php:144` permite reemplazar el generador. La ruta pasa a
`tenants/{slug}/{media_id}/`.

El generador **valida el slug antes de usarlo**, aunque ya venga validado en el
alta. Misma razón que en RFC-05: la validación va pegada al uso, porque el
segundo camino hasta acá lo va a escribir alguien que no leyó esto.

Borrar un inquilino borra `tenants/{slug}/` completo (lote F).

---

# Lote F — Expiración, borrado y padrón

## F.1 Dos etapas separadas

**Marcar** vencido es barato, inmediato y confiable. **Borrar** es caro,
irreversible y puede fallar. Separarlas hace que el corte de acceso no dependa de
que el borrado funcione.

Entre una y otra queda una ventana en la que el inquilino ya no entra pero sus
datos existen. Es deliberada: da margen para atender un reclamo antes de que sea
imposible.

## F.2 El orden del borrado

1. **Cerrar la puerta**: `ALTER DATABASE ... CONNECTION LIMIT 0`.
2. **Terminar** las sesiones vivas.
3. **Borrar** la base.
4. **Borrar** `tenants/{slug}/` del disco.
5. Marcar `borrado`, conservando la fila.

El paso 1 corrige el hallazgo M-1 de la auditoría. Sin él queda una ventana entre
terminar y borrar: el navegador del inquilino reintenta, se reconecta, y el
borrado falla. Con pestañas abiertas eso no es raro, es lo normal.

Se usa `CONNECTION LIMIT 0` en vez de revocar permisos: es una sola sentencia, no
hay que enumerar roles, y se deshace igual de fácil si el borrado se aborta.

**El orden 3 antes que 4 importa.** Si se borran los archivos primero y el
borrado de la base falla, queda un inquilino vivo con las imágenes rotas — peor
que no haber empezado.

## F.3 Reintentos

Cada paso comprueba si ya está hecho antes de hacerlo. Un borrado a medias no
puede quedar en un estado que sólo se arregle a mano.

Tras varios intentos fallidos el inquilino queda visible en el padrón con su
`motivo_falla`. No se reintenta para siempre en silencio.

## F.4 El padrón del operador (RFC-12)

Vive en el host central, donde la conexión por defecto **es** la central. Esa es
la garantía, y es estructural: en ese modo no hay conexión abierta a ninguna base
de inquilino desde donde leer nada de adentro.

Muestra: slug, estado, nacimiento, vencimiento, versión de plantilla,
`motivo_falla`, y los agregados de uso.

No muestra nada de adentro de un inquilino, y **no hay «entrar como»**. Un demo
es exactamente el lugar donde esa función se ve mal: el producto que se está
mostrando promete que los datos de un cliente son de ese cliente.

Acciones: vencer antes de tiempo, reintentar un borrado, abortar un borrado ya
empezado, cerrar las invitaciones. Ninguna abre contenido.

El registro de quién hizo qué queda en fase 2, junto con la pantalla web. El
motivo está en RFC-12: con un solo operador y un solo camino —la consola del
servidor— una tabla que siempre dice el mismo nombre no informa nada.

---

## Tests que cierran los tres lotes

| Test | Lote | Qué protege |
|---|---|---|
| `ResolveTenant` está antes de `StartSession` en la lista de prioridad | D | El orden, que es frágil |
| Un `X-Forwarded-Host` de un cliente no confiable no elige inquilino | D | D.1 |
| Slug inexistente, expirado y malformado devuelven lo mismo | D | Enumeración |
| Sin sesión, ninguna ruta del inquilino devuelve contenido | D | El cierre |
| `/contrato/{token}` funciona sin sesión, y no cruza de inquilino | D | La excepción |
| Dos inquilinos publican home distinta; cada uno ve la suya, **con caché compartido** | E | El corazón de la épica |
| Ninguna clave del frontend sale sin prefijo | E | El punto único |
| Dos inquilinos suben su primera imagen y no se pisan | E | La colisión de rutas |
| Borrar un inquilino con una sesión abierta no falla | F | M-1 |
| Un borrado interrumpido se reintenta y termina bien | F | Idempotencia |
| Tras borrar no queda base, ni archivos, y sí queda la fila | F | Regla de oro 5 |
| El borrado no puede nombrar la central ni una plantilla | F | |
| Ninguna consulta del padrón se conecta a una base de inquilino | F | La garantía de RFC-12 |

## Dependencias fuera de estos lotes

- **Ajustar `trustProxies` en el despliegue** (D.1). No es código: es
  configuración del servidor, y va antes de invitar a nadie.
- El generador de rutas de medios necesita el slug resuelto, así que el lote E va
  después del D.
