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
como mucho un minuto de latencia.

Una línea, como el usuario del sitio y desde el directorio de producción:

```cron
* * * * * cd /home/<usuario>/htdocs/<dominio> && DB_DATABASE=demo_central php artisan schedule:run >> /dev/null 2>&1
```

## Un trabajo encolado también hereda el HOST de su inquilino

Mismo sello, segundo problema. En un trabajo encolado no hay petición, así que
`route()` y `url()` no tienen de dónde sacar el host y caen en `APP_URL` — el
host central.

El síntoma apareció con un contrato real: el enlace de firma le llegaba al
cliente como `demo.landracore.com/contrato/…` en vez de
`<slug>.demo.landracore.com/contrato/…`. Y ese host redirige al sitio
promocional, así que el cliente hacía clic y terminaba en otra página, sin ningún
error que lo explicara.

`InquilinoEnLaCola` sella también la raíz de las direcciones tal como la ve quien
encola —en una petición del panel, el subdominio correcto— y la aplica con
`URL::forceRootUrl()` antes de procesar. **Once notificaciones encoladas arman
direcciones**; la del contrato fue la única que alguien ejercitó.

El esquema (`http`/`https`) no viaja en el sello: lo decide
`URL::forceScheme('https')`, que corre en producción.

## El limitador de peticiones vive en la central

Un 500 en producción a los diez minutos de abrir `/guest`, y la causa vale más
que el arreglo.

`RateLimiter` es un **singleton** que Laravel construye la primera vez que
alguien lo toca. En esta aplicación eso pasa en `AppServiceProvider::boot()`,
donde se declaran los límites de los contratos públicos — o sea **antes de que
corra un solo middleware**. Su almacén de caché se queda con la conexión que
hubiera en ese momento: la por defecto sin inquilino resuelto, el centinela.

`ResolveTenant` la reapunta después, pero el limitador ya guardó la suya. El
resultado era `FATAL: database "demo_sin_resolver" does not exist` en **toda**
ruta con `throttle` — incluidas las de firma de contratos, que arrastraban el
defecto desde antes. Nadie lo había visto porque su correo nunca llegaba.

Por eso `config/cache.php` define un almacén `limitador` con la conexión
DECLARADA a la central, y `'limiter'` apunta ahí. La central es la única base
que existe y es correcta en el arranque, cuando todavía no hay petición. Y encaja
con lo que un limitador cuenta: los intentos son por dirección IP, no por
inquilino — un mismo atacante contra tres demos es un solo balde, no tres.

La suite corre con `CACHE_LIMITER=array` por aislamiento: en una base de verdad
los intentos de login se acumulan entre tests. Esa es también la razón por la que
el defecto pasó 1775 tests — con `array` no hay ninguna base contra la cual
fallar.

## El demo al que nadie entra se suelta antes

Con el registro público la mayoría de las altas no vuelve nunca: alguien deja su
correo, mira dos pantallas y se va. Esa base ocupa disco y conexiones durante
todo su plazo, igual que una que se usa — y el recurso escaso no son los
inquilinos activos, son las bases vivas.

`demo:expirar` ahora corta por dos motivos, y lo dice cuál fue:

| Motivo | Cuándo |
|---|---|
| `venció su plazo` | Pasó `expira_en`, el plazo que se le prometió |
| `nadie entró` | Nadie abrió el panel en `TENANCY_DIAS_SIN_USO` días (5 por defecto) |

**Sólo acorta.** `expira_en` se fija al crear y nada lo mueve: usar el demo no
compra más tiempo del original.

«Uso» es una petición AUTENTICADA al panel — no el sitio público del inquilino.
El enlace para compartir se lo puede pasar a diez personas, y diez desconocidos
mirando una portada no significan que su dueño lo esté usando. Tampoco cuenta la
pantalla de login, o un robot golpeándola mantendría vivo un demo muerto.

`TENANCY_DIAS_SIN_USO=0` apaga la regla. Es una salida explícita, no un efecto
del cero: sin ella, «sin uso desde hace 0 días» sería todo lo activo, y el
interruptor de apagado sería el botón de demolición.

El número es de producto, no técnico: bajarlo cuando falte cupo no requiere
desplegar. `demo:padron` muestra la columna **Último uso** («hoy», «hace 3 d»,
«nunca») para poder calibrarlo mirando datos y no intuición.

