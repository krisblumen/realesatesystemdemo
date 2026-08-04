<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;

class LeadPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->can('leads.manage');
    }

    public function view(User $auth, Lead $lead): bool
    {
        return $auth->can('leads.manage') && $this->canManage($auth, $lead);
    }

    public function create(User $auth): bool
    {
        return $auth->can('leads.manage');
    }

    public function update(User $auth, Lead $lead): bool
    {
        return $auth->can('leads.manage') && $this->canManage($auth, $lead);
    }

    public function delete(User $auth, Lead $lead): bool
    {
        return $auth->can('leads.manage') && $this->canManage($auth, $lead);
    }

    public function restore(User $auth, Lead $lead): bool
    {
        return $auth->can('leads.manage') && $this->canManage($auth, $lead);
    }

    public function forceDelete(User $auth, Lead $lead): bool
    {
        return false;
    }

    private function canManage(User $auth, Lead $lead): bool
    {
        if ($auth->hasAnyRole(['owner', 'admin'])) {
            return true;
        }

        return $lead->agent_id !== null && $lead->agent_id === $auth->id;
    }
}
