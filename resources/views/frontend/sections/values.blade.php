{{--
    values — eyebrow?, title?, items[] {title, description, icon?}

    Cada valor es una PLACA DE ÍCONO con su texto debajo, sin tarjeta. El render
    del CMS los dibujaba en cajas con borde y sombra, que no es el diseño del
    sitio: cuatro cajas en fila compiten entre sí y pesan más que lo que dicen.
    Sin caja, lo que ordena la fila es el ícono.

    El ícono sale de la MISMA allowlist que las tarjetas de «Qué hacemos»
    (`card_icons`): lo que viaja en el payload es una CLAVE y el path del dibujo
    lo pone la configuración. Un `<path>` armado con texto del owner sería
    inyección de SVG.

    Cuatro por fila en escritorio, que es como está el sitio; en pantallas
    medianas bajan a dos y en el teléfono a una.
--}}
@php
    // `values()` reindexa: `filter()` CONSERVA las claves, así que con un ítem
    // descartado los índices quedarían con huecos y el reparto de anchos —que
    // se calcula sobre la posición— saldría corrido.
    $items = collect($s['items'] ?? [])->filter(fn ($v) => is_array($v))->values();
    $iconos = (array) config('frontend-sections.card_icons');

    // El fondo sale de un mapa FIJO: nada del payload se interpola en un nombre
    // de clase. Por defecto, el del sitio — así una sección que nadie tocó se ve
    // igual que antes.
    $paletaIcono = (array) config('frontend-sections.brand_palette');
    $fondo = $paletaIcono[$s['background_color'] ?? 'site']['bg'] ?? 'bg-site-background';

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

    // LOS DOS TRATAMIENTOS que este tipo ya tenía en el sitio, ahora elegibles:
    //
    //   apagado → texto suelto de a cuatro, como «Nuestros valores» en Nosotros
    //   encendido → tarjetas de a dos, como «¿Qué incluye?» en Inversionistas
    //
    // AUSENTE cuenta como apagado, que es como se ve toda sección publicada
    // hasta hoy: sumar esta clave no le cambia el aspecto a ninguna (§16.7).
    $conTarjeta = ($s['as_cards'] ?? false) === true;

    $fondoTarjeta = $paletaIcono[$s['card_bg_color'] ?? 'navy']['bg'] ?? 'bg-navy-50';

    // El grosor sale de un mapa fijo: Tailwind sólo compila las clases que ve
    // escritas, y una armada con el número del payload no existiría.
    $grosores = [1 => 'border-[1px]', 2 => 'border-[2px]', 3 => 'border-[3px]', 4 => 'border-[4px]'];
    $borde = ($s['card_border'] ?? false) === true
        ? ($grosores[$s['card_border_width'] ?? 1] ?? $grosores[1])
            .' '.($paletaIcono[$s['card_border_color'] ?? 'primary-l2']['border'] ?? 'border-brand-primary-l2')
        : '';

    // Con tarjeta, la caja aporta su fondo, su aire y su sombra. Sin ella, el
    // valor es texto sobre la sección y no lleva ninguna de las tres.
    $caja = $conTarjeta ? "rounded-brand-lg {$fondoTarjeta} {$borde} p-8 shadow-md" : '';
@endphp

{{-- Si se agrega un color a `brand_palette`, su clase `bg-*` debe sumarse a la
     lista que ya lleva `team.blade.php` — Tailwind compila leyendo los archivos,
     y una clase que no aparezca escrita deja la sección sin fondo. --}}
{{-- `trim` para no dejar un espacio colgando cuando la sección no lleva filete:
     el atributo tiene que salir igual de limpio en los dos casos. --}}
<section class="{{ trim($fondo.' '.\App\Support\Frontend\SectionBand::edges($s['background_color'] ?? null)) }}">
<div class="mx-auto max-w-[var(--container-content)] px-6 py-16">
    @if (($s['eyebrow'] ?? '') !== '' || ($s['title'] ?? '') !== '')
        <div class="mb-12 max-w-[640px]">
            @if (($s['eyebrow'] ?? '') !== '')
                <p class="eyebrow {{ \App\Support\Frontend\SectionTypography::eyebrow($s) }} mb-3 text-brand-accent-ink">{{ $s['eyebrow'] }}</p>
            @endif
            @if (($s['title'] ?? '') !== '')
                <h2 class="font-brand-heading text-[clamp(26px,3.4vw,36px)] {{ \App\Support\Frontend\SectionTypography::title($s) }} leading-snug tracking-tight text-brand-primary-ink">{{ $s['title'] }}</h2>
            @endif
        </div>
    @endif

    @if ($items->isNotEmpty())
        {{-- Con tarjeta van DE A DOS: cada valor lleva un párrafo largo y
             cuatro columnas los dejarían como columnas de diario. Sin tarjeta
             siguen de a cuatro, que es como se ven hoy.

             En los dos casos la última fila incompleta se reparte el ancho
             —con tres tarjetas, la tercera va entera—, la misma regla que usan
             «Qué hacemos» y el listado de proyectos. --}}
        <div class="{{ \App\Support\Frontend\SectionCardGrid::container($conTarjeta ? 'gap-8' : 'gap-10') }}">
            @foreach ($items as $i => $value)
                @php $icono = $iconos[$value['icon'] ?? ''] ?? null; @endphp
                <div class="{{ \App\Support\Frontend\SectionCardGrid::span($i, $items->count(), porFila: $conTarjeta ? 2 : 4) }} {{ $caja }}">
                    @if ($icono !== null)
                        <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-brand-md {{ $placa }} {{ $glifo }}">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="{{ $icono['path'] }}"/>
                            </svg>
                        </div>
                    @endif
                    <h3 class="font-brand-eyebrow text-lg font-bold text-brand-primary-ink">{{ $value['title'] ?? '' }}</h3>
                    <p class="mt-2.5 text-[15px] leading-relaxed text-stone">{{ $value['description'] ?? '' }}</p>
                </div>
            @endforeach
        </div>
    @endif
</div>
</section>
