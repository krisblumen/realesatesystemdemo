<x-layouts.public title="Inmuebles en Querétaro">
    {{-- ===== Hero ===== --}}
    <section class="relative overflow-hidden {{ $heroProperties->isEmpty() ? 'bg-brand-primary' : '' }}">
        @if ($heroProperties->isNotEmpty())
            {{-- Carrusel de portadas (últimas 3 publicadas, crossfade automático) --}}
            <div id="nh-inmuebles-hero" aria-hidden="true" class="absolute inset-0">
                @foreach ($heroProperties as $i => $prop)
                    <div class="absolute inset-0 transition-opacity duration-1000 ease-in-out {{ $i === 0 ? 'opacity-100' : 'opacity-0' }}">
                        <img src="{{ $prop->getFirstMediaUrl('cover', 'web') }}" alt="" class="h-full w-full object-cover" loading="{{ $i === 0 ? 'eager' : 'lazy' }}">
                    </div>
                @endforeach
            </div>
        @endif
        {{-- Degradado navy → transparente (izq a der) sobre las imágenes --}}
        <div class="absolute inset-0 bg-gradient-to-r from-brand-primary/[0.92] via-brand-primary/[0.55] to-transparent"></div>
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_85%_20%,rgba(246,163,0,0.10),transparent_45%)]"></div>
        <div class="relative mx-auto max-w-[var(--container-content)] px-6 py-16">
            <nav class="mb-8 flex items-center gap-2.5 text-sm text-on-brand-primary/60">
                <a href="{{ url('/') }}" class="transition-colors hover:text-on-brand-primary">Inicio</a>
                <span class="text-on-brand-primary/30">/</span>
                <span class="font-semibold text-on-brand-primary">Inmobiliaria</span>
            </nav>
            <p class="eyebrow mb-4 text-accent-on-brand-primary">Catálogo</p>
            <h1 class="font-brand-heading text-[clamp(30px,4.4vw,48px)] font-extrabold leading-tight text-on-brand-primary">Propiedades en Querétaro</h1>
            <p class="mt-3.5 max-w-[560px] text-[17px] leading-relaxed text-on-brand-primary/80">
                Casas, departamentos y terrenos en venta y renta, opcionados por New Hauz.
            </p>
        </div>
    </section>

    @if ($heroProperties->count() > 1)
        <script>
        (function () {
            const slides = document.querySelectorAll('#nh-inmuebles-hero > div');
            if (slides.length < 2) return;
            let current = 0;
            setInterval(function () {
                slides[current].classList.replace('opacity-100', 'opacity-0');
                current = (current + 1) % slides.length;
                slides[current].classList.replace('opacity-0', 'opacity-100');
            }, 5000);
        })();
        </script>
    @endif

    {{-- ===== Catálogo ===== --}}
    <section class="mx-auto max-w-[var(--container-content)] px-6 pt-8 pb-24">
        <form method="GET" action="{{ route('inmuebles.index') }}" class="grid items-start gap-8 lg:grid-cols-[280px_1fr]">
            {{-- Filtros --}}
            <aside class="rounded-[var(--radius-lg)] border border-cloud bg-white p-6 shadow-sm lg:sticky lg:top-24">
                <div class="mb-5 flex items-center justify-between">
                    <h2 class="font-brand-heading text-lg font-bold text-brand-primary-ink">Filtros</h2>
                    <a href="{{ route('inmuebles.index') }}" class="text-[13px] font-semibold text-brand-accent-ink hover:brightness-110">Limpiar</a>
                </div>

                @php
                    $field = 'block w-full rounded-[var(--radius-md)] border border-cloud bg-white px-3.5 py-2.5 text-sm font-semibold text-brand-primary-ink focus:border-brand-focus focus:outline-none focus:ring-2 focus:ring-brand-focus';
                @endphp

                <div class="space-y-5">
                    <label class="block">
                        <span class="eyebrow text-stone">Operación</span>
                        <select name="operacion" id="cat-op" class="mt-2 {{ $field }}">
                            <option value="">Todas</option>
                            @foreach ($operationOptions as $op)
                                <option value="{{ $op->value }}" @selected(($filters['operacion'] ?? '') === $op->value)>{{ $op->label() }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block">
                        <span class="eyebrow text-stone">Zona</span>
                        <select name="zona" class="mt-2 {{ $field }}">
                            <option value="">Todas las zonas</option>
                            @foreach ($zones as $zone)
                                <option value="{{ $zone->id }}" @selected((int) ($filters['zona'] ?? 0) === $zone->id)>{{ $zone->name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block">
                        <span class="eyebrow text-stone">Tipo de propiedad</span>
                        <select name="tipo" class="mt-2 {{ $field }}">
                            <option value="">Todos los tipos</option>
                            @foreach ($typeOptions as $type)
                                <option value="{{ $type->value }}" @selected(($filters['tipo'] ?? '') === $type->value)>{{ $type->label() }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block">
                        <span class="eyebrow text-stone">Precio</span>
                        <select name="precio" id="cat-precio" class="mt-2 {{ $field }}">
                            {{-- Opciones cargadas por JS según operación activa --}}
                        </select>
                    </label>

                    <label class="block">
                        <span class="eyebrow text-stone">Recámaras</span>
                        <select name="recamaras" class="mt-2 {{ $field }}">
                            <option value="">Cualquiera</option>
                            @foreach ([1, 2, 3, 4] as $n)
                                <option value="{{ $n }}" @selected((int) ($filters['recamaras'] ?? 0) === $n)>{{ $n }}+</option>
                            @endforeach
                        </select>
                    </label>

                    {{-- Oportunidades de inversión.

                         Va como control PROPIO y no como una opción del selector
                         de precio: ahí sería un sí/no metido en una lista de
                         rangos, y elegirlo borraría el rango que el visitante
                         hubiera puesto. Son dos preguntas distintas y se pueden
                         combinar — «oportunidades de hasta 3 millones» tiene
                         sentido y así se puede pedir.

                         Es también el control que hace visible el recorte cuando
                         se llega desde el botón del home: sin él, el catálogo
                         mostraba menos propiedades sin decir por qué, y aplicar
                         otro filtro lo devolvía al listado entero. --}}
                    <label class="flex cursor-pointer items-start gap-3 rounded-[var(--radius-md)] border border-cloud bg-white px-3.5 py-3">
                        <input type="checkbox" name="oportunidad" value="1"
                               @checked(($filters['oportunidad'] ?? '') === '1')
                               class="mt-0.5 h-4 w-4 shrink-0 rounded border-cloud accent-brand-accent focus:ring-2 focus:ring-brand-focus">
                        <span>
                            <span class="block text-sm font-semibold text-brand-primary-ink">Solo oportunidades</span>
                            <span class="mt-0.5 block text-[13px] leading-snug text-stone">Propiedades marcadas con potencial de plusvalía.</span>
                        </span>
                    </label>
                </div>

                <input type="hidden" name="orden" value="{{ $filters['orden'] ?? '' }}">
                <x-button type="submit" variant="primary" class="mt-6 w-full">Aplicar filtros</x-button>
            </aside>

            {{-- Resultados --}}
            <div>
                <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
                    <p class="text-[15px] text-stone">
                        <strong class="text-brand-primary-ink">{{ $properties->total() }}</strong>
                        {{ $properties->total() === 1 ? 'propiedad encontrada' : 'propiedades encontradas' }}
                    </p>
                    <label class="flex items-center gap-2.5 text-sm text-stone">
                        Ordenar por
                        <select name="orden" onchange="this.form.submit()" class="rounded-[var(--radius-md)] border border-cloud bg-white px-3.5 py-2.5 text-sm font-semibold text-brand-primary-ink focus:border-brand-focus focus:outline-none">
                            @foreach (['' => 'Más recientes', 'precio_asc' => 'Precio: menor a mayor', 'precio_desc' => 'Precio: mayor a menor', 'superficie' => 'Superficie'] as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['orden'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                @if ($properties->isEmpty())
                    <div class="rounded-[var(--radius-lg)] border border-cloud bg-white p-16 text-center">
                        <p class="font-brand-heading text-lg font-semibold text-brand-primary-ink">No hay propiedades con esos filtros</p>
                        <p class="mt-2 text-sm text-stone">Prueba ajustando o limpiando los filtros.</p>
                    </div>
                @else
                    <div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($properties as $property)
                            <x-property-card
                                :title="$property->title"
                                :zone="$property->zone?->name ?? 'Querétaro'"
                                :price="$property->priceLabel()"
                                :operation="$property->operation_type->label()"
                                :beds="$property->bedrooms"
                                :baths="$property->bathrooms"
                                :area="$property->displayArea()"
                                :parking="$property->parking_spaces"
                                :href="route('inmuebles.show', $property->slug)"
                                :image="$property->getFirstMediaUrl('cover', 'web') ?: null" />
                        @endforeach
                    </div>

                    <div class="mt-12">
                        {{ $properties->onEachSide(1)->links() }}
                    </div>
                @endif
            </div>
        </form>
    </section>

    <script>
    (function () {
        const NhPrices = {
            default: [
                { value: '', label: 'Cualquier precio' },
                { value: '0-1500000', label: 'Hasta $1,500,000' },
                { value: '1500000-3000000', label: '$1.5M – $3M' },
                { value: '3000000-6000000', label: '$3M – $6M' },
                { value: '6000000+', label: 'Más de $6M' },
            ],
            renta: [
                { value: '', label: 'Cualquier precio' },
                { value: '0-15000', label: 'Hasta $15,000/mes' },
                { value: '15000-30000', label: '$15k – $30k/mes' },
                { value: '30000-60000', label: '$30k – $60k/mes' },
                { value: '60000+', label: 'Más de $60k/mes' },
            ],
        };

        function fillPrices(opValue, selectEl, currentValue) {
            const ranges = opValue === 'renta' ? NhPrices.renta : NhPrices.default;
            selectEl.innerHTML = ranges
                .map(r => `<option value="${r.value}"${r.value === currentValue ? ' selected' : ''}>${r.label}</option>`)
                .join('');
        }

        const opSel = document.getElementById('cat-op');
        const priceSel = document.getElementById('cat-precio');
        const activePrice = '{{ $filters['precio'] ?? '' }}';

        if (opSel && priceSel) {
            fillPrices(opSel.value, priceSel, activePrice);
            opSel.addEventListener('change', () => fillPrices(opSel.value, priceSel, ''));
        }
    })();
    </script>
</x-layouts.public>
