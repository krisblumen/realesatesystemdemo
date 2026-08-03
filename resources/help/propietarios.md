# Manual de Propietarios

Un propietario es la persona dueña de uno o más inmuebles del catálogo. Esta sección administra sus
datos de contacto y su vínculo con las propiedades y comisiones pactadas.

## ¿Para qué sirve?

Evita repetir los datos del dueño en cada inmueble: se carga una sola vez y se vincula desde el
formulario de Inmuebles. También sirve para ver de un vistazo cuántos inmuebles tiene cada propietario.

{{captura: propietarios/listado | Listado de propietarios con la columna Inmuebles}}

## Cómo se usa

1. Captura **Nombre**, **Apellidos** y **Teléfono** (obligatorios) y, opcionalmente, **Email**.

   {{captura: propietarios/form | Formulario de alta de un propietario}}
2. Owner/admin pueden asignar un **Agente responsable** del propietario; el agente que da de alta un
   propietario queda automáticamente como su responsable.
3. Vincula el propietario a un inmueble desde el propio formulario de **Inmuebles** (puedes crearlo ahí
   mismo sin salir de la pantalla) o gestionarlo directamente desde aquí.
4. La columna **Inmuebles** de la tabla muestra cuántas propiedades tiene asociadas cada propietario.

## Campos importantes

- **Teléfono único**: el sistema valida que no se repita el mismo teléfono entre propietarios, para
  evitar duplicados por error.
- **Agente**: solo owner/admin lo ven y lo cambian; determina de qué cartera de propietarios es
  responsable cada agente.
- **Papelera**: solo owner/admin ven y restauran propietarios eliminados.

## Preguntas frecuentes

- **No puedo dar de alta un propietario con un teléfono que ya existe** — el sistema lo bloquea a
  propósito para evitar registros duplicados; busca si ya existe antes de crear uno nuevo.
- **¿Por qué no veo el campo Agente al editar un propietario?** — solo owner/admin lo ven; si eres
  agente, el propietario que creas queda asignado a ti automáticamente.
