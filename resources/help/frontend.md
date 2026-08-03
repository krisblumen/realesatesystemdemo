# Manual del Administrador de Contenidos del Frontend

El módulo **Frontend** te deja configurar el sitio público (identidad, tema, navegación, servicios y
el contenido de las páginas institucionales) sin tocar código. Solo el rol **owner** tiene acceso.

## ¿Para qué sirve?

Centraliza en el panel todo lo que antes estaba escrito en el código del sitio: el nombre y los datos
de contacto, los colores y tipografías de marca, el menú y el footer, los servicios que se ofrecen y el
contenido de las páginas Inicio, Nosotros, Servicios, Inversionistas y Contacto. Si no configuras algo,
el sitio sigue mostrando exactamente el contenido actual (fallback), así que nunca queda en blanco.

## Dos formas de publicar

- **Guardar = publicar** (inmediato): la **Configuración del sitio** (identidad, contacto, SEO, tema,
  navegación, footer y CTAs) y la **disponibilidad de servicios** se aplican al guardar, tras pasar las
  validaciones. Su "vista previa" es el sitio en vivo.
- **Borrador → Publicado**: las **páginas institucionales** guardan un borrador que solo llega al público
  cuando pulsas **Publicar**. Podés revisar el borrador con **Vista previa** antes de publicar.

## Vista previa

En **Frontend → Vista previa** elegís una de las cinco páginas y ves su **borrador sin publicar** dentro
del layout público real, con un banner que aclara que no es el sitio en vivo. La vista previa:

- Solo es visible para el owner (nadie más puede abrirla).
- No es indexable por buscadores (`noindex, nofollow`) y no aparece en el sitemap.
- Muestra el borrador; el público sigue viendo lo último publicado hasta que pulses **Publicar**.

## Publicar una página

1. Editá la página y sus secciones desde su editor.
2. Revisá el resultado en **Vista previa**.
3. Pulsá **Publicar**. El sistema valida la página (por ejemplo, exige que el encabezado/hero esté
   activo como H1) y, si otra persona editó el borrador desde que abriste la pantalla, rechaza la
   publicación para que recargues y no pises su cambio.
4. Al publicar se registra quién publicó y cuándo, y el sitio público se actualiza.

## Notas importantes

- Las imágenes no se borran al reemplazarlas: se conserva la anterior y solo cambia la referencia.
- No hay editor libre de HTML/CSS/JS: el contenido se arma con secciones y campos validados.
- La personalización visual usa variables de tema en tiempo de ejecución; no recompila el sitio.
