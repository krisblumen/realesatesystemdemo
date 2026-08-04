<script>
    (function () {
        // Tailwind `lg` breakpoint — Filament treats the sidebar as a desktop
        // element at/above this width and as a dismissible overlay below it.
        const DESKTOP_BREAKPOINT = 1024;

        function syncSidebarToViewport() {
            const sidebar = window.Alpine?.store('sidebar');

            if (! sidebar) {
                return;
            }

            // Filament defaults `isOpen` to a persisted `true`, which leaves the
            // sidebar covering the screen on mobile. Keep it open on desktop and
            // collapsed on mobile so the hamburger drives it there.
            sidebar.isOpen = window.innerWidth >= DESKTOP_BREAKPOINT;
        }

        document.addEventListener('alpine:initialized', syncSidebarToViewport);
        window.addEventListener('resize', syncSidebarToViewport);
        document.addEventListener('livewire:navigated', syncSidebarToViewport);

        // Alpine may already be running by the time this script executes.
        if (window.Alpine?.store('sidebar')) {
            syncSidebarToViewport();
        }
    })();
</script>
