<?php

namespace App\Policies;

use App\Models\FrontendSetting;
use App\Models\User;

/**
 * Owner-only, double gate (Épica 12, §16.2): role AND permission, following
 * the strict pattern of ZonePolicy. Permission alone must not grant access
 * (a mis-granted permission is not an authorization); role alone must not
 * either (the capability stays revocable via the permission).
 *
 * delete/restore/forceDelete are false unconditionally: the singleton is not
 * deletable (C-4), so no path from the UI can ever reach deleteAllMedia().
 */
class FrontendSettingPolicy
{
    public function viewAny(User $auth): bool
    {
        return $this->manages($auth);
    }

    public function view(User $auth, FrontendSetting $setting): bool
    {
        return $this->manages($auth);
    }

    public function create(User $auth): bool
    {
        return $this->manages($auth);
    }

    public function update(User $auth, FrontendSetting $setting): bool
    {
        return $this->manages($auth);
    }

    public function delete(User $auth, FrontendSetting $setting): bool
    {
        return false;
    }

    public function restore(User $auth, FrontendSetting $setting): bool
    {
        return false;
    }

    public function forceDelete(User $auth, FrontendSetting $setting): bool
    {
        return false;
    }

    private function manages(User $auth): bool
    {
        return $auth->hasRole('owner') && $auth->can('frontend.manage');
    }
}
