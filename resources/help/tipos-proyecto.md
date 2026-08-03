# Manual de Tipos de proyecto

Catálogo de tipos que se pueden asignar a un **Proyecto** (por ejemplo, "Residencial", "Mixto",
"Industrial"), cada uno con su propio color de badge para identificarlo rápido en las tablas.

## ¿Para qué sirve?

Evita tipos de proyecto sueltos y sin control: se definen aquí una vez y luego se eligen desde un
selector al crear o editar un proyecto.

{{captura: tipos-proyecto/listado | Catálogo de tipos de proyecto con color, orden y estado activo}}

## Cómo se usa

1. Captura un **Código** interno (no editable después de creado) y una **Etiqueta** visible para el
   equipo.

   {{captura: tipos-proyecto/form | Formulario de alta de un tipo de proyecto}}
2. Elige un **Color de badge** para diferenciarlo visualmente en las tablas.
3. Define el **Orden** (número) para controlar en qué posición aparece en los selectores, o arrastra
   las filas de la tabla para reordenar directamente.
4. Desactiva el toggle **Activo** en vez de eliminar un tipo que ya no se use — así deja de aparecer en
   los selectores sin romper los proyectos que ya lo tienen asignado.

## Campos importantes

- **Código**: identificador interno, no se puede modificar una vez creado el registro — pensalo bien
  antes de guardar.
- **Activo**: los tipos inactivos no aparecen como opción al crear/editar un proyecto, pero los
  proyectos existentes que ya lo usan no se ven afectados.
- **Solo owner/admin**: esta sección es exclusiva de esos roles; no existe una versión "vista" para
  otros roles.

## Preguntas frecuentes

- **¿Por qué no puedo eliminar un tipo de proyecto?** — no existe acción de borrado por diseño; usa
  **Activo = No** para retirarlo de circulación sin perder el historial de proyectos que lo usan.
- **Cambié el orden pero no se refleja en el selector de Proyectos** — el selector respeta `sort_order`;
  confirma que guardaste el nuevo orden (arrastrar la fila lo guarda automáticamente).
