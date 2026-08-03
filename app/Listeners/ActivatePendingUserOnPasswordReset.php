<?php

namespace App\Listeners;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;

class ActivatePendingUserOnPasswordReset
{
    public function handle(PasswordReset $event): void
    {
        $user = $event->user;

        if ($user instanceof User && $user->isPending()) {
            $user->forceFill(['status' => UserStatus::Active])->save();
        }
    }
}
