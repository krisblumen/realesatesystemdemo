<x-filament-widgets::widget>
    <x-filament::section heading="Mis zonas asignadas">
        @php($zones = $this->getZones())

        @if ($zones->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Aún no tienes zonas asignadas. Contacta al administrador.
            </p>
        @else
            <div class="flex flex-wrap gap-2">
                @foreach ($zones as $zone)
                    <span class="inline-flex items-center gap-1 rounded-md bg-primary-50 px-2.5 py-1 text-sm font-medium text-primary-700 ring-1 ring-primary-600/10 dark:bg-primary-400/10 dark:text-primary-300 dark:ring-primary-400/20">
                        <x-filament::icon icon="heroicon-m-map-pin" class="h-4 w-4" />
                        {{ $zone->name }}
                        @if ($zone->municipality)
                            <span class="text-primary-500/70 dark:text-primary-300/60">· {{ $zone->municipality->name }}</span>
                        @endif
                    </span>
                @endforeach
            </div>

            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                Mira el mapa de cada zona en <span class="font-medium">Mi Zona</span>.
            </p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
