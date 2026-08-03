<?php

namespace App\Policies;

use App\Models\LonaBatch;
use App\Models\User;

class LonaBatchPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->can('lonas.manage');
    }

    public function view(User $auth, LonaBatch $batch): bool
    {
        return $auth->can('lonas.manage');
    }

    public function create(User $auth): bool
    {
        return $auth->can('lonas.manage');
    }

    public function update(User $auth, LonaBatch $batch): bool
    {
        return $auth->can('lonas.manage');
    }

    public function delete(User $auth, LonaBatch $batch): bool
    {
        return $auth->can('lonas.manage');
    }

    public function restore(User $auth, LonaBatch $batch): bool
    {
        return $auth->can('lonas.manage');
    }

    public function forceDelete(User $auth, LonaBatch $batch): bool
    {
        return false;
    }
}
