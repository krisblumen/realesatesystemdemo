<?php

namespace App\Services\Contratos;

use App\Enums\EstadoContrato;
use App\Models\ContratoAcceso;
use App\Models\ContratoIntermediacion;
use App\Notifications\ContratoFirmado;
use App\Notifications\ContratoRechazado;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Captura la firma electrónica simple y su evidencia (RFC-067). Regla central (M-2): TODO
 * el payload (privacidad, firma, datos, identificación) se valida ANTES de consumir el
 * token, para que un POST inválido no queme el enlace. El consumo del "un solo uso" es
 * atómico y con lock dentro de la transacción (R-2).
 */
class ContratoFirmaService
{
    public function __construct(
        private readonly ContratoAccesoService $accesos,
        private readonly ContratoEventoService $eventos,
    ) {}

    /**
     * @param  array{
     *     firma_png_base64?: string,
     *     privacidad_aceptada?: bool,
     *     cliente?: array<string, mixed>,
     *     identificacion_anverso?: ?UploadedFile,
     *     identificacion_reverso?: ?UploadedFile,
     * }  $payload
     */
    public function firmar(ContratoAcceso $acceso, array $payload): ContratoIntermediacion
    {
        $contrato = $acceso->contrato;

        // ---- 0. VALIDACIÓN PREVIA (fuera de la transacción; el token sigue vivo) ----
        $this->assertPrivacidadAceptada($payload['privacidad_aceptada'] ?? false);
        $png = $this->validarFirma($payload['firma_png_base64'] ?? '');
        $this->validarDatosObligatorios($payload['cliente'] ?? []);

        // Identificación: ambas caras (frente + reverso). Foto en vivo (base64 de cámara —
        // anti-fraude) o UploadedFile (compat). El frente sirve para corroborar identidad;
        // ambas caras se conservan como evidencia del expediente.
        $anversoBytes = $this->validarFotoIdOpcional($payload['identificacion_anverso_base64'] ?? null);
        $reversoBytes = $this->validarFotoIdOpcional($payload['identificacion_reverso_base64'] ?? null);
        $anversoFile = $payload['identificacion_anverso'] ?? null;
        $reversoFile = $payload['identificacion_reverso'] ?? null;
        $this->validarIdentificacion($contrato, $anversoBytes, $reversoBytes, $anversoFile, $reversoFile);

        // ---- 1. Transacción: consumo atómico + firma + evidencia + transición ----
        $firmado = DB::transaction(function () use ($acceso, $png, $payload, $anversoBytes, $reversoBytes, $anversoFile, $reversoFile) {
            $accesoLock = ContratoAcceso::whereKey($acceso->id)->lockForUpdate()->first();
            $contrato = $accesoLock->contrato()->lockForUpdate()->first();

            if (! $this->accesos->consumir($accesoLock)) {
                throw ValidationException::withMessages(['token' => 'Este enlace ya fue utilizado.']);
            }

            $contrato->fill($this->soloDatosCliente($payload['cliente'] ?? []));
            $contrato->retencion_revisar_at = now()->addYears(2);   // decisión 10 / P-4
            $contrato->save();

            $this->guardarCaraId($contrato, 'identificacion-anverso', $anversoBytes, $anversoFile);
            $this->guardarCaraId($contrato, 'identificacion-reverso', $reversoBytes, $reversoFile);

            $contrato->addMediaFromString($png)
                ->usingFileName("firma-{$contrato->folio}.png")
                ->toMediaCollection('firma');

            $contrato->evidenciaFirma()->create([
                'ip' => request()->ip(),
                'user_agent' => substr((string) request()->userAgent(), 0, 500),
                'firmado_at' => now(),                 // hora de SERVIDOR
                'firma_hash' => hash('sha256', $png),
            ]);

            $this->eventos->registrar($contrato, 'privacidad_aceptada');
            $contrato->transicionarA(EstadoContrato::Firmado, null, $this->eventos->contextoHttp());

            // PDF final + sello + hash del documento (RFC-068), dentro de la misma
            // transacción: o el contrato queda firmado CON su PDF, o no queda firmado.
            app(ContratoPdfService::class)->generarYSellar($contrato);

            return $contrato->refresh();
        });

        // Notificaciones fuera de la transacción: el agente recibe el aviso con el PDF y el
        // cliente su copia; un fallo de envío no revierte la firma ya consolidada.
        $firmado->agente->notify(new ContratoFirmado($firmado));
        if ($firmado->cliente_email) {
            Notification::route('mail', $firmado->cliente_email)->notify(new ContratoFirmado($firmado));
        }

        return $firmado;
    }

    public function rechazar(ContratoAcceso $acceso, ?string $motivo = null): ContratoIntermediacion
    {
        $rechazado = DB::transaction(function () use ($acceso, $motivo) {
            $accesoLock = ContratoAcceso::whereKey($acceso->id)->lockForUpdate()->first();
            $contrato = $accesoLock->contrato()->lockForUpdate()->first();

            if (! $this->accesos->consumir($accesoLock)) {
                throw ValidationException::withMessages(['token' => 'Este enlace ya fue utilizado.']);
            }

            $contrato->motivo_rechazo = $motivo !== null ? trim($motivo) : null;
            $contrato->save();
            $contrato->transicionarA(EstadoContrato::Rechazado, null, $this->eventos->contextoHttp());

            return $contrato->refresh();
        });

        $rechazado->agente->notify(new ContratoRechazado($rechazado));

        return $rechazado;
    }

    private function assertPrivacidadAceptada(bool $aceptada): void
    {
        if (! $aceptada) {
            throw ValidationException::withMessages([
                'privacidad' => 'Debes aceptar el aviso de privacidad antes de firmar.',
            ]);
        }
    }

