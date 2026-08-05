@php
    // El formulario NO es contenido del CMS: siempre se muestra. Solo el
    // encabezado (hero + intro) entra al cutover publicable (RFC-076).
    $cms = app(\App\Services\Frontend\FrontendPageRenderer::class)->render('contacto');
@endphp
<x-layouts.public title="Contacto" :seo="$cms['seo']">
    @if (! $cms['fallback'])
        @include('frontend.render', ['sections' => $cms['sections']])
    @else
    {{-- Hero: MISMO partial y MISMO presenter que el contenido publicado
         (C-B-1). Sin imagen de fondo, la variante `compact` conserva la
         superficie sólida y la tipografía que esta página ya tenía. --}}
    @include('frontend.sections.hero', [
        's' => $cms['hero'],
        'sectionKey' => 'hero',
        'breadcrumbs' => [
            ['label' => 'Inicio', 'url' => url('/')],
            ['label' => 'Contacto'],
        ],
    ])
    @endif

    {{-- Contenido — el formulario y los datos de contacto se muestran siempre. --}}
    <section class="mx-auto max-w-[var(--container-content)] px-6 py-16">
        <div class="grid items-start gap-10 lg:grid-cols-[1.2fr_1fr]">
            {{-- Formulario --}}
            <div class="rounded-brand-lg border border-cloud bg-white p-9 shadow-md">
                <h2 class="font-brand-heading text-2xl font-bold text-brand-primary-ink">Envíanos un mensaje</h2>
                <p class="mt-1.5 mb-7 text-[15px] text-stone">Cuéntanos qué necesitas y te respondemos en breve.</p>
                <livewire:leads.lead-capture-form source="web" :service-type="$preselectedServiceType ?? null" :locked="false" />
                <p class="mt-4 text-center text-xs leading-relaxed text-mist">Al enviar aceptas nuestro aviso de privacidad.</p>
            </div>

            {{-- Info --}}
            <div class="flex flex-col gap-5">
                <a href="https://wa.me/524422722623?text={{ rawurlencode('¡Hola Landra! Vengo de su sitio web y me gustaría recibir asesoría sobre compra, venta o renta de un inmueble en Querétaro.') }}" target="_blank" rel="noopener"
                   class="flex items-center gap-4 rounded-brand-lg bg-[#25d366] p-5 shadow-[0_8px_24px_rgba(37,211,102,0.28)] transition hover:brightness-105">
                    <span class="flex h-12 w-12 flex-none items-center justify-center rounded-2xl bg-white/25 text-white shadow-inner ring-1 ring-white/30">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M.057 24l1.687-6.163a11.867 11.867 0 0 1-1.587-5.945C.16 5.335 5.495 0 12.05 0a11.817 11.817 0 0 1 8.413 3.488 11.824 11.824 0 0 1 3.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 0 1-5.688-1.448L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884a9.86 9.86 0 0 0 1.51 5.26l-.999 3.648 3.477-1.219zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.612-.916-2.207-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.247-.694.247-1.289.173-1.413z"/></svg>
                    </span>
                    <div>
                        <p class="font-brand-heading text-[17px] font-bold text-white">WhatsApp</p>
                        <p class="text-sm text-white">Respuesta inmediata · +52 442 272 26 23</p>
                    </div>
                </a>

                <div class="rounded-brand-lg border border-cloud bg-white p-6 shadow-sm">
                    <div class="flex flex-col gap-5">
                        @foreach ([
                            ['Teléfono', '+52 442 272 26 23', 'M3 5a2 2 0 0 1 2-2h2l2 5-2.5 1.5a11 11 0 0 0 5 5L18 12l5 2v2a2 2 0 0 1-2 2A16 16 0 0 1 3 5Z'],
                            ['Email', 'hola@landracore.com', 'M3 6h18v12H3z M3 7l9 6 9-6'],
                            ['Oficina', 'Alamos Querétaro Qro.', 'M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0Z'],
                        ] as $i => [$label, $value, $icon])
                            @if ($i > 0)<div class="h-px bg-cloud"></div>@endif
                            <div class="flex items-start gap-3.5">
                                <span class="flex h-11 w-11 flex-none items-center justify-center rounded-[11px] bg-navy-50 text-brand-primary-ink">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icon }}"/></svg>
                                </span>
                                <div>
                                    <p class="eyebrow text-stone">{{ $label }}</p>
                                    <p class="mt-1 text-base text-brand-primary-ink">{{ $value }}</p>
                                    @if ($label === 'Oficina')
                                        <p class="text-sm text-stone">Lun–Vie 9:00–18:00 · Sáb 10:00–14:00</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-layouts.public>
