# Manual de Características

Es el catálogo de amenidades/atributos (por ejemplo, "Alberca", "Jardín", "Seguridad 24h") que se
pueden asignar a los inmuebles.

## ¿Para qué sirve?

Estandariza cómo se describen las amenidades de un inmueble, para que se muestren de forma consistente
en su ficha y puedan usarse como filtro en el sitio público.

{{captura: caracteristicas/listado | Catálogo de características con su slug e ícono}}

## Cómo se usa

1. Captura el **Nombre** de la característica — el **Slug** se autogenera al escribir el nombre, pero es
   editable manualmente.

   {{captura: caracteristicas/form | Formulario de alta de una característica}}
2. Opcionalmente, indica el **Ícono Heroicon** (nombre del ícono) que se mostrará junto a la
   característica en la ficha del inmueble.
3. Una vez creada, la característica queda disponible en el selector múltiple de la sección
   **Características** del formulario de **Inmuebles**.

## Campos importantes

- **Slug**: identificador único usado internamente (por ejemplo, en URLs de filtro); debe ser único en
  todo el catálogo.
- **Ícono**: es el nombre textual del ícono Heroicon (por ejemplo `sparkles`), no un selector visual —
  revisa que el nombre exista en el set de Heroicons para que se vea correctamente.

## Preguntas frecuentes

- **¿Puedo eliminar una característica que ya está en uso?** — sí, pero se desvincula de todos los
  inmuebles que la tenían asignada; revisa antes en qué inmuebles se usa si no quieres perder esa
  información.
- **El ícono no se ve en la ficha del inmueble** — confirma que el nombre cargado corresponde
  exactamente a un ícono Heroicon existente (mismo formato que usa el resto del panel).
