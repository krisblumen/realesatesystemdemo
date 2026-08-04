@php
    $url = $getState();
    $id = 'nh-idz-'.$getRecord()->getKey();
@endphp

{{-- Lightbox puro CSS con estilos inline (no depende del build de Tailwind del panel):
     la miniatura abre la imagen a pantalla completa; clic en el fondo la cierra. --}}
<div class="nh-idzoom">
    <input type="checkbox" id="{{ $id }}" class="nh-idzoom-toggle">

    <label for="{{ $id }}" class="nh-idzoom-thumb">
        <img src="{{ $url }}" alt="Identificación (frente)">
        <span>Clic para ampliar</span>
    </label>

    <label for="{{ $id }}" class="nh-idzoom-overlay">
        <img src="{{ $url }}" alt="Identificación (frente) ampliada">
    </label>
</div>

<style>
    .nh-idzoom-toggle { position: absolute; width: 0; height: 0; opacity: 0; }
    .nh-idzoom-thumb { display: inline-block; cursor: zoom-in; }
    .nh-idzoom-thumb img { max-height: 20rem; border-radius: .5rem; border: 1px solid #e5e7eb; box-shadow: 0 1px 2px rgba(0,0,0,.06); }
    .nh-idzoom-thumb span { display: block; margin-top: .25rem; font-size: .75rem; color: #6b7280; }
    .nh-idzoom-overlay {
        position: fixed; inset: 0; z-index: 9999;
        display: flex; align-items: center; justify-content: center;
        padding: 1rem; background: rgba(0,0,0,.85); cursor: zoom-out;
        visibility: hidden; opacity: 0; transition: opacity .15s ease;
    }
    .nh-idzoom-overlay img { max-height: 92vh; max-width: 92vw; border-radius: .5rem; box-shadow: 0 10px 40px rgba(0,0,0,.5); }
    .nh-idzoom-toggle:checked ~ .nh-idzoom-overlay { visibility: visible; opacity: 1; }
</style>
