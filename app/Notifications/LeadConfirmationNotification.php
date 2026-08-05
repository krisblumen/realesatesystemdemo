<?php

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeadConfirmationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Lead $lead) {}

    /**
     * @return list<string>
     */
    public function via(Lead $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(Lead $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Recibimos tu mensaje — Landra')
            ->greeting("Hola {$notifiable->name}");

        // Solo se personaliza con el asesor si el lead viene de un inmueble
        // Y ya tiene agente resuelto (el formulario de contacto general nunca
        // asigna agente al captar, a proposito -- ver LeadAssignmentService).
        $agent = $this->lead->property_id !== null ? $this->lead->agent : null;

        if ($agent === null) {
            return $mail->line('Gracias por contactarnos. Un asesor de Landra se pondrá en contacto contigo a la brevedad posible.');
        }

        $mail->line("Gracias por contactarnos. El asesor {$agent->name} se pondrá en contacto contigo en breve.");

        if (filled($agent->phone)) {
            $mail->line("También puedes escribirle directo al {$agent->phone}.");
        }

        return $mail;
    }
}