Los inquilinos que ya existían al desplegar esto arrancan con cuerda: la
migración les pone `ultimo_acceso_en = now()`. Nadie los había usado, es cierto,
pero la regla no existía cuando se crearon — aplicarla hacia atrás sería cambiar
el trato después de haber invitado.

## El registro público vive en `/guest` del host central

`https://demo.landracore.com/guest`. La dirección es discreta a propósito
mientras el demo se asienta, y **eso no es lo que la protege**: una dirección se
comparte, se filtra y se adivina. Lo que la protege son los topes de RFC-10, que
se aplican igual ahora que cuando la publiquemos.

| Capa | Qué frena |
|---|---|
| `throttle:10,1` | Que martillen el endpoint. No protege la instancia, sólo la puerta |
| Tope duro (RFC-10) | Que el demo se lleve las 100 conexiones que compartimos con la producción vecina |
| Tope por origen (RFC-10) | Que una sola persona se lleve el cupo de todos. Tres por día |
| Correo ya registrado | Un segundo demo para quien ya tiene uno |
| Señuelo | Robots que llenan todos los campos que encuentran |

Los topes se comprueban **antes de encolar**. Encolar altas que van a fallar es
acumular basura: la cola las levanta, el alta revienta contra el tope, y queda
una fila fallida por cada intento.

`/guest` está exceptuada en `AtiendeElHostCentral`. Sin esa excepción el host
central la redirigiría al sitio promocional y la única puerta de entrada al demo
quedaría inalcanzable — con un 302, no con un error.

**La cola `altas` deja de ser opcional acá.** Con `demo:invitar` el alta corría
en el momento y la cola no hacía falta. Desde el registro público, si el worker
no está andando, la persona ve «en un minuto te llega» y no le llega nunca.

### Y una SEGUNDA línea, sólo para las altas

El alta de un inquilino no viaja por esa cola: va por la suya, `altas`, atendida
por un proceso aparte. **No es organización, es privilegio.**

Crear una base exige `CREATEDB`, que tiene `demo_provisioner` y no `demo_app` —el
rol con el que corre la aplicación, a propósito—. Si el alta cayera en la cola
general, o el worker general llevaría `CREATEDB` (y entonces cualquier trabajo
podría crear bases), o el alta moriría con «permission denied to create
database». Una cola aparte deja ese privilegio en un solo proceso.

```cron
* * * * * cd /home/<usuario>/htdocs/<dominio> && DB_DATABASE=demo_central DB_USERNAME=demo_provisioner DB_PASSWORD='<clave del provisionador>' php artisan queue:work --queue=altas --stop-when-empty --max-time=55 >> storage/logs/altas.log 2>&1
```

Tres cosas de esa línea, que no son adorno:

| Parte | Por qué |
|---|---|
| `--queue=altas` | Un worker sin `--queue` atiende `default` y nunca vería estos trabajos. El de arriba tampoco toma los de `altas`: las dos líneas no se pisan |
| `DB_USERNAME` / `DB_PASSWORD` | La conexión de mantenimiento —la que corre `CREATE DATABASE`— los lee del entorno. Es el único lugar del despliegue donde aparece el provisionador |
| `>> storage/logs/altas.log` | El otro cron va a `/dev/null` porque su salida es ruido. Acá no: si un alta falla, esto y la tabla `failed_jobs` son todo lo que queda |

**La contraseña queda en el crontab**, y eso hay que saberlo en vez de
descubrirlo: `crontab -l` la muestra. Es el precio de no darle `CREATEDB` a la
aplicación entera — un secreto en un archivo del usuario del sitio contra un
privilegio permanente en el rol que atiende peticiones. El intercambio conviene,
pero es un intercambio.

### Un trabajo encolado vuelve solo a su inquilino

Un worker no tiene subdominio del cual deducir de quién es el trabajo que
levanta. Lo resuelve `app/Tenancy/InquilinoEnLaCola.php`: al encolar se sella la
base del inquilino en el PAYLOAD, y al procesar se apunta la conexión desde el
evento `JobProcessing`.

**Tiene que ser el payload y tiene que ser ese evento**, y se descubrió con un
fallo real del 2026-08-07. Las notificaciones de Laravel usan `SerializesModels`,
así que el modelo no viaja entero: viaja como identificador y se re-consulta al
DESERIALIZAR, dentro de `CallQueuedHandler::getCommand()`. Eso ocurre antes de
que exista una instancia del trabajo, así que un middleware de trabajo —que es lo
que había— llegaba tarde por diseño. El payload se lee sin deserializar nada, y
`JobProcessing` se dispara antes.

