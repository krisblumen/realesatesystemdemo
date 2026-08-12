<x-layouts.public title="Verificar contrato" :floatingWhatsapp="false">
    <div class="mx-auto max-w-md px-4 py-10 sm:py-14">
        <header class="mb-6 text-center">
            <p class="text-xs font-semibold uppercase tracking-wide text-[color:var(--color-primary,#2e3842)]">Landra Inmobiliaria</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-900">Verificar integridad</h1>
            <p class="mt-1 text-sm text-slate-500">Folio <span class="font-mono">{{ $folio }}</span></p>
        </header>

        @include('contratos._aviso-demo', ['clase' => 'mb-6 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900'])

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
            <p class="mb-4 text-sm text-slate-600">Sube el PDF del contrato para comprobar su integridad. No almacenamos el archivo.</p>
            @error('documento')
                <p class="mb-2 text-sm text-red-600">{{ $message }}</p>
            @enderror

            {{--
                UNA ZONA DE CARGA Y NO UN `input` PELADO.

                El control nativo del navegador dibuja «Elegir archivo» en texto
                chico, sin borde ni fondo: al lado de un párrafo del mismo tamaño
                parece parte del texto de la tarjeta, y quien llega a verificar un
                documento no encuentra dónde subirlo.

                El `input` sigue existiendo y sigue siendo el que valida: se
                esconde con `sr-only` —que lo mueve fuera de vista pero lo deja
                enfocable— y no con `hidden`. Con `hidden`, un `required` vacío
                deja al navegador intentando enfocar algo que no existe y el aviso
                de «completá este campo» no se muestra en ninguna parte.
            --}}
            <label for="documento" data-zona
                   class="flex cursor-pointer flex-col items-center gap-2 rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center transition hover:border-[color:var(--color-accent,#f5a624)] hover:bg-amber-50">
                <svg class="h-8 w-8 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V6m0 0L8.25 9.75M12 6l3.75 3.75M4.5 16.5v1.875A2.625 2.625 0 007.125 21h9.75a2.625 2.625 0 002.625-2.625V16.5"/>
                </svg>
                <span class="text-base font-semibold text-[color:var(--color-primary,#2e3842)]">Selecciona el PDF del contrato</span>
                <span data-pista class="text-xs text-slate-500">o arrástralo aquí — solo archivos PDF</span>
            </label>

            <input id="documento" type="file" name="documento" accept="application/pdf" required class="sr-only">
            <button type="submit" class="mt-4 w-full rounded-lg bg-[color:var(--color-primary,#2e3842)] px-4 py-3 text-center font-semibold text-white">
                Verificar documento
            </button>
        </form>

        <p class="mt-4 text-center text-xs text-slate-400">
            Esta verificación no expone datos personales del cliente ni del inmueble.
        </p>
    </div>

    {{--
        Dos cosas, y las dos son de honestidad de la interfaz.

        La zona dice «o arrástralo aquí», así que arrastrar tiene que funcionar:
        una interfaz que promete algo que no hace es peor que una que no lo
        ofrece.

        Y al elegir un archivo hay que decir CUÁL. Sin eso, la zona queda igual
        que antes de tocarla y no hay forma de saber si el clic sirvió — con el
        control nativo escondido, el nombre del archivo ya no se ve en ninguna
        parte.
    --}}
    <script>
        (function () {
            const entrada = document.getElementById('documento');
            const zona = document.querySelector('[data-zona]');
            const pista = document.querySelector('[data-pista]');

            if (!entrada || !zona || !pista) {
                return;
            }

            const original = pista.textContent;
            const resaltado = ['border-[color:var(--color-accent,#f5a624)]', 'bg-amber-50'];

            const mostrar = () => {
                const archivo = entrada.files && entrada.files[0];
                pista.textContent = archivo ? archivo.name : original;
                pista.classList.toggle('font-medium', Boolean(archivo));
                pista.classList.toggle('text-slate-700', Boolean(archivo));
            };

            entrada.addEventListener('change', mostrar);

            ['dragenter', 'dragover'].forEach((evento) => zona.addEventListener(evento, (e) => {
                e.preventDefault();
                zona.classList.add(...resaltado);
            }));

            ['dragleave', 'drop'].forEach((evento) => zona.addEventListener(evento, (e) => {
                e.preventDefault();
                zona.classList.remove(...resaltado);
            }));

            zona.addEventListener('drop', (e) => {
                if (e.dataTransfer && e.dataTransfer.files.length) {
                    entrada.files = e.dataTransfer.files;
                    mostrar();
                }
            });
        })();
    </script>
</x-layouts.public>
