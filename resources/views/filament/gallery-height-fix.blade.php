<script>
    (function () {
        // FilePond en panelLayout('grid') (galerias de Proyectos e Inmuebles)
        // posiciona cada foto con position:absolute + transform (tecnica FLIP
        // para animar el reordenamiento), y calcula mal su propio alto a partir
        // de cierta cantidad de filas: dos contenedores quedan cortos --
        // .filepond--root (el marco) y, aparte, .filepond--list-scroller (la
        // caja interna que de verdad recorta/scrollea el contenido, con su
        // propio alto fijo independiente del root). Las fotos de mas SI se
        // guardan -- quedan recortadas visualmente o atras de un scroll interno
        // roto, inalcanzables para arrastrar y reordenar. Se recalculan ambos
        // altos a partir de la posicion real de cada item, para que todo entre
        // en una sola grilla plana sin necesitar scroll interno.
        const SELECTOR = '.filepond--root[data-style-panel-layout="grid"]';

        function setHeightIfChanged(el, target) {
            const current = parseFloat(el.style.height) || 0;

            // Evita loop infinito: setProperty dispara la mutacion que nos
            // llama de nuevo, asi que solo escribimos si el valor cambia.
            if (Math.abs(current - target) > 1) {
                el.style.setProperty('height', `${target}px`, 'important');
            }
        }

        function fixHeight(root) {
            const list = root.querySelector('.filepond--list');
            const scroller = root.querySelector('.filepond--list-scroller');

            if (! list) {
                return;
            }

            const items = list.querySelectorAll('.filepond--item');
            let maxBottom = 0;

            items.forEach((item) => {
                const match = item.style.transform?.match(/translate3d\([^,]+,\s*(-?[\d.]+)px/);
                const translateY = match ? parseFloat(match[1]) : 0;
                maxBottom = Math.max(maxBottom, translateY + item.offsetHeight);
            });

            if (maxBottom === 0) {
                return;
            }

            // Las coordenadas de cada item son relativas a .filepond--list, no
            // a .filepond--root -- el root reserva espacio arriba para el
            // label "Arrastra y solta tus archivos", que hay que sumar. El
            // scroller no necesita ese offset: la lista esta pegada a su borde.
            const listOffset = list.getBoundingClientRect().top - root.getBoundingClientRect().top;

            setHeightIfChanged(root, listOffset + maxBottom + 16);

            if (scroller) {
                setHeightIfChanged(scroller, maxBottom + 16);

                if (scroller.style.overflowY !== 'visible') {
                    scroller.style.setProperty('overflow-y', 'visible', 'important');
                }
            }
        }

        function observe(root) {
            if (root.dataset.galleryHeightFixed) {
                return;
            }

            root.dataset.galleryHeightFixed = 'true';

            new MutationObserver(() => fixHeight(root)).observe(root, {
                attributes: true,
                attributeFilter: ['style'],
                subtree: true,
                childList: true,
            });

            fixHeight(root);
        }

        function scan() {
            document.querySelectorAll(SELECTOR).forEach(observe);
        }

        document.addEventListener('livewire:navigated', scan);
        document.addEventListener('livewire:init', scan);
        new MutationObserver(scan).observe(document.documentElement, {childList: true, subtree: true});
        scan();
    })();
</script>
