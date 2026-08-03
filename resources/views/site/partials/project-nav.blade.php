{{-- Navegación del carrusel: flecha ← · tabs de líneas · flecha →. Espera $count. --}}
<div class="mt-8 flex items-center justify-center gap-5">
    <button type="button" data-prev
            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-brand-primary/15 text-brand-primary-ink transition-colors hover:border-brand-primary hover:bg-brand-primary hover:text-on-brand-primary disabled:cursor-not-allowed disabled:opacity-30 disabled:hover:border-brand-primary/15 disabled:hover:bg-transparent disabled:hover:text-brand-primary-ink"
            aria-label="Anterior">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
    </button>

    <div class="flex flex-wrap items-center justify-center gap-2.5" role="tablist">
        @for ($i = 0; $i < $count; $i++)
            <button type="button" data-tab
                    class="h-1 rounded-full transition-all duration-300 {{ $i === 0 ? 'w-12 bg-brand-accent' : 'w-7 bg-brand-primary/15 hover:bg-brand-primary/30' }}"
                    aria-label="Ir a {{ $i + 1 }}"></button>
        @endfor
    </div>

    <button type="button" data-next
            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-brand-primary/15 text-brand-primary-ink transition-colors hover:border-brand-primary hover:bg-brand-primary hover:text-on-brand-primary disabled:cursor-not-allowed disabled:opacity-30 disabled:hover:border-brand-primary/15 disabled:hover:bg-transparent disabled:hover:text-brand-primary-ink"
            aria-label="Siguiente">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
    </button>
</div>
