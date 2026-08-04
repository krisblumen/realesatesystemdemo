<?php

namespace App\Notifications;

use App\Filament\Resources\LeadResource;
use App\Models\Lead;
use App\Models\User;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeadAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Lead $lead) {}

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
            ->title('Nuevo lead asignado')
            ->body($this->summary())
            ->icon('heroicon-o-user-plus')
            ->iconColor('success')
            ->actions([
                Action::make('view')
                    ->label('Ver lead')
                    ->url($this->leadUrl()),
            ])
            ->getDatabaseMessage();
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Nuevo lead asignado')
            ->greeting("Hola {$notifiable->name}")
            ->line('Se te asignó un nuevo lead en New Hauz.')
            ->line($this->summary())
            ->action('Ver lead', $this->leadUrl())
            ->line('Este aviso fue generado automáticamente desde el panel de New Hauz.');
    }

    private function summary(): string
    {
        $parts = [
            "Lead: {$this->lead->name}",
            "Email: {$this->lead->email}",
        ];

        if ($this->lead->phone !== null) {
            $parts[] = "Teléfono: {$this->lead->phone}";
        }

        $propertyTitle = $this->lead->property?->title;

        if ($propertyTitle !== null) {
            $parts[] = "Inmueble: {$propertyTitle}";
        }

        return implode(' · ', $parts);
    }

    private function leadUrl(): string
    {
        return LeadResource::getUrl('edit', ['record' => $this->lead]);
    }
}
