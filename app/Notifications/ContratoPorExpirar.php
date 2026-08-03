<?php

namespace App\Notifications;

use App\Filament\Resources\ContratoIntermediacionResource;
use App\Models\ContratoIntermediacion;
use App\Models\User;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso al agente de que un contrato está por expirar sin respuesta del cliente (RFC-069).
 */
class ContratoPorExpirar extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ContratoIntermediacion $contrato) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(User $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Contrato por expirar')
            ->body("El contrato {$this->contrato->folio} sigue sin firma y está por expirar.")
            ->icon('heroicon-o-clock')
            ->iconColor('warning')
            ->actions([
                Action::make('view')->label('Ver contrato')->url($this->url()),
            ])
            ->getDatabaseMessage();
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Contrato por expirar · Folio {$this->contrato->folio}")
            ->greeting("Hola {$notifiable->name}")
            ->line("El contrato {$this->contrato->folio} de {$this->contrato->cliente_nombre} sigue sin firma y está por expirar.")
            ->action('Ver contrato', $this->url())
            ->line('Si expira, podrás reenviarlo conservando el mismo folio.');
    }

    private function url(): string
    {
        if (class_exists(ContratoIntermediacionResource::class)) {
            return ContratoIntermediacionResource::getUrl('view', ['record' => $this->contrato]);
        }

        return url('/admin');
    }
}
