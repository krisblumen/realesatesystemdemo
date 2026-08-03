# EPICA-DEMO Demo multi-inquilino

## Objetivo

Que una persona invitada pueda probar el sistema completo en un espacio propio,
cerrado y temporal, sin que sus pruebas toquen las de nadie más.

## Responsable

Owner del producto.

## Épica

EPICA-DEMO. Primera épica de este repositorio: no continúa la numeración del
proyecto del que se copió el código, porque este es un producto distinto.

## Dos fases

| | Fase 1 — **la que se implementa** | Fase 2 — diseñada, en pausa |
|---|---|---|
| Cómo se entra | Por invitación, con un comando | Registro público |
| Quién ve el sitio | Sólo el inquilino, con sesión | Cualquiera |
| Límites de abuso | La invitación es el límite | Por origen y por ventana |

La línea que separa las fases no es el tamaño del trabajo:

> **Lo estructural va ahora. Lo aditivo va cuando haga falta.**

El aislamiento es estructural: agregarlo después obliga a recorrer cada clave de
caché, cada ruta de medios y cada trabajo en cola que existan para entonces. Y
**con dos inquilinos ya se pisan** — no es una protección que dependa del
volumen. El registro público y los límites de abuso son aditivos: existen porque
cualquiera puede entrar.

## Contexto

El sistema nació para una inmobiliaria concreta y todo su modelo de datos asume
una sola empresa: un juego de zonas, un catálogo de proyectos, un padrón de
usuarios y —sobre todo— **un solo sitio**. Las seis páginas del CMS existen como
seis filas fijas en `frontend_pages`, identificadas por `key`.

El demo invierte ese supuesto. Deja de haber una empresa y pasa a haber tantas
como invitaciones se emitan, cada una convencida de que el sistema es suyo.

## Actores

- **Quien invita**: da de alta un inquilino desde la consola y entrega el acceso.
- **Inquilino**: el espacio aislado que se entrega. Vive el tiempo que dure la
  prueba y después desaparece.
- **Usuario demo**: la cuenta con la que la persona invitada entra a su
  inquilino. Tiene rol `owner` **dentro de su inquilino** y ninguna existencia
  fuera.
- **Operador**: quien mantiene el demo. Ve el padrón de inquilinos, no el
  contenido de ninguno.

## Alcance

- Aprovisionamiento de un inquilino aislado por invitación.
- Aislamiento total: datos, archivos, caché y sesión.
- Entorno cerrado: el sitio del inquilino exige su sesión.
- Expiración y borrado del inquilino vencido.
- Padrón central para el operador.

## Fuera de alcance

- Convertir un demo en cuenta de pago. Un inquilino demo nace para morir.
- Personalizar el catálogo geográfico por inquilino. Estados, municipios y
  códigos postales son datos de referencia compartidos.
- Facturación, planes, límites por plan.
- Des-marcar el producto. La copia todavía lleva la identidad visual y la
  documentación del proyecto original; es trabajo aparte y previo a mostrar
  esto a nadie de afuera.

## Flujo del proceso

1. Quien invita corre el comando con el correo de la persona.
2. Se crea la fila del inquilino, se copia la plantilla, se crea su usuario
   `owner` y se fija cuándo vence.
3. El comando imprime el acceso: dirección, usuario y contraseña.
4. Quien invita entrega esas credenciales por donde quiera.
5. Al vencer el plazo, el inquilino pasa a `expirado`; un proceso posterior
   revoca conexiones, corta las vivas, borra la base y borra los archivos.

Que el acceso salga por consola y no por correo no es un atajo: quita el correo
como punto de falla. Un mensaje que cae en spam es una persona que quería probar
el producto y no pudo, con un inquilino aprovisionado ocupando lugar.

## Estados del inquilino

| Estado | Significado | Transición |
|---|---|---|
| `aprovisionando` | El registro existe, la base todavía no | → `activo` o `fallido` |
| `activo` | En uso, dentro de su plazo | → `expirado` |
| `fallido` | El alta no terminó; no hay base que borrar | terminal |
| `expirado` | Venció el plazo; ya no se puede entrar | → `borrado` |
| `borrado` | Base y archivos eliminados; queda sólo el rastro | terminal |

El registro del inquilino sobrevive al borrado de su base a propósito: sirve para
medir el uso del demo.

## Seguridad

El riesgo central de esta épica **no es que alguien rompa el sistema, es que una
persona vea los datos de otra**. En un demo eso no es un error de software: es la
demostración en vivo de que el producto no aísla a sus clientes.

Por eso el aislamiento es estructural y no depende de que ninguna consulta esté
bien escrita. Cada inquilino vive en su propia base de datos. Una consulta cruda
mal hecha, un scope olvidado o un join a mano no pueden alcanzar a otro inquilino
porque no están en la misma base.