El síntoma era invisible desde el panel: un agente enviaba el contrato a su
cliente, la notificación se encolaba, el worker moría con
«relation "contratos_intermediacion" does not exist» y el agente creía que lo
había mandado.

Un trabajo SIN inquilino no se sella ni se toca: el alta de un inquilino corre
cuando su base todavía no existe, y el registro público vive en el host central.

### El fallo del fallo

`failed_jobs` y `job_batches` están fijados a la conexión `central` en
`config/queue.php`, igual que la cola. Por defecto Laravel los deja heredando la
conexión POR DEFECTO — que en un worker es el centinela, porque no hay subdominio
que resolver.

Hoy eso NO estaba roto, y vale decirlo con precisión: el worker general hereda
`DB_DATABASE=demo_central` de la línea de cron del programador, así que `pgsql`
ya resolvía a la central en ese proceso. Hay un fallo anotado del 2026-08-07 que
lo demuestra.

Se fija igual porque **la garantía no debería depender de una variable de
entorno**. Bastaba que alguien agregara una línea de cron sin ella —o un servicio
de systemd— para caer en el peor modo de falla posible: un trabajo revienta, el
sistema intenta anotar el fallo, y anotarlo falla también. Pesa más desde que el
alta corre en la cola, porque no reintenta (`tries = 1`): un alta que falla va
derecho a esa tabla y es todo lo que queda de ella.

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

## Los topes que hacen seguro abrir el demo (RFC-10)

**El número no se elige, se deriva:**

```
inquilinos simultáneos ≈ registros por día × días de vida
```

Si la instancia sostiene 200 bases y llegan 50 registros diarios, el plazo no
puede pasar de cuatro días. Elegir el plazo primero y descubrir el techo después
es cómo se llena un disco un domingo.

**El techo real de este VPS no es el disco.** Son 18 MB por inquilino y ~3.000
por espacio libre — pero las **100 conexiones de Postgres** se comparten con la
producción de New Hauz y con el correo. Ese es el límite, y por eso el tope
duro existe aunque el plazo de vida sea corto.

```dotenv
TENANCY_SAL_DE_ORIGEN=<una cadena larga, fija, con dueño>
TENANCY_TOPE_OCUPADOS=20
TENANCY_ALTAS_POR_ORIGEN=3
TENANCY_VENTANA_HORAS=24
```

### Tres cosas que no son obvias

**El tope cuenta bases VIVAS, no inquilinos «activos».** Un expirado ya no
atiende a nadie pero su base sigue ahí hasta que el barrido de las 3:30 la borre,
ocupando disco y conexiones igual. Contar sólo `activo` daría un cupo que no
existe, y el síntoma sería quedarse sin conexiones con el padrón diciendo que hay
lugar.

**La sal es fija y tiene dueño.** Si rota, los límites por origen se pierden en
silencio y nadie se entera — peor que no tenerlos. Sin sal configurada el sistema
**se niega** a hashear en vez de usar cadena vacía: un límite que parece
funcionar y no protege es peor que ninguno.

**El tope duro también frena la invitación por consola.** El límite por origen no
—a quien invita lo limita ser el operador— pero el tope protege la instancia, y
eso no depende de por dónde entró el alta.

## El correo del demo, con identidad propia

El demo envía como `no-reply@landracore.com` y **no depende de la identidad de
correo de New Hauz**, aunque salga por la misma máquina. Conviene separar las dos
cosas:

| | Qué es | Valor |
|---|---|---|
| `MAIL_HOST` | Por dónde SALE. Infraestructura | `mail.newhauz.com.mx` — el nombre para el que existe el certificado |
| `MAIL_FROM_ADDRESS` | La identidad que se VE | `no-reply@landracore.com` |

Quien recibe nunca ve por qué máquina pasó: ve el remitente, y verifica el DNS de
ese dominio.

```
MAIL_HOST=mail.newhauz.com.mx
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=no-reply@landracore.com
MAIL_PASSWORD=<la casilla en PostfixAdmin>
MAIL_FROM_ADDRESS=no-reply@landracore.com
MAIL_FROM_NAME=Landra
TENANCY_AVISO_DE_ALTAS=<tu correo>
```

### Los tres errores que aparecieron, en orden

**1. `Peer certificate CN=mail.newhauz.com.mx did not match expected CN=127.0.0.1`**
El `.env` apuntaba a `127.0.0.1` y el servidor presenta un certificado con su
nombre. Se arregla apuntando al nombre, **no** desactivando la verificación.

