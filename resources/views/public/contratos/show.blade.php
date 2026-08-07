<x-layouts.public :title="'Contrato ' . $contrato->folio" :floatingWhatsapp="false">
    <div class="mx-auto max-w-2xl px-4 py-8 sm:py-12">
        <header class="mb-6">
            <p class="text-xs font-semibold uppercase tracking-wide text-[color:var(--color-primary,#2e3842)]">Landra Inmobiliaria</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-900">Contrato de intermediación</h1>
            <p class="mt-1 text-sm text-slate-500">Folio <span class="font-mono">{{ $contrato->folio }}</span> · Revisa el contenido y firma desde tu dispositivo.</p>
        </header>

        {{-- Datos del cliente a completar/confirmar (la captura y edición se habilita en el paso de firma, Lote E). --}}
        <section class="mb-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-base font-semibold text-slate-900">Tus datos</h2>
            <dl class="grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
                <div><dt class="text-slate-500">Nombre</dt><dd class="font-medium text-slate-900">{{ $contrato->cliente_nombre }}</dd></div>
                <div><dt class="text-slate-500">Teléfono</dt><dd class="font-medium text-slate-900">{{ $contrato->cliente_telefono ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Email</dt><dd class="font-medium text-slate-900">{{ $contrato->cliente_email ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Dirección</dt><dd class="font-medium text-slate-900">{{ $contrato->cliente_direccion ?? '—' }}</dd></div>
            </dl>
        </section>

        {{-- Clausulado dinámico (varía según operación y exclusividad). --}}
        <section class="mb-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-base font-semibold text-slate-900">Contenido del contrato</h2>
            @include('contratos._aviso-demo', ['clase' => 'mb-6 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900'])

            @include('public.contratos._clausulado')
        </section>

        @if ($errors->any())
            <div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Firma: aviso de privacidad + identificación (si falta) + trazo en canvas. --}}
        <form method="POST" action="{{ route('contratos.publico.firmar', $token) }}" enctype="multipart/form-data" id="form-firma">
            @csrf
            <input type="hidden" name="cliente[cliente_nombre]" value="{{ $contrato->cliente_nombre }}">
            <input type="hidden" name="cliente[cliente_telefono]" value="{{ $contrato->cliente_telefono }}">
            <input type="hidden" name="cliente[cliente_email]" value="{{ $contrato->cliente_email }}">

            <section class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-5">
                <h2 class="mb-2 text-base font-semibold text-slate-900">Aviso de privacidad</h2>
                <p class="text-sm text-slate-700">
                    Tus datos e imágenes de identificación se tratan únicamente para autenticar tu voluntad, integrar el
                    expediente de este contrato, ejecutar la intermediación y cumplir obligaciones legales, con acceso
                    restringido y conforme al periodo de retención aplicable.
                </p>
                <label class="mt-3 flex items-start gap-2 text-sm text-slate-800">
                    <input type="checkbox" name="privacidad" value="1" class="mt-1 h-4 w-4 rounded border-slate-300" required>
                    <span>He leído y acepto el aviso de privacidad.</span>
                </label>
            </section>

            @unless ($contrato->tieneIdentificacionCompleta())
                <section class="mb-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="mb-1 text-base font-semibold text-slate-900">Identificación oficial</h2>
                    <p class="mb-4 text-sm text-slate-500">Toma una <strong>foto en vivo</strong> de ambas caras de tu identificación (no se permiten imágenes de galería). Centra la credencial dentro del recuadro; en el frente, con tu foto visible.</p>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        @foreach (['anverso' => 'Frente (con tu foto)', 'reverso' => 'Reverso'] as $lado => $titulo)
                            <div class="id-capture" data-lado="{{ $lado }}">
                                <p class="mb-1 text-sm font-medium text-slate-700">{{ $titulo }}</p>
                                <div class="relative aspect-[1.586] w-full overflow-hidden rounded-lg bg-slate-900">
                                    <video class="id-video h-full w-full object-cover" playsinline muted></video>
                                    <img class="id-preview hidden h-full w-full object-cover" alt="Foto {{ $lado }}">
                                    {{-- Recuadro guía para centrar la credencial --}}
                                    <div class="id-guide pointer-events-none absolute inset-[8%] rounded-md border-2 border-dashed border-white/80"></div>
                                    <div class="id-placeholder absolute inset-0 flex items-center justify-center text-center text-xs text-white/70">Cámara apagada</div>
                                </div>
                                <input type="hidden" name="identificacion_{{ $lado }}" class="id-input">
                                <div class="mt-2 flex gap-2">
                                    <button type="button" class="id-start flex-1 rounded-lg bg-slate-100 px-3 py-2 text-sm font-medium text-slate-700">Encender cámara</button>
                                    <button type="button" class="id-shoot hidden flex-1 rounded-lg bg-[color:var(--color-primary,#2e3842)] px-3 py-2 text-sm font-semibold text-white">Tomar foto</button>
                                    <button type="button" class="id-retry hidden flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600">Repetir</button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <p class="id-unsupported mt-3 hidden text-sm text-red-600">Tu navegador no permite usar la cámara. Abre el enlace desde tu celular para tomar la foto.</p>
                </section>
            @endunless

            <section class="mb-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-2 text-base font-semibold text-slate-900">Tu firma</h2>
                <p class="mb-3 text-sm text-slate-500">Dibuja tu firma dentro del recuadro con el dedo o el mouse.</p>
                <canvas id="firma-canvas" class="w-full touch-none rounded-lg border border-dashed border-slate-300 bg-slate-50" height="180"></canvas>
                <input type="hidden" name="firma" id="firma-input">
                <button type="button" id="firma-limpiar" class="mt-2 text-sm font-medium text-slate-500 hover:text-slate-700">Limpiar</button>
            </section>

            <button type="submit" class="w-full rounded-lg bg-[color:var(--color-primary,#2e3842)] px-4 py-3 text-center font-semibold text-white">
                Firmar contrato
            </button>
        </form>

        {{-- Rechazo (formulario aparte para no arrastrar validaciones de firma). --}}
        <form method="POST" action="{{ route('contratos.publico.rechazar', $token) }}" class="mt-4"
              onsubmit="return confirm('¿Confirmas que NO deseas firmar este contrato?');">
            @csrf
            <details class="rounded-xl border border-slate-200 bg-white p-4">
                <summary class="cursor-pointer text-sm font-medium text-slate-600">No deseo firmar</summary>
                <textarea name="motivo" rows="2" placeholder="Motivo (opcional)"
                          class="mt-3 block w-full rounded-lg border border-slate-300 p-2 text-sm"></textarea>
                <button type="submit" class="mt-3 w-full rounded-lg border border-red-300 px-4 py-2 text-center text-sm font-semibold text-red-600">
                    Rechazar contrato
                </button>
            </details>
        </form>
    </div>

    <script>
        // --- Captura de identificación por cámara en vivo (anti-fraude, sin galería) ---
        (function () {
            const soporta = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
            const cards = document.querySelectorAll('.id-capture');
            if (!cards.length) return;
            if (!soporta) {
                const msg = document.querySelector('.id-unsupported');
                if (msg) msg.classList.remove('hidden');
                cards.forEach(c => c.querySelector('.id-start').setAttribute('disabled', 'disabled'));
                return;
            }

            cards.forEach(function (card) {
                const video = card.querySelector('.id-video');
                const preview = card.querySelector('.id-preview');
                const input = card.querySelector('.id-input');
                const placeholder = card.querySelector('.id-placeholder');
                const btnStart = card.querySelector('.id-start');
                const btnShoot = card.querySelector('.id-shoot');
                const btnRetry = card.querySelector('.id-retry');
                let stream = null;

                function stop() {
                    if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
                }

                btnStart.addEventListener('click', async function () {
                    try {
                        stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
                        video.srcObject = stream;
                        await video.play();
                        placeholder.classList.add('hidden');
                        preview.classList.add('hidden');
                        video.classList.remove('hidden');
                        btnStart.classList.add('hidden');
                        btnShoot.classList.remove('hidden');
                        btnRetry.classList.add('hidden');
                    } catch (e) {
                        placeholder.textContent = 'No se pudo acceder a la cámara';
                    }
                });

                btnShoot.addEventListener('click', function () {
                    // Recorta a la GUÍA: mapea el recuadro (coords de pantalla) a los píxeles
                    // reales del video, compensando el escalado object-cover. Así la foto queda
                    // limitada a la credencial que el cliente centró en el marco.
                    const vw = video.videoWidth, vh = video.videoHeight;
                    const vr = video.getBoundingClientRect();
                    const guide = card.querySelector('.id-guide');
                    const gr = guide ? guide.getBoundingClientRect() : vr;

                    const scale = Math.max(vr.width / vw, vr.height / vh); // object-cover
                    const cropLeft = (vw * scale - vr.width) / 2;
                    const cropTop = (vh * scale - vr.height) / 2;

                    const sx = ((gr.left - vr.left) + cropLeft) / scale;
                    const sy = ((gr.top - vr.top) + cropTop) / scale;
                    const sw = gr.width / scale;
                    const sh = gr.height / scale;

                    const c = document.createElement('canvas');
                    c.width = Math.max(1, Math.round(sw));
                    c.height = Math.max(1, Math.round(sh));
                    c.getContext('2d').drawImage(video, sx, sy, sw, sh, 0, 0, c.width, c.height);

                    const data = c.toDataURL('image/jpeg', 0.9);
                    input.value = data;
                    preview.src = data;
                    stop();
                    video.classList.add('hidden');
                    preview.classList.remove('hidden');
                    btnShoot.classList.add('hidden');
                    btnRetry.classList.remove('hidden');
                });

                btnRetry.addEventListener('click', function () {
                    input.value = '';
                    btnRetry.classList.add('hidden');
                    btnStart.classList.remove('hidden');
                    preview.classList.add('hidden');
                    placeholder.classList.remove('hidden');
                    placeholder.textContent = 'Cámara apagada';
                });
            });
        })();

        // --- Canvas de firma ---
        (function () {
            const canvas = document.getElementById('firma-canvas');
            const input = document.getElementById('firma-input');
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            let drawing = false, dirty = false;

            function resize() {
                const ratio = window.devicePixelRatio || 1;
                const w = canvas.clientWidth;
                canvas.width = w * ratio;
                canvas.height = 180 * ratio;
                ctx.scale(ratio, ratio);
                ctx.lineWidth = 2;
                ctx.lineCap = 'round';
                ctx.strokeStyle = '#0f172a';
            }
            resize();

            function pos(e) {
                const r = canvas.getBoundingClientRect();
                const p = e.touches ? e.touches[0] : e;
                return { x: p.clientX - r.left, y: p.clientY - r.top };
            }
            function start(e) { drawing = true; const { x, y } = pos(e); ctx.beginPath(); ctx.moveTo(x, y); e.preventDefault(); }
            function move(e) { if (!drawing) return; const { x, y } = pos(e); ctx.lineTo(x, y); ctx.stroke(); dirty = true; e.preventDefault(); }
            function end() { if (!drawing) return; drawing = false; input.value = dirty ? canvas.toDataURL('image/png') : ''; }

            canvas.addEventListener('pointerdown', start);
            canvas.addEventListener('pointermove', move);
            window.addEventListener('pointerup', end);

            document.getElementById('firma-limpiar').addEventListener('click', function () {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                dirty = false; input.value = '';
            });
        })();
    </script>
</x-layouts.public>
