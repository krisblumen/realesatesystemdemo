@php($acciones = $this->getAcciones())

<x-filament-widgets::widget>
    <x-filament::section heading="Acciones rápidas">
        <div class="flex flex-wrap gap-2">
            @foreach ($acciones as $accion)
                <x-filament::button
                    tag="a"
                    :href="$accion['url']"
                    :icon="$accion['icono']"
                    color="gray"
                    outlined
                >
                    {{ $accion['etiqueta'] }}
                </x-filament::button>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
