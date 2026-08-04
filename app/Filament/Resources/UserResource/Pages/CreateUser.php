<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Enums\UserStatus;
use App\Events\UserRegistered;
use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /** @var array<int, string> */
    protected array $roles = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->roles = $data['roles'] ?? [];
        unset($data['roles']);

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        // La contraseña la elige el propio usuario al activar su cuenta
        // (invitación por mail); esta random nunca se comunica a nadie.
        $data['password'] = Str::random(40);
        $data['status'] = UserStatus::Pending->value;

        $record = User::create($data);

        if ($this->roles !== []) {
            $record->syncRoles($this->roles);
        }

        event(new UserRegistered($record));

        return $record;
    }
}
