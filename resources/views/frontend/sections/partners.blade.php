{{--
    partners — items[] {name, media_url?, alt?}, card_border?, card_border_width?,
    card_border_color?

    Una tira de logotipos en tarjetas blancas. CINCO A LA VISTA; a partir del
    sexto la tira avanza sola, en bucle.

    EL BUCLE ES CSS PURO, sin una línea de JavaScript: el sitio se sirve sin
    `unsafe-inline`, así que un carrusel con script sería lo único de la página
    que obligaría a relajar esa política. La lista se dibuja DOS VECES y la pista
    se desplaza -50%: al terminar, la segunda copia está exactamente donde
    arrancó la primera y el salto no se ve. La copia va `aria-hidden` para que un
    lector de pantalla no lea a cada aliado dos veces.

    Con `prefers-reduced-motion` la animación se detiene y el contenedor pasa a
    poder desplazarse a mano. Sin eso, quien pide menos movimiento vería sólo los
    primeros cinco y el resto quedaría inalcanzable — peor que el movimiento que
    quiso evitar.

    El LOGO ES OPCIONAL: sin él la tarjeta muestra el nombre. Los aliados que ya
    estaban cargados sólo tienen nombre, y exigir imagen los habría borrado de la
    página en el momento de publicar.

    El BORDE es el mismo mecanismo que el de «Qué hacemos» y sale de la misma
    paleta cerrada. Las clases son literales y salen de mapas fijos: nada del
    payload se interpola en un nombre de clase (§6.1).

    Si se agrega un color a `brand_palette`, su clase debe sumarse a esta lista o
    Tailwind no la compila y el borde sale sin color:
      border-brand-accent-d2 border-brand-accent-d1 border-brand-accent
      border-brand-accent-l1 border-brand-accent-l2 border-brand-primary-d2
      border-brand-primary-d1 border-brand-primary border-brand-primary-l1
      border-brand-primary-l2
--}}
@php
    $items = collect($s['items'] ?? [])
        ->filter(fn ($p): bool => is_array($p) && trim((string) ($p['name'] ?? '')) !== '')
        ->values();

    // Cinco caben en una fila; a partir de ahí la tira se mueve.
    $enMovimiento = $items->count() > 5;

    $colores = (array) config('frontend-sections.brand_palette');
    $grosores = [1 => 'border', 2 => 'border-2', 3 => 'border-[3px]', 4 => 'border-4'];

    // Sin borde elegido, un contorno mínimo: la tarjeta es blanca sobre un fondo
    // casi blanco y sin nada que la delimite se pierde contra la página.
    $borde = ($s['card_border'] ?? false) === true
        ? ($grosores[$s['card_border_width'] ?? 1] ?? $grosores[1])
            .' '.($colores[$s['card_border_color'] ?? 'accent']['border'] ?? 'border-brand-accent')
        : 'border border-black/5';

    // La tarjeta, sin su ancho: lo pone cada uno de los dos armados.
    //
    // Quietas van en una GRILLA que se reacomoda por tamaño de pantalla: cinco
    // columnas en escritorio, menos en el teléfono, donde cinco logos serían
    // ilegibles. En movimiento van en una pista de ancho fijo, porque la pista
    // mide lo que miden sus hijos y un ancho en porcentaje sería circular.
    $tarjeta = 'flex h-24 items-center justify-center rounded-brand-lg bg-white px-6 shadow-sm '.$borde;
@endphp

@if ($items->isNotEmpty())
    <section class="mx-auto max-w-[var(--container-content)] px-6 py-12">
        <div @class([
            'overflow-hidden motion-reduce:overflow-x-auto' => $enMovimiento,
        ])>
            <div @class([
                'grid grid-cols-2 gap-6 sm:grid-cols-3 lg:grid-cols-5' => ! $enMovimiento,
                'nh-partners-track flex w-max items-stretch gap-6' => $enMovimiento,
            ])>
                @foreach ($items as $partner)
                    <div class="{{ $tarjeta }} {{ $enMovimiento ? 'w-44 shrink-0 sm:w-52' : '' }}">
                        @if (! empty($partner['media_url']))
                            <img src="{{ $partner['media_url'] }}"
                                 alt="{{ $partner['alt'] ?? $partner['name'] }}"
                                 loading="lazy" decoding="async"
                                 class="max-h-14 w-auto max-w-full object-contain">
                        @else
                            <span class="text-center font-brand-heading text-base font-semibold text-stone">{{ $partner['name'] }}</span>
                        @endif
                    </div>
                @endforeach

                @if ($enMovimiento)
                    {{-- La segunda vuelta, que es lo que hace que el bucle no
                         tenga costura. No se anuncia: es la misma lista. --}}
                    @foreach ($items as $partner)
                        <div class="{{ $tarjeta }} w-44 shrink-0 sm:w-52" aria-hidden="true">
                            @if (! empty($partner['media_url']))
                                <img src="{{ $partner['media_url'] }}" alt=""
                                     loading="lazy" decoding="async"
                                     class="max-h-14 w-auto max-w-full object-contain">
                            @else
                                <span class="text-center font-brand-heading text-base font-semibold text-stone">{{ $partner['name'] }}</span>
                            @endif
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    </section>
@endif
