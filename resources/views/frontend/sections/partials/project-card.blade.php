{{--
    Tarjeta de un proyecto, sobre el item VIEW-READY que entrega
    FrontendPageRenderer::projects() — title, type?, description?, href, cover?
    — nunca un modelo Eloquent (Mn-F1). La comparten la grilla por defecto y el
    carrusel de la variante `catalog`: es EXACTAMENTE la misma tarjeta que
    `site/partials/project-card.blade.php` dibuja para un `Project` real, pero
    parametrizada sobre el array — copiarla tres veces (grilla, carrusel
    desktop, carrusel móvil) las habría desincronizado a la primera edición.

    @var array $project
--}}
<a href="{{ $project['href'] }}" class="group relative block min-h-[380px] overflow-hidden rounded-brand-lg shadow-sm">
    @if (! empty($project['cover']))
        <div class="absolute inset-0 bg-cover bg-center transition-transform duration-500 ease-[var(--ease-out-expo)] group-hover:scale-105" style="background-image:url('{{ $project['cover'] }}')"></div>
    @else
        <div class="absolute inset-0 bg-brand-primary bg-gradient-to-br from-black/20 to-white/10 transition-transform duration-500 ease-[var(--ease-out-expo)] group-hover:scale-105"></div>
    @endif
    <div class="absolute inset-0 bg-gradient-to-b from-transparent via-brand-primary/[0.35] to-brand-primary/[0.88]"></div>
    <div class="absolute inset-x-7 bottom-7">
        @if (! empty($project['type']))
            <span class="inline-block rounded-full bg-brand-accent/95 px-3 py-1.5 text-[11px] font-bold uppercase tracking-wide text-on-brand-accent">{{ $project['type'] }}</span>
        @endif
        <h3 class="mt-3.5 font-brand-heading text-2xl font-bold text-on-brand-primary">{{ $project['title'] }}</h3>
        @if (! empty($project['description']))
            <p class="mt-1.5 line-clamp-2 text-sm text-on-brand-primary/80">{{ $project['description'] }}</p>
        @endif
    </div>
</a>
