{{--
    cta — eyebrow?, title?, body?, primary_cta?, secondary_cta?, background_color?, bullets?

    Una banda de cierre. Los botones se dibujan sólo si su CTA resolvió.

    LA TARJETA TIENE DOS FORMAS, y las decide la cantidad de datos destacados:

      sin bullets → centrada y a todo el ancho, como siempre. Es la forma que
                    usan los cierres de las otras cuatro páginas, y no cambia.
      con bullets → partida en dos: el texto a la izquierda, los datos a la
                    derecha, separados por hairlines.

    LOS DATOS NO ESTIRAN LA TARJETA. Su alto lo fija la columna de texto, así que
    a partir del cuarto dato la tipografía y los espacios BAJAN DE ESCALA en vez
    de empujar la caja hacia abajo. Con cinco bajan una vez más. La alternativa
    —dejarla crecer— desbalancea la página entera: el bloque siguiente se corre y
    la columna izquierda queda flotando en un mar de aire.

    EL FONDO sale de la paleta cerrada de la marca (`brand_palette`), la misma
    que elige el borde de las tarjetas de «Qué hacemos». Encima va un degradado
    de negro casi transparente hacia abajo y a la derecha: no es otro color, es
    el mismo apenas ensombrecido, así que sirve para las diez opciones sin
    necesitar diez pares de clases.

    EL TEXTO SE INVIERTE SOLO cuando el fondo es claro. No es un lujo: el acento
    por defecto es un ámbar con contraste 2.1:1 contra blanco, así que un título
    blanco encima quedaría prácticamente ilegible. Quién es «claro» lo calcula
    `BrandPalette` sobre el color real, porque el owner puede cambiar su marca y
    una lista escrita a mano quedaría mintiendo.

    Las clases salen de MAPAS FIJOS y son literales. Nada del payload se
    interpola en un nombre de clase ni en un `style`: es la misma regla del hero
    (§6.1), y es lo que permite servir la página sin `unsafe-inline`.

    Si se agrega un color a `brand_palette`, su clase `bg-*` debe sumarse a la
    lista de abajo o Tailwind no la compila y la tarjeta sale sin fondo:
      bg-brand-accent-d2 bg-brand-accent-d1 bg-brand-accent bg-brand-accent-l1
      bg-brand-accent-l2 bg-brand-primary-d2 bg-brand-primary-d1
      bg-brand-primary bg-brand-primary-l1 bg-brand-primary-l2
--}}
@php
    $bullets = array_slice($s['bullets'] ?? [], 0, 5);
    $partida = $bullets !== [];

    $paleta = (array) config('frontend-sections.brand_palette');
    $colorClave = $s['background_color'] ?? 'primary';
    $fondo = $paleta[$colorClave]['bg'] ?? 'bg-brand-primary';

    // Sobre un fondo claro, tinta oscura. Se calcula sobre el color REAL.
    $claro = app(\App\Services\Frontend\BrandPalette::class)->needsDarkText($colorClave);

    // El TÍTULO puede llevar su propio color, y sólo él: el antetítulo y el
    // cuerpo siguen saliendo del juego de tinta que decide el fondo. Es a
    // propósito — lo que se busca destacar es el título, y dejar que los tres se
    // eligieran por separado es la forma de terminar con tres colores que no se
    // hablan entre sí.
    $tituloPropio = isset($s['title_color'])
        ? ($paleta[$s['title_color']]['text'] ?? null)
        : null;

    // El brillo va un tono más claro cuando el fondo YA ES el acento: ahí el
    // normal sería acento sobre acento y no se vería. Las dos clases están
    // escritas enteras porque Tailwind las compila leyendo este archivo.
    $brillo = str_starts_with($colorClave, 'accent') ? 'brand-glow-light' : 'brand-glow';

    // Los dos juegos de tinta, enteros y literales.
    $tinta = $claro
        ? [
            'antetitulo' => 'text-brand-primary/70',
            'titulo' => 'text-brand-primary',
            'cuerpo' => 'text-brand-primary/75',
            'dato' => 'text-brand-primary',
            'linea' => 'divide-brand-primary/15',
            // Sobre claro el botón lleno va en color principal: un botón de
            // acento sobre un fondo de acento se pierde dentro del fondo.
            'boton' => 'dark',
            'boton_secundario' => 'ghost',
        ]
        : [
            'antetitulo' => 'text-accent-on-brand-primary',
            'titulo' => 'text-on-brand-primary',
            'cuerpo' => 'text-on-brand-primary/75',
            'dato' => 'text-accent-on-brand-primary',
            'linea' => 'divide-on-brand-primary/15',
            'boton' => 'primary',
            // `ghost` a secas usa tinta oscura y desaparece sobre navy; ésta es
            // la variante que el sistema ya tenía para superficies de marca.
            'boton_secundario' => 'ghost-on-dark',
        ];

    // Mapa FIJO de escala por cantidad de datos. Las clases están escritas
    // enteras porque Tailwind las compila leyendo este archivo: una armada por
    // concatenación no existiría en el CSS final.
    //
    // La escalera BAJA COMPLETA cuando baja su peldaño de arriba. Al alinear el
    // caso de hasta tres con el fallback —de 48px a 36px— hubo que bajar
    // también cuatro y cinco: dejándolos donde estaban, tres y cuatro datos
    // quedaban del mismo tamaño en escritorio y desaparecía el achicamiento que
    // impide que la tarjeta se estire. Lo atrapó FrontendCtaBulletsTest.
    $escala = match (true) {
        count($bullets) >= 5 => [
            'dato' => 'text-2xl',
            'texto' => 'text-xs leading-snug',
            'aire' => 'py-2.5',
            'separacion' => 'gap-x-3',
        ],
        count($bullets) === 4 => [
            'dato' => 'text-3xl',
            'texto' => 'text-sm leading-snug',
            'aire' => 'py-3.5',
            'separacion' => 'gap-x-4',
        ],
        // Hasta tres datos, la escala es la del FALLBACK de la portada
        // (`welcome.blade.php`), medida clase por clase. No es una preferencia:
        // §16.7 promete que publicar no cambia el aspecto, y con `sm:text-5xl`
        // el número saltaba de 36px a 48px al publicar. Además, al ser el
        // bloque más alto y estar centrado, arrancaba más arriba y dejaba al
        // antetítulo solo en la esquina.
        default => [
            'dato' => 'text-4xl',
            'texto' => 'pt-1.5 text-[15px] leading-snug',
            // `py-7` y no `py-3.5`: en el fallback la hairline es un elemento
            // más de la columna flex, así que recibe el `gap-7` de los DOS
            // lados —28+1+28 = 57px entre filas—. Acá la línea es un borde, sin
            // gap propio, así que el aire tiene que ponerlo la fila entera.
            'aire' => 'py-6',
            'separacion' => 'gap-x-5',
        ],
    };
