<?php

namespace App\Filament\Resources\PropertyOwnerResource\Pages;

use App\Filament\Resources\PropertyOwnerResource;
use App\Filament\Resources\PropertyOwnerResource\Pages\Concerns\EnforcesClientOwnership;
use Filament\Resources\Pages\CreateRecord;

class CreatePropertyOwner extends CreateRecord
{
    use EnforcesClientOwnership;

    protected static string $resource = PropertyOwnerResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->enforceClientOwnership($data);
    }
}
