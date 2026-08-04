{{--
    Selector de ícono: el mismo disparador plegable que el selector de color
    (`color-palette.blade.php`), pero con el dibujo del ícono en vez de un
    color.

    Antes era un `Select` nativo con sólo el NOMBRE de cada ícono («Obra»,
    «Certificación») — para saber qué dibujo elegía, el owner tenía que abrir
    la galería de referencia aparte y volver. Acá el propio selector ES la
    referencia: cada ficha del desplegable muestra el dibujo, y el disparador
    cerrado muestra el que está elegido.

    El catálogo de íconos NO está fijo en este archivo: llega por `$iconos`
    (vía `->viewData()`), porque hay más de un catálogo en el panel — los 16
    de secciones (`card_icons`) y los 3 de servicios (`service_icons`)—. Un
    solo componente sirve a los dos: lo que cambia es la lista, nunca el
    mecanismo.

    Es OPCIONAL a propósito: los tres usos actuales guardan «sin ícono» como
    estado válido, así que la primera ficha del desplegable es «Ninguno» y no
    un ícono más.

    **Los estilos van inline y no en clases de Tailwind**, por la misma razón
    que el selector de color: el panel de Filament compila su propio CSS y no
    incluye las utilidades del sitio.
--}}
@php
    $statePath = $getStatePath();

    $paths = [];
    $nombre = [];

    foreach ($iconos as $clave => $icono) {
        $paths[$clave] = $icono['path'];
        $nombre[$clave] = $icono['label'];
    }
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            abierto: false,
            elegido: $wire.$entangle('{{ $statePath }}'),
            paths: @js($paths),
            nombre: @js($nombre),
            get pathActual() {
                return this.elegido ? (this.paths[this.elegido] ?? null) : null;
            },
            get nombreActual() {
                return this.elegido ? (this.nombre[this.elegido] ?? this.elegido) : 'Sin ícono';
            },
            elegir(clave) {
                this.elegido = clave;
                this.abierto = false;
            },
        }"
        x-on:keydown.escape.window="abierto = false"
        style="position:relative"
    >
        {{-- Cerrado: el dibujo elegido y su nombre. Sin elección, un círculo
             tachado — el mismo símbolo que la ficha «Ninguno» del desplegable,
             así el estado vacío se reconoce en los dos lugares. --}}
        <button
            type="button"
            x-on:click="abierto = ! abierto"
            x-bind:aria-expanded="abierto ? 'true' : 'false'"
            style="display:inline-flex;align-items:center;gap:8px;width:100%;max-width:220px;padding:5px 10px 5px 6px;border:1px solid #e2e8f0;border-radius:8px;background:#ffffff;cursor:pointer"
        >
            <span style="display:flex;height:28px;width:28px;flex-shrink:0;align-items:center;justify-content:center;border-radius:6px;background:#f8fafc;color:#0f172a">
                <svg x-show="pathActual" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                    <path x-bind:d="pathActual"/>
                </svg>
                <svg x-show="! pathActual" x-cloak width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.75" stroke-linecap="round">
                    <circle cx="12" cy="12" r="9"/>
                    <line x1="6" y1="18" x2="18" y2="6"/>
                </svg>
            </span>

            <span x-text="nombreActual"
                  style="overflow:hidden;white-space:nowrap;text-overflow:ellipsis;font-size:12px;font-weight:600;color:#334155"></span>

            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-left:auto">
                <path d="m6 9 6 6 6-6"/>
            </svg>
        </button>

        {{-- Abierto: la galería, ahora clicable. La grilla va en un HIJO del
             elemento con `x-show` y no en el mismo nodo: mostrarlo pone
             `display:''` y borraría un `display:grid` escrito ahí (el mismo
             defecto que ya costó una fila entera en el selector de color). --}}
        <div
            x-show="abierto"
            x-cloak
            x-on:click.outside="abierto = false"
            style="position:absolute;z-index:30;margin-top:6px;padding:10px;border:1px solid #e2e8f0;border-radius:10px;background:#ffffff;box-shadow:0 10px 26px rgba(15,23,42,.16)"
        >
            <div style="display:grid;grid-template-columns:repeat(8,36px);gap:6px">
                <button
                    type="button"
                    x-on:click="elegir(null)"
                    x-bind:style="{ boxShadow: ! elegido
                        ? '0 0 0 2px #f59e0b, 0 0 0 4px rgba(245,158,11,.25)'
                        : 'inset 0 0 0 1px rgba(0,0,0,.12)' }"
                    style="display:flex;height:36px;width:36px;align-items:center;justify-content:center;border:0;border-radius:6px;padding:0;cursor:pointer;background:#f8fafc"
                    title="Sin ícono"
                    aria-label="Sin ícono"
                >
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.75" stroke-linecap="round">
                        <circle cx="12" cy="12" r="9"/>
                        <line x1="6" y1="18" x2="18" y2="6"/>
                    </svg>
                </button>

                @foreach ($iconos as $clave => $icono)
                    <button
                        type="button"
                        x-on:click="elegir(@js($clave))"
                        x-bind:style="{ boxShadow: elegido === @js($clave)
                            ? '0 0 0 2px #f59e0b, 0 0 0 4px rgba(245,158,11,.25)'
                            : 'inset 0 0 0 1px rgba(0,0,0,.12)' }"
                        style="display:flex;height:36px;width:36px;align-items:center;justify-content:center;border:0;border-radius:6px;padding:0;cursor:pointer;background:#f8fafc;color:#0f172a"
                        title="{{ $icono['label'] }}"
                        aria-label="{{ $icono['label'] }}"
                    >
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                            <path d="{{ $icono['path'] }}"/>
                        </svg>
                    </button>
                @endforeach
            </div>
        </div>
    </div>
</x-dynamic-component>
