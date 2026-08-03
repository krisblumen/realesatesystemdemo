<?php

namespace App\Filament\Widgets\Concerns;

use App\Models\User;

/**
 * Distingue el contexto del dashboard: un agente sólo ve sus propios datos,
 * mientras que owner/admin ven los globales.
 */
trait ScopesToAgent
{
    protected function isAgentScope(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->hasRole('agente')
            && ! $user->hasAnyRole(['owner', 'admin']);
    }

    protected function agentId(): ?int
    {
        return auth()->id();
    }

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['owner', 'admin', 'agente']) ?? false;
    }
}
