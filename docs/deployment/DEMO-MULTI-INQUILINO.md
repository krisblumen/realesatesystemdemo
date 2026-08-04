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

Contrato: `demo_provisioner` crea la base y **le transfiere la propiedad a
`demo_app`** en el mismo paso del alta. La plantilla se construye ya con esa
propiedad, así que la copia la hereda. Verificar en el alta que `demo_app` puede
leer y escribir antes de marcar `activo`.

El motivo de separar los roles está en RFC-05: el nombre de una base se
interpola en DDL porque Postgres no acepta parámetros para identificadores. Si el rol que ejecuta esa
sentencia fuera el mismo que atiende peticiones, un error de validación dejaría
de ser «un inquilino ve a otro» y pasaría a ser «se pierden todos».

## Proxy de confianza

`bootstrap/app.php:27` usa `trustProxies(at: '*')`, y entre los encabezados
confiados está `X-Forwarded-Host` (`TrustProxies.php:23`).

Con el inquilino resuelto por subdominio, eso significa que **quien alcance el
origen sin pasar por CloudPanel elige a qué inquilino resuelve**. No le da
acceso —siguen haciendo falta credenciales— pero convierte la frontera en algo
que el cliente puede elegir, que es justo lo que el diseño evita.

Confiar sólo en la dirección del proxy. CloudPanel corre en el mismo host.

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

## Checklist antes del primer inquilino

- [ ] PostgreSQL 16 con PostGIS en el VPS.
- [ ] Rol `demo_provisioner` con `CREATEDB`, separado de `demo_app`.
- [ ] Propiedad y permisos: `demo_app` es dueño de las bases de inquilino y
      puede leer y escribir sin tener `CREATEDB` (M-1).
- [ ] `CONNECTION LIMIT` puesto en `demo_app`, y `demo_app` **no** es
      superusuario (si lo fuera, el tope no aplicaría).
- [ ] Base central creada y migrada.
- [ ] Plantilla construida, y **ninguna** conexión de la aplicación apuntándole.
- [ ] Cola corriendo y cron activo.
- [ ] `trustProxies` acotado a la dirección del proxy, no `'*'`.
- [ ] DNS comodín resolviendo.
- [ ] Certificado comodín emitido y renovando solo.
- [ ] Verificado que sin sesión ninguna ruta de inquilino devuelve contenido.
