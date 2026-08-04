<?php

namespace App\Filament\Resources\LeadResource\Pages;

use App\Filament\Resources\LeadResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;

class CreateLead extends CreateRecord
{
    protected static string $resource = LeadResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();

        if ($user instanceof User && $user->hasRole('agente')) {
            $data['agent_id'] = $user->id;
            $data['assigned_at'] = now();
        }

        return $data;
    }
}
