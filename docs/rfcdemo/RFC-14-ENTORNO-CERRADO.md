# RFC-14 Entorno cerrado

## Objetivo

Que el inquilino vea su sitio completo y funcionando, y que **nadie de afuera
pueda navegarlo**.

Dicho con precisión, porque la diferencia importa: se cierra el sitio, no cada
byte. Las imágenes publicadas se sirven por el servidor web sin pasar por la
sesión, y eso está aceptado más abajo como límite conocido. Prometer «no lo ve
nadie» sería falso.

## Épica

EPICA-DEMO. Fase 1. Depende de RFC-06.

## Responsable

Backend.

## Qué significa cerrado

El demo sirve para mostrar cómo funciona el producto, no para publicar sitios. Un
inquilino tiene que poder recorrer su sitio entero —home, inmuebles, proyectos,
las páginas institucionales que administró desde el CMS— **como se va a ver de
verdad**, y nadie de afuera tiene que poder entrar.

Cerrado no significa recortado. Significa que el mismo sitio, completo, exige
sesión.

## Alcance

- Las rutas públicas del inquilino exigen sesión.
- Las excepciones, que existen y son necesarias.
- El host central.
- Señales para buscadores.

## 1. Las rutas públicas exigen sesión

Las rutas de `routes/web.php` que hoy son anónimas —`/`, `/inmuebles`,
`/inmuebles/{slug}`, `/nosotros`, `/servicios`, `/inversionistas`, `/proyectos`,
`/proyectos/{slug}`, `/contacto`— pasan a exigir sesión **en el demo**.

No se tocan las rutas ni los controladores: es middleware. El sitio que ve el
inquilino es exactamente el sitio que vería un visitante, con el mismo render y
el mismo caché.

La vista previa del CMS ya está cerrada y no necesita cambios: su controlador es
la puerta y exige `owner` con `frontend.manage` antes de mirar siquiera qué
página se pidió.

## 2. Las excepciones, que son el punto de este RFC

Cerrar todo con una brocha rompe funciones que el demo justamente quiere lucir.

### Firma de contratos

Las rutas `/contrato/{token}` son públicas **por diseño**: un cliente recibe un
enlace y firma sin tener cuenta. Es una de las funciones más vistosas del
producto, y si se cierra el demo no la puede mostrar.

**Se mantienen abiertas.** El control de acceso ahí no es la sesión: es el token,
que ya existe, ya tiene un solo uso y ya está limitado por frecuencia. Y siguen
resolviendo al inquilino por subdominio, así que el token de un inquilino no
alcanza a otro.

Esto no debilita el entorno cerrado: quien tiene el token es porque el inquilino
se lo dio.

### Captura de leads

El formulario de contacto es parte del producto. Queda detrás de sesión como el
resto del sitio: el inquilino prueba el flujo como él mismo, que es lo que se
quiere mostrar. Si más adelante hace falta que un tercero cargue un lead de
prueba, se resuelve con la misma idea del token, no abriendo la ruta.

## 3. El host central

Sirve el padrón del operador (RFC-12) y nada más. En fase 1 no hay registro
público (RFC-11 está en fase 2), así que el host central **no tiene ninguna
página anónima**.

## 4. Señales para buscadores

Aunque todo exija sesión, el demo entero responde `noindex, nofollow`, y
`/sitemap.xml` queda deshabilitado.

Es redundante y va igual: una ruta que alguien abra por error mañana no debería
además terminar indexada. La regla vale para el host central y para todos los
subdominios de inquilino.

## Límite conocido y aceptado: la media publicada NO está cerrada

**El cierre alcanza al HTML, no a los bytes.** La librería de medios guarda en
el disco `public` (`config/media-library.php:36`) y ni `Property` ni `Project`
fuerzan un disco privado. El servidor web sirve esos archivos desde `/storage/…`
**sin pasar por Laravel**: ni `ResolveTenant`, ni sesión, ni este RFC.

O sea: alguien sin sesión que tenga la URL de una imagen publicada, la abre.

Se acepta a propósito. La alternativa —servir `/storage` por Laravel— cierra el
agujero y pone cada imagen a pasar por PHP; para un demo con pocos inquilinos y
contenido de prueba, el intercambio no se justifica.

**Lo que hay que decir en voz alta, porque es lo incómodo:**

- La única barrera es que el `slug` no se adivina. Eso es **oscuridad, no control
  de acceso**. Quien tenga la URL entra, y las URL aparecen en el HTML que el
  propio inquilino ve y puede copiar.
- Por lo tanto **la invitación tiene que advertirlo** (RFC-13): no subir al demo
  nada que no pueda ser público. Si alguien evalúa el producto con fotos de un
  cliente real, esas fotos quedan accesibles.
- El prefijo `tenants/{slug}/` de RFC-08 sigue siendo necesario, pero resuelve
  **colisión de archivos**, no confidencialidad. Son dos cosas distintas y
  conviene no confundirlas.

Si algún día el demo se abre al público (fase 2), esta decisión se revisa: ahí
el contenido deja de ser sólo de conocidos.

## Lo que este RFC NO cambia

**El subdominio sigue siendo la frontera.** Que el sitio esté cerrado quita la
razón "cada inquilino tiene una URL compartible", pero no la otra: el `Host`
llega antes de que corra una sola línea nuestra, así que ningún error de la
aplicación puede confundir de quién es una petición. Ese sigue siendo el motivo
principal, y es el que importa en un producto que vende que los datos de un
cliente son de ese cliente.

Consecuencia práctica: abrir un inquilino al público, si algún día se quiere, es
quitar un middleware. No es rediseñar nada.

## Reglas

1. El cierre es middleware, no cambios en rutas ni controladores.
2. Toda excepción se justifica por escrito acá. Una ruta abierta sin figurar en
   la sección 2 es un error.
3. Un inquilino cerrado no distingue "no autenticado" de "no existe": las dos
   cosas devuelven lo mismo, para no permitir enumerar inquilinos.

## Definition of Done

- Un test recorre todas las rutas públicas sin sesión y verifica que ninguna
  responde contenido.
- Un test verifica que `/contrato/{token}` sigue funcionando sin sesión, y que un
  token del inquilino A no sirve en el subdominio de B.
- Un test verifica que la respuesta lleva `noindex` en todo el demo.
- Un test verifica que un subdominio inexistente y uno sin sesión responden lo
  mismo.
