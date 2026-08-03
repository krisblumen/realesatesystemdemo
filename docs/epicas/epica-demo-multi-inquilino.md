# Épica DEMO — Demo público multi-inquilino

> Estado: diseño, sin implementar.
> RFC de referencia: `docs/rfc/EPICA-DEMO-MULTI-INQUILINO.md`.

## 1. Contexto

El código de este repositorio se copió de un sistema construido para una
inmobiliaria concreta. Funciona, está en producción, y todo su modelo asume una
sola empresa.

Esta épica lo convierte en un demo donde cualquiera se registra y recibe un
espacio propio, temporal y aislado.

## 2. Evidencia verificada en código real

Todo lo que sigue se comprobó en este árbol, no se supone.

- **El aislamiento por roles no existe para un `owner`.**
  `app/Models/Property.php`, `scopeVisibleTo`: `if ($user->hasAnyRole(['owner','admin'])) return $query;`.
  Sólo cuatro modelos tienen `visibleTo` — `Property`, `Lead`, `PropertyOwner`,
  `ContratoIntermediacion`. Los otros 24 modelos no filtran por nadie.
- **El CMS es un singleton por `key`.**
  `frontend_pages` tiene exactamente 6 filas: `home`, `nosotros`, `servicios`,
  `inversionistas`, `contacto`, `proyectos`. Se consulta con
  `FrontendPage::query()->where('key', $key)->first()`
  (`FrontendPageContentService.php:75`), sin usuario en ningún punto.
- **Las claves de caché no llevan inquilino.**
  `FrontendPageContentService.php:46`:
  `sprintf('frontend:g%d:page:%s:v%d', $this->generation->current(), $key, self::SHAPE)`
  → `frontend:g3:page:home:v2`. Lo mismo en `FrontendSettingsService`,
  `FrontendServicesService`, `FrontendNavigationService`, `FrontendThemeService`.
- **Caché, cola y sesión corren sobre `database`** (`php artisan about`).
- **El panel es estático**: `AdminPanelProvider.php:36-37`,
  `->id('admin')->path('admin')`. Las rutas públicas cuelgan de la raíz.
- **Hay 18 consultas crudas en `app/`**, y las que tocan datos de inquilino son
  dos (`leads`, `frontend_services`). El resto es geografía, que es catálogo
  compartido. El código pasa por Eloquent casi siempre.
- **Copiar una plantilla cuesta 0.2 s** y el resultado llega completo: 50
  tablas, PostGIS 3.6 operativo (`ST_Centroid` verificado), los índices GIST de
  `zones` y `postal_code_areas`, y las 6 páginas del CMS ya sembradas.
- **Postgres rechaza copiar una plantilla con conexiones encima.** Verificado:
  `ERROR: source database "demo_template" is being accessed by other users`.
  La documentación de 16 y de 18 lo declara igual. Producción corre 16.14;
  desarrollo local corre 18.3.

## 3. Problema a resolver

Dos visitantes probando al mismo tiempo se pisan. No en el sentido de permisos:
literalmente escriben sobre la misma fila. El primero que publica su página de
inicio le cambia el sitio al segundo.

## 4. Objetivos

1. Que un visitante se registre y reciba un espacio propio en segundos.
2. Que ese espacio esté aislado por construcción y no por disciplina.
3. Que expire y se borre solo.
4. Que el sistema de roles siga significando lo mismo adentro de cada espacio.

## 5. Fuera de alcance

- Convertir un demo en cuenta de pago.
- Catálogo geográfico por inquilino.
- Facturación y planes.
- Des-marcar el producto (trabajo previo y aparte).

## 6. Reglas de oro

1. **El aislamiento no depende de que una consulta esté bien escrita.** Si la
   única protección es que alguien se acuerde de filtrar, no hay protección.
2. **La aplicación jamás abre una conexión contra la plantilla.**
3. **Ningún trabajo en cola confía en la conexión ambiente.** El worker es un
   proceso largo que atiende inquilinos distintos uno detrás del otro.
4. **Toda clave de caché lleva el inquilino**, aunque hoy la base ya lo aísle.
5. **Borrar un inquilino borra su base y sus archivos.** Media huérfana en disco
   es una fuga con retardo.

## 7. Modelo conceptual

Tres clases de base de datos:

