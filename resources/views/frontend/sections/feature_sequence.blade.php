{{--
    feature_sequence — eyebrow?, title?, items[] {eyebrow?, title, body?, media_url, alt?, layout}

    Paneles de imagen con texto. `layout` decide la disposición, y son TRES:

      split_media_start → imagen a la izquierda
      split_media_end   → imagen a la derecha (el caso por defecto)
      full_overlay      → imagen de fondo a todo el ancho, con el texto encima

    La tercera existía en la allowlist y en el formulario desde el principio,
    pero no tenía rama propia acá: el owner la elegía, se guardaba bien, y el
    sitio la dibujaba como si fuera «a la derecha».
--}}
@php $items = collect($s['items'] ?? [])->filter(fn ($p) => is_array($p)); @endphp
<section class="mx-auto max-w-[var(--container-content)] px-6 py-16">
    @if (($s['eyebrow'] ?? '') !== '' || ($s['title'] ?? '') !== '')
        <div class="mb-12 max-w-[640px]">
            @if (($s['eyebrow'] ?? '') !== '')
                <p class="eyebrow {{ \App\Support\Frontend\SectionTypography::eyebrow($s) }} text-brand-accent-ink">{{ $s['eyebrow'] }}</p>
            @endif
            @if (($s['title'] ?? '') !== '')
                <h2 class="mt-3 font-brand-heading text-[clamp(26px,3.4vw,36px)] {{ \App\Support\Frontend\SectionTypography::title($s) }} leading-snug tracking-tight text-brand-primary-ink">{{ $s['title'] }}</h2>
            @endif
        </div>
    @endif

    <div class="space-y-16">
        @foreach ($items as $panel)
            @php
                $layout = $panel['layout'] ?? '';
                $mediaStart = $layout === 'split_media_start';
                // `full_overlay` estaba en la allowlist y en el formulario —«Imagen
                // de fondo»— pero NINGUNA rama lo dibujaba, así que caía al caso por
                // defecto y la imagen salía a la derecha: el owner elegía una opción
                // que el sitio ignoraba en silencio.
                $alFondo = $layout === 'full_overlay' && ! empty($panel['media_url']);
            @endphp

            @if ($alFondo)
                {{-- Imagen a todo el ancho con el texto ENCIMA, abajo y centrado.
                     Es el mismo tratamiento que el fallback de la página
                     (`site/inversionistas.blade.php`), de donde salen el degradado
                     y las medidas. --}}
                <div class="relative overflow-hidden rounded-brand-lg bg-brand-primary bg-gradient-to-br from-black/20 to-white/10 shadow-lg">
                    <img src="{{ $panel['media_url'] }}" alt="{{ $panel['alt'] ?? '' }}"
                         class="block h-auto w-full" loading="lazy">

                    {{-- De transparente arriba al color principal abajo, con la
                         parada intermedia al 50% —la misma que el fallback—.
                         Sólo desde `lg`: más abajo el texto NO va encima de la
                         imagen sino debajo, así que oscurecerla no protegería
                         ninguna lectura y sólo la ensuciaría. --}}
                    <div aria-hidden="true"
                         class="absolute inset-0 hidden bg-gradient-to-b from-transparent via-brand-primary/[0.65] to-brand-primary/[0.92] lg:block"></div>

                    {{-- En móvil el texto cae DEBAJO de la imagen: encima, sobre una
                         foto que ahí es mucho más chica, quedaría ilegible. --}}
                    <div class="relative p-8 sm:p-12 lg:absolute lg:inset-x-0 lg:bottom-0">
                        <div class="mx-auto max-w-[820px] text-center">
                            @if (($panel['eyebrow'] ?? '') !== '')
                                <p class="eyebrow mb-3 text-accent-on-brand-primary">{{ $panel['eyebrow'] }}</p>
                            @endif
                            <h3 class="font-brand-heading text-[clamp(26px,4vw,42px)] font-bold leading-snug tracking-tight text-on-brand-primary">{{ $panel['title'] ?? '' }}</h3>
                            @if (($panel['body'] ?? '') !== '')
                                <p class="mt-5 text-[clamp(16px,2vw,20px)] leading-relaxed text-on-brand-primary/85">{{ $panel['body'] }}</p>
                            @endif
                        </div>
                    </div>
                </div>
            @else
                <div class="grid items-center gap-12 lg:grid-cols-2">
                    <div class="{{ $mediaStart ? 'lg:order-2' : '' }}">
                        @if (($panel['eyebrow'] ?? '') !== '')
                            <p class="eyebrow text-brand-accent-ink">{{ $panel['eyebrow'] }}</p>
                        @endif
                        <h3 class="mt-3 font-brand-heading text-[clamp(22px,2.6vw,28px)] font-bold leading-snug text-brand-primary-ink">{{ $panel['title'] ?? '' }}</h3>
                        @if (($panel['body'] ?? '') !== '')
                            <p class="mt-4 text-[16px] leading-relaxed text-graphite">{{ $panel['body'] }}</p>
                        @endif
                    </div>
                    @if (! empty($panel['media_url']))
                        <div class="min-h-[320px] overflow-hidden rounded-brand-lg shadow-lg {{ $mediaStart ? 'lg:order-1' : '' }}">
                            <img src="{{ $panel['media_url'] }}" alt="{{ $panel['alt'] ?? '' }}" class="h-full min-h-[320px] w-full object-cover" loading="lazy">
                        </div>
                    @endif
                </div>
            @endif
        @endforeach
    </div>
</section>
