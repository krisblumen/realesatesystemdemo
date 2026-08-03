@props([
    'color' => 'navy',
    'solid' => false,
])

@php
    $solidMap = [
        'orange' => 'bg-brand-accent text-on-brand-accent',
        'navy' => 'bg-brand-primary text-on-brand-primary',
        'success' => 'bg-success text-white',
        'danger' => 'bg-danger text-white',
        'warning' => 'bg-warning text-white',
        'neutral' => 'bg-cloud text-stone',
    ];

    $softMap = [
        'orange' => 'bg-brand-accent/10 text-brand-accent-ink',
        'navy' => 'bg-brand-primary/10 text-brand-primary-ink',
        'success' => 'bg-[#e6f4ec] text-success',
        'danger' => 'bg-[#fbe9e7] text-danger',
        'warning' => 'bg-[#fcf3dc] text-warning',
        'neutral' => 'bg-fog text-stone',
    ];

    $map = $solid ? $solidMap : $softMap;
    $classes = 'inline-flex items-center rounded-brand-md px-2.5 py-1 text-xs font-semibold '.($map[$color] ?? $map['navy']);
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</span>
