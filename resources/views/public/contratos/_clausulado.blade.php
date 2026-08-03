{{--
    Clausulado dinámico del contrato en el formulario público (RFC-066). Muestra los datos
    esenciales + el cuerpo legal COMPLETO compartido con el PDF (contratos._cuerpo-contrato).
    Variables: $contrato, $clausulaPago, $clausulaExclusividad, $modalidad.
--}}
<div class="space-y-5 text-sm leading-relaxed text-slate-700">
    <table class="w-full border-collapse text-left text-xs">
        <tbody>
            <tr class="border-b border-slate-200">
                <th class="w-2/5 bg-slate-50 px-3 py-2 font-semibold text-slate-600">Folio</th>
                <td class="px-3 py-2 font-mono">{{ $contrato->folio }}</td>
            </tr>
            <tr class="border-b border-slate-200">
                <th class="bg-slate-50 px-3 py-2 font-semibold text-slate-600">Tipo de operación</th>
                <td class="px-3 py-2">{{ $contrato->tipo_operacion->label() }}</td>
            </tr>
            <tr class="border-b border-slate-200">
                <th class="bg-slate-50 px-3 py-2 font-semibold text-slate-600">Modalidad</th>
                <td class="px-3 py-2">{{ $modalidad }}</td>
            </tr>
            <tr class="border-b border-slate-200">
                <th class="bg-slate-50 px-3 py-2 font-semibold text-slate-600">Vigencia</th>
                <td class="px-3 py-2">
                    {{ optional($contrato->vigencia_inicio)->format('d/m/Y') ?? '—' }}
                    a {{ optional($contrato->vigencia_fin)->format('d/m/Y') ?? '—' }}
                </td>
            </tr>
            <tr class="border-b border-slate-200">
                <th class="bg-slate-50 px-3 py-2 font-semibold text-slate-600">Inmueble</th>
                <td class="px-3 py-2">{{ $contrato->inmueble_tipo }} · {{ $contrato->inmueble_direccion }}</td>
            </tr>
            <tr class="border-b border-slate-200">
                <th class="bg-slate-50 px-3 py-2 font-semibold text-slate-600">Precio / Renta</th>
                <td class="px-3 py-2 font-medium text-slate-900">{{ $contrato->precioFormateado() }}</td>
            </tr>
            <tr>
                <th class="bg-slate-50 px-3 py-2 font-semibold text-slate-600">Comisión</th>
                <td class="px-3 py-2">{{ rtrim(rtrim(number_format((float) $contrato->comision_porcentaje, 2), '0'), '.') }}% + IVA cuando legalmente corresponda</td>
            </tr>
        </tbody>
    </table>

    <div class="contrato-cuerpo max-h-[46vh] overflow-y-auto rounded-lg border border-slate-200 bg-slate-50/50 p-4">
        @include('contratos._cuerpo-contrato')
    </div>

    <p class="border-t border-slate-200 pt-3 text-xs text-slate-500">
        Documento sujeto a validación jurídica previa a producción · Plantilla {{ $contrato->plantilla_version }}
    </p>
</div>

<style>
    .contrato-cuerpo h3 { font-size: .9rem; font-weight: 700; color: #0f172a; margin: 14px 0 6px; }
    .contrato-cuerpo h4 { font-size: .8rem; font-weight: 600; color: #0f172a; margin: 12px 0 2px; }
    .contrato-cuerpo p { margin: 4px 0; text-align: justify; }
    .contrato-cuerpo h3:first-child { margin-top: 0; }
</style>
