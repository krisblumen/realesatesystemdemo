<?php

namespace App\Policies;

use App\Models\FrontendPage;
use App\Models\User;

/**
 * Owner-only, double gate (§16.2 / RFC-075): role AND permission. Pages are the
 * five canonical rows — the owner edits and publishes content, never creates or
 * deletes a page.
 */
class FrontendPagePolicy
{
    public function viewAny(User $auth): bool
    {
        return $this->manages($auth);
    }

    public function view(User $auth, FrontendPage $page): bool
    {
        return $this->manages($auth);
    }

    public function create(User $auth): bool
    {
        return false;
    }

    public function update(User $auth, FrontendPage $page): bool
    {
        return $this->manages($auth);
    }

    public function delete(User $auth, FrontendPage $page): bool
    {
        return false;
    }

    public function forceDelete(User $auth, FrontendPage $page): bool
    {
        return false;
    }

    private function manages(User $auth): bool
    {
        return $auth->hasRole('owner') && $auth->can('frontend.manage');
    }
}
