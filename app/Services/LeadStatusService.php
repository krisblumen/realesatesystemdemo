<?php

namespace App\Services;

use App\Enums\LeadStatus;
use App\Models\Lead;
use Illuminate\Validation\ValidationException;

class LeadStatusService
{
    public function transition(Lead $lead, LeadStatus $target): Lead
    {
        if ($lead->status === $target) {
            return $lead;
        }

        if (! $lead->status->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => 'La transición de estado solicitada no está permitida.',
            ]);
        }

        $lead->status = $target;
        $lead->save();

        return $lead->refresh();
    }
}
