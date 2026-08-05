<x-layouts.public title="Verificar contrato" :floatingWhatsapp="false">
    <div class="mx-auto max-w-md px-4 py-10 sm:py-14">
        <header class="mb-6 text-center">
            <p class="text-xs font-semibold uppercase tracking-wide text-[color:var(--color-primary,#2e3842)]">Landra Inmobiliaria</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-900">Verificar integridad</h1>
            <p class="mt-1 text-sm text-slate-500">Folio <span class="font-mono">{{ $folio }}</span></p>
        </header>

        @if ($resultado !== null)
            @if ($resultado['integro'])
                <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-5 text-center">
                    <p class="text-base font-semibold text-emerald-700">Documento íntegro ✓</p>
                    <p class="mt-1 text-sm text-slate-600">
                        El PDF corresponde a un contrato firmado en Landra y no ha sido alterado.
                        Fecha de firma: {{ $resultado['fecha_firma']->format('d/m/Y H:i') }}.
                    </p>
                </div>
            @else
                <div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-5 text-center">
                    <p class="text-base font-semibold text-red-700">No se pudo verificar</p>
                    <p class="mt-1 text-sm text-slate-600">
                        El documento no coincide con un contrato firmado para este folio, o fue modificado.
                    </p>
                </div>
            @endif
        @endif

        <form method="POST" action="{{ route('contratos.verificar.comparar', $folio) }}" enctype="multipart/form-data"
              class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            @csrf
            <p class="mb-3 text-sm text-slate-600">Sube el PDF del contrato para comprobar su integridad. No almacenamos el archivo.</p>
            @error('documento')
                <p class="mb-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
            <input type="file" name="documento" accept="application/pdf" required class="block w-full text-sm">
            <button type="submit" class="mt-4 w-full rounded-lg bg-[color:var(--color-primary,#2e3842)] px-4 py-3 text-center font-semibold text-white">
                Verificar documento
            </button>
        </form>

        <p class="mt-4 text-center text-xs text-slate-400">
            Esta verificación no expone datos personales del cliente ni del inmueble.
        </p>
    </div>
</x-layouts.public>
