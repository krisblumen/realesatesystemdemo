@php($contratos = $this->getContratos())

<x-filament-widgets::widget>
    <x-filament::section heading="Contratos en proceso">
        @if ($contratos->isEmpty())
            {{--
                El vacío dice QUÉ FALTA para que aparezca algo, y no sólo que no
                hay nada. En un demo recién creado esta es la primera pantalla que
                se ve, y «Sin datos» ahí parece una función rota en vez de una
                lista que todavía nadie llenó.
            --}}
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Todavía no hay contratos enviados. Cuando generes uno desde un inmueble,
                aparece acá hasta que el cliente lo firme.
            </p>
        @else
            <ul class="divide-y divide-gray-100 dark:divide-white/10">
                @foreach ($contratos as $contrato)
                    <li class="flex items-center justify-between gap-3 py-2.5 first:pt-0 last:pb-0">
                        <div class="min-w-0">
                            <p class="truncate font-mono text-sm font-medium text-gray-950 dark:text-white">
                                @if ($contrato['url'])
                                    <a href="{{ $contrato['url'] }}" class="hover:underline">{{ $contrato['folio'] }}</a>
                                @else
                                    {{ $contrato['folio'] }}
                                @endif
                            </p>
                            <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $contrato['cliente'] }}</p>
                        </div>

                        <x-filament::badge :color="$contrato['color']" class="shrink-0">
                            {{ $contrato['etiqueta'] }}
                        </x-filament::badge>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
