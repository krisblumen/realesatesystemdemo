<?php

namespace App\Policies;

use App\Models\LonaUnit;
use App\Models\User;

class LonaUnitPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->can('lonas.manage') || $auth->can('lonas.place');
    }

    public function view(User $auth, LonaUnit $unit): bool
    {
        return $auth->can('lonas.manage') || $this->ownsUnit($auth, $unit);
    }

    /**
     * Registrar evidencia de colocación. Sólo el agente dueño de la unidad,
     * con permiso `lonas.place`. Owner/admin no colocan lonas físicas.
     */
    public function place(User $auth, LonaUnit $unit): bool
    {
        return $this->ownsUnit($auth, $unit);
    }

    public function forceDelete(User $auth, LonaUnit $unit): bool
    {
        return false;
    }

    private function ownsUnit(User $auth, LonaUnit $unit): bool
    {
        return $auth->can('lonas.place') && $unit->agent_id === $auth->id;
    }
}
