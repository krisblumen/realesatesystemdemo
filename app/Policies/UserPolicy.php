<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->can('users.view');
    }

    public function view(User $auth, User $target): bool
    {
        return $auth->can('users.view');
    }

    public function create(User $auth): bool
    {
        return $auth->can('users.create');
    }

    public function update(User $auth, User $target): bool
    {
        if (! $auth->can('users.update')) {
            return false;
        }

        return ! $this->isProtectedOwnerFor($auth, $target);
    }

    public function delete(User $auth, User $target): bool
    {
        if (! $auth->can('users.delete')) {
            return false;
        }

        if ($auth->is($target)) {
            return false;
        }

        if ($target->hasRole('owner')) {
            return false;
        }

        return true;
    }

    public function restore(User $auth, User $target): bool
    {
        return $auth->hasRole('owner');
    }

    public function forceDelete(User $auth, User $target): bool
    {
        return false;
    }

    public function suspend(User $auth, User $target): bool
    {
        if ($auth->is($target)) {
            return false;
        }

        if ($target->hasRole('owner')) {
            return false;
        }

        return $auth->hasAnyRole(['owner', 'admin']);
    }

    public function reactivate(User $auth, User $target): bool
    {
        return $auth->hasAnyRole(['owner', 'admin']);
    }

    public function assignRole(User $auth, string $roleName): bool
    {
        if ($auth->hasRole('owner')) {
            return true;
        }

        if ($auth->hasRole('admin')) {
            return in_array($roleName, ['admin', 'agente', 'arquitectura', 'proyectos'], true);
        }

        return false;
    }

    private function isProtectedOwnerFor(User $auth, User $target): bool
    {
        return $auth->hasRole('admin') && $target->hasRole('owner');
    }
}
