<?php

namespace App\Notifications;

use App\Models\Tenant;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * El acuse para quien opera: se entregó un demo, a quién, y hasta cuándo.
 *
 * NO LLEVA LA CONTRASEÑA, y no es un olvido. Quien opera no la necesita —tiene
 * `demo:padron` para saber qué hay y `demo:reemitir-acceso` para regenerarla— y
 * mandársela la deja en una segunda bandeja de la que ya no sale.
 *
 * Una credencial se guarda en un lugar. Duplicarla no agrega comodidad: agrega
 * superficie.
 */
class AltaDeDemoEntregada extends Notification
{
    public function __construct(public readonly Tenant $tenant) {}

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
            ->subject("Demo entregado a {$this->tenant->email}")
            ->line('Se creó un demo y se envió la invitación.')
            // Mismo motivo que en la invitación: un correo con guiones bajos
            // —que son legales— se rompería igual.
            ->line("**Para:** `{$this->tenant->email}`")
            ->line("**Dirección:** `{$this->tenant->slug}`")
            ->line("**Vence:** {$this->tenant->expira_en->translatedFormat('j \d\e F \d\e Y')}")
            ->line('El acceso no viaja en este mensaje. Si hace falta reemitirlo: `php artisan demo:reemitir-acceso '.$this->tenant->slug.'`.');
    }
}
