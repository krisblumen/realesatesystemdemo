{{--
    La acción principal FLOTANTE del panel, siempre visible.

    Existe UNA sola vez y la inyecta un render hook (AdminPanelProvider) en cada
    página de formulario a pantalla completa. Copiarla en la vista de cada
    formulario habría sido quince lugares donde ajustar el mismo pixel.

    Cuál es «la acción principal» lo decide el hook, no esta vista: en casi todas
    las pantallas es guardar, pero en la de una página del sitio es PUBLICAR
    —guardar ahí sólo aplica el interruptor de visible/oculto, y el sitio público
    no cambia hasta publicar—.

    Los estilos van INLINE a propósito, no por descuido: el tema del panel no
    compila las utilidades de posicionamiento de Tailwind para vistas
    personalizadas, así que una clase acá saldría sin efecto. Es la misma razón
    por la que se escribió así en la pantalla de configuración del sitio, de
    donde viene este diseño.

    @param string  $form    el `id` del formulario que envía. Filament nombra
                            `form` al de sus páginas de recurso; las páginas
                            propias tienen que declararlo igual.
    @param ?string $espeja  selector CSS de un botón YA existente en la página.
                            Si viene, este deja de tener texto propio: copia el
                            del otro y le reenvía el clic.
--}}
@props([
    'form' => 'form',
    'hint' => 'Los cambios se aplican al guardar.',
    'label' => 'Guardar cambios',
    'icon' => 'heroicon-o-check',
    'color' => 'primary',
    'espeja' => null,
])

{{-- Aire al pie: sin esto el botón tapa el último campo, que es justo el que
     alguien está por completar cuando llega al final. --}}
<div aria-hidden="true" style="height:6rem"></div>

{{--
    Con un MODAL abierto el botón se esconde. No es cosmética: dispara la acción
    principal de la PÁGINA, así que flotando sobre un modal invita a guardar una
    cosa creyendo que se guarda otra.

    La señal es el bloqueo de scroll que Alpine pone en `<html>` mientras hay un
    modal (`x-trap.noscroll`). Se eligió por sobre contar los eventos
    `open-modal`/`close-modal`: un modal cerrado con ESC, con la X o clickeando
    afuera NO dispara `close-modal` —lo cierra por dentro—, así que el contador
    se quedaba trabado y el botón no volvía nunca. El atributo, en cambio, lo
    mantiene Alpine y siempre dice la verdad.

    Va en CSS y no en JS porque un selector de atributo ya es reactivo: no hay
    nada que escuchar ni que sincronizar.
--}}
<style>
    html[style*="overflow: hidden"] .nh-floating-save { display: none; }
</style>

<div class="nh-floating-save"
     style="position:fixed;bottom:1.5rem;right:1.5rem;z-index:50"
     @if ($espeja)
         {{--
             MODO ESPEJO. El botón no decide nada: lee el texto del botón real y
             le reenvía el clic.

             No es preciosismo. Este bloque lo dibuja un render hook, que vive en
             el LAYOUT y por lo tanto FUERA del componente Livewire: no se vuelve
             a dibujar cuando la página cambia. Con texto propio, guardar una
             sección dejaba al de arriba diciendo «Publicar cambios» y a éste
             «Publicar» —dos botones contradiciéndose sobre si hay trabajo sin
             publicar—. Espejando hay una sola fuente de verdad, y además la
             confirmación y los permisos siguen siendo los del botón original.
         --}}
         x-data="{
             etiqueta: '',
             origen() {
                 return document.querySelector(@js($espeja));
             },
             sincronizar() {
                 const b = this.origen();
                 this.etiqueta = b ? b.innerText.trim() : '';
                 this.$el.style.display = b ? '' : 'none';

                 // El COLOR también se hereda, y sale del atributo `style` y no
                 // de las clases: Filament les pone a todos los botones las
                 // mismas (`bg-custom-600`) y resuelve el tono con variables
                 // CSS —`--c-600: var(--warning-600)`—. Copiar el atributo trae
                 // el color sin arrastrar el tamaño, que acá es más grande.
                 const boton = this.$refs.boton;
                 if (b && boton) {
                     boton.setAttribute('style', b.getAttribute('style') ?? '');
                 }
             },
         }"
         x-init="
             sincronizar();
             $nextTick(() => sincronizar());
             Livewire.hook('morph.updated', () => sincronizar());
         "
     @endif
>
    {{-- Superficie glass esmerilado (sin borde): fondo semitransparente más
         desenfoque de lo que queda detrás. --}}
    <div style="display:flex;align-items:center;gap:12px;padding:10px 14px;border-radius:14px;background:rgba(255,255,255,0.55);-webkit-backdrop-filter:blur(14px) saturate(160%);backdrop-filter:blur(14px) saturate(160%);box-shadow:0 8px 30px rgba(0,0,0,0.14)">
        {{-- `filled()` y no `!== null`: en una vista suelta `@props` resuelve con
             `??`, así que pasarle `null` NO pisa el valor por defecto y el texto
             de guardar se colaba al lado del botón de publicar. --}}
        @if (filled($hint))
            <span class="hidden text-sm text-gray-600 sm:inline">{{ $hint }}</span>
        @endif

        @if ($espeja)
            <x-filament::button type="button" x-ref="boton" x-on:click="origen()?.click()" :color="$color" size="lg" :icon="$icon">
                <span x-text="etiqueta">{{ $label }}</span>
            </x-filament::button>
        @else
            {{-- `form-id` y no `form`: el componente de Filament usa DOS props
                 con nombres parecidos y significados distintos —`form` es el
                 target de Livewire que enciende el spinner, `formId` es el
                 atributo HTML que decide QUÉ formulario se envía—. Con `form`
                 el botón se dibujaba igual pero no enviaba nada. --}}
            <x-filament::button type="submit" form-id="{{ $form }}" :color="$color" size="lg" :icon="$icon">
                {{ $label }}
            </x-filament::button>
        @endif
    </div>
</div>
