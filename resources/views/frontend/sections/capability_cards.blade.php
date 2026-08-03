{{--
    capability_cards — encabezado editorial + tarjetas propias (1 a 8).

    EL ANCHO SE REPARTE SEGÚN CUÁNTAS HAYA, sobre una grilla de 12:

      1 → una tarjeta a todo el ancho        5 → 4 arriba + 1 a todo el ancho
      2 → dos mitades                        6 → 4 arriba + 2 mitades
      3 → tres tercios                       7 → 4 arriba + 3 tercios
      4 → cuatro cuartos                     8 → 4 arriba + 4 cuartos

    La regla es una sola: hasta cuatro entran en una fila repartiéndose el ancho;
    a partir de cinco, la primera fila lleva cuatro y las restantes se reparten
    lo que queda. Así ninguna cantidad deja un hueco raro al final.

    Las clases son LITERALES y salen de un mapa fijo. Nada del payload se
    interpola en un nombre de clase ni en un `style`: es la misma regla del hero
    (§6.1), y es lo que permite servir la página sin `unsafe-inline`.

    El ícono también sale de una allowlist (`card_icons`): lo que viaja en el
    payload es una CLAVE, y el path del dibujo lo pone la configuración. Un
    `<path>` armado con texto del owner sería inyección de SVG.

    La tarjeta se apila —ícono, título, texto— y se levanta apenas al pasar el
    mouse, el mismo gesto que el resto del sitio.

    El ENCABEZADO se alinea a elección (izquierda, centro o derecha; centro por
    defecto). Las tarjetas NO siguen esa alineación: son bloques con su propia
    composición interna, y arrastrarlas rompería la retícula.

    El BORDE es opcional, de 1 a 4 px, y su color es siempre el acento de la
    marca. No se elige color: el owner ya define su acento una vez en la
    configuración del sitio, y dejarlo libre acá abriría la puerta a tarjetas
    que no combinan con el resto de la página.
--}}
@php
    $paletaIcono = (array) config('frontend-sections.brand_palette');

    // La PLACA del ícono y su DIBUJO. El dibujo sólo obedece al owner si eligió
    // color: sin elección sigue a su placa, porque el principal es tinta oscura
    // y sobre una placa oscura el ícono desaparecía entero. Sobre placa oscura
    // va el foreground que el contraste garantiza (§16.5) y no un blanco fijo,
    // porque el cliente puede tener un primario claro.
    $clavePlaca = $s['icon_bg_color'] ?? 'navy';
    $placa = $paletaIcono[$clavePlaca]['bg'] ?? 'bg-navy-50';
    $placaClara = app(\App\Services\Frontend\BrandPalette::class)->needsDarkText($clavePlaca);
    $glifo = isset($s['icon_color'])
        ? ($paletaIcono[$s['icon_color']]['text'] ?? 'text-brand-primary')
        : ($placaClara ? 'text-brand-primary' : 'text-on-brand-primary');
    $items = array_slice($s['items'] ?? [], 0, 8);
    $total = count($items);
    $iconos = (array) config('frontend-sections.card_icons');

    // Alineación del ENCABEZADO. Mapa FIJO: lo que viaja en el payload es una
    // clave de una allowlist, nunca un fragmento de clase. Centro por defecto,
    // que es como se ve el sitio publicado.
    $alineaciones = [
        'left' => ['texto' => 'text-left', 'caja' => 'mr-auto'],
        'center' => ['texto' => 'text-center', 'caja' => 'mx-auto'],
        'right' => ['texto' => 'text-right', 'caja' => 'ml-auto'],
    ];
    $alineacion = $alineaciones[$s['text_align'] ?? 'center'] ?? $alineaciones['center'];

    // Borde opcional. El grosor sale de un mapa FIJO —Tailwind sólo genera las
    // clases que ve escritas— y el color es SIEMPRE el acento de la marca, que
    // es una variable: si el cliente cambia su acento, el borde lo sigue solo.
    $grosores = [
        1 => 'border-[1px]',
        2 => 'border-[2px]',
        3 => 'border-[3px]',
        4 => 'border-[4px]',
    ];
    // El color sale de la paleta cerrada de `brand_palette`. Las clases se
    // listan literalmente abajo porque Tailwind sólo genera las que ve escritas;
    // el payload aporta la CLAVE, nunca un fragmento de clase.
    $colores = (array) config('frontend-sections.brand_palette');
    $colorBorde = $colores[$s['card_border_color'] ?? 'accent']['border'] ?? 'border-brand-accent';

    $borde = ($s['card_border'] ?? false) === true
        ? ($grosores[$s['card_border_width'] ?? 1] ?? $grosores[1]).' '.$colorBorde
        : '';
