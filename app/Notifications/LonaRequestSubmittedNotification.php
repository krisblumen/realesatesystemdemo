<?php

namespace App\Notifications;

use App\Filament\Resources\LonaRequestResource;
use App\Models\LonaRequest;
use App\Models\User;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso a owner/admin de que un agente solicitó más lonas.
 */
class LonaRequestSubmittedNotification extends Notification implements ShouldQueue
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
            ->title('Nueva solicitud de lonas')
            ->body($this->summary())
            ->icon('heroicon-o-inbox-arrow-down')
            ->iconColor('warning')
            ->actions([
                Action::make('view')
                    ->label('Ver solicitud')
                    ->url($this->url()),
            ])
            ->getDatabaseMessage();
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Nueva solicitud de lonas')
            ->greeting("Hola {$notifiable->name}")
            ->line('Un agente solicitó más lonas en Landra.')
            ->line($this->summary())
            ->action('Ver solicitud', $this->url())
            ->line('Este aviso fue generado automáticamente desde el panel de Landra.');
    }

    private function summary(): string
    {
        return implode(' · ', [
            "Agente: {$this->request->agent->name}",
            'Tipo: '.$this->request->operation_type->label(),
            "Cantidad: {$this->request->cantidad_solicitada}",
        ]);
    }

    private function url(): string
    {
        // LonaRequestResource se crea en Lote D; hasta entonces se cae al panel.
        $resource = LonaRequestResource::class;

        if (class_exists($resource)) {
            return $resource::getUrl('index');
        }

        return url('/admin');
    }
}
