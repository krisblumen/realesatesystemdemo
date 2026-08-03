<x-layouts.public title="Enlace no disponible" :floatingWhatsapp="false">
    <div class="mx-auto flex min-h-[60vh] max-w-md flex-col items-center justify-center px-4 py-12 text-center">
        <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-slate-400">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-7 w-7">
                <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636" />
            </svg>
        </div>
        <h1 class="text-xl font-bold text-slate-900">
            @if (($motivo ?? '') === 'cancelado')
                Contrato no disponible
            @else
                Enlace no válido
            @endif
        </h1>
        <p class="mt-2 text-sm text-slate-500">
            @if (($motivo ?? '') === 'cancelado')
                Este contrato fue cancelado y ya no puede firmarse.
            @else
                Este enlace ya no es válido: puede haber expirado o haber sido utilizado. Si necesitas firmar el
                contrato, solicita a tu asesor que te reenvíe un nuevo enlace.
            @endif
        </p>
    </div>
</x-layouts.public>
