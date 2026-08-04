{{--
    audience_outcomes — eyebrow?, title?, audience_items[] (string list),
    result {eyebrow?, title?, items[] (string list), quote?}
--}}
@php
    $audience = collect($s['audience_items'] ?? [])->filter(fn ($x) => is_string($x) && trim($x) !== '');
    $result = is_array($s['result'] ?? null) ? $s['result'] : [];
    $resultItems = collect($result['items'] ?? [])->filter(fn ($x) => is_string($x) && trim($x) !== '');
@endphp
{{--
    LA MEDIDA DE TODO ESTO ES EL FALLBACK de la página
    (`site/inversionistas.blade.php`): §16.7 promete que publicar no cambia el
    aspecto, y cada diferencia era un salto visible al publicar. Se comparó
    clase por clase.
--}}
<section class="mx-auto max-w-[var(--container-content)] px-6 py-20">
    {{-- El encabezado vive DENTRO de la columna izquierda, arriba de su lista
         —no arriba del grid entero—. Sacado afuera, el título se estiraba a los
         dos tercios del ancho y la sección perdía su lectura de dos columnas
         emparejadas. --}}
    <div class="grid items-center gap-14 lg:grid-cols-2">
        <div>
            @if (($s['eyebrow'] ?? '') !== '')
                <p class="eyebrow {{ \App\Support\Frontend\SectionTypography::eyebrow($s) }} mb-4 text-brand-accent-ink">{{ $s['eyebrow'] }}</p>
            @endif
            @if (($s['title'] ?? '') !== '')
                <h2 class="font-brand-heading text-[clamp(26px,3.4vw,36px)] {{ \App\Support\Frontend\SectionTypography::title($s) }} leading-snug tracking-tight text-brand-primary-ink">{{ $s['title'] }}</h2>
            @endif

            @if ($audience->isNotEmpty())
                <ul class="mt-8 space-y-4">
                    @foreach ($audience as $item)
                        <li class="flex items-start gap-3.5">
                            {{-- Placa cuadrada con el acento, no un círculo
                                 navy: es la del fallback. --}}
                            <span class="mt-0.5 flex h-6 w-6 flex-none items-center justify-center rounded-[7px] bg-brand-accent/10 text-brand-accent-ink">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                            </span>
                            <span class="text-[15px] leading-relaxed text-stone">{{ $item }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="rounded-brand-lg bg-brand-primary bg-gradient-to-br from-black/25 via-transparent to-white/10 p-10 text-on-brand-primary">
            @if (($result['eyebrow'] ?? '') !== '')
                <p class="eyebrow mb-4 text-accent-on-brand-primary">{{ $result['eyebrow'] }}</p>
            @endif
            @if (($result['title'] ?? '') !== '')
                {{-- `text-on-brand-primary` NO es redundante con el color de la
                     tarjeta: `app.css` le da a todo h1-h5 un `color: navy` en la
                     capa base, y una regla propia le gana a lo que herede del
                     padre. Sin esto el título salía azul marino sobre azul
                     marino —contraste 1:1, invisible— y en la tarjeta quedaba un
                     hueco de 56px sin explicación. Es el mismo color que el
                     fallback escribe a mano por la misma razón. --}}
                <h3 class="font-brand-heading text-xl font-bold leading-snug text-on-brand-primary">{{ $result['title'] }}</h3>
            @endif
            @if ($resultItems->isNotEmpty())
                <ul class="mt-6 space-y-3">
                    @foreach ($resultItems as $item)
                        {{-- El punto es un ELEMENTO, no el carácter «·» escrito
                             en el texto: así se alinea solo con la primera línea
                             y los lectores de pantalla no lo leen como parte de
                             la frase. --}}
                        <li class="flex items-center gap-3 text-[15px] text-on-brand-primary/80">
                            <span aria-hidden="true" class="h-1.5 w-1.5 flex-none rounded-full bg-brand-accent"></span>
                            {{ $item }}
                        </li>
                    @endforeach
                </ul>
            @endif
            @if (($result['quote'] ?? '') !== '')
                {{-- Cierra la tarjeta con una línea ARRIBA, no con una barra al
                     costado: es un pie, no una cita destacada dentro del texto. --}}
                <p class="mt-8 border-t border-on-brand-primary/10 pt-6 text-sm italic leading-relaxed text-on-brand-primary/60">{{ $result['quote'] }}</p>
            @endif
        </div>
    </div>
</section>
