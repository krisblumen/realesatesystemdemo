<script>
    document.addEventListener('livewire:init', () => {
        // Confirmacion visual de guardado: el boton de submit pasa a verde
        // (color success del theme) durante ~2.5s. Livewire re-renderiza el
        // boton tras guardar y restaura su color; por eso re-buscamos el boton
        // y forzamos el verde en intervalos cortos mientras dura el flash.
        const SUCCESS = ['fi-btn-color-success', 'fi-color-success'];
        const PRIMARY = ['fi-btn-color-primary', 'fi-color-primary'];
        const DURATION = 2500;

        const submitButton = () => document.querySelector('.fi-form-actions button[type="submit"]');

        window.Livewire.on('nh-record-saved', () => {
            const endsAt = Date.now() + DURATION;

            const interval = setInterval(() => {
                const button = submitButton();

                if (button && ! button.classList.contains('fi-btn-color-success')) {
                    button.classList.remove(...PRIMARY);
                    button.classList.add(...SUCCESS);
                }

                if (Date.now() >= endsAt) {
                    clearInterval(interval);

                    const finalButton = submitButton();

                    if (finalButton) {
                        finalButton.classList.remove(...SUCCESS);
                        finalButton.classList.add(...PRIMARY);
                    }
                }
            }, 100);
        });
    });
</script>
