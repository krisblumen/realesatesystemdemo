{{--
    metrics — background_color?, value_color?, items[] {label, value}. A stat band.

    Las métricas van SEPARADAS por una regla dentro de la misma tarjeta: cuatro
    cifras seguidas se leen como una lista y no como cuatro datos distintos. La
    regla es un PNG con degradado en las puntas —se desvanece contra el fondo en
    vez de cortar a filo—, así que no se puede reemplazar por un `border`.

    La regla cambia de eje con el layout: en una sola columna las métricas se
    APILAN, y ahí lo que separa es una línea horizontal entre una y la siguiente.
    De `sm` para arriba se acomodan en columnas y toma su lugar la vertical. Son
    dos PNG distintos y nunca se ven los dos a la vez.

    Las dos se cuelgan de la métrica que va DESPUÉS de ellas, centradas en el
    `gap-8` (16px hacia afuera del borde de la celda). Por eso la primera nunca
    lleva ninguna, y ninguna que ABRA fila lleva la vertical: quedaría flotando
    en el margen izquierdo de la tarjeta.

    EL COLOR DE LA TARJETA Y EL DE LAS CIFRAS los elige el owner de la paleta
    cerrada. Lo que NO elige es el color del texto que explica cada cifra: ése se
    invierte solo cuando el fondo es oscuro. Dejarlo a mano habría hecho falsa la
    promesa del selector —se puede elegir cualquier color de tarjeta— porque el
    gris de siempre desaparece sobre un fondo de marca. Quién es «claro» lo
    calcula `BrandPalette` sobre el color REAL, así que sigue valiendo cuando el
    cliente cambia su marca.

    Las clases salen de MAPAS FIJOS y son literales: nada del payload se
    interpola en un nombre de clase (§6.1). Si se agrega un color a
    `brand_palette`, sus clases `bg-*` y `text-*` deben sumarse a la lista que ya
    lleva `team.blade.php`.
--}}
@php
    $items = collect($s['items'] ?? [])->filter(fn ($m) => is_array($m));

    $paleta = (array) config('frontend-sections.brand_palette');
    $claveFondo = $s['background_color'] ?? 'navy';
    $fondo = $paleta[$claveFondo]['bg'] ?? 'bg-navy-50';

    // Sobre fondo oscuro, tinta clara. Las clases van enteras y literales.
    $fondoClaro = app(\App\Services\Frontend\BrandPalette::class)->needsDarkText($claveFondo);
    $etiqueta = $fondoClaro ? 'text-stone' : 'text-on-brand-primary/75';

    // La cifra sólo obedece al owner si ELIGIÓ un color. Sin elección sigue al
    // fondo, y no por prolijidad: el primario es tinta oscura, así que una
    // tarjeta oscura hacía desaparecer el número entero. Sobre fondo oscuro va
    // el foreground que el contrato garantiza legible (§16.5) y no un blanco
    // fijo, porque el cliente puede tener un primario claro.
    $cifra = isset($s['value_color'])
        ? ($paleta[$s['value_color']]['text'] ?? 'text-brand-primary')
        : ($fondoClaro ? 'text-brand-primary' : 'text-on-brand-primary');
@endphp
@if ($items->isNotEmpty())
    <section class="mx-auto max-w-[var(--container-content)] px-6 py-16">
        <div class="grid gap-8 rounded-brand-xl {{ $fondo }} px-8 py-8 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($items as $metric)
                @php
                    // La grilla cambia de columnas por breakpoint, así que quién
                    // abre fila cambia con ella: en `lg` son los múltiplos de 4 y
                    // en `sm` los pares. En una sola columna no hay nada a los
                    // costados que separar, de ahí el `hidden` de base.
                    $vertical = match (true) {
                        $loop->index % 4 === 0 => null,          // abre fila en los dos layouts
                        $loop->index % 2 === 1 => 'hidden sm:block',
                        default => 'hidden lg:block',            // tercera columna: sólo existe en `lg`
                    };

                    // La horizontal es al revés: vive sólo mientras las métricas
                    // están apiladas, y la levanta el primer breakpoint que las
                    // pone en columnas.
                    $horizontal = $loop->first ? null : 'sm:hidden';
                @endphp

                <div class="relative text-center">
                    {{-- Decorativas: no aportan información que el texto no dé.

                         Cada regla se estira hasta los bordes de su métrica —de
                         ahí el `w-full` / `h-full`— y sólo se le fija el GROSOR
                         en los 11px nativos del PNG. El degradado de las puntas
                         se encarga de que no corte a filo, así que no hace falta
                         recortarla a mano: cuanto más larga, más suave entra. --}}
                    @if ($horizontal !== null)
                        <img src="{{ asset('images/assets/h_divider.png') }}"
                             alt="" aria-hidden="true"
                             class="pointer-events-none absolute -top-4 left-0 h-[11px] w-full -translate-y-1/2 {{ $horizontal }}">
                    @endif

                    @if ($vertical !== null)
                        <img src="{{ asset('images/assets/v_divider.png') }}"
                             alt="" aria-hidden="true"
                             class="pointer-events-none absolute -left-4 top-0 h-full w-[11px] -translate-x-1/2 {{ $vertical }}">
                    @endif

                    <p class="text-[clamp(30px,4vw,44px)] font-extrabold leading-none {{ $cifra }}">{{ $metric['value'] ?? '' }}</p>
                    <p class="mt-2 text-sm font-medium uppercase tracking-wide {{ $etiqueta }}">{{ $metric['label'] ?? '' }}</p>
                </div>
            @endforeach
        </div>
    </section>
@endif
