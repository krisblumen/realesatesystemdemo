{{--
    hero — eyebrow?, title, subtitle?, primary_cta?, secondary_cta?, slides[],
           logo_enabled, logo_size, text_align, mode, variant

    ESTE es el único renderer del hero: lo usa el contenido publicado por el CMS
    y también el fallback de una instalación sin publicar (C-B-1). Antes esa
    segunda ruta la resolvía cada Blade por su cuenta, así que la primera visita
    —la más importante— era justo la que no recibía ni el fallback por página, ni
    los modos A/B, ni las garantías de CSP.

    El presenter ya decidió TODO lo difícil (§8, §9): slides ordenadas, filtradas
    (solo `promoted`) y con su fallback resuelto; defaults materializados; `mode`
    y `variant` elegidos. Acá solo se muestra.

    Dos modos MUTUAMENTE EXCLUYENTES, nunca a la vez:

    · decorativo  — fondos bajo el overlay. La capa entera va `aria-hidden`, las
                    imágenes sin alt, y rotan con cross-fade CSS. El control de
                    pausa vive FUERA de esa capa, o sería inalcanzable.
    · informativo — alguna slide tiene significado (`decorative:false`, con alt
                    obligatorio). Entonces NO hay `aria-hidden`, NO hay autoplay
                    y se muestra UNA sola imagen: rotar contenido con significado
                    en silencio no es aceptable, y un alt bajo `aria-hidden` no
                    se anunciaría nunca.

    Y `variant` conserva el aspecto propio de cada página: unificar el renderer
    no debe cambiar cómo se ve el sitio.
--}}
@php
    $slides = $s['slides'] ?? [];
    $count = count($slides);
    $informative = ($s['mode'] ?? 'decorative') === 'informative';
    $variant = $s['variant'] ?? 'standard';
    $featured = $variant === 'featured';
    $compact = $variant === 'compact';

    // Enums → clases FIJAS. Ningún valor del payload se concatena a una clase.
    $align = match ($s['text_align'] ?? 'left') {
        'center' => 'text-center items-center mx-auto',
        'right' => 'text-right items-end ml-auto',
        default => 'text-left items-start',
    };

    $logoSize = $featured
        ? match ($s['logo_size'] ?? 'md') {
            'sm' => 'max-h-16 sm:max-h-20',
            'lg' => 'max-h-28 sm:max-h-32 lg:max-h-40',
            'xl' => 'max-h-32 sm:max-h-40 lg:max-h-48',
            default => 'max-h-20 sm:max-h-24 lg:max-h-28',
        }
        : match ($s['logo_size'] ?? 'md') {
            'sm' => 'max-h-10 sm:max-h-12',
            'lg' => 'max-h-16 sm:max-h-20',
            // Rampa propia para la variante `standard` (design D5, cambio
            // cms-pagina-proyectos): 14rem fijos es el alto del logo grande
            // de A-74 hoy (`style="height:14rem"`); en móvil queda en 12rem
            // en vez de un alto fijo — mejor que el original, y sin él muere
            // el único `style=` inline que le quedaba al hero (§6.1).
            'xl' => 'max-h-48 sm:max-h-56',
            default => 'max-h-12 sm:max-h-16',
        };

    $hasTitle = ($s['title'] ?? '') !== '';
    $showLogo = ($s['logo_enabled'] ?? false) === true && ($s['logo_url'] ?? null);
    // Con H1 el logo repite la marca que el encabezado ya nombra: decorativo.
    // Sin H1 es la única identidad visible, así que se nombra.
    $logoAlt = $hasTitle ? '' : ($s['site_name'] ?? '');
@endphp
{{-- Sin imagen de fondo la sección se apoya en la superficie de marca: es lo
     que §8 llama «sin imagen», no un hueco translúcido sobre el fondo de página. --}}
