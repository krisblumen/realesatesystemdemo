<?php

namespace App\Filament\Resources\PropertyResource\Pages\Concerns;

use App\Models\User;
use Illuminate\Validation\ValidationException;

trait EnforcesAgentPropertyOwnership
{
    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function enforceAgentOwnership(array $data): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            throw ValidationException::withMessages([
                'agent_id' => 'Debes iniciar sesión para gestionar inmuebles.',
            ]);
        }

        if ($user->hasAnyRole(['owner', 'admin'])) {
            return $data;
        }

        $data['agent_id'] = $user->id;
        $zoneId = $data['zone_id'] ?? null;

        if (filled($zoneId) && ! $user->zones()->whereKey($zoneId)->exists()) {
            throw ValidationException::withMessages([
                'zone_id' => 'Solo puedes asignar inmuebles a tus zonas.',
            ]);
        }

        return $data;
    }
}
