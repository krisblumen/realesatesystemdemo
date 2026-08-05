@php
    $cms = app(\App\Services\Frontend\FrontendPageRenderer::class)->render('nosotros');
@endphp
<x-layouts.public title="Nosotros" :seo="$cms['seo']">
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
            ['label' => 'Nosotros'],
        ],
    ])

    {{-- Stats --}}
    <section class="mx-auto max-w-[var(--container-content)] px-6 py-16">
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['+15', 'Años de trayectoria en el mercado queretano.'],
                ['+150', 'Operaciones cerradas con familias e inversionistas.'],
                ['+40', 'Proyectos de arquitectura y construcción entregados.'],
                ['4', 'Disciplinas integradas bajo un mismo estándar.'],
            ] as [$num, $txt])
                <div class="rounded-brand-lg bg-white p-8 shadow-sm">
                    <p class="font-brand-heading text-[44px] font-extrabold leading-none tracking-tight text-brand-primary-ink">{{ $num }}</p>
                    <p class="mt-2.5 text-[15px] text-stone">{{ $txt }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Historia --}}
    <section class="mx-auto max-w-[var(--container-content)] px-6 pb-16">
        <div class="grid items-center gap-14 lg:grid-cols-2">
            <div>
                <p class="eyebrow mb-4 text-brand-accent-ink">Nuestra historia</p>
                <h2 class="font-brand-heading text-[clamp(26px,3.4vw,36px)] font-bold leading-snug tracking-tight text-brand-primary-ink">De un despacho de arquitectura a una firma inmobiliaria integral.</h2>
                <p class="mt-5 text-base leading-relaxed text-stone">
                    Landra nació con una convicción: que diseñar, construir y comercializar un inmueble no deberían ser procesos separados. Empezamos proyectando casas a la medida en Juriquilla y, con cada entrega, nuestros clientes nos pidieron acompañarlos también en la inversión y la venta.
                </p>
                <p class="mt-4 text-base leading-relaxed text-stone">
                    Hoy integramos arquitectura, construcción, comercialización e inversión en una sola firma, con un único estándar de calidad y un trato cercano que distingue cada operación.
                </p>
            </div>
            <div class="min-h-[420px] overflow-hidden rounded-brand-lg shadow-lg">
                <img src="{{ asset('images/nosotros/sala_de_juntas.jpg') }}" alt="Sala de juntas de Landra"
                     class="h-full min-h-[420px] w-full object-cover">
            </div>
        </div>
    </section>

    {{-- Valores --}}
    <section class="border-y border-cloud bg-white">
        <div class="mx-auto max-w-[var(--container-content)] px-6 py-20">
            <div class="mb-12 max-w-[600px]">
                <p class="eyebrow mb-4 text-brand-accent-ink">Lo que nos guía</p>
                <h2 class="font-brand-heading text-[clamp(26px,3.4vw,36px)] font-bold tracking-tight text-brand-primary-ink">Nuestros valores</h2>
            </div>
            <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    ['Confianza', 'Acompañamiento legal y patrimonial en cada operación. Lo que prometemos, lo cumplimos.', 'M12 2 4 5v6c0 5 3.4 8.5 8 10 4.6-1.5 8-5 8-10V5z M9 12l2 2 4-4'],
                    ['Excelencia', 'Un solo estándar de calidad, del primer trazo arquitectónico a la entrega de llaves.', 'M12 2v4M12 18v4M2 12h4M18 12h4 M5 5l3 3M16 16l3 3'],
                    ['Cercanía', 'Premium sí, distante no. Cada cliente trata directo con su asesor en todo momento.', 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2 M9 7a4 4 0 1 0 0 .01'],
                    ['Visión', 'Creamos valor donde otros ven metros cuadrados. Invertimos donde otros ven terrenos.', 'M3 17l6-6 4 4 7-7 M17 8h4v4'],
                ] as [$titulo, $desc, $path])
                    <div>
                        <div class="mb-5 flex h-13 w-13 items-center justify-center rounded-brand-md bg-navy-50 p-3.5">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $path }}"/></svg>
                        </div>
                        <h3 class="font-brand-heading text-lg font-semibold text-brand-primary-ink">{{ $titulo }}</h3>
                        <p class="mt-2.5 text-[15px] leading-relaxed text-stone">{{ $desc }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Equipo --}}
    <section class="bg-[linear-gradient(180deg,#d8d8d8_0%,#f3f3f3_50%,#d8d8d8_100%)]">
        <div class="mx-auto max-w-[var(--container-content)] px-6 py-20">
            <div class="mb-11 max-w-[560px]">
                <p class="eyebrow mb-4 text-brand-primary-ink">El equipo</p>
                <h2 class="font-brand-heading text-[clamp(26px,3.4vw,36px)] font-bold tracking-tight text-ink">Las personas detrás de Landra</h2>
            </div>

            <div class="mb-9 flex flex-wrap items-center gap-8 rounded-brand-lg border border-black/5 bg-white p-8 shadow-lg">
                <img src="{{ asset('images/brand/a74-arquitectura.png') }}" alt="A-74 Arquitectura" class="h-28 w-auto">
                <div class="min-w-[240px] flex-1">
                    <p class="eyebrow text-stone">Despacho de arquitectura</p>
                    <h3 class="mt-2 font-brand-heading text-[22px] font-bold text-ink">A-74 Arquitectura es parte de Landra</h3>
                    <p class="mt-2 max-w-[560px] text-[15px] leading-relaxed text-graphite">
                        El brazo de diseño y proyecto de la firma. A-74 lleva cada residencia y desarrollo del concepto arquitectónico a la obra terminada.
                    </p>
                </div>
            </div>

            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                @foreach ([
                    ['Kristian Álvarez', 'Dirección', 'images/team/kristian_alvarez.jpg'],
                    ['Diego Álvarez', 'Arquitectura', 'images/team/Diego_alvarez.jpg'],
                    ['Alejandro Álvarez', 'Construcción y Obra', 'images/team/Alex_Alvarez.jpg'],
                    ['Iván Álvarez', 'Comercialización', 'images/team/Ivan_Alvarez.jpg'],
                    ['Alan Álvarez', 'Inmobiliaria', 'images/team/Alan_Alvarez.jpg'],
                ] as [$nombre, $rol, $foto])
                    <div class="overflow-hidden rounded-brand-lg bg-white shadow-sm">
                        <img src="{{ asset($foto) }}" alt="{{ $nombre }} — {{ $rol }}"
                             class="aspect-[2/3] w-full object-cover object-top" loading="lazy">
                        <div class="p-6">
                            <h3 class="font-brand-heading text-lg font-semibold text-brand-primary-ink">{{ $nombre }}</h3>
                            <p class="mt-1 text-sm text-stone">{{ $rol }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- CTA --}}
    <section class="mx-auto max-w-[var(--container-content)] px-6 py-24">
        <div class="rounded-brand-xl bg-brand-accent bg-gradient-to-br from-white/15 via-transparent to-black/15 px-8 py-16 text-center shadow-lg sm:px-12">
            <h2 class="font-brand-heading text-[clamp(28px,4vw,40px)] font-extrabold leading-tight text-on-brand-accent">Trabajemos juntos en tu próximo proyecto</h2>
            <p class="mx-auto mt-4 max-w-[560px] text-lg leading-relaxed text-on-brand-accent/80">Agenda una asesoría sin costo y conoce cómo podemos acompañarte.</p>
            <div class="mt-8 flex flex-wrap justify-center gap-3.5">
                <x-button variant="dark" :href="route('leads.create')">Agenda una cita</x-button>
            </div>
        </div>
    </section>
    @endif
</x-layouts.public>
