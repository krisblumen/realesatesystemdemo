@php($zonas = $this->getZonas())

<x-filament-widgets::widget>
    <x-filament::section heading="Zonas activas">
        <x-slot name="headerEnd">
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ $this->getTotalDeZonas() }}</span>
        </x-slot>

        @if ($zonas->isEmpty())
            {{-- El vacío dice qué falta, no sólo que no hay nada. --}}
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Todavía no hay zonas activas. Cuando dibujes una en el mapa y la actives,
                aparece acá con sus inmuebles.
            </p>
        @else
            <ul class="divide-y divide-gray-100 dark:divide-white/10">
                @foreach ($zonas as $zona)
                    <li class="flex items-center justify-between gap-3 py-2.5 first:pt-0 last:pb-0">
                        <span class="truncate text-sm text-gray-950 dark:text-white">{{ $zona['nombre'] }}</span>
                        <span class="shrink-0 text-sm font-medium text-gray-500 dark:text-gray-400">{{ $zona['inmuebles'] }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
