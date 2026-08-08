<?php

namespace Tests\Support\Tenancy;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Una notificación encolada que lleva un modelo del inquilino.
 *
 * Copia la forma exacta de las 12 que tiene la aplicación: extiende
 * `Notification` —que usa `SerializesModels`, y ahí está el problema—, implementa
 * `ShouldQueue`, y guarda el modelo como propiedad pública.
 */
class AvisoDePrueba extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ContratoDePrueba $contrato) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Se lee un atributo a propósito: si el modelo se rehidrató de la base
        // equivocada, acá se nota.
        return (new MailMessage)->line("Folio {$this->contrato->folio}");
    }
}