| Base | Qué contiene | Quién se conecta |
|---|---|---|
| `demo_central` | Padrón de inquilinos, cola, sesiones del host central, límites de abuso | La app, siempre |
| `demo_template_vN` | Esquema migrado y sembrado, sin datos de nadie | **Nadie**. Sólo se copia |
| `demo_t_{slug}` | Todo lo del inquilino | La app, cuando el host resuelve a ese inquilino |

Tabla `tenants` en la central:

| Columna | Para qué |
|---|---|
| `id`, `slug` | El `slug` es el subdominio y el nombre de la base |
| `estado` | `aprovisionando`, `activo`, `fallido`, `expirado`, `borrado` |
| `database` | Nombre real de la base; no se deriva del slug al vuelo |
| `email`, `origen_ip_hash` | Contacto y control de abuso |
| `expira_en`, `borrado_en` | Ciclo de vida |
| `template_version` | Con qué plantilla nació; sirve para diagnosticar |

`origen_ip_hash` guarda un hash con sal, no la IP. Sirve para limitar altas
repetidas sin retener un dato personal más tiempo del necesario.

## 8. Diseño técnico consolidado

### 8.1 Resolución del inquilino: por subdominio

`{slug}.demo.example.com` sirve el sitio público del inquilino, y
`{slug}.demo.example.com/admin` su panel. El host central —sin slug— sirve el
registro.

**Por qué subdominio y no sesión.** La sesión es circular: la sesión vive en la
base de datos, así que para leerla hace falta saber a qué base conectarse, y
para saberlo hace falta leer la sesión. El encabezado `Host` está disponible
antes de que corra un solo middleware, así que rompe el ciclo.

**Por qué subdominio y no prefijo de ruta.** El prefijo obliga a tocar todas las
rutas y toda generación de URL, y el panel de Filament se monta con
`->path('admin')`, que es estático. Con subdominio no se toca ninguna ruta:
`Route::domain()` y `->domain()` en el panel envuelven lo que ya existe.

**El costo, dicho de frente**: hace falta DNS comodín y certificado comodín. En
un VPS con certbot y validación DNS-01 es trabajo de una vez.

**Confirmado con el proveedor**: Hostinger emite certificados comodín únicamente
en VPS. Los planes Web, Cloud y Agency dan SSL DV gratuito por dominio o
subdominio individual, no comodín. La decisión se sostiene **sobre VPS**.

Ver 8.11: el certificado resulta ser la menor de las tres razones por las que
este diseño exige VPS.

### 8.2 Orden de resolución (contrato C-1)

El middleware que resuelve el inquilino **corre antes de `StartSession`**. Ese
orden no es preferencia, es requisito: la sesión se guarda en base de datos y se
leería de la base equivocada.

Secuencia por petición:

1. Leer el `Host`, extraer el slug.
2. Buscar el inquilino en la central. Si no existe o no está `activo`, cortar.
3. Apuntar la conexión por defecto a la base del inquilino.
4. Recién ahí, sesión, autenticación y todo lo demás.

Consecuencia buena: como sesión y caché usan la conexión por defecto, quedan
aislados por el mismo movimiento. Uno resuelve tres problemas.

### 8.3 Caché (contrato C-2)

La base ya aísla el caché, porque la tabla vive en la base del inquilino. **Aun
así, toda clave lleva el slug**: `t:{slug}:frontend:g3:page:home:v2`.

Es redundante hoy y deliberado. El día que alguien mueva el caché a Redis por
rendimiento —una decisión razonable y probable— el aislamiento por base
desaparece de golpe y las claves son lo único que queda en pie. Sin prefijo, esa
migración sirve la home de un inquilino a otro y nadie lo nota hasta que un
prospecto lo ve.

### 8.4 Archivos (contrato C-3)

La librería de medios numera desde 1 en cada base. Dos inquilinos suben su
primera imagen y los dos escriben en `1/`. **Se pisan los archivos.**

La base de datos no protege de esto: el disco es compartido.

Solución: un generador de rutas propio que anteponga el inquilino —
`tenants/{slug}/{media_id}/` — registrado en la configuración de la librería. Y
el borrado del inquilino borra `tenants/{slug}/` completo.

### 8.5 Colas (contrato C-4)

