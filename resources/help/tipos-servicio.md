# Manual de Tipos de servicio

Catálogo de servicios que se le pueden asociar a un **Lead** (por ejemplo, "Comercialización", "Renta
de lonas"), cada uno con su propio color de badge.

## ¿Para qué sirve?

Define qué tipos de servicio existen para clasificar los leads entrantes, en vez de escribir texto
libre que después es difícil de filtrar o reportar.

{{captura: tipos-servicio/listado | Catálogo de tipos de servicio con su color de badge}}

## Cómo se usa

1. Captura un **Código** interno (no editable después de creado) y una **Etiqueta** visible.
2. Elige un **Color de badge** — a diferencia de Tipos de proyecto, este campo es obligatorio aquí.
3. Define el **Orden** o arrastra las filas de la tabla para reordenar.
4. Usa el toggle **Activo** para sacarlo de circulación sin eliminarlo.

## Campos importantes

- **Código**: identificador interno usado por el sistema (por ejemplo, para decidir si mostrar el
  selector de Inmueble en un lead con servicio "comercialización") — no lo cambies sin coordinar con el
  equipo técnico.
- **Activo**: los tipos inactivos no aparecen en el selector **Tipo de servicio** al crear/editar un
  lead.
- **Solo owner/admin**: sección exclusiva de esos roles.

## Preguntas frecuentes

- **Agregué un tipo nuevo pero no aparece en el formulario de Lead** — confirma que quedó marcado como
  **Activo**; solo los tipos activos se listan ahí.
- **¿Puedo renombrar el código "comercializacion"?** — evitalo: varias partes del sistema (por ejemplo,
  el selector de Inmueble en Leads) dependen de ese código exacto; cambia solo la Etiqueta si necesitas
  ajustar el texto visible.
