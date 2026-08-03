<?php

namespace App\Listeners;

use App\Events\LeadCaptured;
use App\Notifications\LeadConfirmationNotification;

class SendLeadConfirmationToClient
{
    public function handle(LeadCaptured $event): void
    {
        $lead = $event->lead->fresh();

        $lead->notify(new LeadConfirmationNotification($lead));
    }
}
