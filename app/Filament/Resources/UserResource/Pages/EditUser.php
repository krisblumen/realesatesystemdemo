<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /** @var array<int, string> */
    protected array $roles = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['roles'] = $this->userRecord()->roles()->pluck('name')->all();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->roles = $data['roles'] ?? [];
        unset($data['roles']);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->userRecord()->syncRoles($this->roles);
    }

    private function userRecord(): User
    {
        $record = $this->record;

        if (! $record instanceof User) {
            throw new \LogicException('UserResource edit page must be mounted with a User record.');
        }

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
