{{-- Motor genérico de carrusel para cualquier [data-carousel] de la página.
     Espera dentro: [data-track], varios [data-tab], y opcional [data-prev]/[data-next].
     Si el contenedor tiene [data-swipe], habilita gesto táctil. --}}
<script>
(function () {
    const header = document.querySelector('header');

    document.querySelectorAll('[data-carousel]').forEach((root) => {
        const track = root.querySelector('[data-track]');
        const tabs = Array.from(root.querySelectorAll('[data-tab]'));
        const prev = root.querySelector('[data-prev]');
        const next = root.querySelector('[data-next]');
        if (! track || tabs.length < 2) return;

        let current = 0;
        const last = tabs.length - 1;

        function go(i, scroll = false) {
            current = Math.max(0, Math.min(i, last));
            track.style.transform = `translateX(-${current * 100}%)`;
            tabs.forEach((t, idx) => {
                const active = idx === current;
                t.classList.toggle('w-12', active);
                t.classList.toggle('bg-brand-accent', active);
                t.classList.toggle('w-7', !active);
                t.classList.toggle('bg-brand-primary/15', !active);
                t.classList.toggle('hover:bg-brand-primary/30', !active);
            });
            if (prev) prev.disabled = current === 0;
            if (next) next.disabled = current === last;

            // Sube a ver el contenido nuevo si su inicio no está visible.
            if (scroll) {
                const offset = (header ? header.offsetHeight : 0) + 16;
                if (root.getBoundingClientRect().top < offset) {
                    const top = window.scrollY + root.getBoundingClientRect().top - offset;
                    window.scrollTo({ top, behavior: 'smooth' });
                }
            }
        }

        tabs.forEach((t, idx) => t.addEventListener('click', () => go(idx, true)));
        if (prev) prev.addEventListener('click', () => go(current - 1, true));
        if (next) next.addEventListener('click', () => go(current + 1, true));

        if (root.hasAttribute('data-swipe')) {
            let startX = null;
            let moved = false;
            track.addEventListener('touchstart', (e) => { startX = e.changedTouches[0].clientX; moved = false; }, { passive: true });
            track.addEventListener('touchmove', (e) => {
                if (startX !== null && Math.abs(e.changedTouches[0].clientX - startX) > 10) moved = true;
            }, { passive: true });
            track.addEventListener('touchend', (e) => {
                if (startX === null) return;
                const dx = e.changedTouches[0].clientX - startX;
                if (Math.abs(dx) > 40) go(current + (dx < 0 ? 1 : -1));
                startX = null;
            });
            // Suprime el click fantasma tras un swipe: así deslizar no abre el
            // lightbox/detalle, pero un tap real sí.
            track.addEventListener('click', (e) => {
                if (moved) { e.preventDefault(); e.stopPropagation(); moved = false; }
            }, true);
        }

        go(0);
    });
})();
</script>
