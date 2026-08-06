# Despliegue del demo multi-inquilino

Requisitos de infraestructura de EPICA-DEMO. Complementa `SERVER-SETUP.md` y
`DATABASE-DEPLOYMENT.md`; no los reemplaza.

Diseño: `docs/epicas/epica-demo-multi-inquilino.md`.
RFC: `docs/rfcdemo/`.

## Por qué esto no corre en hosting compartido

Tres requisitos, y el certificado es el menor de los tres.

| Requisito | Por qué | Si falta |
|---|---|---|
| Rol de Postgres con `CREATEDB` y capacidad de terminar sesiones | El sistema crea una base en cada alta y la borra al expirar | No hay aprovisionamiento; el diseño entero cae |
| Proceso largo para la cola más cron | La expiración corre sola y el sistema ya tiene trabajos de fondo | Nada expira |
| Certificado y DNS comodín | Un subdominio por inquilino | Se cae la resolución por subdominio |

Hostinger emite certificados comodín **sólo en VPS**. Los planes Web, Cloud y
Agency dan SSL DV por dominio o subdominio individual. Pero aunque ofrecieran
comodín en compartido, los dos primeros requisitos seguirían exigiendo VPS.

## Versión de PostgreSQL

**Producción: 16.14.** Desarrollo local puede diferir (se observó 18.3).

Importa por una razón concreta y verificada en las dos versiones:

```
ERROR: source database "demo_template" is being accessed by other users
```

`CREATE DATABASE ... TEMPLATE` **falla si la plantilla tiene cualquier conexión
encima**. La documentación de 16 y de 18 lo declara igual. De ahí salen dos
reglas del diseño: la aplicación nunca se conecta a la plantilla, y la plantilla
se versiona en vez de migrarse en su lugar.

Y una segunda, también verificada:

```
ERROR: CREATE DATABASE cannot run inside a transaction block
```

Por eso el cerrojo que serializa las altas es de sesión y no transaccional, y por
eso el caso base de pruebas necesita una conexión de mantenimiento aparte.

## Medición del VPS

Tomada en `srv650075`. Repetir antes de abrir el demo a más gente.

| Dato | Valor |
|---|---|
| Disco en `/var/lib/postgresql` | 96 GB, 55 GB libres |
| Peso de un inquilino recién creado | 18 MB |
| Techo por disco | ~3.000 inquilinos. **No es el límite** |
| `max_connections` | 100 |
| Otras bases en la instancia | `inmo_db`, `museo_textil`, `mail_server`, `postfixadmin`, `roundcubemail` |

## El riesgo operativo real

**El demo comparte instancia de Postgres con la producción de New Hauz y con el
stack de correo.** Las 100 conexiones son de todos.

Un demo descontrolado —un bucle, un worker mal configurado, alguien probando de
más— puede dejar sin conexiones al sitio que factura y al correo. Ese es el
riesgo de esta épica, y no tiene relación con cuántos inquilinos haya.

Se cierra con dos topes, y los dos van **antes de que exista el primer
inquilino**:

```sql
ALTER ROLE demo_app CONNECTION LIMIT 20;
```

Y uno por base de inquilino, fijado en el alta (`ALTER DATABASE ... CONNECTION
LIMIT`). El primero protege a los vecinos del demo; el segundo protege a los
inquilinos entre sí.

> **Sobre las conexiones**, porque es contraintuitivo: no son un costo por
> inquilino sino por petición concurrente. Laravel abre al empezar la petición y
> cierra al terminar, así que un inquilino dormido no consume ninguna. Lo que
> importa no es cuántos inquilinos hay sino cuántas peticiones simultáneas.

## Roles de base de datos

Dos roles distintos, y no es opcional:

| Rol | Privilegios | Lo usa |
|---|---|---|
| `demo_app` | **NO superusuario.** Sin `CREATEDB`. `CONNECTION LIMIT` puesto | La aplicación, en cada petición |
| `demo_provisioner` | `CREATEDB`, terminar sesiones | Sólo el comando de invitación y el borrado, desde consola |

