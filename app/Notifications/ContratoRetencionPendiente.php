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
 * Aviso al Owner de que un contrato cumplió su periodo de retención (2 años) y entró a la
 * lista de eliminación pendiente (RFC-069). La eliminación efectiva la confirma el Owner
 * manualmente; este job NO borra nada.
 */
class ContratoRetencionPendiente extends Notification implements ShouldQueue
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
            ->title('Contrato en retención pendiente')
            ->body("El contrato {$this->contrato->folio} cumplió 2 años desde la firma. Revisa y confirma su eliminación.")
            ->icon('heroicon-o-archive-box-x-mark')
            ->iconColor('warning')
            ->actions([
                Action::make('view')->label('Revisar')->url($this->url()),
            ])
            ->getDatabaseMessage();
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Retención cumplida · Confirmar eliminación · Folio {$this->contrato->folio}")
            ->greeting("Hola {$notifiable->name}")
            ->line("El contrato {$this->contrato->folio} cumplió su periodo de retención de 2 años desde la firma.")
            ->line('Entró a la lista de eliminación pendiente. La eliminación del expediente requiere tu confirmación.')
            ->action('Revisar y confirmar', $this->url());
    }

    private function url(): string
    {
        if (class_exists(ContratoIntermediacionResource::class)) {
            return ContratoIntermediacionResource::getUrl('view', ['record' => $this->contrato]);
        }

        return url('/admin');
    }
}
