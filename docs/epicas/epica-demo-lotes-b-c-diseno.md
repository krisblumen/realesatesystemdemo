# Épica DEMO — Lotes B y C, diseño de detalle

> Diseño, sin código. Cubre RFC-04 (plantilla), RFC-05 (alta) y RFC-13
> (invitación).
>
> Depende del lote A: `docs/epicas/epica-demo-lote-a-diseno.md`.

## Medido, no supuesto

Probado en una base descartable creada desde la plantilla real.

| Dato | Valor |
|---|---|
| Plantilla migrada, sin sembrar | 18 MB |
| Plantilla migrada y sembrada con `DatabaseSeeder` | 22 MB |
| Copiar la plantilla | 0.2 s |
| Contenido tras sembrar | 32 estados, 3.257 códigos postales, 16 características, 6 páginas del CMS, 5 roles, 2 zonas |

---

# Lote B — La plantilla

## 1. `DatabaseSeeder` no sirve para la plantilla

Es el hallazgo que ordena este lote. `DatabaseSeeder` termina llamando a
`OwnerSeeder` y `AgentSeeder`, que **crean usuarios**:

```
Agente creado: agente1@newhauz.test | password: password
```

Una plantilla con usuarios adentro significa que cada inquilino nace con las
mismas cuentas y las mismas contraseñas conocidas, y con un `owner` que no es el
de nadie. El `owner` del inquilino se crea en el alta, con su propia contraseña
generada (RFC-05).

Hace falta un sembrador propio de la plantilla, con una lista explícita:

| Seeder | ¿Va? | Por qué |
|---|---|---|
| `PermissionSeeder` | Sí | Los roles tienen que existir antes de crear al `owner` |
| `ServiceTypeSeeder` | Sí | Catálogo |
| `ProjectTypeSeeder` | Sí | Catálogo |
| `FeatureSeeder` | Sí | Catálogo |
| `FrontendServiceSeeder` | Sí | El CMS |
| `FrontendPageSeeder` | Sí | El CMS |
| `GeoCatalogSeeder` | Sí | Geografía compartida |
| `PostalCodeAreaSeeder` | Sí | Polígonos |
| `ZoneSeeder` | Sí | **Sin zonas no se puede cargar un inmueble** |
| `OwnerSeeder` | **No** | Crea un usuario con correo fijo |
| `AgentSeeder` | **No** | Ídem, con contraseña conocida |
| `DemoDataSeeder` | Sí, **una vez arreglado** | Ver abajo |

La lista se escribe **enumerada, no por descarte**. Un sembrador nuevo que
alguien agregue a `DatabaseSeeder` mañana no debe entrar solo en la plantilla.

## 2. `DemoDataSeeder` está roto, y eso decide si el demo sirve

Verificado corriéndolo:

```
InvalidArgumentException
Geometry type must be an instance of ...\MultiPolygon, ...\Polygon given
at vendor/clickbar/laravel-magellan/src/Cast/GeometryCast.php:87
```

Muere en `DemoDataSeeder.php:38`, creando zonas. Queda a medias: alcanza a crear
5 usuarios y después nada. **Cero inmuebles, cero clientes.**

La causa es que `Zone.polygon` se castea a `MultiPolygon` y el sembrador todavía
le pasa un `Polygon`. Quedó viejo cuando cambió el modelo geográfico. El arreglo
es envolver los polígonos: tres líneas.

**Por qué importa más de lo que parece.** Un inquilino recién creado sin
inmuebles, sin clientes y sin leads es un panel vacío. La persona invitada entra
a ver «cómo funciona el sistema» y no ve funcionar nada: tiene que cargar datos
para recién entonces empezar a mirar. Y no lo va a hacer.

**El demo se juega en el primer minuto**, y el primer minuto lo define el
contenido que viene en la plantilla. Arreglar este sembrador no es una tarea de
limpieza: es un requisito del lote B.

Ventaja de que el contenido esté en la plantilla y no se genere por inquilino: se
copia, no se recalcula. Cuesta 0 s por alta, y todos los inquilinos ven lo mismo
—lo que hace posible dar soporte hablando del mismo inmueble.

## 3. El comando de construcción

Construye una plantilla nueva; **no** cambia cuál está vigente. Son dos actos
separados a propósito: se puede construir la siguiente mientras la actual sigue
sirviendo altas.

Pasos:

1. Crear `demo_template_v{N+1}` vacía.
2. Migrar, apuntando a ella.
3. Sembrar con el sembrador de plantilla.
4. **Verificar** antes de darla por buena: que existan las 6 páginas del CMS, que
   PostGIS responda, que haya códigos postales y zonas, y que **la tabla de
   usuarios esté vacía**.
5. Informar el resultado, incluido el peso.

El paso 4 no es ceremonia. Una plantilla mal sembrada no falla al construirse:
falla más tarde, en cada inquilino que nazca de ella, y en un lugar que no
señala a la plantilla.

## 4. El cambio de versión

Un valor en `config/tenancy.php`. Cambiarlo es la operación completa; volver
atrás es cambiarlo de nuevo.

Una plantilla retirada no se borra mientras haya un alta en curso que la nombre.

## 5. Que nadie se conecte a la plantilla

