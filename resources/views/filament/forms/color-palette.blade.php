{{--
    Selector de color: un disparador chico que abre la paleta.

    Antes las dieciséis fichas estaban SIEMPRE desplegadas, y en los formularios
    que llevan tres o cuatro selectores —«Valores» tiene fondo de sección, placa
    del ícono y dibujo— la pantalla se volvía un muro de colores donde no se
    distinguía cuál era cuál. Plegado, cada selector ocupa un renglón y dice dos
    cosas: qué color está puesto y cómo cambiarlo.

    Lo que se ve cerrado ES la respuesta: el cuadrito muestra el color elegido y
    al lado va su nombre. Sin eso habría que abrir cada uno para saber qué tiene.

    **Los estilos van inline y no en clases de Tailwind**: el panel de Filament
    compila su propio CSS y no incluye las utilidades del sitio, así que un
    `grid-cols-8` acá no existe. Es la misma razón por la que la galería de
    íconos también usa `style`. La regla de CSP sin `unsafe-inline` rige el
    frontend público, no el panel.
--}}
@php
    $muestras = app(\App\Services\Frontend\BrandPalette::class)->swatches();
    $statePath = $getStatePath();

    // `default()` de Filament sólo corre al CREAR: una sección guardada antes de
    // que existiera este selector no trae la clave, así que su estado llega en
    // `null` y el disparador no sabría qué color mostrar — mientras la página se
    // ve pintada con el default. El panel estaría contradiciendo al sitio.
    //
    // Se muestra el color por defecto sin ESCRIBIR el estado: asignarlo acá
    // dejaría el formulario sucio con sólo abrirlo, y el compilador ya pone ese
    // mismo valor al guardar.
    $porDefecto = $field->getDefaultState();

    $hex = [];
    $nombre = [];

    foreach ($muestras as $clave => $muestra) {
        $hex[$clave] = $muestra['hex'];
        $nombre[$clave] = $muestra['label'];
    }
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            abierto: false,
            elegido: $wire.$entangle('{{ $statePath }}'),
            porDefecto: @js($porDefecto),
            hex: @js($hex),
            nombre: @js($nombre),
            get actual() {
                return this.elegido ?? this.porDefecto;
            },
            get hexActual() {
                return this.hex[this.actual] ?? '#ffffff';
            },
            get nombreActual() {
                return this.nombre[this.actual] ?? 'Sin elegir';
            },
            elegir(clave) {
                this.elegido = clave;
                this.abierto = false;
            },
        }"
        x-on:keydown.escape.window="abierto = false"
        style="position:relative"
    >
        {{-- Cerrado: el ícono, el color puesto y su nombre. --}}
        <button
            type="button"
            x-on:click="abierto = ! abierto"
            x-bind:aria-expanded="abierto ? 'true' : 'false'"
            style="display:inline-flex;align-items:center;gap:10px;padding:6px 12px 6px 8px;border:1px solid #e2e8f0;border-radius:8px;background:#ffffff;cursor:pointer"
        >
            <img src="{{ asset('images/assets/color_picker_icon.png') }}"
                 alt="" aria-hidden="true"
                 style="height:26px;width:auto;display:block">

            {{-- OBJETO y no cadena: `x-bind:style` con un string reemplaza el
                 atributo entero, y este cuadrito perdería su tamaño. --}}
            <span x-bind:style="{ backgroundColor: hexActual }"
                  style="display:block;height:24px;width:24px;border-radius:5px;box-shadow:inset 0 0 0 1px rgba(0,0,0,.12)"></span>

            <span x-text="nombreActual"
                  style="font-size:12px;font-weight:600;color:#334155"></span>
        </button>

        {{-- Abierto: la paleta. Se cierra al elegir o al hacer clic afuera.

             La grilla va en un hijo y NO en el elemento con `x-show`: para
             mostrarlo, Alpine le pone `display:''`, que borra el valor que
             estuviera escrito inline. Con la grilla acá afuera el popover salía
             en `block` y las dieciocho fichas caían en una sola fila. --}}
        <div
            x-show="abierto"
            x-cloak
            x-on:click.outside="abierto = false"
            style="position:absolute;z-index:30;margin-top:6px;padding:10px;border:1px solid #e2e8f0;border-radius:10px;background:#ffffff;box-shadow:0 10px 26px rgba(15,23,42,.16)"
        >
            <div style="display:grid;grid-template-columns:repeat(8,30px);gap:6px">
            @foreach ($muestras as $clave => $muestra)
                <button
                    type="button"
                    x-on:click="elegir(@js($clave))"
                    x-bind:style="{ boxShadow: actual === @js($clave)
                        ? '0 0 0 2px #f59e0b, 0 0 0 4px rgba(245,158,11,.25)'
                        : 'inset 0 0 0 1px rgba(0,0,0,.12)' }"
                    style="height:30px;width:30px;border:0;border-radius:6px;padding:0;cursor:pointer;background-color:{{ $muestra['hex'] }}"
                    title="{{ $muestra['label'] }} · {{ strtoupper($muestra['hex']) }}"
                    aria-label="{{ $muestra['label'] }}"
                ></button>
            @endforeach
            </div>
        </div>
    </div>
</x-dynamic-component>
