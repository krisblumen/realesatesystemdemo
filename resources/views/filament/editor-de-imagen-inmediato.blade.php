{{--
    El editor de imagen se abre solo, apenas termina de subir el archivo.

    EL PROBLEMA QUE RESUELVE. El recorte vivía detrás de un lápiz chiquito que
    sólo aparece SOBRE la miniatura. Quien nunca lo vio no sabe que existe: sube
    la foto, la ve mal encuadrada y se resigna. Una función que hay que descubrir
    es una función que casi nadie usa.

    POR QUÉ NO SE USA `imageEditInstantEdit`, que es la opción que el complemento
    de FilePond trae justo para esto. Lo intenté y ROMPE el guardado: esa opción
    espera el contrato del complemento —que el editor conteste con `onconfirm` o
    `oncancel` para que FilePond sepa qué hacer con el archivo— y el
    `imageEditEditor` de Filament tiene el `onconfirm` VACÍO, porque resuelve el
    guardado por su cuenta en `saveEditor()`. Con la apertura instantánea, el
    archivo queda esperando una respuesta que nunca llega: el botón de recortar
    no hace nada y el de guardar sin recortar deja la subida colgada.

    LO QUE SE HACE EN CAMBIO es llamar a `loadEditor(file)` —el mismo método que
    invoca el lápiz— cuando el archivo YA TERMINÓ DE SUBIR. Es exactamente el
    camino que ya funciona, sólo que sin esperar a que alguien encuentre el
    botón. Si Filament cambia por dentro, esto deja de tener efecto y el lápiz
    sigue estando: se degrada a lo que había, no a algo roto.

    SÓLO EN CAMPOS DE UNA SOLA IMAGEN. En la galería, que admite treinta, abrir
    el editor por cada archivo sería una tortura.
--}}
<script>
    (function () {
        const YA_ENGANCHADO = '_landraEditorAutomatico';

        const enganchar = () => {
            if (! window.FilePond || ! window.Alpine) {
                return;
            }

            document.querySelectorAll('.filepond--root').forEach((elemento) => {
                let pond;

                try {
                    pond = FilePond.find(elemento);
                } catch (e) {
                    return;
                }

                if (! pond || pond[YA_ENGANCHADO] || pond.allowMultiple || ! pond.allowImageEdit) {
                    return;
                }

                pond[YA_ENGANCHADO] = true;

                // `processfile` y no `addfile`: recién ahí el archivo está en el
                // mismo estado que cuando alguien hace clic en el lápiz. Abrirlo
                // mientras todavía sube deja al editor peleando con la subida.
                pond.on('processfile', (error, archivo) => {
                    if (error || ! archivo?.file) {
                        return;
                    }

                    // NO SE REABRE CON LA SALIDA DEL PROPIO EDITOR, y sin esto es
                    // un bucle: guardar el recorte agrega un archivo nuevo, ese
                    // archivo sube, y esta misma función lo toma como si alguien
                    // lo hubiera elegido. El editor se abría de vuelta y no se
                    // cerraba nunca.
                    //
                    // `-vN` es la firma que Filament le pone a lo que sale del
                    // editor (`saveEditor()` en `file-upload.js`). Es un acuerdo
                    // con su convención, y si algún día la cambian el costo es que
                    // el editor abra dos veces — molesto, no roto.
                    if (/-v\d+$/.test(archivo.filename.replace(/\.[^.]+$/, ''))) {
                        return;
                    }

                    const componente = Alpine.$data(elemento);

                    if (typeof componente?.loadEditor === 'function') {
                        componente.loadEditor(archivo.file);
                    }
                });
            });
        };

        // Se agrupan los cambios: montar un formulario dispara decenas de
        // mutaciones y no hace falta recorrer el documento en cada una.
        let pendiente = null;
        const agendar = () => {
            clearTimeout(pendiente);
            pendiente = setTimeout(enganchar, 50);
        };

        new MutationObserver(agendar).observe(document.body, { childList: true, subtree: true });

        document.addEventListener('livewire:navigated', agendar);
        agendar();
    })();
</script>
