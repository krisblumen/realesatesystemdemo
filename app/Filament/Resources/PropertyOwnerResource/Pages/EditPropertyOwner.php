<?php

namespace App\Filament\Resources\PropertyOwnerResource\Pages;

use App\Filament\Resources\PropertyOwnerResource;
use App\Filament\Resources\PropertyOwnerResource\Pages\Concerns\EnforcesClientOwnership;
use App\Models\PropertyOwner;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPropertyOwner extends EditRecord
{
    use EnforcesClientOwnership;

    protected static string $resource = PropertyOwnerResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->record;

        return $this->enforceClientOwnership($data, $record instanceof PropertyOwner ? $record->id : null);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn (): bool => auth()->user()?->can('delete', $this->record) ?? false),
            Actions\RestoreAction::make()
                ->visible(fn (): bool => auth()->user()?->can('restore', $this->record) ?? false),
        ];
    }
}
