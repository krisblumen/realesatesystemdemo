@php
    $field = 'block w-full rounded-[var(--radius-md)] border border-cloud bg-white px-4 py-3 text-sm text-graphite placeholder:text-mist focus:border-brand-focus focus:outline-none focus:ring-2 focus:ring-brand-focus';
@endphp

<div>
    @if ($submitted)
        <div class="rounded-[var(--radius-md)] border border-[#bfe3cc] bg-[#eaf6ee] p-5 text-center">
            <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-success text-white">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
            </div>
            <p class="font-brand-heading font-bold text-success">¡Solicitud enviada!</p>
            <p class="mt-1 text-sm text-[#3a6b4d]">Te contactaremos muy pronto.</p>
        </div>
    @else
        @error('rate_limit')
            <div class="mb-4 rounded-[var(--radius-md)] border border-[#fbe9e7] bg-[#fbe9e7] p-4 text-sm text-danger">{{ $message }}</div>
        @enderror

        <form wire:submit="submit" class="space-y-3.5">
            <div class="hidden" aria-hidden="true">
                <label for="company_website">Sitio web de empresa</label>
                <input id="company_website" type="text" wire:model="company_website" tabindex="-1" autocomplete="off">
            </div>

            <input type="hidden" wire:model="property_id">
            <input type="hidden" wire:model="source">

            <div>
                <input id="lead-name" type="text" wire:model="name" autocomplete="name" placeholder="Nombre completo" class="{{ $field }}">
                @error('name') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <input id="lead-phone" type="tel" wire:model="phone" autocomplete="tel" placeholder="Teléfono" class="{{ $field }}">
                @error('phone') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <input id="lead-email" type="email" wire:model="email" autocomplete="email" placeholder="Correo electrónico" class="{{ $field }}">
                @error('email') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <textarea id="lead-message" wire:model="message" rows="3" placeholder="Me interesa esta propiedad…" class="{{ $field }} resize-y"></textarea>
                @error('message') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
            </div>

            @if ($forced_service_type === null)
                {{-- Fail-closed options (M-D1): the SAME rule the submit enforces,
                     so an ineligible service never appears as selectable. --}}
                @php $leadOptions = app(\App\Services\Frontend\FrontendServicesService::class)->leadOptions(); @endphp
                <div>
                    <p class="mb-2 text-sm font-semibold text-graphite">Servicio de interés</p>
                    <div class="flex flex-wrap gap-4">
                        @foreach ($leadOptions as $code => $label)
                            <label class="flex cursor-pointer items-center gap-2">
                                <input type="radio" wire:model="service_type" value="{{ $code }}" class="h-4 w-4 accent-brand-accent">
                                <span class="text-sm text-graphite">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('service_type') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
                </div>
            @else
                <input type="hidden" wire:model="service_type">
            @endif

            <x-button type="submit" variant="primary" class="w-full">Enviar solicitud</x-button>
        </form>
    @endif
</div>