    /** Decodifica y valida el PNG/JPEG de la firma; devuelve los bytes. */
    private function validarFirma(string $raw): string
    {
        if ($raw === '') {
            throw ValidationException::withMessages(['firma' => 'Falta el trazo de la firma.']);
        }

        if (! preg_match('#^data:image/(png|jpeg);base64,#', $raw)) {
            throw ValidationException::withMessages(['firma' => 'Formato de firma no válido.']);
        }

        $bytes = base64_decode(substr($raw, strpos($raw, ',') + 1), true);

        if ($bytes === false) {
            throw ValidationException::withMessages(['firma' => 'La firma está corrupta.']);
        }

        $maxBytes = (int) config('contratos.firma_max_kb', 512) * 1024;
        if (strlen($bytes) > $maxBytes) {
            throw ValidationException::withMessages(['firma' => 'La firma excede el tamaño permitido.']);
        }

        if (! $this->esImagenValida($bytes)) {
            throw ValidationException::withMessages(['firma' => 'El contenido enviado no es una imagen válida.']);
        }

        return $bytes;
    }

    /** Verifica los magic bytes de PNG o JPEG (rechaza payloads no-imagen). */
    private function esImagenValida(string $bytes): bool
    {
        $png = str_starts_with($bytes, "\x89PNG\x0d\x0a\x1a\x0a");
        $jpeg = str_starts_with($bytes, "\xff\xd8\xff");

        return $png || $jpeg;
    }

    /** @param array<string, mixed> $cliente */
    private function validarDatosObligatorios(array $cliente): void
    {
        $nombre = trim((string) ($cliente['cliente_nombre'] ?? ''));
        $telefono = trim((string) ($cliente['cliente_telefono'] ?? ''));
        $email = trim((string) ($cliente['cliente_email'] ?? ''));

        if ($nombre === '') {
            throw ValidationException::withMessages(['cliente_nombre' => 'El nombre del cliente es obligatorio.']);
        }
        if ($telefono === '' && $email === '') {
            throw ValidationException::withMessages(['contacto' => 'Se requiere al menos teléfono o email.']);
        }
    }

    /**
     * Identificación antes de firmar (P-2/M-6): se requieren AMBAS caras (frente + reverso),
     * ya adjuntas en el contrato o venidas en el payload (foto de cámara en base64, o
     * UploadedFile de compat).
     */
    private function validarIdentificacion(
        ContratoIntermediacion $contrato,
        ?string $anversoBytes,
        ?string $reversoBytes,
        ?UploadedFile $anversoFile,
        ?UploadedFile $reversoFile,
    ): void {
        $tieneAnverso = $contrato->hasMedia('identificacion-anverso') || $anversoBytes !== null || $anversoFile instanceof UploadedFile;
        $tieneReverso = $contrato->hasMedia('identificacion-reverso') || $reversoBytes !== null || $reversoFile instanceof UploadedFile;

        if (! $tieneAnverso || ! $tieneReverso) {
            throw ValidationException::withMessages([
                'identificacion' => 'Se requiere una foto de ambas caras de la identificación oficial (frente y reverso).',
            ]);
        }

        foreach (array_filter([$anversoFile, $reversoFile]) as $archivo) {
            $this->validarArchivoIdentificacion($archivo);
        }
    }

    /** Valida una foto de identificación en base64 (data URI). Devuelve los bytes o null. */
    private function validarFotoIdOpcional(?string $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (! preg_match('#^data:image/(png|jpeg);base64,#', $raw)) {
            throw ValidationException::withMessages(['identificacion' => 'Formato de foto de identificación no válido.']);
        }

        $bytes = base64_decode(substr($raw, strpos($raw, ',') + 1), true);
        if ($bytes === false || ! $this->esImagenValida($bytes)) {
            throw ValidationException::withMessages(['identificacion' => 'La foto de identificación no es una imagen válida.']);
        }

        $maxBytes = (int) config('contratos.id_max_kb', 4096) * 1024;
        if (strlen($bytes) > $maxBytes) {
            throw ValidationException::withMessages(['identificacion' => 'La foto de identificación excede el tamaño permitido.']);
        }

        return $bytes;
    }

    /** Guarda una cara de la identificación desde bytes (cámara) o UploadedFile. */
    private function guardarCaraId(ContratoIntermediacion $contrato, string $coleccion, ?string $bytes, ?UploadedFile $file): void
    {
        if ($bytes !== null) {
            $contrato->addMediaFromString($bytes)
                ->usingFileName("{$coleccion}-{$contrato->folio}.png")
                ->toMediaCollection($coleccion);
        } elseif ($file instanceof UploadedFile) {
            $contrato->addMedia($file)->toMediaCollection($coleccion);
        }
    }

    private function validarArchivoIdentificacion(UploadedFile $archivo): void
    {
        if (! in_array($archivo->getMimeType(), ['image/jpeg', 'image/png'], true)) {
            throw ValidationException::withMessages([
                'identificacion' => 'La identificación debe ser una imagen JPEG o PNG.',
            ]);
        }

        $maxBytes = (int) config('contratos.id_max_kb', 4096) * 1024;
        if ($archivo->getSize() > $maxBytes) {
            throw ValidationException::withMessages([
                'identificacion' => 'La imagen de identificación excede el tamaño permitido.',
            ]);
        }
    }

    /**
     * Solo permite sobrescribir campos del cliente (no toca folio, estado, agente, etc.).
     *
     * @param  array<string, mixed>  $cliente
     * @return array<string, mixed>
     */
    private function soloDatosCliente(array $cliente): array
    {
        return array_intersect_key($cliente, array_flip([
            'cliente_nombre',
            'cliente_telefono',
            'cliente_email',
            'cliente_direccion',
        ]));
    }
}
