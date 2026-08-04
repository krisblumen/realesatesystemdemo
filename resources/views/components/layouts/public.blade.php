@props([
    'title' => null,
    'description' => 'New Hauz — Real Estate en Querétaro. Construimos patrimonio, diseñamos espacios y comercializamos oportunidades.',
    // Imagen de portada para el preview al compartir (og:image). Si no se
    // pasa, se usa la imagen institucional por defecto (cuadrada, 1200x1200).
    'image' => null,
    'imageAlt' => null,
    // SEO publicado de la página CMS (M-F1): {meta_title, meta_description,
    // og_title, og_description}. Las páginas kernel (detalle de inmueble, etc.)
    // no lo pasan → null → se usa el prop/título y el fallback de settings().
    'seo' => null,
    // Modo preview owner-only (RFC-077, Lote G): agrega noindex,nofollow y un
    // banner "no es producción". El sitio público real nunca lo activa.
    'preview' => false,
    // Nota extra del banner de preview (p. ej. página deshabilitada). Solo se
    // muestra en modo preview.
    'previewNote' => null,
    // El detalle de inmueble ya tiene su CTA de WhatsApp con el asesor asignado;
    // ahí se oculta el flotante institucional para no duplicar destinos.
    'floatingWhatsapp' => true,
])

<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- Perfil público del kernel, resuelto UNA vez y consumido por todo el head
         y el chrome (M-F3): SEO, branding, contacto. settings() ya trae los
         fallbacks exactos, así que sin configuración el sitio se ve igual. --}}
    @php
        $profile = app(\App\Services\Frontend\FrontendSettingsService::class)->settings();
        $settingsSeo = $profile['seo'];
        $pageSeo = is_array($seo) ? $seo : [];

        // Precedencia (M-F1): SEO publicado de la página → default de settings →
        // el título/descripcion por vista → el hardcodeado. El render lee solo el
        // snapshot publicado; una edición draft nunca cambia estos metadatos.
        $fallbackTitle = $title ? $title.' · New Hauz' : ($settingsSeo['meta_title'] ?: 'New Hauz · Real Estate en Querétaro');
        $metaTitle = ($pageSeo['meta_title'] ?? null) ?: $fallbackTitle;
        $metaDescription = ($pageSeo['meta_description'] ?? null) ?: ($settingsSeo['meta_description'] ?: $description);
        $ogTitle = ($pageSeo['og_title'] ?? null) ?: $metaTitle;
        $ogDescription = ($pageSeo['og_description'] ?? null) ?: $metaDescription;
        $ogImage = $image ?: $profile['brand']['og_image_url'];
        $ogImageAlt = $imageAlt ?: $metaTitle;
        // El tipo/dimensiones JPEG 1200×1200 solo son ciertos para la imagen
        // institucional por defecto; una og:image de media CMS puede ser de otro
        // formato/tamaño, así que no se anuncian (recomendación de la reauditoría).
        $ogIsDefaultImage = ! $image && $ogImage === asset('images/metaimage/meta_image_newhauz.jpg');
    @endphp
    <title>{{ $metaTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    @if ($preview)
        {{-- Preview owner-only (RFC-077): nunca indexable, nunca en sitemap. --}}
        <meta name="robots" content="noindex, nofollow">
    @endif

    {{-- Canonical DERIVADO DE LA RUTA (RFC-076): sin query string, para que un
         parámetro no genere URLs duplicadas ante los buscadores. --}}
    <link rel="canonical" href="{{ url()->to(request()->path() === '/' ? '/' : '/'.trim(request()->path(), '/')) }}">

    {{-- Open Graph: preview al compartir en WhatsApp, Facebook, LinkedIn --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="New Hauz">
    <meta property="og:locale" content="es_MX">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="{{ $ogTitle }}">
    <meta property="og:description" content="{{ $ogDescription }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:image:secure_url" content="{{ $ogImage }}">
    @if ($ogIsDefaultImage)
        <meta property="og:image:type" content="image/jpeg">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="1200">
    @endif
    <meta property="og:image:alt" content="{{ $ogImageAlt }}">

    {{-- Twitter / X --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $ogTitle }}">
    <meta name="twitter:description" content="{{ $ogDescription }}">
    <meta name="twitter:image" content="{{ $ogImage }}">

    <link rel="icon" href="{{ $profile['brand']['favicon_url'] }}" sizes="any">
    <link rel="icon" type="image/png" href="{{ asset('images/brand/newhauz-monogram.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/brand/apple-touch-icon.png') }}">

    {{-- Datos estructurados (RFC-076): Organization + WebSite desde el kernel.
         Se construye con json_encode (nunca concatenación) para que ningún dato
         del CMS pueda romper el bloque <script>. --}}
    @php
        $organizationLd = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $profile['site_name'],
            'url' => url('/'),
            'logo' => $profile['brand']['logo_light_url'],
            'email' => $profile['contact']['email'],
        ];
        if ($profile['contact']['phone']) {
            $organizationLd['telephone'] = $profile['contact']['phone'];
        }
        $socialLinks = app(\App\Services\Frontend\FrontendNavigationService::class)->footer()['social'] ?? [];
        if (! empty($socialLinks)) {
            $organizationLd['sameAs'] = array_values(array_filter(array_map(fn ($s) => $s['url'] ?? null, $socialLinks)));
        }
        $websiteLd = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $profile['site_name'],
            'url' => url('/'),
        ];
    @endphp
    <script type="application/ld+json">{!! json_encode([$organizationLd, $websiteLd], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Las TIPOGRAFÍAS. `@vite` no las trae: el plugin de fuentes las compila en
         un chunk aparte que ninguna entrada importa, así que sin esta línea el
         `@font-face` nunca llega a la página y el sitio dibuja todo con la
         tipografía de reserva del sistema. Pasaba desapercibido mientras el tema
         usaba Montserrat —se parece bastante a la reserva—; con una manuscrita
         quedó a la vista.

         Se le pasan SÓLO los alias de las familias que este sitio usa: sin
         argumento cargaría las seis del catálogo, y ofrecerle variedad al owner
         no puede costarle descargas a quien visita el sitio. --}}
    @if ($fuentes = app(\App\Services\Frontend\FrontendThemeService::class)->fontAliases())
        {{ Vite::fonts($fuentes) }}
    @endif

    {{-- Tema runtime (Épica 12, §16.5): las variables ya vienen validadas y
         normalizadas por FrontendThemeService, así que no puede emitirse aquí
         un valor que rompa el bloque <style>. app.css las puentea a utilities
         semánticas; sin tema configurado, cada var() cae a su token actual. --}}
    <style>
        :root {
@foreach (app(\App\Services\Frontend\FrontendThemeService::class)->cssVariables() as $token => $value)
            {{ $token }}: {{ $value }};
@endforeach
        }
    </style>
</head>
<body class="min-h-screen bg-site-background font-brand-body text-site-text antialiased">
    @if ($preview)
        {{-- Banner de preview (RFC-077): deja claro que NO es el sitio en vivo. --}}
        <div class="sticky top-0 z-[100] flex flex-col items-center justify-center gap-0.5 bg-amber-400 px-4 py-2 text-center text-sm font-semibold text-amber-950">
            <span class="flex items-center gap-2">
                <svg class="h-4 w-4 flex-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/></svg>
                Vista previa — borrador sin publicar. No es el sitio en vivo.
            </span>
            @if ($previewNote)
                <span class="text-xs font-medium text-amber-900">{{ $previewNote }}</span>
            @endif
        </div>
    @endif
    {{-- Navegación, footer y CTAs centralizados (RFC-073). El servicio ya
         validó y normalizó todo: labels y destinos llegan seguros, y sin
         configuración devuelve exactamente la navegación/footer actuales. --}}
    @php
        $frontendNav = app(\App\Services\Frontend\FrontendNavigationService::class);
        $navigation = $frontendNav->navigation();
        $footer = $frontendNav->footer();
        $primaryCta = $navigation['ctas']['primary'];
    @endphp

    {{-- ===== Header ===== --}}
    <input type="checkbox" id="nh-nav" class="peer sr-only">

    <header class="sticky top-0 z-50 border-b border-cloud bg-white/90 backdrop-blur-md">
        <div class="mx-auto flex h-[76px] max-w-[var(--container-content)] items-center justify-between gap-6 px-6">
            <a href="{{ url('/') }}" class="flex-none">
                <img src="{{ $profile['brand']['logo_light_url'] }}" alt="{{ $profile['site_name'] }}" class="h-11 w-auto">
            </a>

            <nav class="hidden items-center gap-2 lg:flex" aria-label="Navegación principal">
                @foreach ($navigation['links'] as $link)
                    @php $isActive = request()->is($link['active_pattern']); @endphp
                    <a href="{{ $link['url'] }}"
                       @if ($isActive) aria-current="page" @endif
                       @class([
                           'group relative rounded-full px-3.5 py-2 text-sm transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus',
                           'bg-navy-50 font-semibold text-brand-primary-ink' => $isActive,
                           'font-medium text-graphite hover:text-brand-primary-ink' => ! $isActive,
                       ])>
                        {{ $link['label'] }}
                        @unless ($isActive)
                            <span class="absolute bottom-1 left-3.5 h-0.5 w-0 bg-brand-accent transition-all duration-300 motion-reduce:transition-none group-hover:w-[calc(100%-1.75rem)]"></span>
                        @endunless
                    </a>
                @endforeach
            </nav>

            <div class="flex items-center gap-3">
                <a href="{{ $primaryCta['url'] }}"
                   @if ($primaryCta['external']) target="_blank" rel="noopener noreferrer" @endif
                   class="hidden rounded-brand-md bg-brand-accent px-5 py-2.5 text-sm font-semibold text-on-brand-accent shadow-cta transition hover:brightness-95 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus sm:inline-block">
                    {{ $primaryCta['label'] }}
                </a>
                <label for="nh-nav" id="nh-nav-toggle" role="button" tabindex="0"
                       aria-controls="nh-mobile-menu" aria-expanded="false"
                       class="flex h-11 w-11 cursor-pointer items-center justify-center rounded-[var(--radius-md)] border border-cloud bg-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus lg:hidden"
                       aria-label="Abrir menú">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round">
                        <path d="M3 6h18M3 12h18M3 18h18" />
                    </svg>
                </label>
            </div>
        </div>
    </header>

    {{-- ===== Drawer móvil ===== --}}
    <label for="nh-nav"
           class="invisible fixed inset-0 z-[60] bg-black/40 opacity-0 transition-opacity duration-300 motion-reduce:transition-none peer-checked:visible peer-checked:opacity-100"
           aria-hidden="true"></label>
    <aside id="nh-mobile-menu"
           class="fixed inset-y-0 right-0 z-[70] w-[300px] max-w-[84vw] translate-x-full overflow-y-auto bg-white p-6 shadow-xl transition-transform duration-300 ease-[var(--ease-out-expo)] motion-reduce:transition-none peer-checked:translate-x-0"
           role="dialog" aria-modal="true" aria-label="Menú">
        <div class="mb-5 flex items-center justify-between">
            <img src="{{ $profile['brand']['logo_light_url'] }}" alt="{{ $profile['site_name'] }}" class="h-9 w-auto">
            <label for="nh-nav" id="nh-nav-close" role="button" tabindex="0"
                   class="flex h-10 w-10 cursor-pointer items-center justify-center rounded-full focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus" aria-label="Cerrar menú">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round">
                    <path d="M6 6l12 12M18 6 6 18" />
                </svg>
            </label>
        </div>
        <nav class="flex flex-col" aria-label="Navegación móvil">
            @foreach ($navigation['links'] as $link)
                @php $isActive = request()->is($link['active_pattern']); @endphp
                <a href="{{ $link['url'] }}"
                   @if ($isActive) aria-current="page" @endif
                   class="flex items-center gap-2.5 border-b border-fog py-3 text-base font-semibold {{ $isActive ? 'text-brand-accent-ink' : 'text-brand-primary-ink' }}">
                    @if ($isActive)
                        <span class="h-2 w-2 shrink-0 rounded-full bg-brand-accent"></span>
                    @endif
                    {{ $link['label'] }}
                </a>
            @endforeach
            <a href="{{ $primaryCta['url'] }}"
               @if ($primaryCta['external']) target="_blank" rel="noopener noreferrer" @endif
               class="mt-5 rounded-brand-md bg-brand-accent px-5 py-3 text-center text-sm font-semibold text-on-brand-accent shadow-cta">
                {{ $primaryCta['label'] }}
            </a>
        </nav>
    </aside>

    {{-- ===== Contenido ===== --}}
    <main>
        {{ $slot }}
    </main>

    {{-- ===== Footer ===== --}}
    <footer class="bg-brand-primary text-on-brand-primary/80">
        <div class="mx-auto max-w-[var(--container-content)] px-6 pt-16 pb-9">
            <div class="grid gap-10 border-b border-white/10 pb-12 sm:grid-cols-2 lg:grid-cols-[1.6fr_1fr_1fr_1fr]">
                <div>
                    <img src="{{ $profile['brand']['logo_dark_url'] }}" alt="{{ $profile['site_name'] }}" class="h-16 w-auto">
                    <p class="mt-5 max-w-[260px] text-sm leading-relaxed text-on-brand-primary/60">
                        Construimos patrimonio, diseñamos espacios y comercializamos oportunidades en Querétaro.
                    </p>
                    @if (! empty($footer['social']))
                        {{-- Redes debajo de la descripción: ícono de app (envolvente
                             redondeada) + nombre de la red. Cada red aparece solo si
                             tiene URL válida (social() filtra vacías/ inseguras). --}}
                        <ul class="mt-6 space-y-3">
                            @foreach ($footer['social'] as $social)
                                <li>
                                    <a href="{{ $social['url'] }}" target="_blank" rel="noopener noreferrer"
                                       aria-label="{{ $social['label'] }}"
                                       class="group inline-flex items-center gap-3 text-sm text-on-brand-primary/70 transition-colors hover:text-accent-on-brand-primary">
                                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-on-brand-primary/10 ring-1 ring-on-brand-primary/15 transition-colors group-hover:bg-on-brand-primary/15">
                                            @include('frontend.social-icon', ['network' => $social['network'], 'class' => 'h-5 w-5'])
                                        </span>
                                        <span>{{ $social['label'] }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                @foreach ($footer['columns'] as $column)
                    <div>
                        <p class="eyebrow text-accent-on-brand-primary">{{ $column['title'] }}</p>
                        <ul class="mt-4 space-y-3 text-sm">
                            @foreach ($column['links'] as $link)
                                @continue (! $link['enabled'])
                                <li>
                                    <a href="{{ $link['url'] }}"
                                       @if ($link['external']) target="_blank" rel="noopener noreferrer" @endif
                                       class="text-on-brand-primary/70 transition-colors hover:text-accent-on-brand-primary">{{ $link['label'] }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
                <div>
                    <p class="eyebrow text-accent-on-brand-primary">Contacto</p>
                    <ul class="mt-4 space-y-3 text-sm text-on-brand-primary/70">
                        {{-- Perfil del kernel (M-F3), con el fallback exacto actual
                             cuando la configuración no trae el dato. --}}
                        <li>{{ $profile['contact']['address'] ?: 'Alamos Querétaro Qro.' }}</li>
                        <li>{{ $profile['contact']['phone'] ?: '+52 442 272 26 23' }}</li>
                        <li>{{ $profile['contact']['email'] }}</li>
                    </ul>
                    @php
                        // Horario de atención configurable: el key-value {Día => Horario}
                        // que arma el editor. Se ORDENA por semana en el render porque
                        // jsonb no preserva el orden de claves; los días no reconocidos
                        // van al final. Se recorre defensivamente (datos del CMS).
                        $storedHours = is_array($profile['contact']['hours'] ?? null) ? $profile['contact']['hours'] : [];
                        $weekOrder = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
                        $businessHours = [];
                        foreach ($weekOrder as $day) {
                            if (is_string($storedHours[$day] ?? null) && trim($storedHours[$day]) !== '') {
                                $businessHours[$day] = $storedHours[$day];
                            }
                        }
                        foreach ($storedHours as $day => $range) {
                            if (is_string($day) && is_string($range) && ! in_array($day, $weekOrder, true) && trim($day) !== '' && trim($range) !== '') {
                                $businessHours[$day] = $range;
                            }
                        }
                    @endphp
                    @if (! empty($businessHours))
                        <p class="eyebrow mt-6 text-accent-on-brand-primary">Horario</p>
                        <ul class="mt-4 space-y-2 text-sm text-on-brand-primary/70">
                            @foreach ($businessHours as $day => $range)
                                <li class="flex justify-between gap-4">
                                    <span>{{ $day }}</span>
                                    <span class="text-on-brand-primary/90">{{ $range }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-4 pt-7">
                <span class="text-xs text-on-brand-primary/40">{{ $footer['legal_text'] }}</span>
            </div>
        </div>
    </footer>

    {{-- ===== WhatsApp flotante — canal de conversión #1, siempre accesible ===== --}}
    @if ($floatingWhatsapp)
        <a href="{{ $profile['contact']['whatsapp_href'] }}"
           target="_blank" rel="noopener"
           {{-- El verde sale del mismo token que el botón de CTA: el hexadecimal
                estaba escrito acá a mano y ahora vive en un solo lugar. Acá el
                logo SÍ va en blanco: es un ícono de 28 px sin texto, la forma
                universal del botón de WhatsApp, y no una etiqueta que leer. --}}
           class="fixed bottom-5 right-5 z-[80] flex h-14 w-14 items-center justify-center rounded-full bg-whatsapp text-white shadow-[0_8px_20px_rgba(37,211,102,0.3)] transition-transform duration-200 hover:scale-105"
           aria-label="Escríbenos por WhatsApp">
            <x-icons.whatsapp class="h-7 w-7" />
        </a>
    @endif

    {{-- A11y del menú móvil (RFC-073): el checkbox da el toggle sin JS; este
         script solo agrega teclado, Escape, aria-expanded y foco. La animación
         respeta prefers-reduced-motion vía las variantes motion-reduce. --}}
    <script>
        (function () {
            const cb = document.getElementById('nh-nav');
            const toggle = document.getElementById('nh-nav-toggle');
            const close = document.getElementById('nh-nav-close');
            const menu = document.getElementById('nh-mobile-menu');
            if (!cb || !toggle || !menu) return;

            function setOpen(open) {
                cb.checked = open;
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                if (open) {
                    (menu.querySelector('a, button, [tabindex]') || menu).focus();
                } else {
                    toggle.focus();
                }
            }

            // A <label role="button"> does not toggle its checkbox from the
            // keyboard on its own; wire Enter/Space explicitly.
            [toggle, close].forEach(function (el) {
                if (!el) return;
                el.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        setOpen(el === toggle ? !cb.checked : false);
                    }
                });
            });

            cb.addEventListener('change', function () {
                toggle.setAttribute('aria-expanded', cb.checked ? 'true' : 'false');
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && cb.checked) setOpen(false);
            });

            // Keep focus inside the open drawer.
            menu.addEventListener('keydown', function (e) {
                if (e.key !== 'Tab' || !cb.checked) return;
                const items = menu.querySelectorAll('a, button, [tabindex="0"]');
                if (!items.length) return;
                const first = items[0];
                const last = items[items.length - 1];
                if (e.shiftKey && document.activeElement === first) {
                    e.preventDefault();
                    last.focus();
                } else if (!e.shiftKey && document.activeElement === last) {
                    e.preventDefault();
                    first.focus();
                }
            });
        })();
    </script>
</body>
</html>