**2. `454 4.7.1 Relay access denied`**
Sin autenticar, un servidor de correo sólo acepta mensajes para sus propios
dominios — si no, sería un relay abierto. Faltaban `MAIL_USERNAME`/`MAIL_PASSWORD`,
y el puerto 25 (servidor a servidor) suele tener la autenticación deshabilitada:
va el **587**.

**3. El que no da error: caer en spam.**
`landracore.com` no tenía SPF, DKIM ni DMARC. El mensaje habría salido sin
problema y aterrizado en la carpeta equivocada — que es el fallo que RFC-11
describe: alguien que quería probar el producto y no pudo, con un inquilino ya
aprovisionado ocupando lugar.

### El DNS que hace verificable la identidad

| Registro | Contenido |
|---|---|
| `@` TXT | `v=spf1 ip4:<IP del VPS> ~all` |
| `default._domainkey` TXT | La llave pública que genera OpenDKIM |
| `_dmarc` TXT | `v=DMARC1; p=none; rua=mailto:dmarc@<dominio>; fo=1` |
| `@` MX | `10 mail.newhauz.com.mx.` |

**DKIM lo firma OpenDKIM, no PostfixAdmin** — PostfixAdmin administra dominios y
casillas, nada más. La llave se genera así:

```bash
mkdir -p /etc/opendkim/keys/<dominio>
opendkim-genkey -b 2048 -d <dominio> -D /etc/opendkim/keys/<dominio> -s default -v
chown -R opendkim:opendkim /etc/opendkim/keys/<dominio>
chmod 600 /etc/opendkim/keys/<dominio>/default.private
```

Y se registra agregando —con `>>`, sin pisar lo que hay— una línea a
`/etc/opendkim/key.table` y otra a `/etc/opendkim/signing.table`, con el formato
que ya usa el dominio existente.

**Respaldar esas dos tablas antes de tocarlas.** Si OpenDKIM no arranca después
del reinicio, el correo del OTRO dominio deja de firmarse — y eso no avisa.

### Cómo se comprueba que quedó bien

En Gmail, «Mostrar original». Tienen que decir las tres:

```
SPF:   PASS
DKIM:  PASS con el dominio <el tuyo>
DMARC: PASS
```

Lo importante es el «**con el dominio**» del DKIM: si la firma fuera del dominio
del servidor y no del remitente, no alinean y DMARC falla.

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
- [ ] Segunda línea de cron para la cola `altas`, con las credenciales del
      provisionador. Sin ella el registro público encola y nada las atiende
      agenda la cola cada minuto). Sin esa variable el programador no arranca.
- [ ] `demo_central` con las tablas `cache` y `cache_locks`, o los cerrojos del
      programador fallan y `demo:borrar` no corre.
- [x] `trustProxies` acotado al bucle local en `bootstrap/app.php`, con test.
- [ ] `php artisan storage:link` corrido, o las imágenes que suba un inquilino
      devuelven 404 sin que nada avise.
- [ ] El dominio base sin subdominio responde (portada propia o
      `TENANCY_SITIO_PROMOCIONAL` apuntando a un sitio que EXISTE).
- [x] `GOOGLE_MAPS_API_KEY` **propia del demo** —no la de producción— autorizada
      para `https://*.demo.<dominio>/*`. Sin el comodín los mapas de Zonas no
      cargan; sin llave propia, el consumo del demo gasta la cuota del sitio que
      factura y un abuso obliga a rotar la de producción.
- [ ] Correo probado de punta a punta, con SPF, DKIM y DMARC en PASS. Que el
      envío no dé error NO alcanza: lo que importa es en qué carpeta cae.
- [ ] `TENANCY_SAL_DE_ORIGEN` puesta y anotada quién es su dueño. NO rota.
- [ ] `TENANCY_TOPE_OCUPADOS` con un número DERIVADO de la medición, no elegido.
- [ ] DNS comodín resolviendo.
- [x] Certificado comodín emitido con acme.sh e instalado con `--reloadcmd`.
- [ ] Verificado que sin sesión ninguna ruta de inquilino devuelve contenido.
- [ ] Assets de Livewire publicados en `public/vendor/livewire/`, o el acceso
      devuelve 405 y la pantalla se ve bien.
- [ ] Sin excepciones a mano en el vhost para `/livewire/`: no hacen falta y el
      panel las pisa.
