<?php

namespace App\Services\Contratos;

use App\Models\ContratoIntermediacion;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Genera el PDF final del contrato firmado, lo sella y guarda su hash (RFC-068). Reusa
 * ContratoClausuladoService para que el PDF y el formulario público muestren EXACTAMENTE
 * el mismo clausulado. El hash SHA-256 se calcula sobre el PDF ya renderizado y se guarda
 * en BD + se expone en /verificar (D-8): no se imprime dentro del propio PDF (evita la
 * circularidad del sello, ver Anexo C.6 del machote).
 */
class ContratoPdfService
{
    public function __construct(
        private readonly ContratoClausuladoService $clausulado,
        private readonly ContratoQrService $qr,
    ) {}

    public function generarYSellar(ContratoIntermediacion $contrato): void
    {
        $bytes = $this->render($contrato, borrador: false)->output();

        // Hash SHA-256 del PDF FINAL (post-sello) → BD (documento_hash) + expediente privado.
        // Asignación directa + save: documento_hash es un dato de sistema, no está en $fillable.
        $contrato->documento_hash = hash('sha256', $bytes);
        $contrato->save();

        $contrato->addMediaFromString($bytes)
            ->usingFileName("contrato-{$contrato->folio}.pdf")
            ->toMediaCollection('documento-final');
    }

    /**
     * El contrato como se vería SIN firmar, para revisarlo o mostrárselo al
     * cliente en pantalla antes de mandarlo.
     *
     * No se sella, no se hashea y no se guarda: sale del mismo render que el
     * documento final —si fueran dos armados distintos, la previa dejaría de
     * servir para verificar los datos— pero es un archivo efímero. El sello del
     * contrato sigue naciendo una sola vez, al firmar.
     */
    public function borrador(ContratoIntermediacion $contrato): string
    {
        return $this->render($contrato, borrador: true)->output();
    }

    /**
     * El armado del PDF, uno solo para el documento final y para el borrador.
     *
     * En modo borrador se omiten la firma y su evidencia —todavía no existen— y
     * la vista imprime una marca de agua. Esa marca NO es decoración: sin ella
     * el borrador es indistinguible del contrato sellado, y alguien podría
     * imprimirlo y tratarlo como el definitivo.
     */
    private function render(ContratoIntermediacion $contrato, bool $borrador): \Barryvdh\DomPDF\PDF
    {
        // Mini-QR del sello → vista pública de verificación por folio.
        $qrVerificacion = $this->qr->dataUri(
            route('contratos.verificar', ['folio' => $contrato->folio]),
            300,
        );

        // La firma se embebe por BYTES desde el disco privado (no por URL /storage, que no
        // existe para 'local' y filtraría media) — riesgo-3 / opcional-5.
        $firmaMedia = $borrador ? null : $contrato->getFirstMedia('firma');
        $firmaDataUri = $firmaMedia !== null
            ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($firmaMedia->getPath()))
            : null;

        return Pdf::loadView('pdf.contrato-intermediacion', [
            'contrato' => $contrato,
            'evidencia' => $borrador ? null : $contrato->evidenciaFirma,
            'firmaDataUri' => $firmaDataUri,
            'qrVerificacion' => $qrVerificacion,
            'clausulaPago' => $this->clausulado->clausulaPago($contrato->tipo_operacion),
            'clausulaExclusividad' => $this->clausulado->clausulaExclusividad($contrato->exclusividad),
            'modalidad' => $this->clausulado->modalidadTexto($contrato->exclusividad),
            'emitidoEn' => now(),
            'borrador' => $borrador,
        ])->setPaper('letter');
    }
}
