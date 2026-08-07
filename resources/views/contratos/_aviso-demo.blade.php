@php($enDemo = app(App\Tenancy\InquilinoActual::class)->hayInquilino())

{{--
    El aviso de que esto es una demostración.

    POR QUÉ EXISTE. El demo firma contratos DE VERDAD: PDF con folio, sello
    digital, hash SHA-256 y página pública de verificación. Es una de las
    funciones que el demo luce, y funciona igual que en producción.

    Los datos fiscales son de relleno a propósito, y eso delata el documento a
    quien lo lea con atención. A quien no, no.

    SE ACTIVA CUANDO HAY INQUILINO, no con una bandera de configuración. Mismo
    criterio que el cierre del entorno y por el mismo motivo: una bandera es algo
    que alguien puede olvidarse de encender, y el síntoma de olvidarla es un
    contrato de demo que parece real. Una instalación sin inquilinos —la
    plataforma corriendo para un cliente propio— emite contratos reales y no
    lleva marca, sin que nadie tenga que acordarse de apagarla.

    Decide ACÁ y no en quien lo incluye: tres vistas preguntándose lo mismo por
    su cuenta son tres oportunidades de que una conteste distinto.
--}}
@if ($enDemo)
    @if (($comoMarcaDeAgua ?? false))
        <div class="marca-demo">DEMOSTRACIÓN</div>
    @endif

    <div class="{{ $clase ?? 'aviso-demo' }}">
        <strong>DOCUMENTO DE DEMOSTRACIÓN — SIN VALIDEZ LEGAL.</strong>
        Generado en un entorno de prueba de Landra, con datos de relleno y por un
        plazo acotado. No representa un acuerdo entre las partes ni obliga a
        nadie.
    </div>
@endif
