{{--
    service_list (dynamic) — title?, eyebrow?; items resolved from the kernel
    (FrontendServicesService, fail-closed per RFC-074). No configured/active
    service ⇒ a graceful empty state, never a blank block.
--}}
@php $items = $s['items'] ?? []; @endphp
<section class="mx-auto max-w-[var(--container-content)] px-6 py-20">
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

    <div class="space-y-20">
        @forelse ($items as $i => $service)
            <div class="grid items-center gap-12 lg:grid-cols-2 {{ $i % 2 === 1 ? 'lg:[&>div:first-child]:order-2' : '' }}">
                <div>
                    <p class="eyebrow text-brand-accent-ink">{{ sprintf('%02d', $i + 1) }} · {{ $service['title'] ?? '' }}</p>
                    <h3 class="mt-3 font-brand-heading text-[clamp(24px,3vw,32px)] font-bold leading-snug tracking-tight text-brand-primary-ink">{{ $service['long_description'] ?? ($service['short_description'] ?? '') }}</h3>
                    @if (! empty($service['bullets']))
                        <ul class="mt-6 grid gap-x-6 gap-y-3 sm:grid-cols-2">
                            @foreach ($service['bullets'] as $bullet)
                                <li class="flex items-center gap-3">
                                    <span class="flex h-6 w-6 flex-none items-center justify-center rounded-[7px] bg-navy-50 text-brand-primary-ink">
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                                    </span>
                                    <span class="text-[15px] text-graphite">{{ $bullet }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    @if (! empty($service['cta']['url']))
                        <div class="mt-7"><x-button variant="ghost" :href="$service['cta']['url']">{{ $service['cta']['label'] ?? 'Ver más' }}</x-button></div>
                    @endif
                </div>
                @if (! empty($service['image_url']))
                    <div class="min-h-[360px] overflow-hidden rounded-brand-lg shadow-lg">
                        <img src="{{ $service['image_url'] }}" alt="{{ $service['image_alt'] ?? '' }}" class="h-full min-h-[360px] w-full object-cover" loading="lazy">
                    </div>
                @endif
            </div>
        @empty
            <div class="text-center">
                <p class="text-[17px] text-stone">Pronto publicaremos nuestros servicios.</p>
                <div class="mt-6"><x-button variant="primary" :href="route('leads.create')">Contáctanos</x-button></div>
            </div>
        @endforelse
    </div>
</section>
