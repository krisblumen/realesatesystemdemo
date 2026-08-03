<?php

namespace App\Policies;

use App\Models\ContratoIntermediacion;
use App\Models\User;

/**
 * Fuente ÚNICA de autorización de contratos de intermediación (RFC-065/070). La UI de
 * Filament y las rutas la consumen; nunca la reemplazan. La identificación oficial y la
 * firma solo las ve el Owner (ni el Admin) — hallazgos M-3/C-2 de la auditoría.
 */
class ContratoIntermediacionPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->can('contratos.manage');
    }

    public function view(User $auth, ContratoIntermediacion $contrato): bool
    {
        if (! $auth->can('contratos.manage')) {
            return false;
        }

        // owner/admin: todos. agente: solo los propios.
        return $auth->hasAnyRole(['owner', 'admin']) || $contrato->agente_id === $auth->id;
    }

    public function create(User $auth): bool
    {
        return $auth->can('contratos.manage');
    }

    public function enviar(User $auth, ContratoIntermediacion $contrato): bool
    {
        return $this->view($auth, $contrato) && $auth->can('contratos.manage');
    }

    public function cancel(User $auth, ContratoIntermediacion $contrato): bool
    {
        return $auth->can('contratos.cancel') && ! $contrato->estado->esTerminal();
    }

    // --- Media privada: un método por colección (M-3) ---

    /** SOLO owner ve la identificación oficial — ni admin. */
    public function verIdentificacion(User $auth, ContratoIntermediacion $contrato): bool
    {
        return $auth->can('contratos.ver-identificacion');
    }

    /** La firma (trazo) es tan sensible como la identificación → SOLO owner. */
    public function verFirma(User $auth, ContratoIntermediacion $contrato): bool
    {
        return $auth->can('contratos.ver-identificacion');
    }

    /** El PDF final lo ve quien puede ver el contrato (agente propio / admin / owner) — P-1. */
    public function verDocumentoFinal(User $auth, ContratoIntermediacion $contrato): bool
    {
        return $this->view($auth, $contrato);
    }

    /** Confirmar eliminación tras retención: SOLO owner y solo si está pendiente (M-4). */
    public function confirmarEliminacion(User $auth, ContratoIntermediacion $contrato): bool
    {
        return $auth->can('contratos.ver-identificacion') && $contrato->eliminacion_pendiente;
    }

    public function delete(User $auth, ContratoIntermediacion $contrato): bool
    {
        return $auth->can('contratos.cancel') && $auth->hasAnyRole(['owner', 'admin']);
    }

    public function forceDelete(User $auth, ContratoIntermediacion $contrato): bool
    {
        return false;
    }
}
