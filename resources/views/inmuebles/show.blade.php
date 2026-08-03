@php
    $zoneName = $property->zone?->name ?? 'Querétaro';
    $cover    = $property->getFirstMediaUrl('cover', 'web') ?: null;
    $gallery  = $property->getMedia('gallery');

    // Todas las imágenes para el lightbox: portada primero, luego galería.
    $allImages = collect();
    if ($cover) {
        $allImages->push(['url' => $cover, 'alt' => $property->title]);
    }
    foreach ($gallery as $img) {
        $allImages->push(['url' => $img->getUrl('web'), 'alt' => $property->title]);
    }

    $thumbs    = $allImages->skip(1);   // panel derecho: todo menos la portada
    $maxThumbs = 8;                     // máximo de thumbs visibles en el panel
    $extraCount = max(0, $thumbs->count() - $maxThumbs);

    // Asesor asignado al inmueble — su contacto va en el CTA de WhatsApp.
    $agent       = $property->agent;
    $agentName   = $agent?->name;
    $agentAvatar = $agent?->avatar
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($agent->avatar)
        : null;
    $agentPhone  = $agent?->whatsapp ?: $agent?->phone;       // mostrado al usuario
    // Número para wa.me: sólo dígitos; si vienen 10 (MX local) anteponer 52.
    $waDigits = $agentPhone ? preg_replace('/\D+/', '', $agentPhone) : null;
    if ($waDigits && strlen($waDigits) === 10) {
        $waDigits = '52'.$waDigits;
    }
    $waNumber = $waDigits ?: '524422722623';                 // fallback institucional

    // Iniciales para el avatar de respaldo cuando el asesor no tiene foto.
    $agentInitials = $agentName
        ? collect(explode(' ', $agentName))->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode('')
        : 'NH';

    // Mensaje de WhatsApp al asesor del inmueble: saludo con su nombre de pila, tipo +
    // título + precio, y cierre según la operación (venta/renta).
    $agentFirstName = $agentName ? explode(' ', trim($agentName))[0] : null;
    $waSaludo = $agentFirstName ? "Hola {$agentFirstName}" : 'Hola';
    $waCola   = $property->operation_type->value === 'renta' ? 'en renta' : 'a la venta';
    $waText   = rawurlencode("{$waSaludo}, me interesa {$property->property_type->label()} {$property->title} ({$property->priceLabel()}). ¿Aún sigue {$waCola}?");

    // Preview al compartir (og:description): operación, zona y precio —
    // lo primero que se lee en la tarjeta de WhatsApp/Facebook.
    $specSummary = collect([
        $property->bedrooms ? "{$property->bedrooms} rec" : null,
        $property->bathrooms ? "{$property->bathrooms} baños" : null,
    ])->filter()->implode(' · ');
    $ogDescription = "{$property->operation_type->label()} en {$zoneName} · {$property->priceLabel()}"
        .($specSummary ? " · {$specSummary}" : '');

    // Texto para el botón "Compartir" (Web Share API / WhatsApp).
    $shareText = "{$property->title} · {$zoneName} · {$property->operation_type->label()} — {$property->priceLabel()}";

    // og:image necesita URL absoluta: el disco 'public' devuelve rutas
    // relativas ("/storage/..."), así que se completan con el host actual.
    $ogImage = $cover ? url($cover) : null;
@endphp