La cola vive **siempre** en la central, fijada explícitamente. No puede vivir en
la base del inquilino por una razón elemental: el trabajo que crea la base del
inquilino corre cuando esa base todavía no existe.

Y cada trabajo que opera sobre un inquilino guarda su slug y **resuelve la
conexión al empezar y la restaura al terminar**. El worker es un proceso largo:
si un trabajo deja la conexión apuntando al inquilino A, el siguiente trabajo
—que puede ser de B, o central— la hereda. Ese es el modo de falla más silencioso
de todo este diseño, porque no da error: escribe en la base equivocada.

### 8.6 Alta de un inquilino (contrato C-5)

Serializada con un **cerrojo de aviso de Postgres** sobre la conexión central, no
con un cerrojo de caché con vencimiento: uno con TTL puede vencer mientras la
copia sigue corriendo y dejar entrar a un segundo.

**Por qué no el cerrojo transaccional.** `pg_advisory_xact_lock` sería lo
natural, porque se suelta solo al terminar la transacción. No se puede usar:

```
ERROR: CREATE DATABASE cannot run inside a transaction block
```

La operación que hay que proteger no admite estar dentro de una transacción, así
que el cerrojo transaccional queda descartado por construcción. Queda el de
sesión, con las precauciones que siguen.

**El cerrojo de sesión y su modo de falla.** `pg_advisory_lock` se ata a la
sesión de base de datos, y **la sesión de un worker de cola no se cierra entre
trabajos**. Eso parte en dos casos:

- **El worker muere** (se lo mata, se cae el proceso): la sesión se cierra y
  Postgres suelta el cerrojo solo. Este caso no necesita nada. Por eso el diseño
  **no** incluye un barrido de cerrojos huérfanos: sería ceremonia sin causa.
- **El trabajo lanza una excepción y el worker sigue vivo**: la sesión sigue
  abierta y **el cerrojo queda tomado**. Todas las altas siguientes esperan, y no
  hay error que lo delate: se ve como lentitud. Este es el caso real, y es el que
  hay que cerrar.

Pasos del trabajo:

1. Tomar el cerrojo con `pg_try_advisory_lock` en un bucle acotado —unos pocos
   intentos con espera entre ellos—. **Nunca con el `pg_advisory_lock` que
   espera sin límite**: si algo dejó el cerrojo tomado, el alta tiene que fallar
   con un mensaje, no colgarse para siempre.
2. `CREATE DATABASE demo_t_{slug} TEMPLATE demo_template_vN`.
3. Soltar el cerrojo **en un `finally`**, no en el camino feliz. Ese `finally` es
   la corrección: sin él, una excepción entre 1 y 3 congela las altas hasta que
   alguien reinicie el worker.
4. **La copia es lo único serializado.** Lo que sigue no toca la plantilla y
   puede correr en paralelo.
5. Conectarse al inquilino y crear su usuario `owner` con contraseña generada.
6. Marcar `activo` y entregar el acceso.

Si algo falla: estado `fallido`, y una tarea de limpieza borra la base a medias
si llegó a crearse.

Vigilancia: una alerta si algún cerrojo de esta clase lleva tomado más que lo que
tarda una copia con holgura. Es la señal de que el `finally` se rompió.

### 8.6.1 El nombre de la base es una superficie de inyección (contrato C-2)

`CREATE DATABASE` es DDL, y **Postgres no acepta parámetros enlazados para
identificadores**. El nombre de la base se interpola en la sentencia sí o sí. Si
ese nombre desciende de algo que escribió un visitante, esto es inyección SQL
ejecutada por un rol con permiso para crear y borrar bases de datos, desde un
formulario abierto a cualquiera.

Es el único punto del diseño donde una falla no significa "un inquilino ve a
otro" sino "se pierden todos". Se cierra con cuatro medidas, y las cuatro
son obligatorias:

1. **El `slug` lo genera el servidor.** El visitante no lo elige ni lo sugiere.
   No hay campo en el formulario que llegue a este camino.
2. **Formato cerrado**: `^[a-z][a-z0-9]{7,31}$`. Se valida contra ese patrón
   **inmediatamente antes** de componer la sentencia, no sólo al crear la fila.
   La validación tiene que estar pegada al uso peligroso; si está lejos, alguien
   agrega un segundo camino que no pasa por ella.
