@props([
    'title',
    'zone',
    'price',
    'operation' => 'Venta',
    'beds' => null,
    'baths' => null,
    'area' => null,
    'parking' => null,
    'href' => '#',
    'image' => null,
])

@php
    // Sólo se muestra la ZONA al público; nunca calle/número (privacidad).
    $opColor = match ($operation) {
        'Renta' => 'navy',
        'Preventa' => 'orange',
        default => 'orange',
    };
@endphp

<a href="{{ $href }}"
   {{ $attributes->merge(['class' => 'group block overflow-hidden rounded-brand-lg bg-white shadow-sm transition-all duration-[350ms] ease-[var(--ease-out-expo)] hover:-translate-y-1 hover:shadow-lg']) }}>
    <div class="relative aspect-[4/3] overflow-hidden">
        @if ($image)
            <img src="{{ $image }}" alt="{{ $title }}" class="h-full w-full object-cover transition-transform duration-500 ease-[var(--ease-out-expo)] group-hover:scale-105">
        @else
            <div class="h-full w-full bg-brand-primary bg-gradient-to-br from-black/20 to-white/10"></div>
        @endif
        <div class="absolute left-4 top-4">
            <x-badge :color="$opColor" solid>{{ $operation }}</x-badge>
        </div>
    </div>

    <div class="p-5">
        <p class="eyebrow text-stone">{{ $zone }}</p>
        <h3 class="mt-1.5 text-lg font-semibold text-brand-primary-ink">{{ $title }}</h3>
        <p class="mt-2 text-xl font-bold text-brand-primary-ink">{{ $price }}</p>

        @if ($beds || $baths || $area || $parking)
            <div class="mt-4 flex flex-wrap gap-x-5 gap-y-2 border-t border-fog pt-4 text-sm text-stone">
                @if ($beds)
                    <span class="inline-flex items-center gap-1.5">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M2 17v-3a4 4 0 0 1 4-4h12a4 4 0 0 1 4 4v3"/><path d="M2 20v-3M22 20v-3M6 10V7a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v3"/></svg>
                        {{ $beds }}
                    </span>
                @endif
                @if ($baths)
                    <span class="inline-flex items-center gap-1.5">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12h16v3a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4z"/><path d="M6 12V6a2 2 0 0 1 2-2 2 2 0 0 1 2 2"/><path d="M6 19l-1 2M19 19l1 2"/></svg>
                        {{ $baths }}
                    </span>
                @endif
                @if ($area)
                    <span class="inline-flex items-center gap-1.5">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h18v18H3z"/><path d="M9 3v18M3 9h18"/></svg>
                        {{ $area }} m²
                    </span>
                @endif
                @if ($parking)
                    <span class="inline-flex items-center gap-1.5">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 17h14M5 17a2 2 0 0 1-2-2v-3l2-5h12l2 5v3a2 2 0 0 1-2 2M7 17v2M17 17v2"/></svg>
                        {{ $parking }}
                    </span>
                @endif
            </div>
        @endif
    </div>
</a>