<x-layouts.public
    :title="$property->title"
    :description="$ogDescription"
    :image="$ogImage"
    :image-alt="$property->title"
    :floating-whatsapp="false">

    {{-- Estilos del lightbox (backdrop no es accesible desde Tailwind v4) --}}
    <style>
        #nh-gallery { margin:0; padding:0; max-width:100vw; max-height:100dvh; width:100vw; height:100dvh; background:transparent; border:none; }
        #nh-gallery::backdrop { background:rgba(5,15,56,0.93); backdrop-filter:blur(6px); }
    </style>

    {{-- Breadcrumb --}}
    <div class="mx-auto max-w-[var(--container-content)] px-6 pt-7">
        <nav class="flex flex-wrap items-center gap-2.5 text-[13px]">
            <a href="{{ url('/') }}" class="text-stone hover:text-brand-primary-ink">Inicio</a><span class="text-mist">/</span>
            <a href="{{ route('inmuebles.index') }}" class="text-stone hover:text-brand-primary-ink">Inmobiliaria</a><span class="text-mist">/</span>
            <span class="text-stone">{{ $zoneName }}</span><span class="text-mist">/</span>
            <span class="font-semibold text-brand-primary-ink">{{ $property->title }}</span>
        </nav>
    </div>

    {{-- Galería --}}
    <section class="mx-auto max-w-[var(--container-content)] px-6 pt-5">
        <div class="grid gap-3.5 lg:grid-cols-[2fr_1fr]">

            {{-- Portada principal --}}
            <div class="relative aspect-[16/10] overflow-hidden rounded-[var(--radius-lg)] {{ $allImages->count() > 1 ? 'cursor-pointer' : '' }} group"
                 {{ $allImages->count() > 1 ? 'onclick=nhGallery.open(0)' : '' }}>
                @if ($cover)
                    <img src="{{ $cover }}" alt="{{ $property->title }}"
                         class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-[1.02]">
                @else
                    <div class="h-full w-full bg-brand-primary"></div>
                @endif
                <div class="absolute left-4 top-4 flex gap-2">
                    <x-badge color="orange" solid>{{ $property->operation_type->label() }}</x-badge>
                </div>
                @if ($allImages->count() > 1)
                    <div class="absolute bottom-4 right-4 flex items-center gap-1.5 rounded-full bg-black/60 px-3.5 py-1.5 text-xs font-semibold text-white backdrop-blur-sm">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
                        {{ $allImages->count() }} fotos
                    </div>
                @endif
            </div>

            {{-- Panel de thumbs (desktop) --}}
            @if ($thumbs->isNotEmpty())
                <div class="hidden lg:grid grid-cols-3 gap-1.5 content-start overflow-hidden" style="max-height:calc(100%)">
                    @foreach ($thumbs->take($maxThumbs) as $i => $img)
                        @php
                            $realIndex  = $i + 1; // índice en $allImages (0 = portada)
                            $isLastSlot = ($i === $maxThumbs - 1) && $extraCount > 0;
                        @endphp
                        <button type="button"
                                onclick="nhGallery.open({{ $realIndex }})"
                                class="relative aspect-square overflow-hidden rounded-[var(--radius-sm)] group focus:outline-none focus:ring-2 focus:ring-brand-focus focus:ring-offset-1">
                            <img src="{{ $img['url'] }}" alt="{{ $img['alt'] }}"
                                 class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105">
                            @if ($isLastSlot)
                                <div class="absolute inset-0 flex flex-col items-center justify-center gap-0.5 bg-black/65 text-white">
                                    <span class="font-brand-heading text-xl font-extrabold">+{{ $extraCount }}</span>
                                    <span class="text-[11px] font-semibold tracking-wide">fotos</span>
                                </div>
                            @endif
                        </button>
                    @endforeach
                </div>

                {{-- Botón galería en mobile --}}
                <button type="button" onclick="nhGallery.open(1)"
                        class="lg:hidden flex items-center justify-center gap-2 rounded-[var(--radius-md)] border border-cloud bg-white py-3 text-sm font-semibold text-brand-primary-ink shadow-sm hover:bg-fog transition">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
                    Ver {{ $allImages->count() }} fotos
                </button>
            @endif
        </div>
    </section>

    {{-- Contenido --}}
    <section class="mx-auto max-w-[var(--container-content)] px-6 pt-7 pb-24">
        <div class="grid gap-12 lg:grid-cols-[1.7fr_1fr]">
            <div>
                <div class="mb-7 flex flex-wrap items-start justify-between gap-5 border-b border-cloud pb-6">
                    <div>
                        <h1 class="font-brand-heading text-[clamp(26px,3.6vw,38px)] font-extrabold leading-tight text-brand-primary-ink">{{ $property->title }}</h1>
                        <p class="mt-2.5 flex items-center gap-1.5 text-base text-stone">
                            <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                            {{ $zoneName }}
                        </p>
                        <button type="button" onclick="nhShare()"
                                class="mt-3.5 inline-flex items-center gap-2 rounded-full border border-cloud bg-white px-4 py-2 text-sm font-semibold text-brand-primary-ink shadow-sm transition hover:bg-fog">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 10.5 6.8-3.9M8.6 13.5l6.8 3.9"/></svg>
                            Compartir ficha
                        </button>
                    </div>
                    <div class="text-right">
                        <p class="text-[13px] text-stone">Precio de {{ mb_strtolower($property->operation_type->label()) }}</p>
                        <p class="text-3xl font-extrabold text-brand-primary-ink">{{ $property->priceLabel() }}</p>
                        <p class="text-[13px] text-mist">MXN</p>
                    </div>
                </div>

                {{-- Specs --}}
                <div class="mb-10 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                    @foreach ([
                        ['Recámaras', $property->bedrooms],
                        ['Baños', $property->bathrooms],
                        ['Estac.', $property->parking_spaces],
                        ['Construcción', $property->construction_area ? (int) $property->construction_area.' m²' : null],
                        ['Terreno', $property->land_area ? (int) $property->land_area.' m²' : null],
                    ] as [$label, $value])
                        @if ($value)
                            <div class="rounded-[var(--radius-md)] border border-cloud bg-white p-4.5">
                                <p class="font-brand-eyebrow text-xl font-bold text-brand-primary-ink">{{ $value }}</p>
                                <p class="mt-0.5 text-[13px] text-stone">{{ $label }}</p>
                            </div>
                        @endif
                    @endforeach
                </div>

                {{-- Descripción --}}
                @if ($property->description)
                    <h2 class="mb-4 font-brand-heading text-2xl font-bold text-brand-primary-ink">Descripción</h2>
                    <div class="mb-10 space-y-3.5 text-[17px] leading-relaxed text-graphite">
                        {!! nl2br(e($property->description)) !!}
                    </div>
                @endif

                {{-- Características --}}
                @if ($property->features->isNotEmpty())
                    <h2 class="mb-4 font-brand-heading text-2xl font-bold text-brand-primary-ink">Características</h2>
                    <div class="mb-11 grid gap-x-6 gap-y-3 sm:grid-cols-2">
                        @foreach ($property->features as $feature)
                            <div class="flex items-center gap-3">
                                <span class="flex h-6 w-6 items-center justify-center rounded-[7px] bg-navy-50 text-brand-primary-ink">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                                </span>
                                <span class="text-[15px] text-graphite">{{ $feature->name }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Ubicación (solo zona — privacidad) --}}
                <h2 class="mb-4 font-brand-heading text-2xl font-bold text-brand-primary-ink">Ubicación</h2>
                <div class="flex items-center gap-4 rounded-[var(--radius-lg)] border border-cloud bg-navy-50 p-6">
                    <span class="flex h-12 w-12 flex-none items-center justify-center rounded-full bg-brand-accent text-on-brand-accent">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                    </span>
                    <div>
                        <p class="font-brand-heading font-semibold text-brand-primary-ink">{{ $zoneName }}</p>
                        <p class="mt-0.5 text-sm text-stone">La ubicación exacta se comparte con el asesor al agendar una visita.</p>
                    </div>
                </div>
            </div>

            {{-- Aside: contacto --}}
            <aside class="flex flex-col gap-5 lg:sticky lg:top-24 lg:self-start">
                <div class="rounded-[var(--radius-lg)] border border-cloud bg-white p-6 shadow-md">
                    <h3 class="font-brand-heading text-lg font-bold text-brand-primary-ink">Solicita información</h3>
                    <p class="mt-1 mb-5 text-sm text-stone">Un asesor te contacta hoy mismo.</p>

                    <livewire:leads.lead-capture-form
                        :property-id="$property->id"
                        source="web"
                        service-type="comercializacion"
                        :locked="true"
                    />

                    {{-- Asesor asignado al inmueble --}}
                    @if ($agentName)
                        <div class="mt-4 flex items-center gap-3 rounded-[var(--radius-md)] border border-cloud bg-fog/50 p-3">
                            @if ($agentAvatar)
                                <img src="{{ $agentAvatar }}" alt="{{ $agentName }}"
                                     class="h-12 w-12 shrink-0 rounded-full object-cover">
                            @else
                                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-brand-primary text-sm font-bold text-on-brand-primary">
                                    {{ $agentInitials }}
                                </span>
                            @endif
                            <div class="min-w-0">
                                <p class="eyebrow text-stone">Tu asesor</p>
                                <p class="truncate font-semibold text-brand-primary-ink">{{ $agentName }}</p>
                                @if ($agentPhone)
                                    <p class="text-sm text-stone">{{ $agentPhone }}</p>
                                @endif
                            </div>
                        </div>
                    @endif

                    <a href="https://wa.me/{{ $waNumber }}?text={{ $waText }}" target="_blank" rel="noopener"
                       class="mt-3 flex h-[52px] items-center justify-center gap-2.5 rounded-[var(--radius-md)] bg-[#25d366] text-[15px] font-semibold text-white shadow-[0_8px_20px_rgba(37,211,102,0.3)] transition hover:brightness-105">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M.057 24l1.687-6.163a11.867 11.867 0 0 1-1.587-5.945C.16 5.335 5.495 0 12.05 0a11.817 11.817 0 0 1 8.413 3.488 11.824 11.824 0 0 1 3.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 0 1-5.688-1.448L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884a9.86 9.86 0 0 0 1.51 5.26l-.999 3.648 3.477-1.219zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.612-.916-2.207-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.247-.694.247-1.289.173-1.413z"/></svg>
                        {{ $agentName ? 'WhatsApp con '.\Illuminate\Support\Str::of($agentName)->before(' ') : 'WhatsApp sobre esta propiedad' }}
                    </a>
                </div>
            </aside>
        </div>

        {{-- Relacionados --}}
        @if ($related->isNotEmpty())
            <div class="mt-20 border-t border-cloud pt-14">
                <h2 class="mb-8 font-brand-heading text-2xl font-bold text-brand-primary-ink">Inmuebles en {{ $zoneName }}</h2>
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($related as $item)
                        <x-property-card
                            :title="$item->title"
                            :zone="$item->zone?->name ?? 'Querétaro'"
                            :price="$item->priceLabel()"
                            :operation="$item->operation_type->label()"
                            :beds="$item->bedrooms"
                            :baths="$item->bathrooms"
                            :area="$item->displayArea()"
                            :parking="$item->parking_spaces"
                            :href="route('inmuebles.show', $item->slug)"
                            :image="$item->getFirstMediaUrl('cover', 'web') ?: null" />
                    @endforeach
                </div>
            </div>
        @endif
    </section>

    {{-- Lightbox --}}
    @if ($allImages->count() > 1)
        <dialog id="nh-gallery" aria-label="Galería de fotos">
            <div class="relative flex h-full w-full items-center justify-center p-4">

                {{-- Cerrar --}}
                <button type="button" onclick="nhGallery.close()"
                        class="absolute right-4 top-4 z-10 flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white backdrop-blur-sm transition hover:bg-white/25"
                        aria-label="Cerrar galería">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>

                {{-- Contador --}}
                <div id="nh-gallery-counter"
                     class="absolute left-4 top-4 z-10 rounded-full bg-white/10 px-3.5 py-1.5 text-sm font-semibold text-white backdrop-blur-sm">
                    1 / {{ $allImages->count() }}
                </div>

                {{-- Anterior --}}
                <button type="button" id="nh-gallery-prev" onclick="nhGallery.prev()"
                        class="absolute left-4 top-1/2 z-10 -translate-y-1/2 flex h-11 w-11 items-center justify-center rounded-full bg-white/10 text-white backdrop-blur-sm transition hover:bg-white/25 focus:outline-none"
                        aria-label="Foto anterior">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m15 18-6-6 6-6"/></svg>
                </button>

                {{-- Imagen activa --}}
                <img id="nh-gallery-img" src="" alt=""
                     class="max-h-[88dvh] max-w-[calc(100vw-120px)] rounded-[var(--radius-md)] object-contain shadow-2xl">

                {{-- Siguiente --}}
                <button type="button" id="nh-gallery-next" onclick="nhGallery.next()"
                        class="absolute right-4 top-1/2 z-10 -translate-y-1/2 flex h-11 w-11 items-center justify-center rounded-full bg-white/10 text-white backdrop-blur-sm transition hover:bg-white/25 focus:outline-none"
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

            // Escape es nativo del <dialog>; flechas del teclado
            document.addEventListener('keydown', e => {
                if (!dialog.open) return;
                if (e.key === 'ArrowLeft')  { e.preventDefault(); prev(); }
                if (e.key === 'ArrowRight') { e.preventDefault(); next(); }
            });

            // Click en el backdrop (el propio <dialog>, no su contenido) cierra
            dialog.addEventListener('click', e => { if (e.target === dialog) close(); });

            // Swipe en mobile
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

    <script>
        // Compartir la ficha: Web Share API en móvil/navegadores compatibles;
        // si no está disponible, fallback directo a WhatsApp Web.
        function nhShare() {
            const url = window.location.href;
            const text = @json($shareText);

            if (navigator.share) {
                navigator.share({ title: text, url }).catch(() => {});
                return;
            }

            window.open(`https://wa.me/?text=${encodeURIComponent(text + ' ' + url)}`, '_blank', 'noopener');
        }
    </script>

    {{-- SEO: datos estructurados --}}
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'RealEstateListing',
            'name' => $property->title,
            'url' => route('inmuebles.show', $property->slug),
            'offers' => ['@type' => 'Offer', 'price' => (string) (int) $property->price, 'priceCurrency' => 'MXN'],
            'address' => ['@type' => 'PostalAddress', 'addressLocality' => $zoneName, 'addressRegion' => 'Querétaro', 'addressCountry' => 'MX'],
            'numberOfRooms' => $property->bedrooms,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
</x-layouts.public>