3. **El nombre se compone de un prefijo fijo más el slug validado**:
   `demo_t_{slug}`. Con el prefijo el identificador queda entre 14 y 38 bytes,
   bajo el límite de 63 de Postgres, y no puede coincidir con una palabra
   reservada.
4. **El rol que crea bases no es el rol de las peticiones** (contrato C-8). Aun
   si las tres medidas anteriores fallaran, el rol con el que corre el panel no
   puede ejecutar `CREATE DATABASE` ni `DROP DATABASE`.

Como red final, la sentencia se arma citando el identificador con las reglas de
Postgres, no concatenando a mano. Es redundante con el punto 2 y va igual: las
redundancias baratas en el único lugar catastrófico del sistema se pagan solas.

### 8.7 Actualizar la plantilla (contrato C-6)

No se migra la plantilla en su lugar. **Se construye una nueva y se cambia cuál
se usa.**

1. Crear `demo_template_v(N+1)` vacía, migrarla y sembrarla.
2. Cambiar en configuración cuál es la plantilla vigente.
3. Borrar la anterior cuando ya no haya altas en vuelo.

Así nunca hay una carrera entre "estoy migrando la plantilla" y "estoy dando de
alta un inquilino", que es exactamente el momento en que Postgres rechaza la
copia. Y permite volver atrás cambiando un valor.

Los inquilinos ya creados **no** reciben la migración por este camino. Si una
migración tiene que alcanzarlos, es un recorrido explícito inquilino por
inquilino, con su propio comando y su propio informe.

### 8.8 Expiración y borrado (contrato C-7)

Una tarea programada marca `expirado` lo vencido. Otra borra:

1. `pg_terminate_backend` sobre las sesiones vivas de esa base. Sin esto,
   `DROP DATABASE` falla contra cualquiera que dejó una pestaña abierta.
2. `DROP DATABASE`.
3. Borrar `tenants/{slug}/` del disco.
4. Marcar `borrado`, conservando la fila.

Borrar en ese orden importa: si se borran los archivos primero y el `DROP` falla,
queda un inquilino vivo con las imágenes rotas.

### 8.9 El CMS

No requiere cambios. Las seis páginas viajan con la plantilla —verificado en la
copia de prueba— y `where('key', 'home')` es correcto **una vez que la conexión
apunta al inquilino**. El singleton deja de serlo por debajo.

### 8.10 Geografía

Estados, municipios, códigos postales y polígonos son catálogo compartido y se
copian con la plantilla. Se acepta la duplicación a cambio de no partir el
modelo: `postal_code_areas` pesa 3.8 MB y se replica por inquilino.

### 8.11 Requisitos de infraestructura (contrato C-8)

Este diseño **no corre en hosting compartido**, y el certificado comodín es la
menor de las tres razones. Las tres son requisitos, no preferencias:

| Requisito | Por qué | Qué pasa si falta |
|---|---|---|
| Rol de Postgres con `CREATEDB` y `pg_terminate_backend` | El sistema crea una base en cada alta y la borra al expirar | No hay aprovisionamiento posible; el diseño entero cae |
| Proceso largo para la cola (`queue:work`) más cron | El alta va en cola para que el visitante no espere; la expiración corre sola | El alta vuelve al request y el primer pico la rompe; nada expira |
| Certificado comodín y DNS comodín | Un subdominio por inquilino | Se cae 8.1 y hay que volver a prefijo de ruta |

En hosting compartido las bases se crean desde el panel de control, con un tope
y sin privilegio para la aplicación; y los procesos largos no se sostienen. Los
dos primeros requisitos son independientes del certificado: **aunque el
proveedor ofreciera comodín en compartido, este diseño seguiría necesitando
VPS.**

Queda por confirmar en el VPS, antes del lote A:

- Que el rol de la aplicación —o un rol aparte dedicado al aprovisionamiento—
  tenga `CREATEDB`. Ver C-2 de la auditoría: **no debe ser el mismo rol con el
  que la aplicación atiende peticiones.**
- Cuántas bases de datos soporta la instancia antes de degradarse. Ese número es
  el techo real de inquilinos simultáneos, y define el plazo de vida (M-2 de la
  auditoría), no al revés.

