<?php

namespace App\Policies;

use App\Models\FrontendSection;
use App\Models\User;

/**
 * Owner-only, double gate (§16.2 / RFC-075). Sections are the canonical rows of
 * a page; forceDelete is barred so deleting a section never drops the media the
 * published snapshot still references.
 */
class FrontendSectionPolicy
{
    public function viewAny(User $auth): bool
    {
        return $this->manages($auth);
    }

    public function view(User $auth, FrontendSection $section): bool
    {
        return $this->manages($auth);
    }

    public function create(User $auth): bool
    {
        // Sections are the canonical registry rows, seeded once. The owner edits
        // them; arbitrary creation would turn a closed registry into a page
        // builder (M-E2), so the policy forbids it — not just the UI.
        return false;
    }

    public function update(User $auth, FrontendSection $section): bool
    {
        return $this->manages($auth);
    }

    public function delete(User $auth, FrontendSection $section): bool
    {
        return false;
    }

    public function forceDelete(User $auth, FrontendSection $section): bool
    {
        return false;
    }

    private function manages(User $auth): bool
    {
        return $auth->hasRole('owner') && $auth->can('frontend.manage');
    }
}
