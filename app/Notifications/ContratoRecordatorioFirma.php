<?php

namespace App\Notifications;

use App\Models\ContratoIntermediacion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Recordatorio al cliente de que su contrato sigue pendiente de firma (RFC-069, ~48h).
 * On-demand por correo (el cliente no es un User).
 *
 * NOTA: no incluye un enlace directo. El token de acceso se guarda hasheado (no se puede
 * reconstruir en claro), así que el recordatorio remite al enlace ya enviado o al asesor.
 */
class ContratoRecordatorioFirma extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ContratoIntermediacion $contrato) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Recordatorio · Contrato pendiente de firma · Folio {$this->contrato->folio}")
            ->greeting("Hola {$this->contrato->cliente_nombre}")
            ->line("Tu contrato de intermediación con New Hauz (folio {$this->contrato->folio}) sigue pendiente de firma.")
            ->line('Puedes firmarlo desde el enlace que te enviamos por correo. Si ya expiró, tu asesor puede reenviártelo conservando el mismo folio.');
    }
}
