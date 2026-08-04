@php
    $cms = app(\App\Services\Frontend\FrontendPageRenderer::class)->render('inversionistas');
@endphp
<x-layouts.public title="Inversionistas · New Hauz" :seo="$cms['seo']">
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
            ['label' => 'Inversionistas'],
        ],
    ])

    {{-- Introducción --}}
    <section class="mx-auto max-w-[var(--container-content)] px-6 py-16">
        <div class="space-y-20">

        {{-- Parte 1: texto izquierda · imagen derecha --}}
        <div class="grid items-start gap-16 lg:grid-cols-2">
            <div>
                <p class="eyebrow mb-4 text-brand-accent-ink">01 · Inversión con visión</p>
                <h2 class="font-brand-heading text-[clamp(26px,3.4vw,36px)] font-bold leading-snug tracking-tight text-brand-primary-ink">Decisiones respaldadas por datos y estrategia.</h2>
                <p class="mt-5 text-base leading-relaxed text-stone">
                    Invertir en bienes raíces no se trata solo de comprar un terreno, adquirir una propiedad o iniciar un desarrollo; se trata de tomar decisiones con visión, datos y estrategia. En New Hauz acompañamos a inversionistas, propietarios y desarrolladores en la evaluación, planeación y estructuración de oportunidades inmobiliarias con potencial de crecimiento, rentabilidad y plusvalía.
                </p>
            </div>
            <div class="min-h-[480px] overflow-hidden rounded-brand-lg shadow-lg">
                <img src="{{ asset('images/inversionistas/01_inversion_con_vision.png') }}" alt="Inversión con visión"
                     class="h-full min-h-[480px] w-full object-cover">
            </div>
        </div>

        {{-- Parte 2: imagen izquierda · texto derecha --}}
        <div class="grid items-start gap-16 lg:grid-cols-2">
            <div class="min-h-[480px] overflow-hidden rounded-brand-lg shadow-lg">
                <img src="{{ asset('images/inversionistas/02_analisis_integral.png') }}" alt="Análisis integral"
                     class="h-full min-h-[480px] w-full object-cover">
            </div>
            <div>
                <p class="eyebrow mb-4 text-brand-accent-ink">02 · Análisis integral</p>
                <h2 class="font-brand-heading text-[clamp(26px,3.4vw,36px)] font-bold leading-snug tracking-tight text-brand-primary-ink">Cada inversión, evaluada desde todos los ángulos.</h2>
                <p class="mt-5 text-base leading-relaxed text-stone">
                    Nuestro servicio está diseñado para quienes buscan convertir una oportunidad en un proyecto viable, rentable y bien fundamentado. Analizamos cada inversión desde una perspectiva integral: ubicación, mercado, vocación del inmueble, potencial comercial, costos estimados, retorno esperado, riesgos técnicos y proyección financiera.
                </p>
            </div>
        </div>

        {{-- Parte 3: imagen ancho completo · texto sobre la imagen, alineado abajo y centrado --}}
        <div class="relative overflow-hidden rounded-brand-lg bg-brand-primary bg-gradient-to-br from-black/20 to-white/10 shadow-lg">
            <img src="{{ asset('images/inversionistas/03_ruta_de_desarrollo.png') }}" alt="Tu ruta de desarrollo"
                 class="block h-auto w-full">
            {{-- Overlay (transparente arriba → navy abajo): sólo en desktop, cuando el texto va encima --}}
            <div class="absolute inset-0 hidden bg-gradient-to-b from-transparent via-brand-primary/[0.65] to-brand-primary/[0.92] lg:block"></div>
            {{-- Texto: debajo de la imagen en móvil, sobre la imagen (inferior) en desktop --}}
            <div class="p-8 sm:p-12 lg:absolute lg:inset-x-0 lg:bottom-0">
                <div class="mx-auto max-w-[820px] text-center">
                    <h2 class="font-brand-heading text-[clamp(26px,4vw,42px)] font-bold leading-snug tracking-tight text-on-brand-primary">Del concepto a la decisión estratégica.</h2>
                    <p class="mt-5 text-[clamp(16px,2vw,20px)] leading-relaxed text-on-brand-primary/85">
                        Te ayudamos a trazar una ruta clara para tu desarrollo, desde la primera idea hasta la toma de decisiones estratégicas. Ya sea que busques desarrollar vivienda, comercializar un terreno, adquirir una propiedad con potencial, participar en una preventa o estructurar un proyecto inmobiliario, nuestro objetivo es darte claridad antes de comprometer capital.
                    </p>
                </div>
            </div>
        </div>

        </div>
    </section>

    {{-- ¿Qué incluye? --}}
    <section class="border-y border-cloud bg-white">
        <div class="mx-auto max-w-[var(--container-content)] px-6 py-20">
            <div class="mb-12 max-w-[600px]">
                <p class="eyebrow mb-4 text-brand-accent-ink">Alcance del servicio</p>
                <h2 class="font-brand-heading text-[clamp(26px,3.4vw,36px)] font-bold tracking-tight text-brand-primary-ink">¿Qué incluye?</h2>
            </div>
            <div class="grid gap-8 sm:grid-cols-2">
                @foreach ([
                    [
                        'Plan de negocio para desarrollos',
                        'Definimos la estructura general del proyecto: tipo de producto inmobiliario, público objetivo, propuesta de valor, estrategia comercial, etapas de desarrollo, costos base, modelo de ingresos y ruta de ejecución. Este plan funciona como hoja de ruta para visualizar el proyecto como una unidad de negocio, no solo como una construcción.',
                        'M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
                    ],
                    [
                        'Proyección financiera y retorno',
                        'Realizamos estimaciones financieras para entender la rentabilidad potencial del proyecto. Analizamos inversión inicial, costos de obra, gastos indirectos, precio de venta o renta, margen esperado, punto de equilibrio y retorno sobre la inversión. El objetivo es identificar si el proyecto tiene viabilidad económica antes de avanzar.',
                        'M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V4z',
                    ],
                    [
                        'Reporte de viabilidad técnica',
                        'Evaluamos las condiciones físicas, urbanas y constructivas del inmueble o terreno. Consideramos factores como ubicación, dimensiones, accesibilidad, uso potencial, restricciones, factibilidad de desarrollo, complejidad constructiva y posibles riesgos técnicos. Este reporte ayuda a detectar oportunidades y focos rojos desde el inicio.',
                        'M9 12l2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0z',
                    ],
                    [
                        'Análisis de mercado por zona',
                        'Estudiamos el comportamiento inmobiliario de la zona: oferta existente, precios de referencia, demanda potencial, perfil del comprador o arrendatario, nivel de competencia, plusvalía esperada y tendencias de crecimiento. Este análisis permite definir si la ubicación tiene verdadero potencial comercial.',
                        'M17.657 16.657L13.414 20.9a1.998 1.998 0 0 1-2.827 0l-4.244-4.243a8 8 0 1 1 11.314 0z M15 11a3 3 0 1 1-6 0 3 3 0 0 1 6 0z',
                    ],
                ] as [$titulo, $desc, $icon])
                    <div class="rounded-brand-lg border border-brand-primary/15 bg-navy-50 p-8 shadow-md">
                        <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-brand-md bg-brand-accent text-on-brand-accent">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icon }}"/></svg>
                        </div>
                        <h3 class="font-brand-eyebrow text-lg font-bold text-brand-primary-ink">{{ $titulo }}</h3>
                        <p class="mt-3 text-[15px] leading-relaxed text-stone">{{ $desc }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ¿Para quién es ideal? --}}
    <section class="mx-auto max-w-[var(--container-content)] px-6 py-20">
        <div class="grid items-center gap-14 lg:grid-cols-2">
            <div>
                <p class="eyebrow mb-4 text-brand-accent-ink">¿Para quién es ideal?</p>
                <h2 class="font-brand-heading text-[clamp(26px,3.4vw,36px)] font-bold leading-snug tracking-tight text-brand-primary-ink">Diseñado para quien ya tiene visión, y quiere los datos para respaldarla.</h2>
                <ul class="mt-8 space-y-4">
                    @foreach ([
                        'Inversionistas que desean entrar al mercado inmobiliario con una estrategia clara.',
                        'Propietarios de terrenos que buscan desarrollar, vender o asociarse.',
                        'Desarrolladores que necesitan validar la viabilidad de un proyecto.',
                        'Personas que quieren adquirir propiedades con potencial de plusvalía o rentabilidad.',
                    ] as $item)
                        <li class="flex items-start gap-3.5">
                            <span class="mt-0.5 flex h-6 w-6 flex-none items-center justify-center rounded-[7px] bg-brand-accent/10 text-brand-accent-ink">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                            </span>
                            <span class="text-[15px] leading-relaxed text-stone">{{ $item }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
            <div class="rounded-brand-lg bg-brand-primary bg-gradient-to-br from-black/25 via-transparent to-white/10 p-10 text-on-brand-primary">
                <p class="eyebrow mb-4 text-accent-on-brand-primary">Resultado esperado</p>
                <h3 class="font-brand-heading text-xl font-bold leading-snug text-on-brand-primary">Al finalizar el proceso, tendrás claridad total sobre tu inversión.</h3>
                <ul class="mt-6 space-y-3">
                    @foreach ([
                        'Visión clara del potencial de inversión',
                        'Escenarios de rentabilidad documentados',
                        'Identificación de riesgos principales',
                        'Rutas de acción con fundamento técnico y financiero',
                    ] as $resultado)
                        <li class="flex items-center gap-3 text-[15px] text-on-brand-primary/80">
                            <span class="h-1.5 w-1.5 flex-none rounded-full bg-brand-accent"></span>
                            {{ $resultado }}
                        </li>
                    @endforeach
                </ul>
                <p class="mt-8 border-t border-on-brand-primary/10 pt-6 text-sm italic leading-relaxed text-on-brand-primary/60">
                    "Te ayudamos a invertir con visión, no con corazonadas disfrazadas de oportunidad."
                </p>
            </div>
        </div>
    </section>

    {{-- CTA --}}
    <section class="mx-auto max-w-[var(--container-content)] px-6 pb-24">
        <div class="rounded-brand-xl bg-brand-primary bg-gradient-to-br from-black/25 via-transparent to-white/10 px-8 py-16 text-center shadow-lg sm:px-12">
            <h2 class="font-brand-heading text-[clamp(28px,4vw,40px)] font-extrabold leading-tight text-on-brand-primary">¿Tienes una oportunidad en mente?</h2>
            <p class="mx-auto mt-4 max-w-[560px] text-lg leading-relaxed text-on-brand-primary/75">Cuéntanos el proyecto y analizamos juntos si tiene potencial real antes de comprometer capital.</p>
            <div class="mt-8 flex justify-center">
                <x-button variant="primary" :href="route('leads.create')">Solicita Atención Personalizada</x-button>
            </div>
        </div>
    </section>
    @endif
</x-layouts.public>
