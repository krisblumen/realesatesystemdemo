# EPICA-DEMO Demo público multi-inquilino

## Objetivo

Que cualquier visitante pueda registrarse, recibir credenciales temporales y
probar el sistema completo —incluido el administrador de contenidos— sin que sus
pruebas toquen las de ningún otro visitante.

## Responsable

Owner del producto.

## Épica

EPICA-DEMO. Primera épica de este repositorio: no continúa la numeración del
proyecto del que se copió el código, porque este es un producto distinto.

## Contexto

El sistema nació para una inmobiliaria concreta y todo su modelo de datos asume
una sola empresa: un juego de zonas, un catálogo de proyectos, un padrón de
usuarios y —sobre todo— **un solo sitio público**. Las seis páginas del CMS
existen como seis filas fijas en `frontend_pages`, identificadas por `key`.

Un demo público invierte ese supuesto. Deja de haber una empresa y pasa a haber
tantas como visitantes se registren, cada una convencida de que el sistema es
suyo.

## Actores

- **Visitante**: llega al sitio del demo y se registra. No tiene cuenta previa.
- **Inquilino**: el espacio aislado que se le entrega al visitante. Vive el
  tiempo que dure la prueba y después desaparece.
- **Usuario demo**: la cuenta con la que el visitante entra a su inquilino.
  Tiene rol `owner` **dentro de su inquilino** y ninguna existencia fuera.
- **Operador**: quien mantiene el demo. Ve el padrón de inquilinos, no el
  contenido de ninguno.

## Alcance

- Registro público con entrega de credenciales temporales.
- Aprovisionamiento de un inquilino aislado por registro.
- Aislamiento total entre inquilinos: datos, archivos, caché y sesión.
- Expiración automática y borrado del inquilino vencido.
- Padrón central de inquilinos para el operador.
- Límites de abuso: cuántos inquilinos por origen y por unidad de tiempo.

## Fuera de alcance

- Convertir un demo en cuenta de pago. Un inquilino demo nace para morir; migrar
  su contenido a una cuenta real es otra épica.
- Personalizar el catálogo geográfico por inquilino. Estados, municipios y
  códigos postales son datos de referencia compartidos.
- Facturación, planes, límites por plan.
- Des-marcar el producto. La copia todavía lleva la identidad visual y la
  documentación del proyecto original; es trabajo aparte y previo a mostrar
  esto en público.

## Flujo del proceso

1. El visitante llega a la página de registro y deja un correo.
2. El sistema crea un registro de inquilino en estado `aprovisionando` y encola
   el trabajo de alta. La respuesta es inmediata: no espera a Postgres.
3. El trabajo de alta copia la plantilla, crea el usuario `owner` del inquilino
   con contraseña generada, y pasa el inquilino a `activo`.
4. El visitante recibe su acceso y entra.
5. Al vencer el plazo, el inquilino pasa a `expirado`; un proceso posterior
   corta las conexiones vivas, borra la base y borra los archivos.

## Estados del inquilino

| Estado | Significado | Transición |
|---|---|---|
| `aprovisionando` | El registro existe, la base todavía no | → `activo` o `fallido` |
| `activo` | En uso, dentro de su plazo | → `expirado` |
| `fallido` | El alta no terminó; no hay base que borrar | terminal, se recicla |
| `expirado` | Venció el plazo; ya no se puede entrar | → `borrado` |
| `borrado` | Base y archivos eliminados; queda sólo el rastro | terminal |

El registro del inquilino sobrevive al borrado de su base a propósito: sirve
para medir el uso del demo y para impedir que el mismo origen recicle inquilinos
sin límite.

## Seguridad

El riesgo central de esta épica **no es que alguien rompa el sistema, es que un
visitante vea los datos de otro**. En un demo público eso no es un error de
software: es la demostración en vivo de que el producto no aísla a sus clientes.

Por eso el aislamiento es estructural y no depende de que ninguna consulta esté
bien escrita. Cada inquilino vive en su propia base de datos. Una consulta
cruda mal hecha, un scope olvidado o un join a mano no pueden alcanzar a otro
inquilino porque no están en la misma base.

Eso deja tres superficies que la base de datos **no** cubre, y que el diseño
técnico tiene que cerrar una por una:

- **Caché**: las claves actuales no llevan inquilino.
- **Archivos**: la librería de medios numera desde 1 en cada base, así que dos
  inquilinos generan la misma ruta en disco.
- **Sesión**: se resuelve antes de saber quién es el inquilino.

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

## Decisiones tomadas

- **D-1** — Aislamiento por base de datos, no por columna `tenant_id` ni por
  rol. En un demo público la falla del enfoque por columna es que un prospecto
  vea los datos de otro, y de eso no se vuelve.
- **D-2** — El alta se hace copiando una plantilla ya migrada
  (`CREATE DATABASE ... TEMPLATE`), no corriendo migraciones. Medido: 0.2 s
  contra los segundos que tarda la suite de migraciones.
- **D-3** — El alta va en cola y serializada. Postgres rechaza copiar una
  plantilla que tenga cualquier conexión encima; dos registros simultáneos
  hacen fallar al segundo.
- **D-4** — La aplicación nunca abre una conexión contra la plantilla.
- **D-5** — El registro del inquilino y la cola viven en una base central, que
  es la única que la aplicación conoce antes de resolver quién es el visitante.

## Puntos que quedan abiertos

Se cierran en el documento de épica, no acá.

- Cómo se resuelve el inquilino en cada petición.
- Dónde vive la sesión y en qué orden corre respecto de la resolución.
- Cómo se aíslan las claves de caché.
- Cómo se separan los archivos en disco.
- Cuánto dura un inquilino y qué límite hay por origen.

## Casos QA

- Dos inquilinos publican su página de inicio con contenidos distintos; cada uno
  ve el suyo, en la misma petición y con caché caliente.
- Dos inquilinos suben una imagen como primer archivo; ninguno ve la del otro.
- Un inquilino no encuentra al usuario de otro en ningún selector.
- Dos registros simultáneos producen dos inquilinos, no un error.
- Un inquilino expirado no permite entrar y su base deja de existir.
- Borrar un inquilino con una sesión abierta no falla.

## Definition of Done

- Existe un test que publica contenido distinto en dos inquilinos y verifica que
  ninguno ve el del otro, con caché habilitado.
- Existe un test que prueba el alta concurrente.
- El borrado de un inquilino no deja bases, archivos ni filas huérfanas.
- El operador tiene dónde ver el padrón sin poder abrir el contenido de nadie.
- La documentación de despliegue dice qué versión de Postgres se requiere y por
  qué.
