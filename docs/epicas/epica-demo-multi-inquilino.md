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
un VPS con certbot y validación DNS-01 es trabajo de una vez. **Si el hosting no
puede emitir certificados comodín, esta decisión se cae y hay que volver al
prefijo de ruta.** Es lo primero que hay que confirmar.

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

Serializada con un **cerrojo de aviso de Postgres** (`pg_advisory_lock`) sobre la
conexión central, no con un cerrojo de caché con vencimiento. Si el worker muere
a mitad de la copia, el cerrojo de Postgres se suelta solo al cerrarse la sesión;
uno con TTL puede vencer mientras la copia sigue corriendo y dejar entrar a un
segundo.

Pasos del trabajo:

1. Tomar el cerrojo.
2. `CREATE DATABASE demo_t_{slug} TEMPLATE demo_template_vN`.
3. Soltar el cerrojo. **La copia es lo único serializado**; lo que sigue no
   toca la plantilla y puede correr en paralelo.
4. Conectarse al inquilino y crear su usuario `owner` con contraseña generada.
5. Marcar `activo` y notificar al visitante.

Si algo falla: estado `fallido`, y una tarea de limpieza borra la base a medias
si llegó a crearse.

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
| **A** | Base central: conexión, tabla `tenants`, cola fijada a central | — |
| **B** | Plantilla versionada y comando para construirla | A |
| **C** | Alta en cola, con cerrojo, y creación del usuario del inquilino | A, B |
| **D** | Resolución por subdominio y orden de middleware | A |
| **E** | Prefijo de inquilino en caché y en rutas de media | D |
| **F** | Expiración, borrado y límites de abuso | C, D |
| **G** | Registro público y entrega de credenciales | C, D, E |

El registro —que es por donde uno querría empezar— es el último. Sin A a E, un
visitante registrado entra a un sistema que se pisa con el vecino.

## 12. Matriz de riesgos

| Riesgo | Impacto | Mitigación |
|---|---|---|
| El hosting no emite certificados comodín | La decisión 8.1 se cae | Confirmarlo **antes** del lote D |
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

- [ ] Confirmado que el hosting emite certificado comodín, o tomada la decisión
      alternativa de prefijo de ruta.
- [ ] Confirmada la versión de Postgres de producción y anotada en despliegue.
- [ ] Los siete contratos tienen un test nombrado que los verifica.
- [ ] Auditoría de diseño hecha con contexto fresco, no por quien lo escribió.
