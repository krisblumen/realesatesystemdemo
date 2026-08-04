@php
    $cover   = $project->getFirstMediaUrl('cover', 'web') ?: null;
    $gallery = $project->getMedia('gallery');

    // Todas las imágenes para el lightbox: portada primero, luego galería.
    $allImages = collect();
    if ($cover) {
        $allImages->push(['url' => $cover, 'alt' => $project->title]);
    }
    foreach ($gallery as $img) {
        $allImages->push(['url' => $img->getUrl('web'), 'alt' => $project->title]);
    }
@endphp

<x-layouts.public :title="$project->title">

    {{-- Estilos del lightbox (backdrop no es accesible desde Tailwind v4) --}}
    <style>
        #nh-gallery { margin:0; padding:0; max-width:100vw; max-height:100dvh; width:100vw; height:100dvh; background:transparent; border:none; }
        #nh-gallery::backdrop { background:rgba(5,15,56,0.93); backdrop-filter:blur(6px); }
    </style>

    {{-- Hero con la portada del proyecto --}}
    <section class="relative overflow-hidden">
        @if ($cover)
            <div class="absolute inset-0 bg-cover bg-center" style="background-image:url('{{ $cover }}')"></div>
        @else
            <div class="absolute inset-0 bg-brand-primary bg-gradient-to-br from-black/25 via-transparent to-white/10"></div>
        @endif
        <div class="absolute inset-0 bg-gradient-to-br from-brand-primary/[0.90] via-brand-primary/[0.78] to-brand-primary/[0.72]"></div>

        <div class="relative mx-auto max-w-[var(--container-content)] px-6 py-20">
            <nav class="mb-8 flex flex-wrap items-center gap-2.5 text-sm text-on-brand-primary/60">
                <a href="{{ url('/') }}" class="transition-colors hover:text-on-brand-primary">Inicio</a><span class="text-on-brand-primary/30">/</span>
                <a href="{{ route('proyectos') }}" class="transition-colors hover:text-on-brand-primary">Proyectos</a><span class="text-on-brand-primary/30">/</span>
                <span class="font-semibold text-on-brand-primary">{{ $project->title }}</span>
            </nav>

            <div class="max-w-[760px]">
                <img src="{{ asset('images/brand/a74-arquitectura.png') }}" alt="A-74 Arquitectura"
                     class="mb-6 h-12 w-auto brightness-0 invert opacity-90">
                @if ($project->projectType)
                    <span class="inline-block rounded-full bg-brand-accent/95 px-3.5 py-1.5 font-brand-heading text-[11px] font-bold uppercase tracking-wide text-on-brand-accent">{{ $project->projectType->label }}</span>
                @endif
                <h1 class="mt-4 font-brand-heading text-[clamp(32px,5vw,52px)] font-extrabold leading-[1.06] tracking-tight text-on-brand-primary">{{ $project->title }}</h1>
            </div>
        </div>
    </section>

    {{-- Descripción --}}
    @if ($project->description)
        <section class="mx-auto max-w-[var(--container-content)] px-6 py-14">
            <div class="max-w-[760px]">
                <p class="eyebrow mb-4 text-accent-on-brand-primary">El proyecto</p>
                <p class="whitespace-pre-line text-[17px] leading-relaxed text-graphite">{{ $project->description }}</p>
            </div>
        </section>
    @endif

    {{-- Galería --}}
    @if ($allImages->isNotEmpty())
        <section class="mx-auto max-w-[var(--container-content)] px-6 pb-20">
            <p class="eyebrow mb-5 text-brand-accent-ink">Galería</p>

            {{-- Desktop: mosaico tipo masonry (alto natural, click → lightbox) --}}
            <div class="hidden lg:block lg:columns-3 lg:gap-3.5">
                @foreach ($allImages as $i => $img)
                    <button type="button" onclick="nhGallery.open({{ $i }})"
                            class="group relative mb-3.5 block w-full break-inside-avoid overflow-hidden rounded-brand-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-focus focus:ring-offset-2">
                        <img src="{{ $img['url'] }}" alt="{{ $img['alt'] }}"
                             class="block w-full transition-transform duration-500 group-hover:scale-105" loading="lazy">
                    </button>
                @endforeach
            </div>

            {{-- Mobile: una foto por vista con swipe + tabs; tap abre la foto completa --}}
            <div class="lg:hidden" data-carousel data-swipe>
                <div class="overflow-hidden rounded-brand-lg shadow-sm">
                    <div class="flex transition-transform duration-500 ease-[var(--ease-out-expo)]" data-track>
                        @foreach ($allImages as $i => $img)
                            <button type="button" onclick="nhGallery.open({{ $i }})"
                                    class="relative aspect-[4/3] w-full shrink-0 focus:outline-none">
                                <img src="{{ $img['url'] }}" alt="{{ $img['alt'] }}" class="h-full w-full object-cover" loading="lazy">
                                <span class="absolute bottom-3 right-3 flex h-9 w-9 items-center justify-center rounded-full bg-brand-primary/65 text-on-brand-primary backdrop-blur-sm">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>
                                </span>
                            </button>
                        @endforeach
                    </div>
                </div>
                @if ($allImages->count() > 1)
                    @include('site.partials.project-nav', ['count' => $allImages->count()])
                @endif
            </div>
        </section>
    @endif

    {{-- Proyectos relacionados --}}
    @if ($related->isNotEmpty())
        <section class="bg-[linear-gradient(180deg,#e0e0e0_0%,#f0f0f0_50%,#dcdcdc_100%)] px-6 py-16">
            <div class="mx-auto max-w-[var(--container-content)]">
                <h2 class="mb-8 font-brand-heading text-2xl font-bold text-brand-primary-ink">Otros proyectos</h2>
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($related as $project)
                        @include('site.partials.project-card')
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- CTA --}}
    <section class="mx-auto max-w-[var(--container-content)] px-6 py-24">
        <div class="rounded-brand-xl bg-[linear-gradient(135deg,#2e2e2e_0%,#4a4a4a_35%,#383838_65%,#525252_100%)] px-8 py-16 text-center shadow-lg sm:px-12">
            <h2 class="font-brand-heading text-[clamp(28px,4vw,40px)] font-extrabold leading-tight text-white">¿Tienes un terreno o un proyecto en mente?</h2>
            <p class="mx-auto mt-4 max-w-[560px] text-lg leading-relaxed text-white/75">Conversemos cómo convertirlo en realidad, del diseño a la entrega.</p>
            <div class="mt-8 flex justify-center"><x-button variant="primary" :href="route('leads.create')">Agenda una cita</x-button></div>
        </div>
    </section>

    {{-- Lightbox --}}
    @if ($allImages->isNotEmpty())
        <dialog id="nh-gallery" aria-label="Galería del proyecto">
            <div class="relative flex h-full w-full items-center justify-center p-4">
                <button type="button" onclick="nhGallery.close()"
                        class="absolute right-4 top-4 z-10 flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white backdrop-blur-sm transition hover:bg-white/25"
                        aria-label="Cerrar galería">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>

                <div id="nh-gallery-counter"
                     class="absolute left-4 top-4 z-10 rounded-full bg-white/10 px-3.5 py-1.5 font-brand-heading text-sm font-semibold text-white backdrop-blur-sm">
                    1 / {{ $allImages->count() }}
                </div>

                <button type="button" id="nh-gallery-prev" onclick="nhGallery.prev()"
                        class="absolute left-4 top-1/2 z-10 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-white backdrop-blur-sm transition hover:bg-white/25 focus:outline-none"
                        aria-label="Foto anterior">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m15 18-6-6 6-6"/></svg>
                </button>

                <img id="nh-gallery-img" src="" alt=""
                     class="max-h-[88dvh] max-w-[calc(100vw-120px)] rounded-brand-md object-contain shadow-2xl">

                <button type="button" id="nh-gallery-next" onclick="nhGallery.next()"
                        class="absolute right-4 top-1/2 z-10 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-white backdrop-blur-sm transition hover:bg-white/25 focus:outline-none"
                        aria-label="Foto siguiente">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m9 18 6-6-6-6"/></svg>
                </button>
            </div>
        </dialog>

        <script>
        const nhGallery = (() => {
            const images  = @json($allImages->values());
            const dialog  = document.getElementById('nh-gallery');
            const img     = document.getElementById('nh-gallery-img');
            const counter = document.getElementById('nh-gallery-counter');
            const prevBtn = document.getElementById('nh-gallery-prev');
            const nextBtn = document.getElementById('nh-gallery-next');
            let current   = 0;

            function render() {
                img.src = images[current].url;
                img.alt = images[current].alt;
                counter.textContent = `${current + 1} / ${images.length}`;
                const single = images.length < 2;
                prevBtn.hidden = single;
                nextBtn.hidden = single;
            }

            function open(index) {
                current = ((index % images.length) + images.length) % images.length;
                render();
                dialog.showModal();
            }

            function close() { dialog.close(); }
            function prev() { current = (current - 1 + images.length) % images.length; render(); }
            function next() { current = (current + 1) % images.length; render(); }

            document.addEventListener('keydown', e => {
                if (!dialog.open) return;
                if (e.key === 'ArrowLeft')  { e.preventDefault(); prev(); }
                if (e.key === 'ArrowRight') { e.preventDefault(); next(); }
            });

            dialog.addEventListener('click', e => { if (e.target === dialog) close(); });

            let touchX = 0;
            dialog.addEventListener('touchstart', e => { touchX = e.touches[0].clientX; }, { passive: true });
            dialog.addEventListener('touchend', e => {
                const delta = touchX - e.changedTouches[0].clientX;
                if (Math.abs(delta) > 48) delta > 0 ? next() : prev();
            });

            return { open, close, prev, next };
        })();
        </script>
    @endif

    @include('site.partials.carousel-script')
</x-layouts.public>