**`demo_app` no puede ser superusuario, y no es un detalle de higiene.**
`CONNECTION LIMIT` —el de la base y el del rol— **no aplica a superusuarios**.
Si `demo_app` lo fuera, los dos topes que protegen a la producción vecina no
protegerían nada, y el cierre de puerta previo a borrar un inquilino tampoco:
el borrado fallaría contra cualquiera que dejó una pestaña abierta. Se descubrió
al probarlo, porque como `postgres` el mecanismo parecía no funcionar.

**Propiedad y permisos de la base clonada (hallazgo M-1 de la auditoría).**
Separar los roles no alcanza: hay que decir quién queda como dueño de la base
nueva y con qué permisos entra `demo_app`. Si no se define, el alta termina
bien y **el primer request del inquilino falla por permisos** — y la salida
apurada sería darle `CREATEDB` a `demo_app`, que rompe justamente la separación
que protege el DDL.

Contrato, y **una sola sentencia lo resuelve entero**:

```sql
GRANT demo_app TO demo_provisioner;
```

Con esa membresía pasan dos cosas a la vez. `demo_provisioner` **hereda** los
permisos de `demo_app`, así que puede leer y escribir la tabla `tenants` de la
central —que es de `demo_app` por haberla migrado—. Y puede crear bases
declarando `OWNER demo_app`, cosa que Postgres sólo permite a quien puede asumir
ese rol.

### Cómo se invocan los comandos que crean o borran bases

La conexión `maintenance` toma `DB_USERNAME` del `.env`, y ahí vive `demo_app`,
que **no tiene `CREATEDB` a propósito**. Así que construir una plantilla, invitar
o borrar se corre pasando el rol de aprovisionamiento por el entorno:

```bash
DB_USERNAME=demo_provisioner DB_PASSWORD='...' php artisan demo:plantilla:construir demo_template_vN
DB_USERNAME=demo_provisioner DB_PASSWORD='...' php artisan demo:invitar persona@ejemplo.com
DB_USERNAME=demo_provisioner DB_PASSWORD='...' php artisan demo:borrar
```

Funciona porque Laravel lee el `.env` sin pisar variables que ya existan en el
entorno. Sin esto:

```
SQLSTATE[42501]: Insufficient privilege: permission denied to create database
```

Y **no** rompe la separación: `demo:plantilla:construir` corre las migraciones en
un proceso hijo al que le quita esas credenciales, para que las tablas queden a
nombre de `demo_app`. Crear la base necesita privilegio; crear las tablas no.
Cada paso corre con el rol que le toca.

El resto de los comandos —`demo:padron`, `demo:reemitir-acceso`, `demo:expirar`,
`demo:por-cada-inquilino`— NO crean ni borran bases y corren con el `.env` tal
cual.

El dueño se declara **al crear** y no se transfiere después: entre crear y
transferir habría una ventana en la que la base existe y la aplicación no puede
usarla, y un fallo en el medio la dejaría así para siempre. Se configura con
`TENANCY_ROL_APLICACION=demo_app`.

Sin esto, el alta reporta éxito y el PRIMER request del inquilino falla por
permisos. La salida apurada —dar `CREATEDB` a `demo_app`— destruye la separación
que protege el DDL.

El motivo de separar los roles está en RFC-05: el nombre de una base se
interpola en DDL porque Postgres no acepta parámetros para identificadores. Si el rol que ejecuta esa
sentencia fuera el mismo que atiende peticiones, un error de validación dejaría
de ser «un inquilino ve a otro» y pasaría a ser «se pierden todos».

## PostGIS va en `template1`, y no es una comodidad

**Paso descubierto en el primer despliegue.** `CREATE EXTENSION postgis` exige
superusuario, y `demo_provisioner` no lo es a propósito. Sin resolverlo, la
primera migración de la plantilla muere en `create_zones_table`.

La salida NO es hacer superusuario al rol que aprovisiona. Ese rol crea y borra
bases; sumarle superusuario le daría además leer y escribir cualquier base de la
instancia —incluidas `inmo_db` y el correo—, que es exactamente lo que la
separación de dos roles existe para impedir.

Se instala la extensión una vez en `template1`, con superusuario y a mano:

```bash
sudo -u postgres psql -d template1 -c "CREATE EXTENSION IF NOT EXISTS postgis;"
```

A partir de ahí **toda base nueva la hereda**, y el `CREATE EXTENSION IF NOT
EXISTS` de la migración pasa sin privilegios: devuelve un aviso de que ya existe
y sigue. Verificado con un rol sin superusuario.

