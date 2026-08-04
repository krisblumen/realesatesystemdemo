{{--
    rich_text — eyebrow?, title?, body (texto plano; el HTML lo rechaza el
    schema), media_url?, alt?, text_align?, layout?

    SIN FOTO el texto va centrado a 720 px, como hasta ahora: el mismo tipo arma
    la presentación de contacto, que no lleva imagen.

    CON FOTO, `layout` decide dónde va —las mismas tres opciones y la misma
    allowlist que `feature_sequence`, porque es la misma decisión sobre la misma
    foto—:

      split_media_end   → imagen a la derecha (el caso por defecto)
      split_media_start → imagen a la izquierda
      full_overlay      → imagen de fondo a todo el ancho, con el texto encima
--}}
@php
    $conFoto = ! empty($s['media_url']);

    // Alineación del texto. Mapa FIJO, el mismo que usan el hero y
    // `capability_cards`: lo que viaja en el payload es una clave de una
    // allowlist, nunca un fragmento de clase (§6.1).
    //
    // A la IZQUIERDA por defecto, que es como se ven los snapshots publicados
    // hasta hoy: sumar esta opción no le mueve el texto a ninguna sección que
    // nadie haya tocado.
    $alineaciones = ['left' => 'text-left', 'center' => 'text-center', 'right' => 'text-right'];
    $alineacion = $alineaciones[$s['text_align'] ?? 'left'] ?? $alineaciones['left'];

    $layout = $s['layout'] ?? 'split_media_end';
    $mediaStart = $conFoto && $layout === 'split_media_start';
    $alFondo = $conFoto && $layout === 'full_overlay';
@endphp

@if ($alFondo)
    {{-- Imagen a todo el ancho con el texto ENCIMA, abajo. Mismo tratamiento y
         mismo degradado que la disposición de fondo de `feature_sequence`, para
         que las dos secciones se vean cortadas por la misma mano. --}}
    <section class="mx-auto max-w-[var(--container-content)] px-6 py-16">
        <div class="relative overflow-hidden rounded-brand-xl bg-brand-primary bg-gradient-to-br from-black/20 to-white/10 shadow-lg">
            <img src="{{ $s['media_url'] }}" alt="{{ $s['alt'] ?? '' }}"
                 class="block h-auto w-full" loading="lazy" decoding="async">

            {{-- De transparente arriba al color principal abajo. Sólo desde
                 `lg`: más abajo el texto NO va encima de la imagen sino
                 debajo, así que oscurecerla no protegería ninguna lectura. --}}
            <div aria-hidden="true"
                 class="absolute inset-0 hidden bg-gradient-to-b from-transparent via-brand-primary/[0.65] to-brand-primary/[0.92] lg:block"></div>

            {{-- En móvil el texto cae DEBAJO de la imagen: encima, sobre una
                 foto mucho más chica, quedaría ilegible. --}}
            <div class="relative p-8 sm:p-12 lg:absolute lg:inset-x-0 lg:bottom-0">
                <div class="mx-auto max-w-[820px] {{ $alineacion }}">
                    @if (($s['eyebrow'] ?? '') !== '')
                        <p class="eyebrow {{ \App\Support\Frontend\SectionTypography::eyebrow($s) }} mb-3 text-accent-on-brand-primary">{{ $s['eyebrow'] }}</p>
                    @endif
                    @if (($s['title'] ?? '') !== '')
                        <h2 class="mb-5 font-brand-heading text-[clamp(26px,4vw,42px)] {{ \App\Support\Frontend\SectionTypography::title($s) }} leading-snug tracking-tight text-on-brand-primary">{{ $s['title'] }}</h2>
                    @endif
                    @if (($s['body'] ?? '') !== '')
                        <div class="space-y-4 text-[clamp(16px,2vw,19px)] leading-relaxed text-on-brand-primary/85">
                            @foreach (preg_split('/\R{2,}/', (string) $s['body']) as $paragraph)
                                @if (trim($paragraph) !== '')
                                    <p>{!! nl2br(e(trim($paragraph))) !!}</p>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
@else
    <section class="mx-auto max-w-[var(--container-content)] px-6 py-16">
        <div @class([
            'mx-auto max-w-[720px]' => ! $conFoto,
            'grid items-center gap-10 lg:grid-cols-2 lg:gap-16' => $conFoto,
        ])>
            {{-- `lg:order-2` manda el TEXTO a la derecha, que es la forma de
                 dejar la imagen a la izquierda sin reordenar el HTML: en
                 lectura lineal y para un lector de pantalla, el texto sigue
                 viniendo primero. --}}
            <div class="{{ trim($alineacion.' '.($mediaStart ? 'lg:order-2' : '')) }}">
                @if (($s['eyebrow'] ?? '') !== '')
                    <p class="eyebrow {{ \App\Support\Frontend\SectionTypography::eyebrow($s) }} mb-3 text-brand-accent-ink">{{ $s['eyebrow'] }}</p>
                @endif
                @if (($s['title'] ?? '') !== '')
                    <h2 class="mb-6 font-brand-heading text-[clamp(24px,3vw,34px)] {{ \App\Support\Frontend\SectionTypography::title($s) }} leading-snug tracking-tight text-brand-primary-ink">{{ $s['title'] }}</h2>
                @endif
                @if (($s['body'] ?? '') !== '')
                    <div class="space-y-4 text-[17px] leading-relaxed text-graphite">
                        @foreach (preg_split('/\R{2,}/', (string) $s['body']) as $paragraph)
                            @if (trim($paragraph) !== '')
                                <p>{!! nl2br(e(trim($paragraph))) !!}</p>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>

            @if ($conFoto)
                {{-- El `alt` viene del payload y el schema lo exige junto con la
                     imagen, así que no debería faltar. Igual se cae a cadena vacía:
                     un `alt` AUSENTE es peor que uno vacío — el lector de pantalla
                     leería el nombre del archivo en su lugar. --}}
                <img src="{{ $s['media_url'] }}"
                     alt="{{ $s['alt'] ?? '' }}"
                     loading="lazy" decoding="async"
                     class="{{ trim(($mediaStart ? 'lg:order-1 ' : '').'w-full rounded-brand-xl object-cover shadow-md') }}">
            @endif
        </div>
    </section>
@endif
