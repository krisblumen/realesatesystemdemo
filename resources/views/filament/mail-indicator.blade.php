@php
    $user = auth()->user();
@endphp

@if ($user && $user->hasCorporateMailbox())
    <x-filament::icon-button
        tag="a"
        href="{{ config('mail_indicator.webmail_url') }}"
        target="_blank"
        color="gray"
        icon="heroicon-o-envelope"
        icon-size="lg"
        label="Correo nuevo"
        :badge="$user->mail_unseen_count ?: null"
        badge-color="danger"
        class="fi-topbar-mail-btn"
    />
@endif
