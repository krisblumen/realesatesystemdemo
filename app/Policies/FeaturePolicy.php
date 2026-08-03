<?php

namespace App\Policies;

use App\Models\Feature;
use App\Models\User;

class FeaturePolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->hasAnyRole(['owner', 'admin']);
    }

    public function view(User $auth, Feature $feature): bool
    {
        return $auth->hasAnyRole(['owner', 'admin']);
    }

    public function create(User $auth): bool
    {
        return $auth->hasAnyRole(['owner', 'admin']);
    }

    public function update(User $auth, Feature $feature): bool
    {
        return $auth->hasAnyRole(['owner', 'admin']);
    }

    public function delete(User $auth, Feature $feature): bool
    {
        return $auth->hasRole('owner');
    }
}
