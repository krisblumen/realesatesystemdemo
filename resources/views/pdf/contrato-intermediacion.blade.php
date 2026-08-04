<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Contrato {{ $contrato->folio }}</title>
    <style>
        @page { margin: 2cm 1.8cm; }
        * { box-sizing: border-box; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; color: #1f2937; font-size: 11px; line-height: 1.5; margin: 0; }
        h1 { font-size: 16px; color: #091A5B; margin: 0 0 2px; }
        h2 { font-size: 12px; color: #091A5B; margin: 16px 0 6px; border-bottom: 1px solid #e2e8f0; padding-bottom: 3px; }
        h3 { font-size: 12px; margin: 12px 0 4px; color: #091A5B; }
        h4 { font-size: 11px; margin: 9px 0 2px; color: #0f172a; }
        .muted { color: #64748b; }
        .header { border-bottom: 2px solid #091A5B; padding-bottom: 8px; margin-bottom: 12px; }
        table.data { width: 100%; border-collapse: collapse; margin: 6px 0; }
        table.data th { background: #f1f5f9; text-align: left; padding: 4px 8px; width: 38%; font-weight: bold; color: #334155; }
        table.data td { padding: 4px 8px; border-bottom: 1px solid #e2e8f0; }
        p { margin: 4px 0; text-align: justify; }
        .firmas { width: 100%; margin-top: 22px; }
        .firmas td { width: 50%; vertical-align: top; padding: 10px; border: 1px solid #cbd5e1; }
        .firma-img { max-height: 70px; max-width: 90%; }
        .sello { border: 1.5px dashed #091A5B; border-radius: 8px; padding: 10px; text-align: center; }
        .sello .marca { font-size: 13px; font-weight: bold; color: #091A5B; letter-spacing: 1px; }
        .sello .qr { height: 90px; width: 90px; margin: 6px auto 0; display: block; }
        .evidencia { font-size: 9px; color: #64748b; margin-top: 6px; }
        .foot { margin-top: 14px; border-top: 1px solid #e2e8f0; padding-top: 6px; font-size: 9px; color: #94a3b8; }
        /* Marca de agua del borrador. `position: fixed` en DomPDF se repite en
           TODAS las páginas, que es justo lo que hace falta: una marca sólo en
           la primera dejaría el resto indistinguible del contrato sellado. */
        .marca-borrador {
            position: fixed; top: 40%; left: 0; width: 100%; text-align: center;
            font-size: 62px; font-weight: bold; color: #091A5B; opacity: 0.10;
            transform: rotate(-24deg); letter-spacing: 6px; z-index: 0;
        }
        .aviso-borrador {
            border: 1.5px solid #B8860B; background: #fdf7e7; color: #7a5c00;
            border-radius: 6px; padding: 7px 10px; margin-bottom: 12px; font-size: 10px;
        }
    </style>
</head>
<body>
    @if ($borrador ?? false)
        {{-- Doble aviso a propósito: la marca de agua se ve de lejos y en una
             fotocopia, y el recuadro dice POR QUÉ no sirve como contrato. --}}
        <div class="marca-borrador">VISTA PREVIA</div>
        <div class="aviso-borrador">
            <strong>VISTA PREVIA — DOCUMENTO SIN VALOR.</strong>
            Este borrador es sólo para revisar los datos: no está firmado ni sellado, y no
            tiene folio de verificación válido. El contrato definitivo se genera al firmarlo.
        </div>
    @endif

    <div class="header">
        <h1>NEW HAUZ INMOBILIARIA</h1>
        <div class="muted">Contrato de prestación de servicios de intermediación inmobiliaria</div>
        <div class="muted">Folio <strong>{{ $contrato->folio }}</strong> · Plantilla {{ $contrato->plantilla_version }} · Tipo: {{ $contrato->tipo_operacion->label() }} · {{ $modalidad }}</div>
    </div>

    <h2>Datos esenciales de la operación</h2>
    <table class="data">
        <tr><th>Propietario</th><td>{{ $contrato->cliente_nombre }}</td></tr>
        <tr><th>Teléfono / Email</th><td>{{ $contrato->cliente_telefono ?? '—' }} · {{ $contrato->cliente_email ?? '—' }}</td></tr>
        <tr><th>Tipo de inmueble</th><td>{{ $contrato->inmueble_tipo }}</td></tr>
        <tr><th>Domicilio del inmueble</th><td>{{ $contrato->inmueble_direccion }}</td></tr>
        <tr><th>Precio / Renta autorizado</th><td>{{ $contrato->precioFormateado() }}</td></tr>
        <tr><th>Comisión</th><td>{{ rtrim(rtrim(number_format((float) $contrato->comision_porcentaje, 2), '0'), '.') }}% + IVA cuando legalmente corresponda</td></tr>
        <tr><th>Exclusividad</th><td>{{ $modalidad }}</td></tr>
        <tr><th>Vigencia</th><td>{{ optional($contrato->vigencia_inicio)->format('d/m/Y') ?? '—' }} a {{ optional($contrato->vigencia_fin)->format('d/m/Y') ?? '—' }}</td></tr>
    </table>

    @include('contratos._cuerpo-contrato')

    <table class="firmas">
        <tr>
            <td>
                <strong>EL PROPIETARIO</strong><br>
                @if ($firmaDataUri)
                    <img src="{{ $firmaDataUri }}" class="firma-img" alt="Firma"><br>
                @endif
                {{ $contrato->cliente_nombre }}
                @if ($evidencia)
                    <div class="evidencia">
                        Fecha/hora servidor: {{ $evidencia->firmado_at->format('d/m/Y H:i:s') }}<br>
                        IP: {{ $evidencia->ip }}<br>
                        Dispositivo: {{ \Illuminate\Support\Str::limit($evidencia->user_agent, 60) }}
                    </div>
                @endif
            </td>
            <td>
                <strong>EL PROFESIONAL INMOBILIARIO</strong>
                <div class="sello">
                    {{-- Placeholder del sello (R-4/DIF-6): el arte SVG final lo entrega el equipo de diseño. --}}
                    <div class="marca">NEW HAUZ</div>
                    @if ($borrador ?? false)
                        {{-- SIN QR en el borrador. El código lleva a la
                             verificación pública, que sólo dice la verdad de un
                             contrato ya sellado: ofrecerlo acá invitaría a
                             escanear un sello que todavía no existe. --}}
                        <div class="muted">Sello digital de verificación</div>
                        <div class="evidencia">
                            Folio: {{ $contrato->folio }}<br>
                            <strong>Pendiente de firma.</strong> El sello y su código de
                            verificación se generan cuando el propietario firma.
                        </div>
                    @else
                        <div class="muted">Sello digital de verificación</div>
                        <img src="{{ $qrVerificacion }}" class="qr" alt="QR de verificación">
                        <div class="evidencia">
                            Folio: {{ $contrato->folio }}<br>
                            Emisión: {{ $emitidoEn->format('d/m/Y H:i') }}<br>
                            Verifica en {{ route('contratos.verificar', ['folio' => $contrato->folio]) }}
                        </div>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <div class="foot">
        Documento sujeto a validación jurídica previa a producción. La verificación de integridad se realiza en la página
        pública de verificación comparando el hash SHA-256 del documento; el hash no se imprime dentro de este archivo.
    </div>
</body>
</html>
