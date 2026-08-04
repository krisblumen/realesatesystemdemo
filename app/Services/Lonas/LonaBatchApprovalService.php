<?php

namespace App\Services\Lonas;

use App\Enums\LonaRequestStatus;
use App\Enums\LonaUnitStatus;
use App\Enums\OperationType;
use App\Enums\PropertyStatus;
use App\Models\LonaBatch;
use App\Models\LonaRequest;
use App\Models\LonaUnit;
use App\Models\Property;
use App\Models\User;
use App\Notifications\LonaRequestApprovedNotification;
use App\Notifications\LonaRequestRejectedNotification;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Autorización de entregas de lonas (lado admin/owner): crea el lote, sus unidades
 * físicas y arma el PDF automáticamente. El tipo venta/renta reutiliza OperationType
 * (I-1); el QR usa endroid v6 (CD-2).
 */
class LonaBatchApprovalService
{
    public function __construct(private readonly LonaEligibilityService $eligibility) {}

    public function grant(
        User $agent,
        OperationType $type,
        int $cantidad,
        User $authorizedBy,
        ?Property $property = null,
        ?LonaRequest $request = null,
    ): LonaBatch {
        // M-3: sólo se asignan lonas a un agente activo.
        if (! $agent->hasRole('agente') || ! $agent->isActive()) {
            throw ValidationException::withMessages([
                'agent' => 'Sólo se pueden asignar lonas a un agente activo.',
            ]);
        }

        // Tope de reposición (5 sin colocar por tipo): un agente no puede terminar con
        // más de CAP_PER_TYPE lonas de este tipo sin colocar. Aplica tanto a la
        // aprobación de una solicitud como a la asignación directa del admin.
        $sinColocar = $this->eligibility->uncolocatedCount($agent, $type);
        $disponible = max(0, LonaEligibilityService::CAP_PER_TYPE - $sinColocar);

        if ($cantidad < 1 || $cantidad > $disponible) {
            throw ValidationException::withMessages([
                'cantidad' => 'El agente no puede tener más de '.LonaEligibilityService::CAP_PER_TYPE.' lonas de '.$type->label().' sin colocar. Cupo disponible ahora: '.$disponible.'.',
            ]);
        }

        // Mn-IMP-1: el inmueble del QR debe estar publicado (su detalle público debe
        // existir para que el QR sea útil). El admin puede elegir cualquier inmueble
        // publicado del sistema (no se restringe al agente receptor) — decisión I-8.
        if ($property !== null && $property->status !== PropertyStatus::Publicado) {
            throw ValidationException::withMessages([
                'property_id' => 'El inmueble del QR debe estar publicado.',
            ]);
        }

        // Todo en una sola transacción: si la generación del PDF falla (dependencia
        // faltante, disco lleno, lo que sea), el lote y sus unidades NO deben quedar
        // creados a medias sin su PDF — o se entrega completo, o no se entrega nada.
        $batch = DB::transaction(function () use ($agent, $type, $cantidad, $authorizedBy, $property, $request): LonaBatch {
            $batch = LonaBatch::create([
                'agent_id' => $agent->id,
                'lona_request_id' => $request?->id,
                'operation_type' => $type->value,
                'cantidad' => $cantidad,
                'created_by' => $authorizedBy->id,
            ]);

            // M-1: la unidad NO hereda property_id del lote. Nace sin inmueble/ubicación;
            // el agente lo fija al colocarla. El inmueble del QR es un dato aparte.
            $now = now();
            LonaUnit::insert(array_fill(0, $cantidad, [
                'lona_batch_id' => $batch->id,
                'agent_id' => $agent->id,
                'operation_type' => $type->value,
                'status' => LonaUnitStatus::PendienteColocacion->value,
                'created_at' => $now,
                'updated_at' => $now,
            ]));

            if ($request !== null) {
                $request->update([
                    'estado' => LonaRequestStatus::Aprobada->value,
                    'reviewed_by' => $authorizedBy->id,
                    'reviewed_at' => $now,
                ]);
            }

            $this->attachDesignPdf($batch, $agent, $type, $property);

            return $batch;
        });

        if ($request !== null) {
            $agent->notify(new LonaRequestApprovedNotification($batch));
        }

        return $batch;
    }

    public function reject(LonaRequest $request, User $reviewedBy, string $motivo): LonaRequest
    {
        $request->update([
            'estado' => LonaRequestStatus::Rechazada->value,
            'reviewed_by' => $reviewedBy->id,
            'reviewed_at' => now(),
            'motivo_rechazo' => $motivo,
        ]);

        $request->agent->notify(new LonaRequestRejectedNotification($request));

        return $request;
    }

    private function attachDesignPdf(LonaBatch $batch, User $agent, OperationType $type, ?Property $property): void
    {
        $qrDataUri = $property !== null
            ? (new Builder(
                data: $property->canonical(),
                encoding: new Encoding('UTF-8'),
                errorCorrectionLevel: ErrorCorrectionLevel::High,
                size: 600,
            ))->build()->getDataUri()
            : null;

        // 90cm × 120cm en puntos (1cm ≈ 28.3465pt). CD-6 cerrada; el arte final es R-1.
        $pdf = Pdf::loadView('pdf.lona-design', [
            'agent' => $agent,
            'operationType' => $type,
            'property' => $property,
            'qrDataUri' => $qrDataUri,
        ])->setPaper([0, 0, 2551, 3402]);

        $batch->addMediaFromString($pdf->output())
            ->usingFileName("lona-{$type->value}-{$batch->id}.pdf")
            ->toMediaCollection('diseno-pdf');
    }
}
