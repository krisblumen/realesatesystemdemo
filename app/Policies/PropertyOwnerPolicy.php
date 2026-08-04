<?php

namespace App\Policies;

use App\Models\PropertyOwner;
use App\Models\User;

class PropertyOwnerPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->can('owners.manage');
    }

    public function view(User $auth, PropertyOwner $owner): bool
    {
        return $auth->can('owners.manage') && $this->canManage($auth, $owner);
    }

    public function create(User $auth): bool
    {
        return $auth->can('owners.manage');
    }

    public function update(User $auth, PropertyOwner $owner): bool
    {
        return $auth->can('owners.manage') && $this->canManage($auth, $owner);
    }

    public function delete(User $auth, PropertyOwner $owner): bool
    {
        return $auth->can('owners.manage') && $auth->hasAnyRole(['owner', 'admin']);
    }

    public function restore(User $auth, PropertyOwner $owner): bool
    {
        return $auth->can('owners.manage') && $auth->hasAnyRole(['owner', 'admin']);
    }

    public function forceDelete(User $auth, PropertyOwner $owner): bool
    {
        return false;
    }

    private function canManage(User $auth, PropertyOwner $owner): bool
    {
        if ($auth->hasAnyRole(['owner', 'admin'])) {
            return true;
        }

        return $owner->agent_id === $auth->id;
    }
}
