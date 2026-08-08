{{--
    El registro público de un demo.

    Vive en una ruta que por ahora sólo conocemos nosotros. Eso NO es lo que la
    protege —una dirección se comparte, se filtra, se adivina— y por eso los
    topes de alta (RFC-10) valen igual acá: tope duro de la instancia y tope por
    origen. La dirección discreta sólo evita el tráfico casual mientras el demo
    se termina de asentar.
--}}
@extends('central.layout')

@section('titulo', 'Probá Landra')

@section('contenido')
    <h1>Probá Landra con tu propio demo</h1>

    <p>
        Dejanos tu correo y te mandamos tu acceso: un sitio inmobiliario completo,
        con su panel, cargado con datos de ejemplo para que lo recorras.
    </p>

    @if (session('registro.listo'))
        <p class="aviso aviso-bien">{{ session('registro.listo') }}</p>
    @endif

    @if ($errors->any())
        <p class="aviso aviso-mal">{{ $errors->first() }}</p>
    @endif

    <form method="POST" action="{{ route('registro.enviar') }}">
        @csrf

        <label for="email" class="senuelo">Tu correo</label>
        <input
            type="email"
            id="email"
            name="email"
            value="{{ old('email') }}"
            placeholder="tu@correo.com"
            autocomplete="email"
            required
            autofocus
        >

        {{-- El señuelo. Un robot llena todo lo que encuentra; una persona no ve
             este campo. Va con un nombre creíble a propósito: `honeypot` se
             saltea solo. --}}
        <div class="senuelo" aria-hidden="true">
            <label for="sitio_web">No completar</label>
            <input type="text" id="sitio_web" name="sitio_web" tabindex="-1" autocomplete="off">
        </div>

        <button type="submit">Quiero mi demo</button>
    </form>

    <p class="letra-chica">
        El demo dura {{ $dias }} días y después se borra solo, con todo lo que
        hayas cargado. No subas nada que no pueda ser público.
    </p>
@endsection
