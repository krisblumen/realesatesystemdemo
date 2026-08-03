@php
    // Mismo conteo que ya usa el badge de "Leads" en el sidebar -- se
    // reutiliza la logica existente (owner/admin ven abiertos sin asignar,
    // el agente ve los propios) en vez de duplicarla aca.
    $leadsBadgeCount = \App\Filament\Resources\LeadResource::getNavigationBadge();
@endphp
<script>
    (function () {
        const COUNT = @js($leadsBadgeCount);

        function sync() {
            const button = document.querySelector('.fi-topbar-open-sidebar-btn');

            if (! button) {
                return;
            }

            let badge = button.querySelector('.nh-mobile-leads-badge');

            if (! COUNT) {
                badge?.remove();

                return;
            }

            if (! badge) {
                badge = document.createElement('span');
                badge.className = 'nh-mobile-leads-badge';
                button.appendChild(badge);
            }

            badge.textContent = COUNT;
        }

        document.addEventListener('livewire:navigated', sync);
        document.addEventListener('livewire:init', sync);
        sync();
    })();
</script>
