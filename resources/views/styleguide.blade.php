<x-layouts.public title="Guía de estilo">
    <section class="mx-auto max-w-[var(--container-content)] px-6 py-16">
        <p class="eyebrow text-orange-600">Fundaciones · Fase 0</p>
        <h1 class="mt-2 font-display text-5xl font-extrabold tracking-tight text-navy">Guía de estilo viva</h1>
        <p class="mt-4 max-w-xl text-lg leading-relaxed text-stone">
            Tokens del design system mapeados a Tailwind v4. Esta página valida la base sobre la que
            construimos el sitio público de Landra.
        </p>

        {{-- Paleta --}}
        <h2 class="mt-16 font-display text-2xl font-bold text-navy">Paleta</h2>
        <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-4 lg:grid-cols-6">
            @foreach ([
                ['Navy', 'bg-navy', 'text-white'],
                ['Navy 900', 'bg-navy-900', 'text-white'],
                ['Orange', 'bg-orange', 'text-white'],
                ['Orange hover', 'bg-orange-hover', 'text-white'],
                ['Ink', 'bg-ink', 'text-white'],
                ['Canvas', 'bg-canvas', 'text-graphite'],
            ] as [$name, $bg, $fg])
                <div class="overflow-hidden rounded-lg shadow-sm">
                    <div class="flex h-20 items-end p-3 {{ $bg }} {{ $fg }}">
                        <span class="text-xs font-medium">{{ $name }}</span>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Tipografía --}}
        <h2 class="mt-16 font-display text-2xl font-bold text-navy">Tipografía</h2>
        <div class="mt-6 space-y-4 rounded-xl bg-white p-8 shadow-sm">
            <p class="eyebrow text-orange-600">Eyebrow · Montserrat tracked</p>
            <p class="font-display text-5xl font-extrabold tracking-tight text-navy">Display · Montserrat</p>
            <p class="font-display text-2xl font-bold text-navy">Encabezado · Montserrat Bold</p>
            <p class="max-w-2xl text-lg leading-relaxed text-graphite">
                Cuerpo · Inter. Invertimos donde otros ven terrenos. Creamos valor donde otros ven
                metros cuadrados.
            </p>
            <p class="text-sm text-stone">Meta · Inter · texto secundario y captions.</p>
        </div>

        {{-- Botones --}}
        <h2 class="mt-16 font-display text-2xl font-bold text-navy">Botones</h2>
        <div class="mt-6 flex flex-wrap items-center gap-4">
            <x-button variant="primary" href="#">Agenda una Asesoría</x-button>
            <x-button variant="secondary" href="#">Ver Propiedades</x-button>
            <x-button variant="ghost" href="#">Conocer Proyectos</x-button>
            <x-button variant="dark" href="#">Contactar Asesor</x-button>
            <x-button variant="link" href="#">Solicitar Valuación</x-button>
        </div>
        <div class="mt-4 flex flex-wrap items-center gap-4">
            <x-button variant="primary" size="sm" href="#">Pequeño</x-button>
            <x-button variant="primary" size="md" href="#">Mediano</x-button>
            <x-button variant="primary" size="lg" href="#">Grande</x-button>
            <x-button variant="secondary" disabled>Deshabilitado</x-button>
        </div>
        <p class="mt-3 text-sm text-stone">Naranja = único CTA primario por vista. Navy y ghost para apoyo.</p>

        {{-- Badges --}}
        <h2 class="mt-16 font-display text-2xl font-bold text-navy">Badges</h2>
        <p class="mt-4 eyebrow text-orange-600">Operación</p>
        <div class="mt-3 flex flex-wrap gap-2">
            <x-badge color="orange" solid>Venta</x-badge>
            <x-badge color="navy" solid>Renta</x-badge>
            <x-badge color="orange">Preventa</x-badge>
        </div>
        <p class="mt-5 eyebrow text-orange-600">Estado comercial</p>
        <div class="mt-3 flex flex-wrap gap-2">
            <x-badge color="success">Publicado</x-badge>
            <x-badge color="danger">Vendido</x-badge>
            <x-badge color="navy">Rentado</x-badge>
            <x-badge color="warning">Pausado</x-badge>
            <x-badge color="neutral">Borrador</x-badge>
        </div>

        {{-- Tarjeta de muestra --}}
        <h2 class="mt-16 font-display text-2xl font-bold text-navy">Elevación</h2>
        <div class="mt-6 grid gap-6 sm:grid-cols-3">
            @foreach (['shadow-sm' => 'Sutil', 'shadow-md' => 'Media', 'shadow-lg' => 'Hover'] as $shadow => $label)
                <div class="rounded-xl bg-white p-6 {{ $shadow }}">
                    <p class="font-display font-semibold text-navy">{{ $label }}</p>
                    <p class="mt-1 text-sm text-stone">{{ $shadow }}</p>
                </div>
            @endforeach
        </div>
    </section>
</x-layouts.public>