Eso mueve el privilegio al momento correcto: una vez, en el despliegue, y nunca
más.

## Proxy de confianza

**Resuelto en `bootstrap/app.php`: sólo se confía en el bucle local.**

Antes usaba `trustProxies(at: '*')`, y entre los encabezados confiados está
`X-Forwarded-Host` (`TrustProxies.php:23`). Con el inquilino resuelto por
subdominio, eso significaba que **quien alcanzara el origen elegía a qué
inquilino resolvía**. No daba acceso —siguen haciendo falta credenciales— pero
convertía la frontera en algo que el cliente podía elegir.

El bucle local es la respuesta correcta sin importar cómo esté armado el
servidor, y por eso no hace falta averiguarlo:

| Arquitectura | `REMOTE_ADDR` que ve PHP | Qué pasa |
|---|---|---|
| nginx → PHP-FPM por FastCGI | La del cliente real | Nunca es el bucle: sus encabezados se ignoran y el esquema HTTPS sale de `fastcgi_param HTTPS "on"` del vhost |
| Un proxy delante en el mismo host | El bucle | Se respetan los encabezados que pone el proxy |

Si algún día el proxy pasa a otra máquina —una CDN, un balanceador— hay que
poner su dirección ahí. Nunca `'*'`.

Lo protege un test: `ResolucionDeInquilinoTest::test_a_client_cannot_choose_its_tenant_with_a_forwarded_host_header`.
Simula un cliente de afuera y verifica que su `X-Forwarded-Host` no cambie de
inquilino. Existe porque este fallo **no da ningún síntoma**: nada falla, nada se
registra, y todo parece funcionar.

> Lo mismo aplica al New Hauz de producción, que corre el mismo
> `bootstrap/app.php`. Ahí no hay inquilinos, pero un `Host` elegible afecta la
> generación de URL — enlaces de recuperación de contraseña, entre otros.
> Depende de si el origen es alcanzable sin pasar por el proxy: confirmarlo con
> el firewall.

## DNS y certificado

- Registro comodín `*.demo.<dominio>` apuntando al VPS.
- Certificado comodín con validación **DNS-01**: HTTP-01 no emite comodines.

### Procedimiento (hecho en `landracore.com`, DNS en Hostinger)

Se usa **acme.sh** y no certbot, porque tiene complemento oficial para la API de
Hostinger y con eso la renovación queda automática de punta a punta. Todo como
root, para que el cron de renovación sea de root.

```bash
curl https://get.acme.sh | sh -s email=<correo>
~/.acme.sh/acme.sh --set-default-ca --server letsencrypt
export HOSTINGER_Token="<token>"
~/.acme.sh/acme.sh --issue --dns dns_hostinger \
  -d demo.<dominio> -d '*.demo.<dominio>' --dnssleep 180
```

Tres cosas que cuestan un intento fallido cada una:

**El nombre de la variable lo manda el script instalado, no la wiki.** Verificar
con `grep -oE "HOSTINGER_[A-Za-z_]+" ~/.acme.sh/dnsapi/dns_hostinger.sh`. La wiki
del proyecto documenta `HOSTINGER_API_KEY`; la versión instalada esperaba
`HOSTINGER_Token`.

**`--dnssleep 180` no es opcional.** Un comodín necesita DOS valores TXT en el
mismo nombre `_acme-challenge`, y Let's Encrypt valida desde varios puntos de la
red. La comprobación propia de acme.sh los dio por buenos y la validación del
comodín falló cuatro segundos después con «No TXT record found»: los servidores
de Hostinger todavía no habían convergido.

**`--ecc` en la instalación**, porque acme.sh emite en `<dominio>_ecc`.

La instalación apunta a las rutas que ya usa el vhost de CloudPanel, y con
`--reloadcmd` cada renovación las reescribe y recarga nginx sola:

```bash
~/.acme.sh/acme.sh --install-cert -d demo.<dominio> --ecc \
  --key-file /etc/nginx/ssl-certificates/demo.<dominio>.key \
  --fullchain-file /etc/nginx/ssl-certificates/demo.<dominio>.crt \
  --reloadcmd "systemctl reload nginx"
```

Copiar los archivos a mano funcionaría hoy y se caería en silencio en 60 días.

