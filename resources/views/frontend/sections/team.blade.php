{{--
    team — eyebrow?, title?, spotlight? {eyebrow?, title?, body?, media_url?,
    alt?}, members[] {name, role?, media_url?, alt?}

    La sección va sobre una BANDA a todo el ancho, y todo lo que lleva adentro
    son tarjetas blancas con sombra: el destacado y cada integrante. Es el diseño
    que el sitio ya tenía; el render del CMS lo había perdido —fondo plano y
    nombres sueltos sobre la página—, así que acá se recupera.

    El color de la banda y el del encabezado los ELIGE el owner, de la paleta
    cerrada del sitio. Las clases salen de un mapa fijo: nada del payload se
    interpola en un nombre de clase (§6.1).

    Los retratos van en 9:16, que es el formato en que se toman. Eran cuadrados
    y recortaban por la mitad fotos de cuerpo entero. La vista previa del panel
    usa esta misma proporción, así que lo que el owner encuadra al subir es lo
    que se publica.

    El DESTACADO puede llevar su propio logo, a la izquierda de su texto: es una
    división de la empresa con imagen comercial propia, no la marca principal
    repetida.
--}}
@php
    $members = collect($s['members'] ?? [])->filter(fn ($m) => is_array($m));
    $spotlight = is_array($s['spotlight'] ?? null) ? $s['spotlight'] : [];

    $paleta = (array) config('frontend-sections.brand_palette');
    $fondo = $paleta[$s['background_color'] ?? 'neutral-1']['bg'] ?? 'bg-fog';
    $tinta = $paleta[$s['title_color'] ?? 'primary']['text'] ?? 'text-brand-primary';
@endphp

{{--
    Si se agrega un color a `brand_palette`, sus clases `bg-*` y `text-*` deben
    sumarse a esta lista o Tailwind no las compila y la sección sale sin fondo o
    con el título en negro:
      bg-site-background border-site-background text-site-text
      bg-navy-50 border-navy-50 text-navy-50
      bg-white bg-fog bg-cloud bg-mist bg-stone bg-ink
      bg-brand-accent-d2 bg-brand-accent-d1 bg-brand-accent bg-brand-accent-l1
      bg-brand-accent-l2 bg-brand-primary-d2 bg-brand-primary-d1
      bg-brand-primary bg-brand-primary-l1 bg-brand-primary-l2
      text-white text-fog text-cloud text-mist text-stone text-ink
      text-brand-accent-d2 text-brand-accent-d1 text-brand-accent
      text-brand-accent-l1 text-brand-accent-l2 text-brand-primary-d2
      text-brand-primary-d1 text-brand-primary text-brand-primary-l1
      text-brand-primary-l2
--}}
{{-- El filete gris arriba y abajo, sólo si el fondo elegido difiere del general
     del sitio: la sección se lee como una banda y sin borde queda flotando
     contra la vecina. La regla vive en SectionBand, que la comparten las tres
     secciones cuyo color pinta el ancho completo. --}}
<section class="{{ trim($fondo.' '.\App\Support\Frontend\SectionBand::edges($s['background_color'] ?? null)) }} px-6 py-16">
    <div class="mx-auto max-w-[var(--container-content)]">
        @if (($s['eyebrow'] ?? '') !== '' || ($s['title'] ?? '') !== '')
            <div class="mb-10 max-w-[640px]">
                @if (($s['eyebrow'] ?? '') !== '')
                    <p class="eyebrow {{ \App\Support\Frontend\SectionTypography::eyebrow($s) }} mb-3 {{ $tinta }}">{{ $s['eyebrow'] }}</p>
                @endif
                @if (($s['title'] ?? '') !== '')
                    <h2 class="font-brand-heading text-[clamp(26px,3.4vw,36px)] {{ \App\Support\Frontend\SectionTypography::title($s) }} leading-snug tracking-tight {{ $tinta }}">{{ $s['title'] }}</h2>
                @endif
            </div>
        @endif

        @if (array_filter($spotlight) !== [])
            {{-- Tarjeta blanca sobre el gris: el logo a la izquierda y el texto
                 a la derecha, centrados entre sí. Sin logo, el texto ocupa la
                 tarjeta entera en vez de dejar un hueco a su izquierda. --}}
            {{-- El aire vertical es MENOR que el horizontal a propósito: con el
                 mismo por los cuatro lados la tarjeta se veía pesada al lado de
                 los retratos, que son altos y angostos. El logo, en cambio, va
                 más grande — es la identidad de la división y competía en tamaño
                 con su propio texto. --}}
            <div class="mb-8 flex flex-wrap items-center gap-6 rounded-brand-lg bg-white px-6 py-5 shadow-sm sm:flex-nowrap sm:px-8 sm:py-6">
                @if (! empty($spotlight['media_url']))
                    <img src="{{ $spotlight['media_url'] }}"
                         alt="{{ $spotlight['alt'] ?? '' }}"
                         loading="lazy" decoding="async"
                         class="h-24 w-auto max-w-[200px] shrink-0 object-contain">
                @endif

                <div>
                    @if (($spotlight['eyebrow'] ?? '') !== '')
                        <p class="eyebrow mb-2 text-stone">{{ $spotlight['eyebrow'] }}</p>
                    @endif
                    @if (($spotlight['title'] ?? '') !== '')
                        <h3 class="font-brand-heading text-xl font-bold text-brand-primary-ink">{{ $spotlight['title'] }}</h3>
                    @endif
                    @if (($spotlight['body'] ?? '') !== '')
                        <p class="mt-3 max-w-[720px] text-[15px] leading-relaxed text-graphite">{{ $spotlight['body'] }}</p>
                    @endif
                </div>
            </div>
        @endif

        @if ($members->isNotEmpty())
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-5">
                @foreach ($members as $member)
                    {{-- El mismo gesto que las tarjetas de propiedad: blanca,
                         sombra suave y una elevación mínima al pasar el mouse. --}}
                    <div class="overflow-hidden rounded-brand-lg bg-white shadow-sm transition-all duration-[350ms] ease-[var(--ease-out-expo)] hover:-translate-y-1 hover:shadow-lg">
                        <div class="aspect-[9/16] overflow-hidden bg-brand-primary/10">
                            @if (! empty($member['media_url']))
                                <img src="{{ $member['media_url'] }}"
                                     alt="{{ $member['alt'] ?? ($member['name'] ?? '') }}"
                                     class="h-full w-full object-cover" loading="lazy">
                            @endif
                        </div>
                        <div class="px-5 py-4">
                            <p class="font-bold text-brand-primary-ink">{{ $member['name'] ?? '' }}</p>
                            @if (($member['role'] ?? '') !== '')
                                <p class="mt-0.5 text-sm text-stone">{{ $member['role'] }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
