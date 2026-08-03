<x-filament-widgets::widget>
    <x-filament::section heading="Solicitudes pendientes de aprobación" icon="heroicon-o-clock">
        @php($requests = $this->getPendingRequests())

        <div class="flex flex-col gap-2">
            @foreach ($requests as $request)
                <div class="flex items-center justify-between gap-3 rounded-md bg-warning-50 px-3 py-2 ring-1 ring-warning-600/10 dark:bg-warning-400/10 dark:ring-warning-400/20">
                    <div class="flex items-center gap-2">
                        <x-filament::icon icon="heroicon-m-clock" class="h-4 w-4 text-warning-600 dark:text-warning-400" />
                        <span class="text-sm font-medium text-warning-800 dark:text-warning-200">
                            {{ $request->operation_type->label() }} · {{ $request->cantidad_solicitada }} {{ Str::plural('lona', $request->cantidad_solicitada) }}
                        </span>
                    </div>
                    <span class="text-xs text-warning-700/80 dark:text-warning-300/70">
                        Solicitado el {{ $request->created_at->format('d/m/Y') }} · pendiente de aprobación
                    </span>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
