# RFC-04 Plantilla versionada

## Objetivo

Tener una base ya migrada y sembrada, lista para copiar, que se pueda actualizar
sin detener las altas.

## Épica

EPICA-DEMO. Lote B. Depende de RFC-01.

Cierra el contrato C-6.

## Responsable

Backend.

## Alcance

- Comando que construye una plantilla nueva: la crea, la migra y la siembra.
- Un valor de configuración que dice cuál es la plantilla vigente.
- Comando que borra plantillas retiradas.

## La regla que ordena todo esto

**La plantilla no se migra en su lugar. Se construye una nueva y se cambia cuál
se usa.**

1. Crear `demo_template_v{N+1}` vacía, migrarla y sembrarla.
2. Cambiar en configuración cuál es la vigente.
3. Borrar la anterior cuando ya no haya altas en vuelo.

Migrar la plantilla viva sería una carrera contra las altas: Postgres rechaza
copiar una plantilla que tenga cualquier conexión encima, y una migración es una
conexión. Con versiones nunca hay carrera, y volver atrás es cambiar un valor.

## Qué contiene la plantilla

El esquema completo del inquilino, más el catálogo compartido: estados,
municipios, códigos postales, polígonos, tipos de servicio, y las seis páginas
del CMS ya sembradas.

**No contiene** las migraciones de la central (RFC-01), ni usuarios, ni datos de
nadie.

## Nadie se conecta a la plantilla

Es la regla de la que depende que las altas funcionen, y la única sin mecanismo
que la haga cumplir si no se agrega uno. Alcanza con que alguien sume la
plantilla a una configuración de diagnóstico para romper el alta, y el síntoma
aparecerá lejos de la causa.

Se agrega una verificación automática: **ninguna conexión configurada de la
aplicación apunta a una plantilla.** Corre al arrancar y falla ruidosamente.

## Los inquilinos existentes no reciben la migración por acá

Cambiar la plantilla afecta sólo a los inquilinos que nazcan después. Si una
migración tiene que alcanzar a los que ya existen, es un recorrido explícito
inquilino por inquilino, con su propio comando y su propio informe de qué se
migró y qué falló. No es parte de este RFC.

Para un demo con inquilinos de vida corta, lo normal es no necesitarlo: los
viejos expiran y los nuevos nacen con el esquema nuevo.

## Reglas

1. La versión vigente es un valor de configuración, no un nombre calculado.
2. Cada inquilino guarda con qué versión nació (`template_version`, RFC-01).
3. Una plantilla no se borra mientras haya altas encoladas que la nombren.

## Definition of Done

- El comando construye una plantilla nueva sin tocar la vigente.
- Un test verifica que la copia trae las seis páginas del CMS y PostGIS
  operativo.
- La verificación de arranque falla si alguna conexión apunta a una plantilla.
- Cambiar la versión vigente no requiere reiniciar nada más que la configuración.
