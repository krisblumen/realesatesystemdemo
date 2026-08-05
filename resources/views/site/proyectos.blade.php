@php
    $cms = app(\App\Services\Frontend\FrontendPageRenderer::class)->render('proyectos');
@endphp
<x-layouts.public title="Proyectos · A-74 Arquitectura" :seo="$cms['seo']">
    @if (! $cms['fallback'])
        {{-- Página publicada desde el CMS (RFC-076): renderiza el snapshot. --}}
        @include('frontend.render', ['sections' => $cms['sections']])
    @else
    {{-- Hero: MISMO partial y MISMO presenter que el contenido publicado.
         Antes esta rama tenía su propio hero —con su propio carrusel inline
         (`#hero-carousel` + el <script> del pie) y su propio logo grande con
         un `style=` inline— así que la primera visita no recibía ni el
         fallback por página, ni el modo A/B, ni las garantías de CSP
         (C-B-1). Las migas de pan son chrome de la página y viajan como
         datos, nunca como markup. --}}
    @include('frontend.sections.hero', [
        's' => $cms['hero'],
        'sectionKey' => 'hero',
        'breadcrumbs' => [
            ['label' => 'Inicio', 'url' => url('/')],
            ['label' => 'Proyectos'],
        ],
    ])

    {{-- Grid brandeado A-74 --}}
    <section class="bg-[linear-gradient(180deg,#e0e0e0_0%,#f0f0f0_50%,#dcdcdc_100%)] px-6 py-16">
        <div class="mx-auto max-w-[var(--container-content)]">

            {{-- Header A-74 --}}
            <div class="mb-8 flex flex-wrap items-center gap-6 rounded-brand-lg border border-black/5 bg-white px-8 py-5 shadow-md">
                <img src="{{ asset('images/brand/a74-arquitectura.png') }}" alt="A-74 Arquitectura" class="h-16 w-auto">
                <div class="flex-1">
                    <p class="eyebrow text-stone">Despacho de arquitectura · Landra</p>
                    <h2 class="mt-1.5 font-brand-heading text-[clamp(18px,2.4vw,26px)] font-bold text-brand-primary-ink">
                        A-74 lleva cada proyecto del concepto arquitectónico a la obra terminada.
                    </h2>
                </div>
            </div>

            @if ($projects->isEmpty())
                <div class="rounded-brand-lg border border-black/5 bg-white p-16 text-center shadow-sm">
                    <p class="font-brand-heading text-lg font-semibold text-brand-primary-ink">Pronto publicaremos nuestros proyectos</p>
                    <p class="mt-2 text-sm text-stone">Estamos preparando el portafolio de A-74 Arquitectura.</p>
                </div>
            @else
                @php $pages = $projects->chunk(6); @endphp

                {{-- Desktop: carrusel de páginas de 6 (grid 3 columnas) --}}
                <div class="hidden lg:block" data-carousel>
                    <div class="overflow-hidden">
                        <div class="flex transition-transform duration-500 ease-[var(--ease-out-expo)]" data-track>
                            @foreach ($pages as $pageProjects)
                                <div class="grid w-full shrink-0 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                                    @foreach ($pageProjects as $project)
                                        @include('site.partials.project-card')
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>
                    @if ($pages->count() > 1)
                        @include('site.partials.project-nav', ['count' => $pages->count()])
                    @endif
                </div>

                {{-- Mobile: carrusel de 1 tarjeta por vista, con swipe --}}
                <div class="lg:hidden" data-carousel data-swipe>
                    <div class="overflow-hidden">
                        <div class="flex transition-transform duration-500 ease-[var(--ease-out-expo)]" data-track>
                            @foreach ($projects as $project)
                                <div class="w-full shrink-0">
                                    @include('site.partials.project-card')
                                </div>
                            @endforeach
                        </div>
                    </div>
                    @if ($projects->count() > 1)
                        @include('site.partials.project-nav', ['count' => $projects->count()])
                    @endif
                </div>

                @include('site.partials.carousel-script')
            @endif
        </div>
    </section>

    {{-- CTA --}}
    <section class="mx-auto max-w-[var(--container-content)] px-6 py-24">
        <div class="rounded-brand-xl bg-[linear-gradient(135deg,#2e2e2e_0%,#4a4a4a_35%,#383838_65%,#525252_100%)] px-8 py-16 text-center shadow-lg sm:px-12">
            <h2 class="font-brand-heading text-[clamp(28px,4vw,40px)] font-extrabold leading-tight text-white">¿Tienes un terreno o un proyecto en mente?</h2>
            <p class="mx-auto mt-4 max-w-[560px] text-lg leading-relaxed text-white/75">Conversemos cómo convertirlo en realidad, del diseño a la entrega.</p>
            <div class="mt-8 flex justify-center"><x-button variant="primary" :href="route('leads.create')">Agenda una cita</x-button></div>
        </div>
    </section>
    @endif
</x-layouts.public>
