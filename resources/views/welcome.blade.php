@php
    $cms = app(\App\Services\Frontend\FrontendPageRenderer::class)->render('home');
@endphp
<x-layouts.public :seo="$cms['seo']">
    @if (! $cms['fallback'])
        {{-- Home publicado desde el CMS (RFC-076): renderiza el snapshot. --}}
        @include('frontend.render', ['sections' => $cms['sections']])
    @else
    {{-- Hero: MISMO partial y MISMO presenter que el contenido publicado
         (C-B-1). La variante `featured` conserva el aspecto propio de la
         portada — alto completo, tipografía mayor y su degradado — así que
         unificar el renderer no cambia cómo se ve el sitio. --}}
    @include('frontend.sections.hero', ['s' => $cms['hero'], 'sectionKey' => 'hero'])

    {{-- Buscador: flotante superpuesto en desktop, tarjeta en flujo en mobile --}}
    <div class="relative z-20 mx-auto -mt-8 w-full max-w-[1140px] px-6 lg:-mt-16">
        <form method="GET" action="{{ route('inmuebles.index') }}">
            <div class="flex flex-col gap-3 rounded-brand-lg bg-white p-4 shadow-xl
                        lg:flex-row lg:items-center lg:gap-0">

                @php
                    $sel = 'appearance-none bg-transparent border-none p-0 pr-6 font-brand-heading text-sm font-semibold text-brand-primary-ink cursor-pointer w-full outline-none lg:pr-0';
                    // mobile: cada campo es una tarjeta con borde + chevron; desktop: divisor vertical.
                    $field = 'relative flex flex-col gap-1 cursor-pointer rounded-brand-md border border-cloud px-3.5 py-2.5 lg:flex-1 lg:rounded-none lg:border-0 lg:px-3 lg:py-1';
                    $chevron = '<svg class="pointer-events-none absolute right-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-stone lg:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>';
                @endphp

                <label class="{{ $field }}">
                    <span class="eyebrow text-stone">Operación</span>
                    <select name="operacion" id="nh-op" class="{{ $sel }}">
                        <option value="">Cualquiera</option>
                        <option value="venta">Venta</option>
                        <option value="renta">Renta</option>
                    </select>
                    {!! $chevron !!}
                </label>

                <label class="{{ $field }} lg:border-l lg:border-fog">
                    <span class="eyebrow text-stone">Tipo</span>
                    <select name="tipo" class="{{ $sel }}">
                        <option value="">Todos los tipos</option>
                        @foreach ($typeOptions as $type)
                            <option value="{{ $type->value }}">{{ $type->label() }}</option>
                        @endforeach
                    </select>
                    {!! $chevron !!}
                </label>

                <label class="{{ $field }} lg:border-l lg:border-fog">
                    <span class="eyebrow text-stone">Zona</span>
                    <select name="zona" class="{{ $sel }}">
                        <option value="">Todas las zonas</option>
                        @foreach ($searchZones as $zone)
                            <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                        @endforeach
                    </select>
                    {!! $chevron !!}
                </label>

                <label class="{{ $field }} lg:border-l lg:border-fog">
                    <span class="eyebrow text-stone">Precio</span>
                    <select name="precio" id="nh-precio" class="{{ $sel }}">
                        {{-- Cargado por JS según operación --}}
                    </select>
                    {!! $chevron !!}
                </label>

                <x-button type="submit" variant="primary" class="w-full lg:ml-2 lg:w-auto lg:shrink-0 lg:px-8">
                    Buscar
                </x-button>
            </div>
        </form>
    </div>

        <script>
        (function () {
            const NhPrices = {
                default: [
                    { value: '', label: 'Cualquier precio' },
                    { value: '0-1500000', label: 'Hasta $1,500,000' },
                    { value: '1500000-3000000', label: '$1.5M – $3M' },
                    { value: '3000000-6000000', label: '$3M – $6M' },
                    { value: '6000000+', label: 'Más de $6M' },
                ],
                renta: [
                    { value: '', label: 'Cualquier precio' },
                    { value: '0-15000', label: 'Hasta $15,000/mes' },
                    { value: '15000-30000', label: '$15k – $30k/mes' },
                    { value: '30000-60000', label: '$30k – $60k/mes' },
                    { value: '60000+', label: 'Más de $60k/mes' },
                ],
            };

            function fillPrices(opValue, selectEl, current) {
                const ranges = opValue === 'renta' ? NhPrices.renta : NhPrices.default;
                selectEl.innerHTML = ranges
                    .map(r => `<option value="${r.value}"${r.value === current ? ' selected' : ''}>${r.label}</option>`)
                    .join('');
            }

            const opSel = document.getElementById('nh-op');
            const priceSel = document.getElementById('nh-precio');
            if (opSel && priceSel) {
                fillPrices(opSel.value, priceSel, '');
                opSel.addEventListener('change', () => fillPrices(opSel.value, priceSel, ''));
            }
        })();
        </script>

    {{-- ===== Servicios ===== --}}
    <section class="mx-auto max-w-[var(--container-content)] px-6 pt-32 pb-24">
        <div class="mx-auto mb-14 max-w-[640px] text-center">
            <p class="eyebrow text-brand-accent-ink">Qué hacemos</p>
            <h2 class="mt-4 font-brand-heading text-[clamp(28px,4vw,40px)] font-bold leading-tight text-brand-primary-ink">Cuatro disciplinas, un solo equipo</h2>
            <p class="mt-4 text-[17px] leading-relaxed text-stone">
                Del terreno a la entrega de llaves: arquitectura, construcción, comercialización e inversión bajo un mismo estándar.
            </p>
        </div>
        @php
            // Servicios activos configurados para home (RFC-074, §16.6). Sin
            // configuración, el servicio devuelve el fallback actual.
            $homeServices = app(\App\Services\Frontend\FrontendServicesService::class)->services('home');
            $iconPaths = (array) config('frontend-sections.service_icons');
        @endphp
        @if (! empty($homeServices))
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($homeServices as $service)
                    <div class="rounded-brand-lg bg-white p-8 shadow-sm transition-all duration-[350ms] ease-[var(--ease-out-expo)] hover:-translate-y-1 hover:shadow-lg">
                        <div class="mb-6 flex h-13 w-13 items-center justify-center rounded-brand-md bg-navy-50 p-3.5">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $iconPaths[$service['icon']]['path'] ?? $iconPaths['trending-up']['path'] }}"/></svg>
                        </div>
                        <h3 class="font-brand-heading text-xl font-semibold text-brand-primary-ink">{{ $service['title'] }}</h3>
                        <p class="mt-2.5 text-[15px] leading-relaxed text-stone">{{ $service['short_description'] }}</p>
                        <a href="{{ $service['cta']['url'] ?? route('servicios') }}" class="mt-4 inline-block font-brand-heading text-[13px] font-bold uppercase tracking-wide text-brand-accent-ink transition-colors hover:brightness-110">Conocer más →</a>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    {{-- ===== Inmuebles destacados ===== --}}
    @if (isset($featured) && $featured->isNotEmpty())
        <section class="mx-auto max-w-[var(--container-content)] px-6 pt-16 pb-24">
            <div class="mb-10 flex flex-wrap items-end justify-between gap-6">
                <div>
                    <p class="eyebrow text-brand-accent-ink">Selección New Hauz</p>
                    <h2 class="mt-3.5 font-brand-heading text-[clamp(28px,4vw,40px)] font-bold leading-tight text-brand-primary-ink">Inmuebles destacados</h2>
                </div>
                <x-button variant="secondary" :href="route('inmuebles.index')">Ver todo el catálogo</x-button>
            </div>
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($featured as $property)
                    <x-property-card
                        :title="$property->title"
                        :zone="$property->zone?->name ?? 'Querétaro'"
                        :price="$property->priceLabel()"
                        :operation="$property->operation_type->label()"
                        :beds="$property->bedrooms"
                        :baths="$property->bathrooms"
                        :area="$property->displayArea()"
                        :parking="$property->parking_spaces"
                        :href="route('inmuebles.show', $property->slug)"
                        :image="$property->getFirstMediaUrl('cover', 'web') ?: null" />
                @endforeach
            </div>
        </section>
    @endif

    {{-- ===== Oportunidades de inversión ===== --}}
    @if (isset($opportunities) && $opportunities->isNotEmpty())
        <section class="px-6 pb-10">
            <div class="mx-auto max-w-[var(--container-content)] rounded-brand-xl border border-orange-100 bg-orange-50 px-8 py-14 sm:px-12">
                <div class="mb-10 flex flex-wrap items-end justify-between gap-6">
                    <div>
                        <p class="eyebrow mb-3.5 inline-flex items-center gap-2 text-orange-600">
                            <svg class="h-4 w-4 text-brand-accent-ink" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 17l6-6 4 4 7-7"/><path d="M17 8h4v4"/></svg>
                            Alto potencial de plusvalía
                        </p>
                        <h2 class="font-brand-heading text-[clamp(28px,4vw,40px)] font-bold leading-tight text-brand-primary-ink">Oportunidades de inversión</h2>
                        <p class="mt-2.5 max-w-[520px] text-base leading-relaxed text-[#8a6a1e]">
                            Inmuebles seleccionados por nuestro equipo con el mejor potencial de retorno en zonas de alto crecimiento.
                        </p>
                    </div>
                    <x-button variant="primary" :href="route('inmuebles.index')">Ver oportunidades</x-button>
                </div>
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($opportunities as $property)
                        <x-property-card
                            class="ring-2 ring-brand-accent"
                            :title="$property->title"
                            :zone="$property->zone?->name ?? 'Querétaro'"
                            :price="$property->priceLabel()"
                            :operation="$property->operation_type->label()"
                            :beds="$property->bedrooms"
                            :baths="$property->bathrooms"
                            :area="$property->displayArea()"
                            :parking="$property->parking_spaces"
                            :href="route('inmuebles.show', $property->slug)"
                            :image="$property->getFirstMediaUrl('cover', 'web') ?: null" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ===== Proyectos destacados — brandeado A-74 Arquitectura ===== --}}
    @if ($featuredProjects->isNotEmpty())
    <section class="bg-[linear-gradient(180deg,#e0e0e0_0%,#f0f0f0_50%,#dcdcdc_100%)] px-6 pt-16 pb-24">
        <div class="mx-auto max-w-[var(--container-content)]">

            {{-- Header A-74 — misma anatomía que nosotros/equipo --}}
            <div class="mb-8 flex flex-wrap items-center gap-6 rounded-brand-lg border border-black/5 bg-white px-8 py-5 shadow-md">
                <img src="{{ asset('images/brand/a74-arquitectura.png') }}" alt="A-74 Arquitectura" class="h-16 w-auto">
                <div class="flex-1">
                    <p class="eyebrow text-stone">Despacho de arquitectura · New Hauz</p>
                    <h2 class="mt-1.5 font-brand-heading text-[clamp(22px,3vw,32px)] font-bold leading-tight text-brand-primary-ink">Proyectos destacados</h2>
                </div>
                <a href="{{ route('proyectos') }}"
                   class="hidden shrink-0 font-brand-heading text-sm font-semibold text-brand-accent-ink transition-colors hover:text-orange-hover sm:block">
                    Ver todos los proyectos →
                </a>
            </div>

            @php
                // El grid se reparte según cuántos destacados haya (máx. 4).
                $cols = match ($featuredProjects->count()) {
                    1 => 'grid-cols-1',
                    2 => 'sm:grid-cols-2',
                    3 => 'sm:grid-cols-2 lg:grid-cols-3',
                    default => 'sm:grid-cols-2 lg:grid-cols-4',
                };
            @endphp
            <div class="grid gap-6 {{ $cols }}">
                @foreach ($featuredProjects as $project)
                    @php $cover = $project->getFirstMediaUrl('cover', 'web') ?: null; @endphp
                    <a href="{{ route('proyectos.show', $project->slug) }}" class="group relative block min-h-[380px] overflow-hidden rounded-brand-lg shadow-sm">
                        @if ($cover)
                            <div class="absolute inset-0 bg-cover bg-center transition-transform duration-500 ease-[var(--ease-out-expo)] group-hover:scale-105" style="background-image:url('{{ $cover }}')"></div>
                        @else
                            <div class="absolute inset-0 bg-brand-primary bg-gradient-to-br from-black/20 to-white/10 transition-transform duration-500 ease-[var(--ease-out-expo)] group-hover:scale-105"></div>
                        @endif
                        <div class="absolute inset-0 bg-[linear-gradient(180deg,rgba(5,15,56,0)_35%,rgba(5,15,56,0.85)_100%)]"></div>
                        <div class="absolute inset-x-7 bottom-7">
                            @if ($project->projectType)
                                <span class="inline-block rounded-full bg-brand-accent/95 px-3 py-1.5 font-brand-heading text-[11px] font-bold uppercase tracking-wide text-on-brand-accent">{{ $project->projectType->label }}</span>
                            @endif
                            <h3 class="mt-3.5 font-brand-heading text-2xl font-bold text-on-brand-primary">{{ $project->title }}</h3>
                            @if ($project->description)
                                <p class="mt-1.5 line-clamp-2 text-sm text-on-brand-primary/80">{{ $project->description }}</p>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- ===== Inversionistas ===== --}}
    <section class="px-6 pb-24">
        <div class="relative mx-auto max-w-[var(--container-content)] overflow-hidden rounded-brand-xl bg-brand-primary">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_85%_20%,rgba(246,163,0,0.14),transparent_50%)]"></div>
            <div class="relative grid gap-12 px-8 py-16 sm:px-14 lg:grid-cols-2">
                <div>
                    <p class="eyebrow mb-5 text-accent-on-brand-primary">Inversionistas</p>
                    <h2 class="font-brand-heading text-[clamp(28px,3.6vw,38px)] font-extrabold leading-tight text-on-brand-primary">Invierte en donde otros solo ven Tierra.</h2>
                    <p class="mt-5 max-w-[480px] text-[17px] leading-relaxed text-on-brand-primary/75">
                        Creamos valor donde otros ven metros cuadrados. Accede a oportunidades opcionadas con análisis de plusvalía y acompañamiento patrimonial.
                    </p>
                    <div class="mt-8"><x-button variant="primary" href="#">Agenda una Asesoría</x-button></div>
                </div>
                <div class="flex flex-col justify-center gap-7">
                    @foreach ([
                        ['+12%', 'Plusvalía anual promedio en zonas de Querétaro que operamos.'],
                        ['+150', 'Operaciones cerradas acompañando a inversionistas y familias.'],
                        ['100%', 'Acompañamiento legal y patrimonial en cada operación.'],
                    ] as $i => [$num, $txt])
                        @if ($i > 0)<div class="h-px bg-white/10"></div>@endif
                        <div class="flex items-start gap-5">
                            <span class="min-w-[96px] font-brand-heading text-4xl font-extrabold leading-none text-accent-on-brand-primary">{{ $num }}</span>
                            <p class="pt-1.5 text-[15px] leading-snug text-on-brand-primary/75">{{ $txt }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- ===== Partners ===== --}}
    <section class="mx-auto max-w-[var(--container-content)] px-6 pb-24">
        <p class="eyebrow mb-10 text-center text-stone">Confían en nosotros</p>
        <div class="grid grid-cols-2 gap-5 sm:grid-cols-3 lg:grid-cols-5">
            @for ($i = 0; $i < 5; $i++)
                <div class="flex h-18 items-center justify-center rounded-brand-md border border-cloud bg-white py-6 font-brand-heading font-bold tracking-wide text-mist">PARTNER</div>
            @endfor
        </div>
    </section>

    {{-- ===== CTA final ===== --}}
    <section class="mx-auto max-w-[var(--container-content)] px-6 pb-28">
        <div class="rounded-brand-xl bg-brand-accent bg-gradient-to-br from-white/15 via-transparent to-black/15 px-8 py-16 text-center shadow-lg sm:px-12">
            <h2 class="font-brand-heading text-[clamp(28px,4vw,40px)] font-extrabold leading-tight text-on-brand-accent">¿Listo para tu próxima inversión?</h2>
            <p class="mx-auto mt-4 max-w-[560px] text-lg leading-relaxed text-on-brand-accent/80">
                Agenda una asesoría sin costo y déjanos acompañarte en cada paso.
            </p>
            <div class="mt-8 flex flex-wrap justify-center gap-3.5">
                <x-button variant="dark" href="#">Agenda una cita</x-button>
                <a href="https://wa.me/524422722623" target="_blank" rel="noopener"
                   class="inline-flex h-[52px] items-center gap-2.5 rounded-brand-md bg-[#25d366] px-6 font-brand-heading text-[15px] font-semibold text-white shadow-[0_8px_20px_rgba(37,211,102,0.3)] transition hover:brightness-105">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M.057 24l1.687-6.163a11.867 11.867 0 0 1-1.587-5.945C.16 5.335 5.495 0 12.05 0a11.817 11.817 0 0 1 8.413 3.488 11.824 11.824 0 0 1 3.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 0 1-5.688-1.448L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884a9.86 9.86 0 0 0 1.51 5.26l-.999 3.648 3.477-1.219zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.612-.916-2.207-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.247-.694.247-1.289.173-1.413z"/></svg>
                    Escríbenos por WhatsApp
                </a>
            </div>
        </div>
    </section>
    @endif
</x-layouts.public>
