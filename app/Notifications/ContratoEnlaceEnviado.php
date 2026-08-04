<?php

namespace App\Notifications;

use App\Models\ContratoIntermediacion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Enlace del contrato enviado al cliente (RFC-069). Va por email; el WhatsApp es un enlace
 * wa.me asistido que el agente dispara desde el panel (D-9 / DIF-2), no un envío automático.
 * El cliente no es un User: se notifica on-demand por correo.
 */
class ContratoEnlaceEnviado extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ContratoIntermediacion $contrato,
        public readonly string $token,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('contratos.publico.show', ['token' => $this->token]);

        return (new MailMessage)
            ->subject("Contrato de intermediación · Folio {$this->contrato->folio}")
            ->greeting("Hola {$this->contrato->cliente_nombre}")
            ->line('New Hauz Inmobiliaria te compartió un contrato de intermediación para su revisión y firma.')
            ->line("Folio: {$this->contrato->folio}")
            ->action('Revisar y firmar', $url)
            ->line('El enlace es de un solo uso y expira en 72 horas. Si expira, tu asesor puede reenviarlo.');
    }
}
