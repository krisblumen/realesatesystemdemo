{{--
    opportunity_properties (dynamic) — title?, eyebrow?, body?, primary_cta?;
    items are Property models resolved from the kernel (published +
    opportunity). Empty ⇒ hidden.

    Su botón lleva al catálogo FILTRADO a oportunidades, no al catálogo entero:
    mandar a todo el listado perdería en el camino justo lo que la sección
    promete. El filtro viaja dentro del CTA y lo aplica el mismo resolver que
    arma el resto de los enlaces del sitio.
--}}
@php $items = $s['items'] ?? []; @endphp
@if (! empty($items))
    <section class="px-6 py-10">
        <div class="mx-auto max-w-[var(--container-content)] rounded-brand-xl border border-orange-100 bg-orange-50 px-8 py-14 sm:px-12">
            {{-- Encabezado y salida al catálogo en la misma línea, alineados por
                 su base. La fila se dibuja si hay encabezado O botón: atarla al
                 título dejaría al botón sin lugar donde aparecer. --}}
            @if (($s['eyebrow'] ?? '') !== '' || ($s['title'] ?? '') !== '' || ($s['body'] ?? '') !== '' || ($s['primary_cta'] ?? null))
                <div class="mb-10">
                    {{-- El botón comparte la línea del TÍTULO, no la del bloque
                         entero: si el texto descriptivo estuviera en esta misma
                         columna, `items-end` lo bajaría hasta el pie del párrafo
                         y dejaría de leerse como la acción del encabezado. Por
                         eso la descripción va DEBAJO de la fila. --}}
                    <div class="flex flex-wrap items-end justify-between gap-x-8 gap-y-5">
                        <div class="max-w-[560px]">
                            @if (($s['eyebrow'] ?? '') !== '')
                                <p class="eyebrow {{ \App\Support\Frontend\SectionTypography::eyebrow($s) }} mb-3 text-orange-600">{{ $s['eyebrow'] }}</p>
                            @endif
                            @if (($s['title'] ?? '') !== '')
                                <h2 class="font-brand-heading text-[clamp(28px,4vw,40px)] {{ \App\Support\Frontend\SectionTypography::title($s) }} leading-tight text-brand-primary-ink">{{ $s['title'] }}</h2>
                            @endif
                        </div>

                        @if ($s['primary_cta'] ?? null)
                            {{-- En acento: es el color con el que el sitio marca
                                 la oportunidad, el mismo del anillo de estas
                                 tarjetas. --}}
                            @include('frontend.cta-button', ['cta' => $s['primary_cta'], 'variant' => 'primary'])
                        @endif
                    </div>

                    @if (($s['body'] ?? '') !== '')
                        <p class="mt-4 max-w-[560px] text-lg leading-relaxed text-stone">{{ $s['body'] }}</p>
                    @endif
                </div>
            @endif
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($items as $property)
                    <x-property-card
                        class="ring-2 ring-brand-accent"
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
        </div>
    </section>
@endif
