{{--
    La galería de íconos disponibles, PINTADA con los colores que se están
    eligiendo justo arriba.

    El repintado es del LADO DEL CLIENTE y no un `live()` que vuelva al servidor:
    elegir color es tantear, y tantear con una vuelta de red por ficha se siente
    roto. Alpine lee el mismo estado que los selectores —el que ya entrelazan con
    Livewire— así que la galería se entera del cambio sin que nadie se lo avise.

    La regla del dibujo se calcula ACÁ TAMBIÉN, y eso es una duplicación
    deliberada: el servidor no puede resolverla sin una vuelta, y la galería
    tiene que decir exactamente lo que la página va a publicar. Lo que se
    duplica no es el criterio sino su RESULTADO — `$clavesOscuras` sale de
    `needsDarkText()`, el mismo que corre la vista.

    Los estilos van inline porque el panel compila su propio CSS y no tiene
    ninguna utility del sitio. Es la misma razón que la paleta de colores.
--}}
<div
    x-data="{
        placa: $wire.$entangle('{{ $rutaPlaca }}'),
        glifo: $wire.$entangle('{{ $rutaGlifo }}'),
        paleta: @js($hexPorClave),
        oscuras: @js($clavesOscuras),
        tintaClara: @js($tintaClara),
        tintaOscura: @js($tintaOscura),
        get hexPlaca() {
            return this.paleta[this.placa || 'navy'] ?? this.paleta['navy'];
        },
        get hexGlifo() {
            if (this.glifo) {
                return this.paleta[this.glifo] ?? this.tintaOscura;
            }

            // Sin elección, el dibujo sigue a su placa — igual que en la página.
            return this.oscuras.includes(this.placa || 'navy') ? this.tintaClara : this.tintaOscura;
        },
    }"
    style="display:flex;flex-wrap:wrap;gap:14px"
>
    @foreach ($iconos as $clave => $icono)
        <div style="display:flex;flex-direction:column;align-items:center;gap:6px;width:82px">
            <div style="display:flex;height:44px;width:44px;align-items:center;justify-content:center;border-radius:10px"
                 {{-- OBJETO y no cadena: con un string, `x-bind:style` reemplaza
                      el atributo entero y la celda perdería su tamaño y su
                      centrado. --}}
                 x-bind:style="{ background: hexPlaca, color: hexGlifo }">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                    <path d="{{ $icono['path'] }}"/>
                </svg>
            </div>
            <span style="font-size:11px;line-height:1.3;text-align:center;color:#64748b">{{ $icono['label'] }}</span>
        </div>
    @endforeach
</div>
