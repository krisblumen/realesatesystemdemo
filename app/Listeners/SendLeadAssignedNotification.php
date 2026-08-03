<?php

namespace App\Listeners;

use App\Events\LeadAssigned;
use App\Notifications\LeadAssignedNotification;

class SendLeadAssignedNotification
{
    public function handle(LeadAssigned $event): void
    {
        $event->agent->notify(new LeadAssignedNotification($event->lead));
    }
}