### El costo que se aceptó

El token de la API de Hostinger **no se puede acotar a un dominio**: es de cuenta,
y quien lo tenga puede cambiar el DNS de todos los dominios de esa cuenta. Vive en
`~/.acme.sh/account.conf` del VPS.

Se aceptó porque esa máquina ya sirve la producción y las bases, así que un
compromiso del disco no es un problema nuevo; y porque un certificado vencido por
olvido es una falla bastante más probable que una intrusión.

### Verificación

```bash
curl -4 -sI "https://<slug>.demo.<dominio>/admin" | head -1   # sin -k
echo | openssl s_client -connect <slug>.demo.<dominio>:443 \
  -servername <slug>.demo.<dominio> 2>/dev/null | \
  openssl x509 -noout -issuer -dates -ext subjectAltName
```

El `subjectAltName` tiene que listar `*.demo.<dominio>`.

## El servidor web sirve por extensión, y eso rompe Livewire

CloudPanel —y casi cualquier vhost armado por un panel— trae un bloque que
atiende por extensión todo lo que termine en `.css`, `.js`, `.png` y demás:

```nginx
location ~* ^.+\.(css|js|jpg|...|map)$ {
    expires max;
    access_log off;
}
```

Ese bloque **nunca llega a PHP**. Si Livewire sirve su JavaScript desde una ruta
de la aplicación (`/livewire/livewire.min.js`, que es lo que hace cuando sus
assets no están publicados), la petición cae ahí y devuelve 404.

Lo que sigue cuesta de rastrear porque el síntoma aparece lejos: sin ese script,
Livewire no arranca; sin Livewire, el formulario de acceso de Filament se envía
de forma nativa a `/admin/login`, que sólo acepta GET; y la respuesta es **405
Method Not Allowed** al intentar entrar. La pantalla de acceso se ve perfecta.

**No se arregla en nginx.** Se arregla publicando los assets, que ya viajan en
git en `public/vendor/livewire/`: entonces la dirección pasa a ser
`/vendor/livewire/livewire.min.js`, un archivo real, y ese mismo bloque lo sirve
bien. Al actualizar Livewire hay que volver a publicarlos —hay un test que lo
verifica— porque el aviso de desajuste sale sólo por la consola del navegador.

Se intentó primero esquivarlo con una excepción en el vhost
(`location ^~ /livewire/ { try_files $uri /index.php?$query_string; }`) y **no
sirve**: la petición llega a PHP con la URL cambiada, Laravel enruta la portada
en vez del script, el cierre del demo la manda al login y la respuesta pasa a ser
un 302 hacia `/index.php/admin/login`. Además, cualquier edición del sitio desde
el panel pisa el vhost y se lleva la excepción puesta a mano.

## Tareas programadas: una sola línea de cron

**No hace falta un servicio de systemd para la cola.** El programa de tareas ya
agenda `queue:work --stop-when-empty --max-time=55` cada minuto: drena la cola y
sale. Un worker permanente sería una pieza más para vigilar a cambio de ahorrar
como mucho un minuto de latencia — y el camino crítico, el alta de un inquilino,
es síncrono y no pasa por la cola.

Una línea, como el usuario del sitio y desde el directorio de producción:

```cron
* * * * * cd /home/<usuario>/htdocs/<dominio> && DB_DATABASE=demo_central php artisan schedule:run >> /dev/null 2>&1
```

### `DB_DATABASE=demo_central` no es opcional

`schedule:run` pregunta si el programa está pausado (`illuminate:schedule:paused`)
usando el almacén de caché POR DEFECTO, no el de los cerrojos. Sin la variable,
ese almacén resuelve el centinela y el programador muere en la primera línea:

```
FATAL: database "demo_sin_resolver" does not exist
(SQL: select * from "cache" where "key" in (...:schedule:paused))
```

El razonamiento de fondo: **un proceso del programador no está «sin resolver»,
está resuelto a la central**. El centinela existe para lo que no se sabe, y acá
sí se sabe. Declararlo es más honesto que agregar código que lo adivine.

Los hijos que lanza `demo:por-cada-inquilino` pisan esa variable con la base de
su inquilino y heredan el resto, así que el recorrido no se ve afectado. Y
`queue:work`, que el programador lanza como subproceso, la hereda — su cola ya
apunta a la central por conexión declarada.

