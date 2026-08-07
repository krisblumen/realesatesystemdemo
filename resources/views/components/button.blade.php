@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
])

@php
    // Roles de marca vía utilities semánticas (Épica 12, §16.5): resuelven en
    // runtime contra las --theme-* que inyecta el layout. Los tonos de hover y las
    // sombras siguen fijos a propósito: son decoración, no rol configurable.
    $base = 'inline-flex items-center justify-center gap-2 font-semibold transition-all duration-200 ease-[var(--ease-out-expo)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus disabled:cursor-not-allowed disabled:opacity-50';

    $variants = [
        // Único CTA primario por vista (color de acento configurable).
        'primary' => 'rounded-brand-md bg-brand-accent text-on-brand-accent shadow-cta hover:brightness-95',
        // Acción de apoyo fuerte (color principal configurable).
        'secondary' => 'rounded-brand-md bg-brand-primary text-on-brand-primary hover:brightness-110',
        // Acción de apoyo discreta (contorno).
        'ghost' => 'rounded-brand-md border border-brand-primary/20 text-brand-primary-ink hover:border-brand-primary hover:bg-brand-primary/5',
        // Contorno SOBRE superficie de marca (hero, bloques navy). El ghost
        // normal usa tinta oscura y prácticamente desaparece ahí; el hero legacy
        // lo resolvía con un <a> a mano, y al unificar el renderer eso pasó a
        // ser una variante del sistema. Ambos tonos son translúcidos: siguen
        // siendo color de texto tematizable, no una superficie sólida.
        'ghost-on-dark' => 'rounded-brand-md border border-on-brand-primary/60 text-on-brand-primary hover:bg-on-brand-primary/10',
        // Sobre fondos claros, énfasis premium: es superficie de marca, no un
        // tono decorativo, así que también se tematiza.
        'dark' => 'rounded-brand-md bg-brand-primary text-on-brand-primary hover:brightness-90',
        // WhatsApp: el único par que NO sigue al tema, porque es la firma de un
        // canal ajeno y reconocerla de un vistazo es lo que la hace funcionar.
        // Su resplandor es verde: uno ámbar lo devolvería a la marca del sitio
        // y se perdería justamente la señal de que abre otro canal.
        'whatsapp' => 'rounded-brand-md bg-whatsapp text-on-whatsapp shadow-whatsapp hover:brightness-95',
        // Enlace con subrayado en hover.
        'link' => 'text-brand-primary-ink underline-offset-4 hover:text-brand-accent-ink hover:underline',
    ];

    $sizes = [
        'sm' => 'h-10 px-5 text-sm',
        'md' => 'h-[52px] px-6 text-sm',
        'lg' => 'h-[60px] px-8 text-base',
    ];

    $classes = trim($base.' '.($variants[$variant] ?? $variants['primary']).' '.($variant === 'link' ? '' : ($sizes[$size] ?? $sizes['md'])));
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['type' => 'button', 'class' => $classes]) }}>{{ $slot }}</button>
@endif
