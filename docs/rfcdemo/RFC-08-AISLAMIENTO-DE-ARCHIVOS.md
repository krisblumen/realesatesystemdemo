# RFC-08 Aislamiento de archivos

## Objetivo

Que la imagen de un inquilino no sobrescriba la de otro.

## Épica

EPICA-DEMO. Lote E. Depende de RFC-06.

Cierra el contrato C-3 del diseño.

## Responsable

Backend.

## El problema

La librería de medios guarda cada archivo en una ruta derivada del identificador
de su fila. Con una base por inquilino, **los identificadores arrancan en 1 en
cada base**.

Dos inquilinos suben su primera imagen. Los dos escriben en `1/`. El disco es uno
solo. Se pisan.

La base de datos no protege de esto por la misma razón que no protege el caché:
el disco es otro almacén.

## Alcance

- **`path_generator`** propio, con el inquilino adelante. Es esa pieza y no
  `url_generator`: la que decide dónde se ESCRIBE el archivo es `path_generator`
  (`config/media-library.php:144`). Cambiar sólo `url_generator` haría que las
  URL se vean distintas y el disco colisione igual.
- Borrado de los archivos del inquilino junto con su base.

## La ruta

```
tenants/{slug}/{media_id}/{nombre}
```

El slug ya viene validado contra formato cerrado (RFC-05), así que no puede
contener separadores de directorio ni escapar hacia arriba. Aun así el generador
lo vuelve a validar: es la misma lógica que en RFC-05, la validación va pegada al
uso.

En el host central no hay inquilino, y no se suben archivos.

## Borrado

Cuando se borra un inquilino (RFC-09) se borra `tenants/{slug}/` completo.

Media huérfana en disco es una fuga con retardo: el inquilino ya no existe, la
fila ya no está, y los archivos siguen ahí servidos por una ruta adivinable.

El orden importa y está en RFC-09: primero la base, después los archivos. Si se
borran los archivos primero y el borrado de la base falla, queda un inquilino
vivo con las imágenes rotas.

## Reglas

1. Ninguna ruta de medios se compone sin el slug.
2. El generador valida el slug antes de usarlo, aunque ya venga validado.
3. **Este RFC resuelve colisión, no confidencialidad.** El prefijo impide que
   dos inquilinos escriban en la misma ruta. NO impide que alguien lea la ruta
   de otro: la media vive en el disco `public` y el servidor web la sirve sin
   pasar por Laravel. Ese límite está aceptado por escrito en RFC-14; no lo
   cubre esta pieza y no hay que suponer que sí.

## Definition of Done

- Un test sube la primera imagen en dos inquilinos y verifica que hay dos
  archivos distintos y cada uno ve el suyo.
- Un test verifica que borrar un inquilino no deja archivos suyos en disco.
- Un test verifica que un slug malformado no llega a componer una ruta.