@endphp

<section class="mx-auto max-w-[var(--container-content)] px-6 py-16">
    {{-- El degradado va SOBRE el color de fondo: `bg-*` pinta el color y
         `bg-gradient-*` la imagen, así que conviven en el mismo elemento. --}}
    <div class="relative overflow-hidden rounded-brand-xl {{ $fondo }} bg-gradient-to-br from-black/0 to-black/30 px-8 py-16 shadow-lg sm:px-12">
        {{-- El brillo de marca, en su propia capa.

             No puede ser otra clase de fondo en la tarjeta: el `background-image`
             ya lo ocupa el degradado de oscurecimiento y se pisarían. El
             `overflow-hidden` del padre lo recorta contra las esquinas
             redondeadas, que si no asomaría en la punta.

             `aria-hidden` y sin eventos: es decoración pura, no debe aparecer en
             un lector de pantalla ni robar un clic al contenido. --}}
        <span aria-hidden="true" class="{{ $brillo }} pointer-events-none absolute inset-0"></span>

        {{-- Sin `items-center`: el fallback deja que cada columna arranque
             arriba y centra SÓLO la lista de datos dentro de su celda
             (`justify-center` más abajo). Centrar la celda entera movía el
             encabezado según cuántos datos hubiera. --}}
        <div class="relative {{ $partida ? 'grid gap-12 lg:grid-cols-2' : '' }}">

            @if ($partida)
                {{-- La regla que separa el texto de los datos: el MISMO PNG que
                     usa `metrics`, no una línea de CSS, para que las dos
                     secciones se vean cortadas por la misma mano. Sus puntas
                     van en degradado, así que no corta a filo contra los bordes
                     de la tarjeta.

                     Va en `left-1/2` y no como borde de la columna derecha: con
                     `grid-cols-2` y `gap-12` ese borde cae 24px corrido del
                     centro real —la mitad del gap—, y se notaba.

                     Sólo en `lg`: más abajo la grilla es de UNA columna y las
                     dos partes quedan apiladas, así que una regla vertical al
                     medio cortaría el contenido en vez de separarlo.

                     Decorativa: la relación entre dato y descripción ya la dice
                     el `dl`. De ahí el `alt` vacío y el `aria-hidden`. --}}
                <img src="{{ asset('images/assets/v_divider.png') }}"
                     alt="" aria-hidden="true"
                     class="pointer-events-none absolute left-1/2 top-0 hidden h-full w-[11px] -translate-x-1/2 lg:block">
            @endif

            {{-- El texto. Es el mismo en las dos formas: sólo cambia si se
                 centra o se alinea a la izquierda para acompañar a los datos. --}}
            <div class="{{ $partida ? 'text-left' : 'text-center' }}">
                {{-- Los tres tamaños y márgenes son los del FALLBACK de la
                     portada (`welcome.blade.php`), medidos clase por clase:
                     §16.7 promete que publicar no cambia el aspecto, y cada
                     diferencia acá era un salto visible al publicar. --}}
                @if (($s['eyebrow'] ?? '') !== '')
                    <p class="eyebrow {{ \App\Support\Frontend\SectionTypography::eyebrow($s) }} mb-5 {{ $tinta['antetitulo'] }}">{{ $s['eyebrow'] }}</p>
                @endif
                @if (($s['title'] ?? '') !== '')
                    <h2 class="font-brand-heading text-[clamp(28px,3.6vw,38px)] {{ \App\Support\Frontend\SectionTypography::title($s) }} leading-tight {{ $tituloPropio ?? $tinta['titulo'] }}">{{ $s['title'] }}</h2>
                @endif
                @if (($s['body'] ?? '') !== '')
                    <p class="mt-5 text-[17px] leading-relaxed {{ $tinta['cuerpo'] }} {{ $partida ? 'max-w-[480px]' : 'mx-auto max-w-[560px]' }}">{{ $s['body'] }}</p>
                @endif
                @if (($s['primary_cta'] ?? null) || ($s['secondary_cta'] ?? null))
                    <div class="mt-8 flex flex-wrap gap-4 {{ $partida ? 'justify-start' : 'justify-center' }}">
                        @include('frontend.cta-button', ['cta' => $s['primary_cta'] ?? null, 'variant' => $tinta['boton']])
                        @include('frontend.cta-button', ['cta' => $s['secondary_cta'] ?? null, 'variant' => $tinta['boton_secundario']])
                    </div>
                @endif
            </div>

            @if ($partida)
                {{-- Los datos.

                     LA COLUMNA DEL DATO LA IGUALA `subgrid`: cada fila hereda
                     las columnas del `dl`, así que todas las descripciones
                     arrancan en la misma vertical aunque «+12%» sea más ancho
                     que «+9». Un ancho mínimo fijo no alcanzaba —el dato que lo
                     superaba empujaba su propio texto y la lista se veía
                     quebrada—, y un ancho fijo recortaría un dato largo.

                     `divide-y` pone la línea ENTRE filas y no arriba de la
                     primera ni debajo de la última, así que el bloque no
                     arranca ni termina con una raya suelta. --}}
                <dl class="grid content-center grid-cols-[auto_1fr] divide-y {{ $tinta['linea'] }}">
                    @foreach ($bullets as $b)
                        {{-- `first:pt-0 last:pb-0`: el aire vertical separa filas
                             ENTRE sí, no despega la lista de sus bordes. Sin
                             esto el bloque arrancaba 14px más abajo que en el
                             fallback, que usa `gap` —y un gap no pone espacio
                             antes del primero ni después del último—. --}}
                        <div class="col-span-2 grid grid-cols-subgrid items-baseline first:pt-0 last:pb-0 {{ $escala['separacion'] }} {{ $escala['aire'] }}">
                            {{-- `font-brand-heading` y `leading-none` salen del
                                 fallback: sin la primera el número se dibujaba
                                 con la fuente de cuerpo, y sin la segunda su
                                 caja de línea empujaba la fila. --}}
                            <dt class="font-brand-heading font-extrabold leading-none {{ $tinta['dato'] }} {{ $escala['dato'] }}">
                                {{ $b['value'] }}
                            </dt>
                            <dd class="{{ $tinta['cuerpo'] }} {{ $escala['texto'] }}">{{ $b['text'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </div>
    </div>
</section>
