@php($firmado = $accion === 'firmado')
<x-layouts.public :title="$firmado ? 'Contrato firmado' : 'Contrato rechazado'" :floatingWhatsapp="false">
    <div class="mx-auto flex min-h-[60vh] max-w-md flex-col items-center justify-center px-4 py-12 text-center">
        <div @class([
            'mb-4 flex h-14 w-14 items-center justify-center rounded-full',
            'bg-emerald-100 text-emerald-600' => $firmado,
            'bg-slate-100 text-slate-400' => ! $firmado,
        ])>
            @if ($firmado)
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-7 w-7">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                </svg>
            @else
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-7 w-7">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            @endif
        </div>
        <h1 class="text-xl font-bold text-slate-900">
            {{ $firmado ? '¡Contrato firmado!' : 'Contrato rechazado' }}
        </h1>
        <p class="mt-2 text-sm text-slate-500">
            @if ($firmado)
                Registramos tu firma del contrato con folio <span class="font-mono">{{ $contrato->folio }}</span>.
                Recibirás una copia del documento firmado. Gracias.
            @else
                Registramos tu decisión de no firmar el contrato con folio <span class="font-mono">{{ $contrato->folio }}</span>.
                Tu asesor será notificado.
            @endif
        </p>
    </div>
</x-layouts.public>
