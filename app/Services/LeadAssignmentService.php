<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Events\LeadAssigned;
use App\Models\Lead;
use App\Models\LeadAssignmentLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeadAssignmentService
{
    public function assign(Lead $lead): ?User
    {
        return DB::transaction(function () use ($lead): ?User {
            $lockedLead = Lead::query()
                ->with(['property.agent', 'zone.agents'])
                ->lockForUpdate()
                ->findOrFail($lead->getKey());

            if ($lockedLead->agent_id !== null) {
                return $lockedLead->agent;
            }

            $agent = $this->resolveAgent($lockedLead);

            if (! $agent instanceof User) {
                Log::warning('Lead remained unassigned because no active agents were available.', [
                    'lead_id' => $lockedLead->id,
                ]);

                return null;
            }

            $this->persistAssignment($lockedLead, $agent, 'automatic');

            LeadAssigned::dispatch($lockedLead->refresh(), $agent, 'automatic');

            return $agent;
        });
    }

    public function reassign(Lead $lead, User $agent, User $assignedBy, ?string $reason = null): Lead
    {
        return DB::transaction(function () use ($lead, $agent, $assignedBy, $reason): Lead {
            $lockedLead = Lead::query()->lockForUpdate()->findOrFail($lead->getKey());

            $this->persistAssignment($lockedLead, $agent, 'manual', $assignedBy, $reason);

            LeadAssigned::dispatch($lockedLead->refresh(), $agent, 'manual');

            return $lockedLead;
        });
    }

    /**
     * Reasigna en bloque todos los leads ABIERTOS de un agente a otro.
     * Pensado para cobertura operativa (renuncia, vacaciones). Devuelve el conteo.
     */
    public function reassignOpenLeads(User $from, User $to, User $assignedBy, ?string $reason = null): int
    {
        return DB::transaction(function () use ($from, $to, $assignedBy, $reason): int {
            $leads = Lead::query()
                ->where('agent_id', $from->id)
                ->open()
                ->lockForUpdate()
                ->get();

            foreach ($leads as $lead) {
                $this->persistAssignment($lead, $to, 'manual', $assignedBy, $reason);
                LeadAssigned::dispatch($lead->refresh(), $to, 'manual');
            }

            return $leads->count();
        });
    }

    private function resolveAgent(Lead $lead): ?User
    {
        $propertyAgent = $lead->property?->agent;

        if ($this->isAssignableAgent($propertyAgent)) {
            return $propertyAgent;
        }

        $zoneAgents = $lead->zone?->agents;

        if ($zoneAgents instanceof Collection && $zoneAgents->isNotEmpty()) {
            return $this->leastLoadedAgent($zoneAgents->filter(fn (User $user): bool => $this->isAssignableAgent($user)));
        }

        // Sin inmueble ni zona no hay un agente "dueño" del lead: queda sin
        // asignar para revisión manual de owner/admin en vez de caer en
        // cualquier agente activo del sistema.
        return null;
    }

    /** @param Collection<int, User> $agents */
    private function leastLoadedAgent(Collection $agents): ?User
    {
        if ($agents->isEmpty()) {
            return null;
        }

        $since = $this->recentSince();
        $agentIds = $agents->pluck('id')->all();

        $loads = DB::table('leads')
            ->select('agent_id')
            ->selectRaw('COUNT(*) as recent_count')
            ->selectRaw('MAX(assigned_at) as last_assigned_at')
            ->whereIn('agent_id', $agentIds)
            ->whereNotNull('assigned_at')
            ->where('assigned_at', '>=', $since)
            ->groupBy('agent_id')
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                (int) $row->agent_id => [
                    'recent_count' => (int) $row->recent_count,
                    'last_assigned_at' => $row->last_assigned_at === null ? null : (string) $row->last_assigned_at,
                ],
            ]);

        return $agents
            ->sortBy([
                fn (User $agent): int => $loads->get($agent->id)['recent_count'] ?? 0,
                fn (User $agent): int => ($loads->get($agent->id)['last_assigned_at'] ?? null) === null ? 0 : 1,
                fn (User $agent): string => $loads->get($agent->id)['last_assigned_at'] ?? '',
                fn (User $agent): int => (int) $agent->id,
            ])
            ->first();
    }

    private function persistAssignment(
        Lead $lead,
        User $agent,
        string $source,
        ?User $assignedBy = null,
        ?string $reason = null,
    ): void {
        $fromAgentId = $lead->agent_id;

        $lead->forceFill([
            'agent_id' => $agent->id,
            'assigned_at' => now(),
        ])->save();

        LeadAssignmentLog::create([
            'lead_id' => $lead->id,
            'from_agent_id' => $fromAgentId,
            'to_agent_id' => $agent->id,
            'assigned_by_id' => $assignedBy?->id,
            'source' => $source,
            'reason' => $reason,
        ]);
    }

    private function isAssignableAgent(?User $user): bool
    {
        return $user instanceof User
            && $user->status === UserStatus::Active
            && $user->hasRole('agente');
    }

    private function recentSince(): Carbon
    {
        return now()->subDays((int) config('leads.auto_assignment.recent_days', 30));
    }
}