### 8.12 Cómo se prueba todo esto (contrato C-9)

El test que justifica la épica —dos inquilinos publican una home distinta y cada
uno ve la suya— **no puede correr con la suite tal como está**, y por eso este
apartado es trabajo del **lote A** y no del final. Un plan de pruebas que no sabe
cómo montar su prueba más importante lo descubre cuando ya no hay tiempo.

**El choque.** `RefreshDatabase` envuelve cada test en una transacción sobre la
conexión por defecto. Este diseño cambia la conexión por defecto en mitad de la
petición, y además necesita crear bases de verdad — que es justamente lo que no
se puede hacer dentro de una transacción.

**La salida** son tres piezas:

1. **Transaccionar sólo la central.** `RefreshDatabase` permite acotar qué
   conexiones envuelve. La central se prueba como siempre; los inquilinos no se
   transaccionan porque son bases efímeras que se tiran enteras.
2. **Una conexión de mantenimiento**, apuntada a la base `postgres` y sin
   transacción abierta, que es la que ejecuta `CREATE DATABASE` y
   `DROP DATABASE` en los tests. Así el `CREATE` nunca cae dentro de la
   transacción del test.
3. **Un caso base propio** —`TenantTestCase`— que da un método para levantar un
   inquilino de prueba y los borra a todos al terminar el test, pase o falle.

**La plantilla de tests se construye una vez para toda la suite**, no por test.
Copiarla cuesta 0.2 s; migrar desde cero cuesta segundos. Con dos inquilinos por
test y una decena de tests de aislamiento, el costo total ronda los cuatro
segundos: aceptable.

Si la plantilla no existe, el caso base **falla con un mensaje que dice qué
comando correr**. No con un error de Postgres sobre una base inexistente, que
manda a depurar el lugar equivocado.

**Acá sí hace falta un barrido**, al revés que con los cerrojos de 8.6: una
corrida de tests interrumpida deja bases reales en el servidor, y esas no
desaparecen solas. Se nombran con un prefijo reconocible y hay un comando que
borra todo lo que quedó de corridas anteriores.

## 9. Contratos que la implementación debe cerrar

| # | Contrato | Dónde se verifica |
|---|---|---|
| C-1 | El middleware de inquilino corre antes de `StartSession` | Test de orden + test de sesión cruzada |
| C-2 | Toda clave de caché lleva el slug | Test con caché caliente en dos inquilinos |
| C-3 | Las rutas de media llevan el slug | Test de dos primeras subidas |
| C-4 | Los trabajos resuelven y restauran la conexión | Test de dos trabajos seguidos de inquilinos distintos |
| C-5 | El alta está serializada | Test de alta concurrente |
| C-6 | La plantilla se versiona, no se migra en su lugar | Comando + test del cambio de versión |
| C-7 | El borrado corta conexiones antes del `DROP` | Test de borrado con sesión abierta |
| C-8 | El rol que crea bases no es el rol de las peticiones | Revisión de despliegue + verificación al arrancar |
| C-9 | Existe un caso base capaz de levantar dos inquilinos reales | Es la condición para verificar C-1 a C-7 |

## 10. Matriz de tests

| Test | Qué protege |
|---|---|
| Dos inquilinos publican home distinta; cada uno ve la suya, con caché caliente | C-2, el corazón de la épica |
| Dos inquilinos suben su primera imagen; los archivos no se pisan | C-3 |
| El selector de agente de un inquilino no lista usuarios de otro | Aislamiento del padrón |
| Dos altas simultáneas producen dos inquilinos | C-5 |
| Un trabajo de A seguido de uno de B escribe en la base correcta | C-4 |
| Un inquilino expirado no deja entrar | Ciclo de vida |
| Borrar un inquilino con sesión abierta no falla | C-7 |
| Borrar un inquilino no deja base, archivos ni filas | Regla de oro 5 |
| El host central no resuelve a ningún inquilino | Que el registro no quede adentro de uno |

El primero es el que justifica la épica entera. Si sólo se pudiera escribir uno,
sería ese.

## 11. Lotes de implementación