<section data-nh-hero
         class="{{ $featured ? 'relative flex min-h-[92vh] items-center overflow-hidden' : 'relative overflow-hidden' }} {{ $count === 0 ? 'bg-brand-primary' : '' }}">
    @if ($informative)
        {{-- Modo informativo: una imagen real, anunciable, sin rotación. --}}
        <img src="{{ $slides[0]['media_url'] }}" alt="{{ $slides[0]['alt'] ?? '' }}"
             class="absolute inset-0 h-full w-full object-cover">
    @elseif ($count > 0)
        {{-- Modo decorativo. Se emiten como <img aria-hidden alt=""> y no como
             `background-image`: un atributo `style` es la única superficie inline
             que quedaba, y ninguna directiva de CSP la admite sin
             `unsafe-inline`. Para tecnología asistiva el resultado es idéntico —
             la capa entera ya está oculta y las imágenes no tienen alt. --}}
        <div class="nh-hero-slides nh-hero-slides--{{ min($count, 6) }} absolute inset-0"
             data-nh-hero-slides aria-hidden="true">
            @foreach ($slides as $i => $slide)
                <img src="{{ $slide['media_url'] }}" alt=""
                     class="nh-hero-slide nh-hero-delay-{{ $i }} absolute inset-0 h-full w-full object-cover">
            @endforeach
        </div>
    @endif

    @if ($featured)
        <div class="absolute inset-0 bg-gradient-to-br from-brand-primary/[0.90] via-brand-primary/[0.78] to-brand-primary/[0.72]"></div>
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_78%_30%,rgba(245,166,36,0.12),transparent_45%)]"></div>
    @elseif ($compact)
        <div class="absolute inset-0 bg-gradient-to-br from-black/25 via-transparent to-white/10"></div>
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_82%_25%,rgba(245,166,36,0.10),transparent_48%)]"></div>
    @else
        <div class="absolute inset-0 bg-gradient-to-r from-brand-primary/[0.92] via-brand-primary/[0.55] to-transparent"></div>
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_85%_20%,rgba(245,166,36,0.10),transparent_45%)]"></div>
    @endif

    <div class="relative mx-auto max-w-[var(--container-content)] px-6 {{ $featured ? 'w-full pt-24 pb-28 lg:pb-44' : ($compact ? 'py-14' : 'py-20') }}">
        @if (! empty($breadcrumbs ?? []))
            {{-- Migas de pan: chrome de la página, no contenido editable del
                 hero. Se reciben como DATOS (etiqueta + url), nunca como markup:
                 el partial sigue sin aceptar HTML de nadie. --}}
            <nav class="mb-8 flex items-center gap-2.5 text-sm text-on-brand-primary/60">
                @foreach ($breadcrumbs as $i => $crumb)
                    @if (! empty($crumb['url']))
                        <a href="{{ $crumb['url'] }}" class="hover:text-on-brand-primary">{{ $crumb['label'] }}</a>
                        <span class="text-on-brand-primary/30">/</span>
                    @else
                        <span class="font-semibold text-on-brand-primary">{{ $crumb['label'] }}</span>
                    @endif
                @endforeach
            </nav>
        @endif

        <div class="flex max-w-[760px] flex-col {{ $align }}">
            @if ($showLogo)
                <img src="{{ $s['logo_url'] }}" alt="{{ $logoAlt }}"
                     @if ($logoAlt === '') aria-hidden="true" @endif
                     class="{{ $logoSize }} mb-9 w-auto max-w-full object-contain">
            @endif
            @if (($s['eyebrow'] ?? '') !== '')
                <p class="eyebrow {{ \App\Support\Frontend\SectionTypography::eyebrow($s) }} mb-5 text-accent-on-brand-primary">{{ $s['eyebrow'] }}</p>
            @endif
            @if ($hasTitle)
                <h1 class="{{ $featured ? 'text-[clamp(40px,6vw,68px)] leading-[1.04]' : ($compact ? 'text-[clamp(30px,4.4vw,48px)] leading-tight' : 'text-[clamp(34px,5vw,56px)] leading-[1.06]') }} m-0 font-brand-heading {{ \App\Support\Frontend\SectionTypography::title($s) }} tracking-tight text-on-brand-primary">{{ $s['title'] }}</h1>
            @endif
            @if (($s['subtitle'] ?? '') !== '')
                <p class="mt-5 max-w-[600px] text-[clamp(16px,2vw,19px)] leading-relaxed text-on-brand-primary/80">{{ $s['subtitle'] }}</p>
            @endif
            @if (! empty($s['logo']['media_url'] ?? null))
                {{-- Distintivo A-74 (hallazgo #5): un badge PROPIO,
                     independiente de $showLogo/logo_enabled — un logo propio
                     resuelto alcanza para mostrarlo, aunque el interruptor del
                     logo grande esté apagado.

                     El blanqueo se aplica SÓLO al logo del fallback. Sobre él
                     es lo correcto —así se veía el distintivo en el blade
                     estático y §16.7 manda conservarlo—, pero sobre el que sube
                     el owner le borraría su color de marca, que es justamente
                     lo que «logo propio» existe para mostrar. El origen lo
                     marca el renderer con `from_fallback`: desde el payload no
                     se distinguen. --}}
                <div class="mt-8 inline-flex items-center gap-3.5 rounded-brand-md border border-on-brand-primary/10 bg-on-brand-primary/5 px-4 py-2.5 backdrop-blur-sm">
                    <img src="{{ $s['logo']['media_url'] }}" alt="{{ $s['logo']['alt'] ?? '' }}"
                         class="max-h-8 max-w-[120px] w-auto object-contain {{ ($s['logo']['from_fallback'] ?? false) ? 'brightness-0 invert ' : '' }}opacity-80">
                    <span class="font-brand-heading text-sm font-semibold text-on-brand-primary/80">{{ $s['logo']['alt'] ?? '' }}</span>
                </div>
            @endif
            @if (($s['primary_cta'] ?? null) || ($s['secondary_cta'] ?? null))
                <div class="mt-8 flex flex-wrap gap-4">
                    @include('frontend.cta-button', ['cta' => $s['primary_cta'] ?? null, 'variant' => 'primary'])
                    {{-- `ghost` usa tinta oscura y desaparece sobre el overlay de
                         marca del hero; esta variante es la de contorno claro. --}}
                    @include('frontend.cta-button', ['cta' => $s['secondary_cta'] ?? null, 'variant' => 'ghost-on-dark'])
                </div>
            @endif
        </div>
    </div>

    @if (! $informative && $count > 1)
        {{-- WCAG 2.2.2: lo que se mueve solo por más de cinco segundos necesita
             una forma de detenerlo. Va FUERA de la capa `aria-hidden` para que
             exista para lectores de pantalla y para el teclado. El módulo de
             Vite lo oculta cuando el usuario pidió menos movimiento: ahí no hay
             nada que pausar. --}}
        <button type="button" data-nh-hero-toggle
                data-label-pause="Pausar" data-label-resume="Reanudar"
                class="absolute bottom-5 right-5 z-10 rounded-full border border-on-brand-primary/25 bg-brand-primary/60 px-4 py-2 text-xs font-semibold text-on-brand-primary backdrop-blur transition-colors hover:border-brand-accent hover:text-accent-on-brand-primary">
            Pausar
        </button>
    @endif
</section>
