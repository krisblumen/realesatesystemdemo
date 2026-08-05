@php($marca = $this->getMarca())

<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            @if ($marca['logo'])
                <div class="flex items-center gap-4">
                    {{--
                        La caja vive en el tema del panel (`.landra-marca-logo`)
                        y no en utilidades de Tailwind: el panel carga SU PROPIO
                        bundle, y las de la aplicación no existen ahí. Escritas en
                        el Blade serían texto muerto.
                    --}}
                    <img src="{{ $marca['logo'] }}" alt="{{ $marca['nombre'] }}"
                         class="landra-marca-logo">

                    <div>
                        <p class="text-base font-semibold text-gray-950 dark:text-white">
                            {{ $marca['nombre'] }}
                        </p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Así te ve quien entra a tu sitio.
                        </p>
                    </div>
                </div>

                <div class="shrink-0">
                    <x-filament::button tag="a" :href="$marca['url']" color="gray" outlined icon="heroicon-o-photo">
                        Cambiar mi marca
                    </x-filament::button>
                </div>
            @else
                <div>
                    <p class="text-base font-semibold text-gray-950 dark:text-white">
                        Todavía no subiste tu logo
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Mientras tanto tu sitio muestra una marca genérica. Subí la tuya
                        y aparece en el encabezado, el pie y al compartir el enlace.
                    </p>
                </div>

                <div class="shrink-0">
                    <x-filament::button tag="a" :href="$marca['url']" icon="heroicon-o-arrow-up-tray">
                        Subir mi logo
                    </x-filament::button>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
