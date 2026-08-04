<?php

namespace App\Notifications;

use App\Filament\Pages\AgentLonas;
use App\Models\LonaRequest;
use App\Models\User;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso al agente de que su solicitud de lonas fue rechazada, con el motivo.
 */
class LonaRequestRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly LonaRequest $request) {}

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
            ->title('Solicitud de lonas rechazada')
            ->body($this->summary())
            ->icon('heroicon-o-x-circle')
            ->iconColor('danger')
            ->actions([
                Action::make('view')
                    ->label('Ver mis lonas')
                    ->url($this->url()),
            ])
            ->getDatabaseMessage();
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Solicitud de lonas rechazada')
            ->greeting("Hola {$notifiable->name}")
            ->line('Tu solicitud de lonas fue rechazada.')
            ->line($this->summary())
            ->action('Ver mis lonas', $this->url())
            ->line('Este aviso fue generado automáticamente desde el panel de New Hauz.');
    }

    private function summary(): string
    {
        $parts = [
            'Tipo: '.$this->request->operation_type->label(),
            "Cantidad: {$this->request->cantidad_solicitada}",
        ];

        if ($this->request->motivo_rechazo !== null) {
            $parts[] = "Motivo: {$this->request->motivo_rechazo}";
        }

        return implode(' · ', $parts);
    }

    private function url(): string
    {
        $page = AgentLonas::class;

        if (class_exists($page)) {
            return $page::getUrl();
        }

        return url('/admin');
    }
}
