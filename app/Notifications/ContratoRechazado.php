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
 * Contrato rechazado por el cliente (RFC-069). Aviso al agente (base de datos + email).
 */
class ContratoRechazado extends Notification implements ShouldQueue
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
            ->title('Contrato rechazado')
            ->body("El cliente {$this->contrato->cliente_nombre} rechazó el contrato {$this->contrato->folio}.")
            ->icon('heroicon-o-x-circle')
            ->iconColor('danger')
            ->actions([
                Action::make('view')->label('Ver contrato')->url($this->url()),
            ])
            ->getDatabaseMessage();
    }

    public function toMail(User $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Contrato rechazado · Folio {$this->contrato->folio}")
            ->greeting("Hola {$notifiable->name}")
            ->line("El cliente {$this->contrato->cliente_nombre} rechazó el contrato {$this->contrato->folio}.");

        if ($this->contrato->motivo_rechazo) {
            $mail->line("Motivo: {$this->contrato->motivo_rechazo}");
        }

        return $mail->line('Puedes reenviar el contrato desde el panel si corresponde.');
    }

    private function url(): string
    {
        if (class_exists(ContratoIntermediacionResource::class)) {
            return ContratoIntermediacionResource::getUrl('view', ['record' => $this->contrato]);
        }

        return url('/admin');
    }
}
