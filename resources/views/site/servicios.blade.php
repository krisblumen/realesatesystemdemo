@php
    $cms = app(\App\Services\Frontend\FrontendPageRenderer::class)->render('servicios');
@endphp
<x-layouts.public title="Servicios" :seo="$cms['seo']">
    @if (! $cms['fallback'])
        {{-- Página publicada desde el CMS (RFC-076): renderiza el snapshot. --}}
        @include('frontend.render', ['sections' => $cms['sections']])
    @else
    {{-- Hero: MISMO partial y MISMO presenter que el contenido publicado.
         Antes esta rama tenía su propio hero, así que la primera visita no
         recibía el fallback por página, los modos A/B ni las garantías de
         CSP (C-B-1). Las migas de pan son chrome de la página y viajan como
         datos. --}}
    @include('frontend.sections.hero', [
        's' => $cms['hero'],
        'sectionKey' => 'hero',
        'breadcrumbs' => [
            ['label' => 'Inicio', 'url' => url('/')],
            ['label' => 'Servicios'],
        ],
    ])

    {{-- Servicios --}}
    @php
        // Servicios activos configurados para esta página (RFC-074, §16.6).
        $pageServices = app(\App\Services\Frontend\FrontendServicesService::class)->services('servicios');
        // Imagen por defecto por código, cuando el servicio no tiene media propia.
        $serviceImages = [
            'arquitectura' => 'images/servicios/arquitectura_service.png',
            'construccion' => 'images/servicios/construccion_service.png',
            'comercializacion' => 'images/servicios/comercializacion_service.png',
            'inversion' => 'images/servicios/iversion_inmobiliaria_service.png',
        ];
    @endphp
    <section class="mx-auto max-w-[var(--container-content)] space-y-20 px-6 py-20">
        @forelse ($pageServices as $i => $service)
            @php $img = $service['image_url'] ?? asset($serviceImages[$service['code']] ?? 'images/metaimage/meta_image_landra.jpg'); @endphp
            <div class="grid items-center gap-12 lg:grid-cols-2 {{ $i % 2 === 1 ? 'lg:[&>div:first-child]:order-2' : '' }}">
                <div>
                    <p class="eyebrow text-brand-accent-ink">{{ sprintf('%02d', $i + 1) }} · {{ $service['title'] }}</p>
                    <h2 class="mt-3 font-brand-heading text-[clamp(26px,3.4vw,36px)] font-bold leading-snug tracking-tight text-brand-primary-ink">{{ $service['long_description'] ?: $service['short_description'] }}</h2>
                    <ul class="mt-6 grid gap-x-6 gap-y-3 sm:grid-cols-2">
                        @foreach ($service['bullets'] as $bullet)
                            <li class="flex items-center gap-3">
                                <span class="flex h-6 w-6 flex-none items-center justify-center rounded-[7px] bg-navy-50 text-brand-primary-ink">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                                </span>
                                <span class="text-[15px] text-graphite">{{ $bullet }}</span>
                            </li>
                        @endforeach
                    </ul>
                    @if ($service['cta'])
                        <div class="mt-7"><x-button variant="ghost" :href="$service['cta']['url']">{{ $service['cta']['label'] }}</x-button></div>
                    @endif
                </div>
                <div class="min-h-[360px] overflow-hidden rounded-brand-lg shadow-lg">
                    <img src="{{ $img }}" alt="{{ $service['image_alt'] }}"
                         class="h-full min-h-[360px] w-full object-cover" loading="lazy">
                </div>
            </div>
        @empty
            <div class="text-center">
                <p class="text-[17px] text-stone">Pronto publicaremos nuestros servicios.</p>
                <div class="mt-6"><x-button variant="primary" :href="route('leads.create')">Contáctanos</x-button></div>
            </div>
        @endforelse
    </section>

    {{-- CTA --}}
    <section class="mx-auto max-w-[var(--container-content)] px-6 pb-24">
        <div class="rounded-brand-xl bg-brand-primary bg-gradient-to-br from-black/20 to-white/10 px-8 py-16 text-center shadow-lg sm:px-12">
            <h2 class="font-brand-heading text-[clamp(28px,4vw,40px)] font-extrabold leading-tight text-on-brand-primary">¿No sabes por dónde empezar?</h2>
            <p class="mx-auto mt-4 max-w-[560px] text-lg leading-relaxed text-on-brand-primary/75">Cuéntanos tu proyecto y te orientamos sin compromiso.</p>
            <div class="mt-8 flex justify-center"><x-button variant="primary" :href="route('leads.create')">Agenda una Asesoría</x-button></div>
        </div>
    </section>
    @endif
</x-layouts.public>