Si alguien rehace el cron sin la variable, **falla al instante y con ese mensaje**.
Es de los pocos puntos de esta épica que se anuncian solos.

### Por qué esto no es "prender el cron y listo"

Un comando de consola **no tiene subdominio del cual resolver un inquilino**, así
que su conexión por defecto se queda en el centinela. Eso parte las tareas en dos
familias que no se pueden agendar igual, y la plataforma de origen —una sola
base— no distinguía:

| Familia | Ejemplos | Cómo se agenda |
|---|---|---|
| Central | `demo:expirar`, `demo:borrar`, `queue:work` | Directo. Leen el padrón, que vive en la central por conexión declarada |
| De inquilino | `leads:reconcile`, `frontend:media:reconcile`, `contratos:*` | `demo:por-cada-inquilino <comando>`: una corrida por inquilino activo, cada una en su base |

`demo:por-cada-inquilino` lanza un **proceso aparte** por inquilino, con
`DB_DATABASE` apuntando a su base. Es el mismo motivo que en la construcción de
la plantilla: reapuntar la conexión dentro de un proceso vivo deja atrás todo lo
que ya la resolvió y memoizó. Cuesta un arranque de Laravel por inquilino, y se
paga con gusto — la alternativa es que el aislamiento dependa de que nadie se
olvide de limpiar un singleton.

Un fallo en un inquilino se reporta y el recorrido sigue: con veinte, que el
tercero tumbe a los diecisiete de atrás convierte un fallo puntual en un apagón.
Y el recorrido **rechaza comandos destructivos** (`migrate:fresh`, `db:wipe` y
compañía): es el peor lugar posible para que se cuele uno, porque multiplica el
daño por la cantidad de gente que confió en el demo.

### Los cerrojos van en la central

`withoutOverlapping()` y `onOneServer()` guardan su cerrojo en el caché, y el
caché usa la conexión por defecto — el centinela. Sin `Schedule::useCache('central')`
(en `routes/console.php`), **`demo:borrar` falla antes de mirar un solo
inquilino**: el comando que libera disco no libera nada.

Requiere que `demo_central` tenga `cache` y `cache_locks`. Verificar:

```bash
psql -d demo_central -c "\dt cache*"
```

Todo esto está protegido por `TareasProgramadasTest`.

## El enlace de `storage` no es opcional

```bash
php artisan storage:link
```

Sin él, la librería de medios guarda los archivos correctamente y el servidor web
los devuelve con **404**. El sistema acepta la subida, la confirma, y la imagen no
aparece nunca.

Es un enlace por instalación y no por inquilino: las rutas ya llevan el inquilino
adelante (RFC-08), porque la numeración de la librería arranca en 1 en cada base
y sin ese prefijo dos inquilinos escribirían en `1/`.

Se descubrió cuando el widget de marca del escritorio mostró una imagen rota en
desarrollo. En el servidor el síntoma habría sido el mismo, con la diferencia de
que quien lo sufriría es la persona invitada subiendo su logo.

## Qué sirve el dominio base sin subdominio

El host central apunta su conexión por defecto a la base central **a propósito**,
para que no pueda tocar datos de ningún inquilino. Esa base tiene el padrón, las
sesiones y la cola — no tiene páginas. Así que cualquier ruta del sitio moría ahí
con un 500, buscando tablas del CMS que no existen ni deben existir.

No era un bug: era una decisión que faltaba tomar.

Ahora responde en dos niveles:

1. **Una portada propia**, mínima, que no consulta base de datos, ni CMS, ni usa
   los assets compilados. Es el piso: no puede fallar aunque el build no haya
   corrido.
2. **Una redirección**, si `TENANCY_SITIO_PROMOCIONAL` tiene un valor.

```dotenv
TENANCY_SITIO_PROMOCIONAL=https://www.ejemplo.com
```

**Ese orden es deliberado.** Redirigir siempre ataría el host central a que
exista otro sitio: mientras la landing no esté lista, se cambia un 500 por el 500
del otro dominio — y encima aparece en un dominio distinto del que lo causa, que
es peor de diagnosticar. Con la portada de piso, la redirección es una mejora y
no un requisito.

