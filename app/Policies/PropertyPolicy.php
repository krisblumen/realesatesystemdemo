<?php

namespace App\Policies;

use App\Models\Property;
use App\Models\User;

class PropertyPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->can('properties.manage');
    }

    public function view(User $auth, Property $property): bool
    {
        return $auth->can('properties.manage') && $this->canManage($auth, $property);
    }

    public function create(User $auth): bool
    {
        return $auth->can('properties.manage');
    }

    public function update(User $auth, Property $property): bool
    {
        return $auth->can('properties.manage') && $this->canManage($auth, $property);
    }

    public function delete(User $auth, Property $property): bool
    {
        return $auth->can('properties.manage') && $auth->hasAnyRole(['owner', 'admin']);
    }

    public function restore(User $auth, Property $property): bool
    {
        return $auth->can('properties.manage') && $auth->hasAnyRole(['owner', 'admin']);
    }

    public function forceDelete(User $auth, Property $property): bool
    {
        return false;
    }

    private function canManage(User $auth, Property $property): bool
    {
        if ($auth->hasAnyRole(['owner', 'admin'])) {
            return true;
        }

        if ($property->agent_id !== null) {
            return $property->agent_id === $auth->id;
        }

        return $property->zone_id !== null
            && $auth->zones()->whereKey($property->zone_id)->exists();
    }
}
