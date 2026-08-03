<x-filament-panels::page>
    {{-- El `id` no es decorativo: el botón flotante vive FUERA del formulario
         —lo inyecta un render hook al final del contenido— y lo envía con el
         atributo `form` de HTML5, que necesita este identificador. Es el mismo
         que Filament le pone a los formularios de sus páginas de recurso, así
         que el botón no tiene que saber en qué pantalla está. --}}
    <form id="form" wire:submit="save">
        <div class="space-y-6">
            {{ $this->form }}
        </div>
    </form>

    {{-- El botón de guardado NO se dibuja acá: lo pone el render hook del panel
         (AdminPanelProvider), el mismo que en el resto de los formularios.
         Antes esta pantalla tenía su propia copia, y era la única que lo
         tenía; ahora la copia es el componente compartido. --}}
</x-filament-panels::page>
