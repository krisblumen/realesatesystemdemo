<x-filament-panels::page>
    {{-- Toolbar mobile-first (M-G-1): en móvil apila etiqueta, select full-width
         y enlace en su propia fila, sin flex horizontal que se solape con el
         header sticky del panel. En sm+ se alinea en una fila. --}}
    <div class="space-y-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
            <label for="preview-page" class="text-sm font-medium text-gray-700 dark:text-gray-300">Página a previsualizar</label>
            <select
                id="preview-page"
                wire:model.live="pageKey"
                class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:w-auto dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
            >
                @foreach (\App\Filament\Pages\FrontendPreview::pages() as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>

            <a
                href="{{ $this->getPreviewUrl() }}"
                target="_blank"
                rel="noopener"
                class="inline-flex items-center gap-1.5 text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400"
            >
                Abrir en pestaña nueva
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6"/><path d="M10 14 21 3"/></svg>
            </a>
        </div>

        <p class="text-sm text-gray-500 dark:text-gray-400">
            Estás viendo el <strong>borrador sin publicar</strong>. Los cambios no llegan al sitio en vivo hasta que publiques la página desde su editor.
        </p>

        {{-- El iframe carga la ruta owner-gated que renderiza el layout público
             real con los datos draft (noindex,nofollow). wire:key fuerza el
             recambio del src al cambiar la página seleccionada. --}}
        <div class="overflow-hidden rounded-xl border border-gray-200 shadow-sm dark:border-gray-700">
            <iframe
                wire:key="preview-{{ $pageKey }}"
                src="{{ $this->getPreviewUrl() }}"
                title="Vista previa del borrador"
                class="h-[75vh] w-full bg-white"
            ></iframe>
        </div>
    </div>
</x-filament-panels::page>
