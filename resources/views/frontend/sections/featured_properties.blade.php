{{--
    featured_properties (dynamic) — title?, eyebrow?, primary_cta?; items are Property models
    resolved from the kernel (published + featured). Empty ⇒ nothing rendered.
--}}
@php $items = $s['items'] ?? []; @endphp
@if (! empty($items))
    <section class="mx-auto max-w-[var(--container-content)] px-6 py-16">
        {{-- Encabezado y salida al catálogo, EN LA MISMA LÍNEA: el título a la
             izquierda y el botón a la derecha, alineados por su base para que no
             floten uno respecto del otro.

             La fila se dibuja si hay encabezado O si hay botón. Condicionarla
             sólo al título dejaría el botón sin lugar donde aparecer en una
             sección sin encabezado.

             En pantallas angostas `flex-wrap` manda el botón abajo: a 375 px,
             título y botón en la misma línea no entran sin que uno de los dos
             quede ilegible. --}}
        @if (($s['eyebrow'] ?? '') !== '' || ($s['title'] ?? '') !== '' || ($s['primary_cta'] ?? null))
            <div class="mb-10 flex flex-wrap items-end justify-between gap-x-8 gap-y-5">
                <div class="max-w-[640px]">
                    @if (($s['eyebrow'] ?? '') !== '')
                        <p class="eyebrow {{ \App\Support\Frontend\SectionTypography::eyebrow($s) }} text-brand-accent-ink">{{ $s['eyebrow'] }}</p>
                    @endif
                    @if (($s['title'] ?? '') !== '')
                        <h2 class="mt-3 font-brand-heading text-[clamp(26px,3.4vw,36px)] {{ \App\Support\Frontend\SectionTypography::title($s) }} leading-snug tracking-tight text-brand-primary-ink">{{ $s['title'] }}</h2>
                    @endif
                </div>

                {{-- La salida al catálogo completo. Sin esto la sección es un
                     callejón: muestra unas pocas propiedades y no dice que haya
                     más.

                     Sale del payload como cualquier otro CTA, así que su URL la
                     resolvió el mismo resolver que la de todos los enlaces del
                     sitio. Si no resolvió —destino inválido— no se dibuja nada,
                     en vez de un botón que no lleva a ningún lado. --}}
                @if ($s['primary_cta'] ?? null)
                    {{-- `dark` es el color PRINCIPAL de la marca, y se tematiza:
                         sigue al primario que el cliente configure. --}}
                    @include('frontend.cta-button', ['cta' => $s['primary_cta'], 'variant' => 'dark'])
                @endif
            </div>
        @endif
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($items as $property)
                <x-property-card
                    :title="$property['title']"
                    :zone="$property['zone']"
                    :price="$property['price']"
                    :operation="$property['operation']"
                    :beds="$property['beds']"
                    :baths="$property['baths']"
                    :area="$property['area']"
                    :parking="$property['parking']"
                    :href="$property['href']"
                    :image="$property['image']" />
            @endforeach
        </div>
    </section>
@endif