@endphp

{{--
    Las clases de color del borde, escritas para que Tailwind las compile. No se
    usan acá: la que se aplica sale del mapa de arriba. Si alguna vez se agrega
    un color a `brand_palette`, su clase debe sumarse a esta lista o el
    borde saldrá sin color.

    border-brand-accent-d2 border-brand-accent-d1 border-brand-accent
    border-brand-accent-l1 border-brand-accent-l2
    border-brand-primary-d2 border-brand-primary-d1 border-brand-primary
    border-brand-primary-l1 border-brand-primary-l2
--}}

{{--
    El reparto —hasta cuatro por fila, la última fila incompleta a todo el
    ancho— vive en SectionCardGrid: lo comparte `featured_projects`, y copiado en
    cada vista se desincroniza en el primer ajuste que alguien haga en una sola.
--}}

@if ($total > 0)
    <section class="mx-auto max-w-[var(--container-content)] px-6 py-20">
        @if (($s['eyebrow'] ?? '') !== '' || ($s['title'] ?? '') !== '' || ($s['body'] ?? '') !== '')
            <div class="mb-12 max-w-[720px] {{ $alineacion['texto'] }} {{ $alineacion['caja'] }}">
                @if (($s['eyebrow'] ?? '') !== '')
                    <p class="eyebrow {{ \App\Support\Frontend\SectionTypography::eyebrow($s) }} text-brand-accent-ink">{{ $s['eyebrow'] }}</p>
                @endif
                @if (($s['title'] ?? '') !== '')
                    <h2 class="mt-3 font-brand-heading text-[clamp(26px,3.4vw,36px)] {{ \App\Support\Frontend\SectionTypography::title($s) }} leading-snug tracking-tight text-brand-primary-ink">{{ $s['title'] }}</h2>
                @endif
                @if (($s['body'] ?? '') !== '')
                    <p class="mt-4 text-[17px] leading-relaxed text-graphite">{{ $s['body'] }}</p>
                @endif
            </div>
        @endif

        <div class="{{ \App\Support\Frontend\SectionCardGrid::container() }}">
            @foreach ($items as $i => $card)
                @php $icono = $iconos[$card['icon'] ?? ''] ?? null; @endphp
                <article class="{{ \App\Support\Frontend\SectionCardGrid::span($i, $total) }} {{ $borde }} flex flex-col rounded-brand-lg bg-white p-8 shadow-sm transition-all duration-[350ms] ease-[var(--ease-out-expo)] hover:-translate-y-1 hover:shadow-lg">
                    @if ($icono !== null)
                        <div class="mb-6 flex h-13 w-13 items-center justify-center rounded-brand-md {{ $placa }} p-3.5 {{ $glifo }}">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="{{ $icono['path'] }}"/>
                            </svg>
                        </div>
                    @endif
                    <h3 class="font-brand-eyebrow text-xl font-semibold leading-snug tracking-tight text-brand-primary-ink">
                        {{ $card['title'] ?? '' }}
                    </h3>
                    @if (($card['description'] ?? '') !== '')
                        <p class="mt-2.5 text-[15px] leading-relaxed text-stone">{{ $card['description'] }}</p>
                    @endif
                </article>
            @endforeach
        </div>
    </section>
@endif