Eso deja tres superficies que la base de datos **no** cubre, y que el diseño
técnico cierra una por una:

- **Caché**: las claves actuales no llevan inquilino.
- **Archivos**: la librería de medios numera desde 1 en cada base, así que dos
  inquilinos generan la misma ruta en disco.
- **Sesión**: se resolvería antes de saber quién es el inquilino.

Y una cuarta superficie que no es de aislamiento sino de convivencia: **el demo
comparte la instancia de Postgres con la producción de New Hauz y con el stack de
correo.** Un demo descontrolado puede dejarlos sin conexiones. Se cierra con
topes por rol y por base.

## Permisos y roles

El sistema de roles no cambia. `owner`, `admin` y `agente` siguen significando
exactamente lo mismo — **adentro de un inquilino**.

Queda descartada explícitamente la idea de un rol por encima de `owner`. Los
roles contestan *qué puede hacer* un usuario; el aislamiento contesta *qué datos
existen para él*. Son ejes perpendiculares, y el código lo demuestra:

```php
// app/Models/Property.php — scopeVisibleTo
if ($user->hasAnyRole(['owner', 'admin'])) {
    return $query;   // ve todo
}
```

Un usuario demo necesita poderes de `owner` para probar el sistema completo. Con
un enfoque de roles, eso le entrega la base entera.

## Relación con módulos existentes

| Módulo | Impacto |
|---|---|
| CMS del frontend | Alto. Seis filas fijas por `key`, caché sin inquilino. |
| Inmuebles, leads, clientes, contratos | Bajo. Ya viven por debajo del aislamiento. |
| Geografía (estados, municipios, CP) | Compartida. Se copia con la plantilla. |
| Media Library | Alto. Colisión de rutas en disco. |
| Usuarios y roles | Medio. El padrón pasa a ser por inquilino. |
| Colas | Alto. La cola no puede vivir en la base del inquilino. |
| Firma de contratos | Su ruta pública se mantiene abierta: el token es el control. |

## Decisiones tomadas

- **D-1** — Aislamiento por base de datos, no por columna `tenant_id` ni por rol.
- **D-2** — El alta se hace copiando una plantilla ya migrada, no corriendo
  migraciones. Medido: 0.2 s.
- **D-3** — La copia se serializa. Postgres rechaza copiar una plantilla que
  tenga cualquier conexión encima.
- **D-4** — La aplicación nunca abre una conexión contra la plantilla.
- **D-5** — El registro del inquilino y la cola viven en una base central.
- **D-6** — El inquilino se resuelve por subdominio. El entorno cerrado quita una
  razón —la URL compartible— y deja la principal: el `Host` llega antes de que
  corra una línea nuestra, así que ningún error de la aplicación puede confundir
  de quién es una petición.
- **D-7** — El demo arranca por invitación y cerrado. Fase 2 queda diseñada.
- **D-8** — El rol del demo lleva tope de conexiones, para no poder desabastecer
  a la producción con la que comparte instancia.

## Medido en el VPS

| Dato | Valor | Consecuencia |
|---|---|---|
| Disco libre | 55 GB de 96 GB | ~3.000 inquilinos a 18 MB. No es el límite |
| Peso de un inquilino nuevo | 18 MB | |
| `max_connections` | 100, **compartidas** | Es el recurso escaso, y no por el demo |
| Vecinos en la instancia | `inmo_db`, `museo_textil`, correo | El riesgo real de la épica |
| Postgres en producción | 16.14 | La restricción de plantilla aplica |

## Casos QA

- Dos inquilinos publican su página de inicio con contenidos distintos; cada uno
  ve el suyo, en la misma petición y con caché caliente.
- Dos inquilinos suben una imagen como primer archivo; ninguno ve la del otro.
- Un inquilino no encuentra al usuario de otro en ningún selector.
- Sin sesión, ninguna ruta del inquilino devuelve contenido.
- Un enlace de firma de contrato funciona sin sesión, y el de un inquilino no
  sirve en el subdominio de otro.
- Un inquilino expirado no permite entrar y su base deja de existir.
- Borrar un inquilino con una sesión abierta no falla.

## Definition of Done

- Existe un test que publica contenido distinto en dos inquilinos y verifica que
  ninguno ve el del otro, con caché habilitado.
- El borrado de un inquilino no deja bases, archivos ni filas huérfanas.
- El operador tiene dónde ver el padrón sin poder abrir el contenido de nadie.
- El rol del demo tiene tope de conexiones antes del primer inquilino.
- La documentación de despliegue dice qué versión de Postgres se requiere y por
  qué.

## RFC

En `docs/rfcdemo/`, con numeración propia. Índice en su `README.md`.
