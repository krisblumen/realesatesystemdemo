{{--
    featured_projects (dynamic) — title?, eyebrow?, primary_cta?, media_id?,
    alt?, background_color?; items are Project models resolved from the kernel
    (is_featured, o TODOS en la variante `catalog` — decidido en el
    renderer). Empty ⇒ nothing rendered, EXCEPTO en `catalog`, donde el
    listado completo de `/proyectos` siempre tuvo un estado vacío propio
    (design D6, cambio cms-pagina-proyectos).

    CON LOGO, el encabezado se dibuja como una tarjeta DESTACADO —blanca, con
    sombra, sobre una banda gris a todo el ancho— con el mismo diseño que el
    destacado de `team`: el logo puede ser una división con imagen comercial
    propia (A-74 Arquitectura), no la marca principal repetida. SIN logo, el
    encabezado se ve exactamente como antes de que este campo existiera —texto
    solo, sin banda—, porque ninguna sección publicada antes de hoy tiene uno.
    Esto NO cambia entre variantes: el listado seguido siembra sin logo, así
    que su primera publicación también arranca sin tarjeta (riesgo ya
    documentado en el design).

    El botón al catálogo va como ENLACE de texto (variante `link`) y no como
    botón sólido: es el mismo gesto que ya usa el sitio para «Conocer más →»,
    discreto al lado de un título que ya lleva su propia tarjeta.

    LA VARIANTE `catalog` (única hoy: `/proyectos`) diverge del resumen en
    TRES cosas — la autoridad de datos ya la decidió el renderer, acá sólo
    quedan presentación:
      · layout   — carrusel paginado de 6 (desktop) / 1 con swipe (móvil), en
                   vez de la grilla `SectionCardGrid`.
      · vacío    — «Pronto publicaremos nuestros proyectos» en vez de nada.
      · fondo    — el gradiente literal de 3 paradas por defecto (design D7),
                   nunca el gesto tieneLogo/sin-logo que usa el resumen.
--}}
@php
    $items = $s['items'] ?? [];
    $tieneLogo = ! empty($s['media_url']);
    $catalogo = ($s['variant'] ?? 'default') === 'catalog';

    // El fondo sale de la MISMA paleta cerrada que el resto del panel; sus
    // clases literales ya están declaradas en team.blade.php, donde Tailwind
    // las encuentra para compilarlas (§6.1). Ausente el campo —toda sección
    // publicada antes de que existiera—, se conserva el gesto de siempre: gris
    // con logo, fondo del sitio sin él. `catalog` reemplaza ese default por el
    // gradiente literal que ya tenía `/proyectos` (§16.7 + design D7): no
    // existe en `brand_palette` — dos paradas de gris que ninguna paleta de
    // marca reproduce — así que sobrevive como clase propia de la variante.
    $paleta = (array) config('frontend-sections.brand_palette');
    $gradienteCatalogo = 'bg-[linear-gradient(180deg,#e0e0e0_0%,#f0f0f0_50%,#dcdcdc_100%)]';
    $fondoDefault = $catalogo ? $gradienteCatalogo : ($tieneLogo ? 'bg-fog' : '');
    $fondo = array_key_exists('background_color', $s)
        ? ($paleta[$s['background_color']]['bg'] ?? $fondoDefault)
        : $fondoDefault;
