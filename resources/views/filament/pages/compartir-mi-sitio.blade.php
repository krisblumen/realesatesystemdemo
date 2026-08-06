@php($vigente = $this->getVigente())

<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Tu sitio está cerrado, y así queda</x-slot>

        <x-slot name="description">
            Nadie puede verlo sin entrar. Con este enlace le das paso a alguien
            puntual —tu socio, tu equipo— sin prestarle tu cuenta.
        </x-slot>

        @if ($this->enlace)
            {{--
                Se muestra UNA sola vez: de este enlace sólo se guarda su huella,
                igual que una contraseña. Si se pierde, se genera otro.
            --}}
            <div class="fi-fo-field-wrp">
                <x-filament::input.wrapper>
                    <x-filament::input
                        type="text"
                        readonly
                        x-data="{}"
                        x-on:focus="$el.select()"
                        value="{{ $this->enlace }}"
                    />
                </x-filament::input.wrapper>
            </div>

            <p class="fi-fo-field-wrp-hint">
                Copialo ahora: no se vuelve a mostrar. El enlace sigue sirviendo,
                pero para verlo de nuevo hay que generar otro — y eso deja al
                actual sin efecto.
            </p>
        @elseif ($vigente)
            <p>
                Hay un enlace activo. Vence el
                <strong>{{ $vigente->expira_en->translatedFormat('j \d\e F \d\e Y') }}</strong>,
                y podés revocarlo antes desde el botón de arriba.
            </p>
            <p class="fi-fo-field-wrp-hint">
                No se puede volver a mostrar, porque no se guarda: si lo perdiste,
                generá uno nuevo.
            </p>
        @else
            <p>Todavía no hay ningún enlace activo.</p>
        @endif
    </x-filament::section>

    <x-filament::section collapsible collapsed>
        <x-slot name="heading">Qué ve quien lo abre</x-slot>

        <ul>
            <li>Tu sitio público, tal como lo vería un visitante.</li>
            <li>Nada del panel: no entra a administrar, no ve tus contratos ni tus leads.</li>
            <li>Sólo mientras el enlace siga vigente.</li>
        </ul>
    </x-filament::section>
</x-filament-panels::page>
