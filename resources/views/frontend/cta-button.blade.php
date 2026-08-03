{{--
    Renders a single resolved CTA as a button, or nothing when the CTA is null
    (an invalid/unsafe target was dropped by CtaResolver). External links get
    target/rel — never trust an editorial URL with the opener window. A null
    attribute is omitted by Blade, so internal links stay clean.

    UN DESTINO DE WHATSAPP SE DIBUJA COMO WHATSAPP: verde y con su ícono, sin
    importar qué variante haya pedido la sección. No es una preferencia estética
    —es la señal de que ese botón abre una conversación y no otra página del
    sitio, y quien la reconoce sabe qué va a pasar antes de tocarla. Por eso la
    decisión vive acá, en el único lugar por donde pasan TODOS los botones de
    CTA, y no repetida en el hero y en cada cierre.

    @var array|null $cta      resolved ['label'=>, 'type'=>, 'url'=>, 'external'=>] or null
    @var string     $variant  x-button variant (default primary)
--}}
@if (is_array($cta ?? null) && ($cta['url'] ?? '') !== '')
    @php
        $external = (bool) ($cta['external'] ?? false);
        $esWhatsapp = ($cta['type'] ?? null) === 'whatsapp';
    @endphp
    <x-button
        :variant="$esWhatsapp ? 'whatsapp' : ($variant ?? 'primary')"
        :href="$cta['url']"
        :target="$external ? '_blank' : null"
        :rel="$external ? 'noopener noreferrer' : null"
    >
        @if ($esWhatsapp)
            {{-- El `gap-2` de la base separa ícono y texto. --}}
            <x-icons.whatsapp class="h-5 w-5" />
        @endif
        {{ $cta['label'] }}
    </x-button>
@endif