Es la regla de la que dependen todas las altas y la única sin mecanismo propio.
Verificación al arrancar: **ninguna conexión configurada apunta a una base con el
prefijo de plantilla**. Falla ruidosamente.

El síntoma que evita —`source database is being accessed by other users`—
aparecería en el alta, lejos de quien agregó la conexión.

---

# Lote C — El alta y la invitación

## 6. El `slug`

Se genera del lado del servidor. Formato `^[a-z][a-z0-9]{7,31}$`.

Además del formato, **el alfabeto excluye caracteres confundibles** — sin `l`,
`1`, `0`, `o`. El slug es un subdominio que alguien va a leer de una pantalla y
tipear en otra; una `l` que resulta ser un `1` es una consulta de soporte
garantizada.

Se genera, se comprueba que no exista, y se reintenta un número acotado de veces.

## 7. La validación, pegada al uso

`CREATE DATABASE` es DDL y Postgres no acepta parámetros para identificadores. El
nombre se interpola sí o sí.

La validación contra el formato corre **inmediatamente antes de componer la
sentencia**, no sólo al crear la fila. No es desconfianza del código de arriba:
es que dentro de seis meses va a haber un segundo camino hasta acá, y ese camino
no va a pasar por la validación de arriba.

El nombre se compone con prefijo fijo: `demo_t_{slug}`. Entre 14 y 38 bytes, bajo
el límite de 63 de Postgres, imposible que coincida con palabra reservada.

## 8. El cerrojo

Clave fija y documentada en `config/tenancy.php`. No derivada del slug: lo que se
serializa es **el acceso a la plantilla**, que es una sola, no el alta de un
inquilino en particular.

- Se toma con `pg_try_advisory_lock` en un bucle acotado. Nunca con la variante
  que espera sin límite: un cerrojo trabado tiene que dar un mensaje, no
  lentitud sin causa.
- Se suelta en un `finally`.
- **No hay barrido de huérfanos**: si el proceso muere, Postgres suelta solo.

Se toma sobre la conexión `central`, no sobre `maintenance`: `maintenance` se
abre y se cierra alrededor del DDL, y un cerrojo de sesión se iría con ella.

## 9. Los pasos del alta, y qué estado deja cada falla

| Paso | Si falla |
|---|---|
| 1. Validar slug y componer nombre | Nada creado. No se escribe fila |
| 2. Crear fila en `aprovisionando` | Nada creado |
| 3. Tomar cerrojo | `fallido`, `motivo_falla` explica que no se pudo serializar |
| 4. `CREATE DATABASE ... TEMPLATE` | `fallido`. No hay base que limpiar |
| 5. Soltar cerrojo (`finally`) | — |
| 6. Crear el `owner` del inquilino | `fallido` **y hay base a medias**: la limpieza la borra |
| 7. Fijar `expira_en` y pasar a `activo` | `fallido`, con base a medias |
| 8. Imprimir el acceso | Ya está `activo`; el acceso se puede reimprimir |

El paso 6 es el primero que deja basura. De ahí que el estado `fallido` no sea
terminal para la limpieza: hay que mirar si la base existe.

## 10. El `owner` del inquilino

- Rol `owner`, dentro de su inquilino.
- Contraseña generada, nunca elegida.
- **Se crea con la conexión apuntada a la base del inquilino**, y esa conexión se
  restaura al terminar — el comando corre en un proceso que puede seguir haciendo
  cosas después.

## 11. El comando de invitación (RFC-13)

```
php artisan demo:invitar {email} [--dias=]
```

1. Rechaza si ya hay un inquilino `activo` con ese correo.
2. Ejecuta el alta.
3. Imprime dirección, usuario y contraseña, **una sola vez**, con la advertencia.

`--dias` cae a un valor por defecto de configuración. Se fija en el alta, no se
calcula al leer: un inquilino sin vencimiento es un inquilino eterno.

El acceso sale por consola. No hay correo, y por lo tanto no hay un correo que
se pierda.

## 12. Tests que cierran los lotes

| Test | Qué protege |
|---|---|
| La plantilla construida tiene **cero usuarios** | Lo que rompe `DatabaseSeeder` |
| La plantilla tiene las 6 páginas, PostGIS, códigos postales y zonas | Que sirva para algo |
| Un inquilino recién creado tiene inmuebles y clientes | Que el demo se vea vivo al entrar |
| Un slug fuera de formato no llega a la sentencia | La superficie de inyección |
| El alfabeto del slug no contiene caracteres confundibles | Soporte |
| Dos altas concurrentes producen dos inquilinos | El cerrojo |
| Un alta que falla tras tomar el cerrojo no bloquea la siguiente | El `finally` |
| Un alta que falla en el paso 6 deja la base borrada | La limpieza |
| Invitar dos veces el mismo correo activo se rechaza | RFC-13 |
| El `owner` creado puede entrar al panel de su inquilino | De punta a punta |

## 13. Dependencias fuera de estos lotes

- **Arreglar `DemoDataSeeder`** (`MultiPolygon`). Es requisito del lote B, no
  trabajo aparte.
- La resolución por subdominio (lote D) no hace falta acá: el alta apunta la
  conexión de forma directa.
- El borrado de la base a medias usa la misma pieza que el lote F; en el lote C
  alcanza con la versión mínima.
