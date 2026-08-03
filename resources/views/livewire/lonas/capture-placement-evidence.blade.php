<div x-data="lonaCapture()" x-init="startCamera()" class="space-y-3">
    {{-- Sólo cámara en vivo: no hay <input type=file>, por lo que no existe forma de
         elegir una imagen de galería (RFC-062 5.4). --}}
    <video x-ref="video" autoplay playsinline muted class="w-full rounded-lg bg-black"></video>
    <canvas x-ref="canvas" class="hidden"></canvas>

    <p x-show="cameraError" class="text-sm text-red-600" x-text="cameraError"></p>

    {{-- Estilos inline en los botones a propósito: este componente se renderiza dentro
         de un modal de Filament, cuyo bundle de CSS no compila utilidades Tailwind
         arbitrarias como bg-green-600 (por eso antes el botón "Confirmar" salía con
         texto blanco sobre fondo transparente = invisible). Inline garantiza que se vean
         siempre, en cualquier contexto. --}}
    @if ($placed)
        <p style="font-size:0.875rem;font-weight:600;color:#16a34a;">Colocación registrada correctamente.</p>
    @else
        <div class="flex gap-2">
            <button type="button" x-show="ready && !captured" @click="capture()"
                style="background-color:#f4960e;color:#fff;border-radius:0.5rem;padding:0.6rem 1.1rem;font-size:0.9rem;font-weight:600;border:0;cursor:pointer;">
                Capturar foto
            </button>
            <button type="button" x-show="captured" @click="retake()"
                style="background-color:#fff;color:#374151;border:1px solid #d1d5db;border-radius:0.5rem;padding:0.6rem 1.1rem;font-size:0.9rem;cursor:pointer;">
                Repetir
            </button>
        </div>

        <img x-show="captured" :src="dataUrl" class="w-full rounded-lg" alt="Evidencia capturada">

        <div x-show="captured" class="space-y-2">
            <div>
                <label class="block text-sm font-medium">Inmueble (opcional, sólo publicados)</label>
                <select wire:model="propertyId" class="mt-1 w-full rounded-md border">
                    <option value="">— Sin inmueble asociado —</option>
                    @foreach ($properties as $property)
                        <option value="{{ $property->id }}">{{ $property->title }}</option>
                    @endforeach
                </select>
                @error('propertyId') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium">Referencia de ubicación (si no eliges inmueble)</label>
                <input type="text" wire:model="ubicacionReferencia"
                    placeholder="Ej. Av. Reforma 123, esquina con Juárez"
                    class="mt-1 w-full rounded-md border">
                @error('ubicacionReferencia') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </div>

            @error('photoData') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            @error('lonaUnit') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            {{-- La foto se pasa como argumento (no vía $wire.set previo) para evitar la
                 carrera en la que confirmPlacement corre antes de que photoData se sincronice. --}}
            <button type="button"
                x-bind:disabled="saving"
                @click="saving = true; $wire.confirmPlacement(dataUrl).then(() => saving = false)"
                style="background-color:#16a34a;color:#fff;border-radius:0.5rem;padding:0.7rem 1.3rem;font-size:0.95rem;font-weight:700;border:0;cursor:pointer;width:100%;">
                <span x-show="!saving">Confirmar colocación</span>
                <span x-show="saving">Guardando…</span>
            </button>
        </div>
    @endif
</div>

@script
<script>
    Alpine.data('lonaCapture', () => ({
        stream: null,
        ready: false,
        captured: false,
        saving: false,
        dataUrl: null,
        cameraError: null,

        async startCamera() {
            try {
                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'environment' },
                    audio: false,
                });
                this.$refs.video.srcObject = this.stream;
                this.ready = true;
            } catch (e) {
                this.cameraError = 'No se pudo acceder a la cámara. Habilita el permiso e intenta de nuevo.';
            }
        },

        capture() {
            const v = this.$refs.video;
            const c = this.$refs.canvas;
            c.width = v.videoWidth;
            c.height = v.videoHeight;
            c.getContext('2d').drawImage(v, 0, 0);
            this.dataUrl = c.toDataURL('image/jpeg', 0.85);
            this.captured = true;
        },

        retake() {
            this.captured = false;
            this.dataUrl = null;
        },
    }));
</script>
@endscript