Alcanza a **todas** las rutas de ese host, incluido `/admin` —el panel tampoco
funciona ahí: buscaría los usuarios en una base sin esa tabla— salvo el chequeo
de salud. El día que exista un panel de operación en la central (RFC-12), sus
rutas se suman a esa excepción.

## La llave de Google Maps y el comodín

Los mapas de Zonas mueren con:

```
Google Maps JavaScript API error: RefererNotAllowedMapError
```

La llave está restringida por referente HTTP, y **cada inquilino vive en un
subdominio distinto y generado al azar**. No se pueden enumerar: hay que
autorizar el comodín.

En Google Cloud Console → Credenciales → la llave → Referentes HTTP:

```
https://*.demo.<dominio>/*
```

Tarda unos minutos en propagar. Y ojo con el diagnóstico: que la llave esté en el
`.env` no significa que la aplicación la vea —una configuración cacheada devuelve
null—, así que conviene separar las dos preguntas:

```bash
php artisan tinker --execute='echo config("services.google_maps.key") ? "la app la ve" : "la app NO la ve", PHP_EOL;'
```

Si la app no la ve, el propio componente lo dice en pantalla («Configura
GOOGLE_MAPS_API_KEY»). Si la ve, el rechazo es de Google y el motivo está en la
consola del navegador.

### Conviene una llave APARTE para el demo

Una llave de Maps JS **siempre es pública**: viaja en el HTML de cada página. Lo
único que la protege es la restricción por dominio. Al sumarle los subdominios del
demo, la llave de producción empieza a funcionar también desde ahí.

Tres consecuencias, y ninguna aparece hasta que duele:

- El consumo del demo gasta la **cuota de producción**.
- Un abuso en el demo obliga a rotar la llave, y eso **se lleva puesto al sitio
  que factura**.
- La superficie autorizada de la llave de producción crece sin necesidad.

Con una llave propia restringida sólo a `*.demo.<dominio>/*`, los tres
desaparecen. Es una variable en el `.env` del demo.

## Checklist antes del primer inquilino

- [ ] PostgreSQL 16 con PostGIS en el VPS.
- [ ] PostGIS instalado **en `template1`**, o la plantilla no se puede construir
      sin volver superusuario al rol que aprovisiona.
- [ ] Rol `demo_provisioner` con `CREATEDB`, separado de `demo_app`.
- [ ] `GRANT demo_app TO demo_provisioner;` y `TENANCY_ROL_APLICACION=demo_app`
      en el `.env`. Sin eso, el alta funciona y el inquilino no puede entrar.
- [ ] `CONNECTION LIMIT` puesto en `demo_app`, y `demo_app` **no** es
      superusuario (si lo fuera, el tope no aplicaría).
- [ ] Base central creada y migrada.
- [ ] Plantilla construida, y **ninguna** conexión de la aplicación apuntándole.
- [ ] Línea de cron con `DB_DATABASE=demo_central php artisan schedule:run` como
      el usuario del sitio (no hace falta worker de systemd: el programa ya
      agenda la cola cada minuto). Sin esa variable el programador no arranca.
- [ ] `demo_central` con las tablas `cache` y `cache_locks`, o los cerrojos del
      programador fallan y `demo:borrar` no corre.
- [x] `trustProxies` acotado al bucle local en `bootstrap/app.php`, con test.
- [ ] `php artisan storage:link` corrido, o las imágenes que suba un inquilino
      devuelven 404 sin que nada avise.
- [ ] El dominio base sin subdominio responde (portada propia o
      `TENANCY_SITIO_PROMOCIONAL` apuntando a un sitio que EXISTE).
- [ ] `GOOGLE_MAPS_API_KEY` autorizada para `https://*.demo.<dominio>/*`, o los
      mapas de Zonas no cargan. **Idealmente una llave propia del demo**, no la
      de producción.
- [ ] DNS comodín resolviendo.
- [x] Certificado comodín emitido con acme.sh e instalado con `--reloadcmd`.
- [ ] Verificado que sin sesión ninguna ruta de inquilino devuelve contenido.
- [ ] Assets de Livewire publicados en `public/vendor/livewire/`, o el acceso
      devuelve 405 y la pantalla se ve bien.
- [ ] Sin excepciones a mano en el vhost para `/livewire/`: no hacen falta y el
      panel las pisa.
