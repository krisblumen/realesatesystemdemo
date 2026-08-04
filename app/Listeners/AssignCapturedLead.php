<?php

namespace App\Listeners;

use App\Events\LeadCaptured;
use App\Services\LeadAssignmentService;

class AssignCapturedLead
{
    public function __construct(private readonly LeadAssignmentService $assignmentService) {}

    public function handle(LeadCaptured $event): void
    {
        if (! config('leads.auto_assignment.enabled', true)) {
            return;
        }

        $this->assignmentService->assign($event->lead);
    }
}
