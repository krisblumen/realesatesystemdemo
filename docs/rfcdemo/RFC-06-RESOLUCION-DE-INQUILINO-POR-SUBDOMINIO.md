# RFC-06 Resolución de inquilino por subdominio

## Objetivo

Que cada petición sepa a qué inquilino pertenece antes de tocar la base de datos.

## Épica

EPICA-DEMO. Lote D. Depende de RFC-01.

Cierra el contrato C-1 del diseño.

## Responsable

Backend.

## Alcance

- Middleware que lee el `Host`, resuelve el inquilino y apunta la conexión por
  defecto a su base.
- Su posición en la cadena de middleware.
- El host central, sin inquilino.

## Por qué el `Host` y no la sesión

La sesión sería lo intuitivo y **es circular**: la sesión vive en la base de
datos, así que para leerla hay que saber a qué base conectarse, y para saberlo
hay que leer la sesión.

El encabezado `Host` está disponible antes de que corra un solo middleware. Rompe
el ciclo.

## Por qué subdominio y no prefijo de ruta

El prefijo obliga a tocar todas las rutas y toda generación de URL, y el panel se
monta con `->path('admin')`, que es estático.

Con subdominio no se toca ninguna ruta. **Ni siquiera hace falta declarar el
dominio en el panel**: las rutas que no declaran dominio matchean cualquier host,
y el middleware ya resolvió el inquilino leyendo el `Host`. Declararlo
introduciría un parámetro `{tenant}` en toda la generación de URL de Filament,
que después habría que resolver con valores por defecto — trabajo y superficie de
error a cambio de nada.

Sólo las rutas del registro se acotan al host central.

Requiere DNS comodín y certificado comodín, y por eso el despliegue es en VPS.

**El demo es un entorno cerrado (RFC-14), y eso no cambia esta decisión.** Quita
una de las razones —"cada inquilino tiene una URL compartible"— y deja en pie la
principal: el `Host` llega antes de que corra una sola línea nuestra, así que
ningún error de la aplicación puede confundir de quién es una petición. La
alternativa evaluada era un solo host con el inquilino guardado en la sesión;
ahorra el comodín una vez y a cambio apoya la frontera en el orden de middleware
y en el ciclo de vida de la sesión, para siempre.

## Orden en la cadena

**El middleware corre antes del que arranca la sesión.** No es preferencia, es
requisito: la sesión se guarda en base de datos y se leería de la equivocada.

Secuencia:

1. Leer el `Host` y extraer el slug.
2. Buscar el inquilino en la conexión `central`.
3. Si no existe, o no está `activo`, cortar.
4. Apuntar la conexión por defecto a su base.
5. Recién ahí: sesión, autenticación, todo lo demás.

Consecuencia buena: como sesión y caché usan la conexión por defecto, quedan
aislados por el mismo movimiento. Un mecanismo resuelve tres problemas.

Este orden es frágil frente a cualquier paquete que se agregue después y se
registre antes. Hace falta un test que verifique la posición, no sólo el
comportamiento.

## El host central

El host sin slug sirve el registro (RFC-11) y el padrón del operador (RFC-12). Ahí
la conexión por defecto **es** la central y no se resuelve ningún inquilino.

Un slug que no corresponde a ningún inquilino activo devuelve la misma respuesta
que uno expirado: no se distingue entre "no existe" y "venció". Distinguirlos
permitiría enumerar inquilinos.

## Reglas

1. Ningún controlador resuelve el inquilino por su cuenta.
2. El inquilino resuelto se expone por un único punto, no por variable global.
3. Un estado distinto de `activo` no resuelve nunca.

## Definition of Done

- Un test verifica que el middleware está antes del de sesión en la cadena, y
  falla si alguien lo mueve.
- Un test verifica que dos hosts distintos leen dos bases distintas en la misma
  corrida.
- Un test verifica que el host central no resuelve a ningún inquilino.
- Un test verifica que un slug inexistente y uno expirado devuelven lo mismo.
