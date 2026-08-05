<?php

namespace App\Notifications;

use App\Filament\Pages\AgentLonas;
use App\Models\LonaBatch;
use App\Models\User;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso al agente de que su solicitud de lonas fue aprobada (con el lote entregado).
 */
class LonaRequestApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly LonaBatch $batch) {}

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
            ->title('Solicitud de lonas aprobada')
            ->body($this->summary())
            ->icon('heroicon-o-check-badge')
            ->iconColor('success')
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
            ->subject('Solicitud de lonas aprobada')
            ->greeting("Hola {$notifiable->name}")
            ->line('Tu solicitud de lonas fue aprobada.')
            ->line($this->summary())
            ->action('Ver mis lonas', $this->url())
            ->line('Este aviso fue generado automáticamente desde el panel de Landra.');
    }

    private function summary(): string
    {
        return implode(' · ', [
            'Tipo: '.$this->batch->operation_type->label(),
            "Cantidad: {$this->batch->cantidad}",
        ]);
    }

    private function url(): string
    {
        // AgentLonas se crea en Lote D; hasta entonces se cae al panel.
        $page = AgentLonas::class;

        if (class_exists($page)) {
            return $page::getUrl();
        }

        return url('/admin');
    }
}
