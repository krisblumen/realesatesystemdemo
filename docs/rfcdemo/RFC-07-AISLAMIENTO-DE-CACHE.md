# RFC-07 Aislamiento de caché

## Objetivo

Que la página publicada de un inquilino no se le sirva jamás a otro.

## Épica

EPICA-DEMO. Lote E. Depende de RFC-06.

Cierra el contrato C-2 del diseño.

## Responsable

Backend.

## El problema, con evidencia

Las claves de caché del frontend **no llevan inquilino**:

```php
// app/Services/Frontend/FrontendPageContentService.php:46
sprintf('frontend:g%d:page:%s:v%d', $this->generation->current(), $key, self::SHAPE);
// → "frontend:g3:page:home:v2"
```

Lo mismo en `FrontendSettingsService`, `FrontendServicesService`,
`FrontendNavigationService` y `FrontendThemeService`.

Aislar la base de datos **no protege de esto**. El caché es otro almacén.

## Alcance

- Prefijo de inquilino en toda clave de caché.
- Los cinco servicios de `app/Services/Frontend/`.
- El contador de generación de caché.

## La decisión, y por qué es redundante a propósito

Hoy el caché usa el driver de base de datos, así que la tabla vive en la base del
inquilino y el aislamiento sale gratis del cambio de conexión (RFC-06).

**Aun así, toda clave lleva el slug**: `t:{slug}:frontend:g3:page:home:v2`.

El día que alguien mueva el caché a Redis por rendimiento —una decisión razonable
y probable— el aislamiento por base desaparece de golpe, y las claves son lo
único que queda en pie. Sin prefijo, esa migración sirve la home de un inquilino
a otro y nadie lo nota hasta que un prospecto lo ve.

Es una redundancia barata contra un cambio de infraestructura previsible.

## Cómo se aplica

El prefijo no se escribe a mano en cada servicio. Se compone en un único lugar
por el que pasan todas las claves del frontend. Cinco servicios que arman su
clave por su cuenta son cinco oportunidades de olvidarse.

El contador de generación (`frontend_cache_generation`) vive en la base del
inquilino y viaja con la plantilla. Cada inquilino invalida su caché sin tocar el
de nadie.

## Reglas

1. Ninguna clave de caché del frontend se arma sin pasar por el punto único.
2. En el host central no hay prefijo de inquilino, porque no hay inquilino.
3. Cambiar el driver de caché no debe requerir revisar este RFC. Ese es
   exactamente el punto.

## Definition of Done

- Un test publica una home distinta en dos inquilinos, calienta el caché de uno y
  verifica que el otro sigue viendo la suya. **Este es el test que justifica la
  épica entera.**
- Un test verifica que ninguna clave del frontend sale sin prefijo.
- El test de aislamiento pasa también con el caché apuntado a un almacén
  compartido — que es la prueba de que el prefijo hace el trabajo, y no la base.
