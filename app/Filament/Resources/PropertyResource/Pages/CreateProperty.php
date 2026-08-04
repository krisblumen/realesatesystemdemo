<?php

namespace App\Filament\Resources\PropertyResource\Pages;

use App\Filament\Resources\PropertyResource;
use App\Filament\Resources\PropertyResource\Pages\Concerns\EnforcesAgentPropertyOwnership;
use App\Models\Property;
use App\Support\PropertySlugGenerator;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProperty extends CreateRecord
{
    use EnforcesAgentPropertyOwnership;

    protected static string $resource = PropertyResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->enforceAgentOwnership($data);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $property = new Property($data);
        app(PropertySlugGenerator::class)->persist($property);

        return $property;
    }
}
