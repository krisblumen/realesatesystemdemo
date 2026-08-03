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
 * Contrato firmado por el cliente (RFC-069). Va al agente (base de datos + email, con el PDF
 * final adjunto) y también al cliente como copia (solo email, on-demand). El canal se adapta
 * según el destinatario sea User o cliente anónimo.
 */
class ContratoFirmado extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ContratoIntermediacion $contrato) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? ['database', 'mail'] : ['mail'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Contrato firmado')
            ->body("El cliente {$this->contrato->cliente_nombre} firmó el contrato {$this->contrato->folio}.")
            ->icon('heroicon-o-check-badge')
            ->iconColor('success')
            ->actions([
                Action::make('view')->label('Ver contrato')->url($this->url()),
            ])
            ->getDatabaseMessage();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $esAgente = $notifiable instanceof User;

        $mail = (new MailMessage)
            ->subject("Contrato firmado · Folio {$this->contrato->folio}")
            ->greeting($esAgente ? "Hola {$notifiable->name}" : "Hola {$this->contrato->cliente_nombre}")
            ->line($esAgente
                ? "El cliente {$this->contrato->cliente_nombre} firmó el contrato {$this->contrato->folio}."
                : "Tu contrato de intermediación (folio {$this->contrato->folio}) quedó firmado. Adjuntamos tu copia.");

        $media = $this->contrato->getFirstMedia('documento-final');
        if ($media !== null) {
            $mail->attach($media->getPath(), [
                'as' => "contrato-{$this->contrato->folio}.pdf",
                'mime' => 'application/pdf',
            ]);
        }

        return $mail;
    }

    private function url(): string
    {
        if (class_exists(ContratoIntermediacionResource::class)) {
            return ContratoIntermediacionResource::getUrl('view', ['record' => $this->contrato]);
        }

        return url('/admin');
    }
}
