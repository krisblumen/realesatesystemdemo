<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Zone;

class ZonePolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->can('zones.manage');
    }

    public function view(User $auth, Zone $zone): bool
    {
        return $auth->can('zones.manage');
    }

    public function create(User $auth): bool
    {
        return $auth->can('zones.manage');
    }

    public function update(User $auth, Zone $zone): bool
    {
        return $auth->can('zones.manage');
    }

    public function delete(User $auth, Zone $zone): bool
    {
        return $auth->can('zones.manage') && $auth->hasRole('owner');
    }

    public function restore(User $auth, Zone $zone): bool
    {
        return $auth->can('zones.manage') && $auth->hasRole('owner');
    }

    public function forceDelete(User $auth, Zone $zone): bool
    {
        return false;
    }
}
