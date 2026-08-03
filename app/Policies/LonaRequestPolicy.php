<?php

namespace App\Policies;

use App\Models\LonaRequest;
use App\Models\User;

/**
 * Gate de la bandeja administrativa de solicitudes (owner/admin).
 *
 * La creación de solicitudes por parte del agente NO pasa por esta policy: ocurre
 * en su página `AgentLonas` y se autoriza con `lonas.place` + la regla de elegibilidad
 * (RFC-062 5.3). Aquí sólo se gobierna quién administra la bandeja.
 */
class LonaRequestPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->can('lonas.manage');
    }

    public function view(User $auth, LonaRequest $request): bool
    {
        return $auth->can('lonas.manage');
    }

    public function create(User $auth): bool
    {
        return $auth->can('lonas.manage');
    }

    public function update(User $auth, LonaRequest $request): bool
    {
        return $auth->can('lonas.manage');
    }

    public function delete(User $auth, LonaRequest $request): bool
    {
        return $auth->can('lonas.manage');
    }

    public function restore(User $auth, LonaRequest $request): bool
    {
        return $auth->can('lonas.manage');
    }

    public function forceDelete(User $auth, LonaRequest $request): bool
    {
        return false;
    }
}
