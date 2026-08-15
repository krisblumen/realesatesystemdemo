{{--
    El editor de imagen se abre solo, apenas se elige el archivo.

    EL PROBLEMA QUE RESUELVE. El recorte vivía detrás de un lápiz chiquito que
    sólo aparece SOBRE la miniatura, o sea después de subir. Quien nunca lo vio
    no sabe que existe: sube la foto, la ve torcida o mal encuadrada, y se
    resigna. Una función que hay que descubrir es una función que casi nadie usa.

    CÓMO. El complemento `image-edit` de FilePond —que Filament ya trae— tiene la
    opción `imageEditInstantEdit`, que abre el editor al agregar el archivo. Viene
    apagada y se enciende acá, sin tocar nada de Filament: si mañana cambia por
    dentro, esto deja de tener efecto y el lápiz sigue estando. Se degrada a lo
    que había, no a algo roto.

    POR QUÉ UN OBSERVADOR Y NO EL EVENTO `FilePond:init`: ese evento no llega
    hasta `document` —lo comprobé en la página—, y además los campos aparecen en
    cualquier momento, no sólo al cargar: adentro de un modal, de una pestaña o
    de un repetidor que alguien recién abrió.

    SÓLO EN CAMPOS DE UNA SOLA IMAGEN. En la galería, que admite treinta, abrir
    el editor por cada archivo convertiría subir fotos en una tortura.
--}}
<script>
    (function () {
        const encender = () => {
            if (! window.FilePond) {
                return;
            }

            document.querySelectorAll('.filepond--root').forEach((elemento) => {
                let pond;

                try {
                    pond = FilePond.find(elemento);
                } catch (e) {
                    return;
                }

                if (! pond || pond.allowMultiple || ! pond.allowImageEdit) {
                    return;
                }

                pond.imageEditInstantEdit = true;
            });
        };

        // Se agrupan los cambios: montar un formulario dispara decenas de
        // mutaciones y no hace falta recorrer el documento en cada una.
        let pendiente = null;
        const agendar = () => {
            clearTimeout(pendiente);
            pendiente = setTimeout(encender, 50);
        };

        new MutationObserver(agendar).observe(document.body, { childList: true, subtree: true });

        document.addEventListener('livewire:navigated', agendar);
        agendar();
    })();
</script>
