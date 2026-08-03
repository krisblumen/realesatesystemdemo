<x-filament-panels::page>
    @php($current = $this->currentSection())

    @if ($current)
        {{-- Vista de artículo --}}
        <div class="flex items-center gap-3">
            <a href="{{ \App\Filament\Pages\Ayuda::getUrl() }}"
               class="text-sm text-primary-600 hover:underline dark:text-primary-400">
                &larr; Volver al índice
            </a>
        </div>

        <article class="prose prose-slate max-w-none dark:prose-invert">
            {!! $current['html'] !!}
        </article>
    @else
        {{-- Índice agrupado --}}
        @if ($this->seccion !== null)
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Esa sección no está disponible para tu cuenta.
            </p>
        @endif

        @foreach ($this->visibleSections() as $group => $sections)
            <section class="space-y-3">
                <h2 class="text-lg font-semibold text-gray-950 dark:text-white">{{ $group }}</h2>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($sections as $section)
                        <a href="{{ \App\Filament\Pages\Ayuda::getUrl(['seccion' => $section['key']]) }}"
                           class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-4
                                  text-sm font-medium text-gray-900 shadow-sm transition
                                  hover:border-primary-400 hover:shadow
                                  dark:border-white/10 dark:bg-white/5 dark:text-white">
                            @svg($section['icon'], 'h-5 w-5 shrink-0 text-primary-500 dark:text-primary-400')
                            <span>{{ $section['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach
    @endif
</x-filament-panels::page>
