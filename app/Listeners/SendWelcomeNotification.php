<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Notifications\WelcomeNotification;
use Illuminate\Support\Facades\Password;

class SendWelcomeNotification
{
    public function handle(UserRegistered $event): void
    {
        $token = Password::createToken($event->user);

        $event->user->notify(new WelcomeNotification($token));
    }
}
