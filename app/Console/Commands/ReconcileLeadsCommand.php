<?php

namespace App\Console\Commands;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Services\LeadAssignmentService;
use Illuminate\Console\Command;

class ReconcileLeadsCommand extends Command
{
    protected $signature = 'leads:reconcile {--minutes=10 : Minimum lead age in minutes before reconciliation}';

    protected $description = 'Assign pending new leads that remained without an agent.';

    public function handle(LeadAssignmentService $assignmentService): int
    {
        $minutes = max(0, (int) $this->option('minutes'));
        $cutoff = now()->subMinutes($minutes);
        $assigned = 0;

        Lead::query()
            ->where('status', LeadStatus::Nuevo->value)
            ->whereNull('agent_id')
            ->where('created_at', '<=', $cutoff)
            ->orderBy('id')
            ->each(function (Lead $lead) use ($assignmentService, &$assigned): void {
                if ($assignmentService->assign($lead) !== null) {
                    $assigned++;
                }
            });

        $this->info("{$assigned} pending leads assigned.");

        return self::SUCCESS;
    }
}