| Lote | Contenido | Depende de |
|---|---|---|
| **A** | Base central: conexión, tabla `tenants`, cola fijada a central. **Y el caso base de tests con inquilinos reales (8.12)** | — |
| **B** | Plantilla versionada y comando para construirla | A |
| **C** | Alta en cola, con cerrojo, y creación del usuario del inquilino | A, B |
| **D** | Resolución por subdominio y orden de middleware | A |
| **E** | Prefijo de inquilino en caché y en rutas de media | D |
| **F** | Expiración, borrado y límites de abuso | C, D |
| **G** | Registro público y entrega de credenciales | C, D, E |

El registro —que es por donde uno querría empezar— es el último. Sin A a E, un
visitante registrado entra a un sistema que se pisa con el vecino.

## 11.1 RFC de la épica

Viven en `docs/rfcdemo/`, con numeración propia: este es un producto distinto del
que se copió el código. El índice está en `docs/rfcdemo/README.md`.

| RFC | Título | Lote |
|---|---|---|
| 01 | Base central y modelo de inquilino | A |
| 02 | Caso base de pruebas con inquilinos | A |
| 03 | Colas ancladas a la central | A |
| 04 | Plantilla versionada | B |
| 05 | Alta de inquilino | C |
| 06 | Resolución de inquilino por subdominio | D |
| 07 | Aislamiento de caché | E |
| 08 | Aislamiento de archivos | E |
| 09 | Expiración y borrado | F |
| 10 | Límites de abuso y plazo de vida | F |
| 11 | Registro público y entrega de acceso | G |
| 12 | Padrón del operador | transversal |

## 12. Matriz de riesgos

| Riesgo | Impacto | Mitigación |
|---|---|---|
| Se despliega en hosting compartido | El diseño no corre: ni crea bases, ni sostiene la cola | Resuelto: **VPS obligatorio** (8.11) |
| Un trabajo deja la conexión apuntando al inquilino anterior | Escritura en la base equivocada, sin error | C-4 con test explícito |
| Alguien mueve el caché a Redis y se pierde el aislamiento | Fuga entre inquilinos | Prefijo de clave desde el día uno (8.3) |
| Cantidad de bases: cada inquilino es una base completa | Límite operativo de Postgres y de disco | Expiración agresiva; medir cuántas soporta el servidor antes de abrir |
| 3.8 MB de polígonos por inquilino | Disco | Aceptado; se revisa si el volumen lo obliga |
| Una conexión olvidada contra la plantilla | Las altas fallan | La plantilla no está en ninguna configuración de la app |
| Postgres 16 en producción, 18 en desarrollo | Comportamiento distinto entre ambientes | Documentado; la restricción de plantilla aplica en las dos |

## 13. Archivos a crear

- `config/tenancy.php` — plantilla vigente, dominio base, plazo de vida.
- `database/migrations/central/` — migraciones de la base central, separadas.
- `app/Models/Tenant.php`
- `app/Tenancy/TenantResolver.php`, `app/Http/Middleware/ResolveTenant.php`
- `app/Jobs/ProvisionTenant.php`, `app/Jobs/DeleteTenant.php`
- `app/Console/Commands/BuildTenantTemplate.php`
- `app/Support/TenantMediaPathGenerator.php`
- `app/Providers/TenancyServiceProvider.php`

## 14. Definition of Done del diseño

- [x] Confirmado que el hosting emite certificado comodín. Hostinger lo hace
      **sólo en VPS**; los planes compartidos no. Decisión 8.1 sostenida sobre
      VPS, y 8.11 documenta que el VPS haría falta igual sin el certificado.
- [ ] Confirmada la versión de Postgres de producción y anotada en despliegue.
      Sabido: 16.14. Falta anotarlo en `docs/deployment/`.
- [ ] Confirmado en el VPS: rol con `CREATEDB` separado del rol de peticiones, y
      cuántas bases soporta la instancia (C-8).
- [ ] Definido el plazo de vida de un inquilino y el límite por origen (M-2 de la
      auditoría), derivados del techo de bases del punto anterior.
- [ ] Los nueve contratos tienen un test nombrado que los verifica.
- [x] Corregidos C-1, C-2 y C-3 de la auditoría: 8.6 (cerrojo con `finally` y
      espera acotada), 8.6.1 (el nombre de la base como superficie de inyección)
      y 8.12 (cómo se prueba, movido al lote A).
- [ ] Auditoría de diseño hecha con contexto fresco, no por quien lo escribió.
