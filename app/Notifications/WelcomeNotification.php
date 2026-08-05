<?php

namespace App\Notifications;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $token) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Bienvenido a Landra — activa tu cuenta')
            ->greeting("Hola {$notifiable->name}")
            ->line('Te dimos de alta en el panel de administración de Landra.')
            ->action('Activar mi cuenta', Filament::getResetPasswordUrl($this->token, $notifiable))
            ->line('Ese link te deja elegir tu propia contraseña y vence en 60 minutos.')
            ->line('Si no esperabas este correo, puedes ignorarlo.');
    }
}