@endphp
@if (! empty($items) || $catalogo)
    {{-- El gradiente hace que la sección se lea como un CORTE en la página: una
         sombra interior arriba y abajo, y el medio limpio. Va en una capa aparte
         y no en el `background` de la sección porque ahí competiría con el color
         de la paleta —una sola propiedad no puede llevar los dos—. `aria-hidden`
         y sin eventos: es decoración, no debe interceptar clics ni anunciarse. --}}
    {{-- El filete gris arriba y abajo, sólo si el fondo elegido difiere del
         general del sitio (ver SectionBand). --}}
    <section class="{{ trim('relative '.$fondo.' '.\App\Support\Frontend\SectionBand::edges($s['background_color'] ?? null)) }} px-6 py-16">
        <div aria-hidden="true"
             class="pointer-events-none absolute inset-0 bg-[linear-gradient(180deg,rgba(0,0,0,0.2)_0%,rgba(0,0,0,0)_20%,rgba(0,0,0,0)_80%,rgba(0,0,0,0.2)_100%)]"></div>

        <div class="relative mx-auto max-w-[var(--container-content)]">
            @if ($tieneLogo)
                {{-- La tarjeta DESTACADO: logo a la izquierda, encabezado al
                     centro, botón a la derecha — las tres alineadas entre sí.
                     Mismo gesto que el destacado de `team`: tarjeta blanca con
                     sombra sobre la banda gris de la sección. --}}
                <div class="mb-10 flex flex-wrap items-center justify-between gap-6 rounded-brand-lg bg-white px-6 py-5 shadow-sm sm:flex-nowrap sm:px-8 sm:py-6">
                    <div class="flex flex-wrap items-center gap-6 sm:flex-nowrap">
                        <img src="{{ $s['media_url'] }}"
                             alt="{{ $s['alt'] ?? '' }}"
                             loading="lazy" decoding="async"
                             class="h-24 w-auto max-w-[200px] shrink-0 object-contain">

                        <div>
                            @if (($s['eyebrow'] ?? '') !== '')
                                <p class="eyebrow {{ \App\Support\Frontend\SectionTypography::eyebrow($s) }} mb-2 text-stone">{{ $s['eyebrow'] }}</p>
                            @endif
                            @if (($s['title'] ?? '') !== '')
                                <h2 class="font-brand-heading text-[clamp(26px,3.4vw,36px)] {{ \App\Support\Frontend\SectionTypography::title($s) }} leading-snug tracking-tight text-brand-primary-ink">{{ $s['title'] }}</h2>
                            @endif
                        </div>
                    </div>

                    @if ($s['primary_cta'] ?? null)
                        <span class="shrink-0 whitespace-nowrap">
                            @include('frontend.cta-button', ['cta' => $s['primary_cta'], 'variant' => 'link'])
                            <span aria-hidden="true">→</span>
                        </span>
                    @endif
                </div>
            @else
                {{-- Sin logo: el encabezado de siempre, sin tarjeta ni banda. --}}
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

                        @if ($s['primary_cta'] ?? null)
                            @include('frontend.cta-button', ['cta' => $s['primary_cta'], 'variant' => 'dark'])
                        @endif
                    </div>
                @endif
            @endif

            @if ($catalogo && empty($items))
                {{-- Estado vacío propio de `catalog` (design D6): antes del
                     cutover esto NO se veía nunca —sin `Project` la página no
                     existía— así que no hay snapshot previo que preservar,
                     sólo el texto que `site/proyectos.blade.php` ya usaba. --}}
                <div class="rounded-brand-lg border border-black/5 bg-white p-16 text-center shadow-sm">
                    <p class="font-brand-heading text-lg font-semibold text-brand-primary-ink">Pronto publicaremos nuestros proyectos</p>
                    <p class="mt-2 text-sm text-stone">Estamos preparando el portafolio de A-74 Arquitectura.</p>
                </div>
            @elseif ($catalogo)
                @php $paginas = array_chunk($items, 6); @endphp
                {{-- Desktop: carrusel de páginas de 6 (grid 3 columnas). --}}
                <div class="hidden lg:block" data-carousel>
                    <div class="overflow-hidden">
                        <div class="flex transition-transform duration-500 ease-[var(--ease-out-expo)]" data-track>
                            @foreach ($paginas as $pagina)
                                <div class="grid w-full shrink-0 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                                    @foreach ($pagina as $project)
                                        @include('frontend.sections.partials.project-card')
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>
                    @if (count($paginas) > 1)
                        @include('site.partials.project-nav', ['count' => count($paginas)])
                    @endif
                </div>

                {{-- Mobile: carrusel de 1 tarjeta por vista, con swipe. --}}
                <div class="lg:hidden" data-carousel data-swipe>
                    <div class="overflow-hidden">
                        <div class="flex transition-transform duration-500 ease-[var(--ease-out-expo)]" data-track>
                            @foreach ($items as $project)
                                <div class="w-full shrink-0">
                                    @include('frontend.sections.partials.project-card')
                                </div>
                            @endforeach
                        </div>
                    </div>
                    @if (count($items) > 1)
                        @include('site.partials.project-nav', ['count' => count($items)])
                    @endif
                </div>

                @include('site.partials.carousel-script')
            @else
                {{-- Resumen (default, sin cambios): el reparto es el MISMO que
                     el de «Qué hacemos» — hasta cuatro por fila y la última
                     fila incompleta a todo el ancho. La regla vive en
                     SectionCardGrid, no acá, porque la comparten las dos
                     secciones y copiada se desincroniza. La tarjeta queda
                     INLINE (no el partial compartido de `catalog`) para no
                     tocar un byte del DOM que `home` ya tiene publicado. --}}
                <div class="{{ \App\Support\Frontend\SectionCardGrid::container() }}">
                    @foreach ($items as $i => $project)
                        <a href="{{ $project['href'] }}" class="{{ \App\Support\Frontend\SectionCardGrid::span($i, count($items)) }} group relative block min-h-[380px] overflow-hidden rounded-brand-lg shadow-sm">
                            @if (! empty($project['cover']))
                                <div class="absolute inset-0 bg-cover bg-center transition-transform duration-500 ease-[var(--ease-out-expo)] group-hover:scale-105" style="background-image:url('{{ $project['cover'] }}')"></div>
                            @else
                                <div class="absolute inset-0 bg-brand-primary bg-gradient-to-br from-black/20 to-white/10"></div>
                            @endif
                            <div class="absolute inset-0 bg-[linear-gradient(180deg,rgba(5,15,56,0)_35%,rgba(5,15,56,0.85)_100%)]"></div>
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
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endif
