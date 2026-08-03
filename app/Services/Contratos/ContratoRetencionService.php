<?php

namespace App\Services\Contratos;

use App\Models\ContratoIntermediacion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Confirmación de eliminación de un expediente tras cumplir la retención (D-10 / M-4). La
 * ejecuta el Owner manualmente desde el panel. Política de borrado: purga los datos
 * personales (identificación y firma) y hace soft delete del contrato, PERO conserva el PDF
 * final y el hash para que la verificación pública por folio siga funcionando (P-4).
 */
class ContratoRetencionService
{
    private const MEDIA_PERSONAL = [
        'identificacion-anverso',
        'identificacion-reverso',
        'firma',
    ];

    public function confirmarEliminacion(ContratoIntermediacion $contrato, User $owner): void
    {
        // Autorización interna (Mn-IMP-1): el servicio no confía en que el caller ya validó.
        // Se autoriza contra el owner EXPLÍCITO (no el contexto HTTP), para que siga siendo
        // seguro desde CLI/jobs y no solo desde la acción Filament.
        Gate::forUser($owner)->authorize('confirmarEliminacion', $contrato);

        DB::transaction(function () use ($contrato, $owner) {
            // Auditar ANTES de purgar, para que el registro sobreviva a la degradación.
            $contrato->registrarEvento('eliminacion_confirmada', $owner);

            foreach (self::MEDIA_PERSONAL as $coleccion) {
                $contrato->clearMediaCollection($coleccion);
            }

            $contrato->eliminacion_pendiente = false;
            $contrato->save();

            // Soft delete: conserva folio, hash y PDF para verificación; oculta el expediente.
            $contrato->delete();
        });
    }
}
