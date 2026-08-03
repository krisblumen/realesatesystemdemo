# Manual de Inmuebles

La sección Inmuebles es el catálogo de propiedades que la inmobiliaria comercializa: cada registro es
un inmueble en venta o renta, con su ficha completa y su estado de publicación.

## ¿Para qué sirve?

Aquí se da de alta y se mantiene actualizado cada inmueble: datos generales, dirección, propietario,
precio, características, galería de fotos y, cuando corresponde, su publicación en el sitio público.

El listado agrupa los inmuebles en pestañas según su estado (Publicados, Pausados, Borradores,
Vendidos/Rentados), y desde cada fila accedes a las acciones disponibles.

{{captura: inmuebles/listado | Listado de inmuebles con las pestañas por estado}}

### Las pestañas de estado

Arriba del listado hay una pestaña por estado, con el número de inmuebles que tiene cada una. Sirven
para filtrar rápido: **Publicados** son los que se ven en el sitio público, **Pausados** los que se
retiraron temporalmente, **Borradores** los que todavía no se publican, y **Vendidos / Rentados**
los que ya cerraron operación.

{{captura: inmuebles/tabs | Pestañas Publicados, Pausados, Borradores y Vendidos o Rentados}}

### Los botones de cada fila

Cada inmueble del listado trae sus propias acciones. **Solo aparecen las válidas para su estado
actual**: por ejemplo, "Pausar" únicamente en un inmueble publicado, o "Publicar" en un borrador que
ya tenga los datos obligatorios.

{{captura: inmuebles/acciones | Botones de acción de una fila del listado}}

### De borrador a publicado

**Todo inmueble que captures nace como Borrador** y queda guardado en esa pestaña. Mientras esté ahí
**no se ve en el sitio público**, aunque tenga todos sus datos y sus fotos cargadas.

Para que salga a la web tienes que usar la acción **Publicar** en su fila. Solo a partir de ese
momento el inmueble aparece en el sitio público: publicar es un paso manual y explícito, no algo que
ocurra solo al guardar.

{{captura: inmuebles/borrador | Fila en la pestaña Borradores con la acción Publicar disponible}}

Si el botón te devuelve un error, es porque falta alguno de los datos obligatorios para publicar
(foto de portada, propietario, comisión y colonia). El sistema te indica cuál falta.

## Cómo se usa

1. En **Datos del inmueble** captura título, tipo de operación (Venta/Renta), tipo de inmueble
   (Casa, Departamento, Terreno, Local, Oficina, Bodega) y descripción.

   {{captura: inmuebles/form-datos | Sección Datos del inmueble del formulario}}

2. En **Dirección**, elige primero Estado y Municipio para filtrar las Zonas disponibles; al elegir la
   Zona se completa el Código Postal automáticamente (y viceversa: si escribes el CP, se autocompletan
   Estado, Municipio y Zona). La Colonia se filtra por ese CP. Calle y número exterior/interior solo los
   ve el agente asignado, owner y admin — el resto del equipo no accede a la dirección exacta.

   {{captura: inmuebles/form-direccion | Sección Dirección del formulario}}

3. En **Propietario y comisión**, asigna el propietario (puedes crear uno nuevo sin salir del
   formulario) y la comisión pactada.

   {{captura: inmuebles/form-propietario | Sección Propietario y comisión del formulario}}

4. Captura precio, dimensiones, características y al menos la imagen principal en **Galería**.
5. Con los campos obligatorios completos (foto, propietario, comisión y colonia), usa la acción
   **Publicar** en la tabla para que el inmueble aparezca en el sitio público.

## Campos importantes

- **Destacado / Oportunidad**: dos interruptores exclusivos entre sí (activar uno desactiva el otro),
  visibles solo para owner/admin. Controlan si el inmueble aparece en "Destacados" u "Oportunidades de
  inversión" del sitio público.
- **Agente responsable**: determina quién ve la dirección exacta y quién gestiona el inmueble día a
  día. Si entras al sistema **como agente**, este campo ni siquiera se te muestra: el inmueble se
  asigna **automáticamente a tu usuario**, porque el sistema presume que quien lo captura es su
  opcionador. Por eso es importante que **no compartas tu usuario con ningún otro agente** — todo
  inmueble capturado desde tu sesión queda registrado a tu nombre, y lo mismo ocurre al editarlo.
  Solo owner/admin pueden elegir o cambiar el agente responsable de un inmueble.
- **Estado**: Borrador → Publicado → Pausado/Vendido/Rentado, con reglas de transición (por ejemplo, no
  puedes marcar "Vendido" un inmueble en Renta). Las acciones de la tabla solo muestran las transiciones
  válidas para el estado actual.
- **Regenerar slug**: reconstruye la URL pública del inmueble; úsalo si cambiaste el título y la URL
  quedó desactualizada.

## Preguntas frecuentes

- **No puedo publicar un inmueble** — revisa que tenga foto de portada, propietario, comisión y colonia
  cargados; el sistema te muestra el motivo exacto si falta alguno al intentar publicar.
- **¿Por qué no veo la calle de algunos inmuebles?** — la dirección exacta es visible solo para el
  agente asignado, owner y admin; el resto del equipo ve la zona y colonia, no la calle ni el número.
- **Un agente ya no aparece en "Agente responsable"** — el selector solo lista agentes con rol `agente`
  y estado activo; si fue suspendido, no aparecerá hasta reactivarse.
