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
- Certificado comodín con certbot y validación **DNS-01** — la validación HTTP-01
  no emite comodines.
- Renovación automática verificada antes de invitar a nadie.

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
- [ ] Cola corriendo y cron activo.
- [x] `trustProxies` acotado al bucle local en `bootstrap/app.php`, con test.
- [ ] DNS comodín resolviendo.
- [ ] Certificado comodín emitido y renovando solo.
- [ ] Verificado que sin sesión ninguna ruta de inquilino devuelve contenido.
- [ ] Assets de Livewire publicados en `public/vendor/livewire/`, o el acceso
      devuelve 405 y la pantalla se ve bien.
- [ ] Sin excepciones a mano en el vhost para `/livewire/`: no hacen falta y el
      panel las pisa.
