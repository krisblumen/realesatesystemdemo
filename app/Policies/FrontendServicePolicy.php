<?php

namespace App\Policies;

use App\Models\FrontendService;
use App\Models\User;

/**
 * Owner-only, double gate (§16.2 / RFC-074): role AND permission, like
 * FrontendSettingPolicy. `admin` edits ServiceType.active on its own resource,
 * but the frontend service module (content + toggles) is owner-only.
 *
 * create/delete/restore/forceDelete are false: a FrontendService exists 1:1 for
 * a ServiceType — the owner edits content and toggles, never creates or destroys
 * rows, and forceDelete would let Spatie drop the referenced media.
 */
class FrontendServicePolicy
{
    public function viewAny(User $auth): bool
    {
        return $this->manages($auth);
    }

    public function view(User $auth, FrontendService $service): bool
    {
        return $this->manages($auth);
    }

    public function create(User $auth): bool
    {
        return false;
    }

    public function update(User $auth, FrontendService $service): bool
    {
        return $this->manages($auth);
    }

    public function delete(User $auth, FrontendService $service): bool
    {
        return false;
    }

    public function restore(User $auth, FrontendService $service): bool
    {
        return false;
    }

    public function forceDelete(User $auth, FrontendService $service): bool
    {
        return false;
    }

    private function manages(User $auth): bool
    {
        return $auth->hasRole('owner') && $auth->can('frontend.manage');
    }
}
