{{--
    Diseño real de la lona 90cm × 120cm (2551pt × 3402pt), a partir del arte final de
    Diseño (docs de referencia entregados 2026-07-13, ajustes 2026-07-13 post-feedback).
    R-1 CERRADO: usa los assets reales public/images/brand/fondo_lonas.jpg (fondo) y
    Logo_lonas.svg (logo). public/images/brand/lona-gradient-overlay.png es un degradado
    navy (#050f38) generado con GD — dompdf NO soporta linear-gradient() de CSS
    (verificado con un render aislado antes de esta decisión), así que el degradado se
    pre-renderiza como PNG con canal alfa y se superpone como imagen estática.
    El QR (si hay inmueble) apunta al detalle público del inmueble; sin inmueble, se
    omiten el recuadro de QR y su leyenda.
--}}
@php
    // LA RANURA DEL LOGO, y por qué se calcula en vez de escribirse.
    //
    // Antes el CSS fijaba `width: 950pt` y nada más. Con el logo horizontal
    // original funcionaba; cuando la des-marcación lo cambió por uno casi
    // cuadrado (proporción 0.95), esos mismos 950pt de ancho pasaron a ser
    // 998pt de ALTO — de 330pt a 1328pt— y el logo se comió la palabra VENTA,
    // que arranca en 1060pt.
    //
    // Una caja con una sola dimensión no acota nada. Acá se declara la ranura
    // entera y el logo se encaja adentro sin deformarse, sea cual sea la forma
    // del archivo que alguien ponga mañana.
    $ranura = ['ancho' => 1400.0, 'alto' => 700.0, 'izquierda' => 575.5, 'arriba' => 300.0];

    $logo = \App\Support\EncajeDeSvg::contener(
        public_path('images/brand/Logo_lonas.svg'),
        $ranura['ancho'], $ranura['alto'], $ranura['izquierda'], $ranura['arriba'],
    );

    $esDemostracion = app(\App\Tenancy\InquilinoActual::class)->esUnaDemostracion();
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        html, body { margin: 0; padding: 0; }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            width: 2551pt;
            height: 3402pt;
        }
        .lona {
            position: relative;
            width: 2551pt;
            height: 3402pt;
            overflow: hidden;
        }
        .lona-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 2551pt;
            height: 3402pt;
        }
        .lona-gradient {
            position: absolute;
            top: 0;
            left: 0;
            width: 2551pt;
            height: 1701pt;
        }
        .frame {
            position: absolute;
            left: 190pt;
            top: 190pt;
            width: 2171pt;
            height: 2812pt;
            border: 14pt solid #F4960E;
            border-radius: 50pt;
        }
        /* Sin medidas: las pone el encaje, arriba. Dejarlas acá sería tener la
           geometría en dos lugares y que uno de los dos mienta. */
        .logo { position: absolute; }
        .tipo {
            position: absolute;
            left: 190pt;
            top: 1060pt;
            width: 2171pt;
            text-align: center;
            font-size: 600pt;
            font-weight: bold;
            color: #ffffff;
            letter-spacing: 0pt;
        }
        .phone {
            position: absolute;
            left: 280pt;
            top: 2163pt;
            font-size: 300pt;
            font-weight: bold;
            color: #ffffff;
        }
        .agent-name {
            position: absolute;
            left: 280pt;
            top: 2563pt;
            font-size: 90pt;
            font-weight: bold;
            color: #ffffff;
            text-transform: uppercase;
        }
        .agent-email {
            position: absolute;
            left: 280pt;
            top: 2696pt;
            font-size: 58pt;
            color: #ffffff;
        }
        .website {
            position: absolute;
            left: 280pt;
            top: 2796pt;
            font-size: 72pt;
            font-weight: bold;
            color: #F4960E;
        }
        .qr-box {
            position: absolute;
            left: 1681pt;
            top: 2662pt;
            width: 680pt;
            height: 680pt;
            background: #ffffff;
            border-radius: 55pt;
            text-align: center;
        }
        .qr-box img {
            width: 570pt;
            height: 570pt;
            margin-top: 55pt;
        }
        /*
            LA MARCA DE AGUA DE DEMOSTRACIÓN.

            Una lona se IMPRIME: 90×120cm colgados en la calle. Es la pieza del
            demo con más chance de terminar en el mundo real sin que nadie
            recuerde de dónde salió, así que la marca va grande y repetida, no
            en una esquina.

            `transform: rotate()` y `opacity` andan en esta versión de dompdf —
            el contrato los usa en producción desde hace meses—, a diferencia de
            `linear-gradient()`, que no (ver la nota del encabezado).

            En blanco porque el fondo es una foto oscura con un degradado navy
            encima; el ocre del contrato se pierde ahí.
        */
        .marca-demo {
            position: absolute;
            left: 0;
            width: 2551pt;
            text-align: center;
            font-size: 250pt;
            font-weight: bold;
            color: #ffffff;
            opacity: 0.17;
            letter-spacing: 24pt;
            transform: rotate(-24deg);
        }
        .qr-caption {
            position: absolute;
            left: 280pt;
            top: 3060pt;
            width: 1341pt;
            font-size: 46pt;
            color: #cbd5e1;
        }
    </style>
</head>
<body>
    <div class="lona">
        <img class="lona-bg" src="{{ public_path('images/brand/fondo_lonas.jpg') }}" alt="">
        <img class="lona-gradient" src="{{ public_path('images/brand/lona-gradient-overlay.png') }}" alt="">

        <div class="frame"></div>

        <img class="logo" src="{{ public_path('images/brand/Logo_lonas.svg') }}" alt="Landra"
             style="left: {{ round($logo['izquierda'], 1) }}pt; top: {{ round($logo['arriba'], 1) }}pt; width: {{ round($logo['ancho'], 1) }}pt; height: {{ round($logo['alto'], 1) }}pt;">

        <div class="tipo">{{ strtoupper($operationType->label()) }}</div>

        @if ($agent->phone)
            <div class="phone">{{ \App\Support\MexicanPhoneFormatter::format($agent->phone) }}</div>
        @endif

        <div class="agent-name">{{ $agent->name }}</div>
        <div class="agent-email">{{ $agent->email }}</div>

        <div class="website">www.landracore.com</div>

        @if ($qrDataUri)
            <div class="qr-box">
                <img src="{{ $qrDataUri }}" alt="QR del inmueble">
            </div>
            <div class="qr-caption">Consulta las características de este inmueble en el QR</div>
        @endif

        @if ($esDemostracion)
            {{-- Al final del documento: en dompdf lo que se dibuja después queda
                 encima, y una marca de agua tapada por la foto no marca nada. --}}
            <div class="marca-demo" style="top: 620pt;">DEMOSTRACIÓN</div>
            <div class="marca-demo" style="top: 1560pt;">DEMOSTRACIÓN</div>
            <div class="marca-demo" style="top: 2500pt;">DEMOSTRACIÓN</div>
        @endif
    </div>
</body>
</html>
