<?php

namespace App\Notifications;

use App\Models\Tenant;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * La invitación que recibe quien va a probar el demo.
 *
 * NO IMPLEMENTA `ShouldQueue`, a diferencia del resto de las notificaciones de
 * esta aplicación, y es a propósito: la manda un comando de consola con alguien
 * mirando. Encolarla le sacaría a quien invita la única señal de que el correo
 * salió — y el correo es la parte que no controlamos.
 *
 * El día que el registro público exista, el alta entera corre en un trabajo y
 * esto ya es asíncrono por estar adentro.
 *
 * LLEVA LA CONTRASEÑA. Se decidió con el reparo sobre la mesa: queda en texto
 * plano en la bandeja de quien la recibe. A cambio, esa persona puede volver a
 * entrar durante todo el plazo sin pedir nada.
 */
class InvitacionAlDemo extends Notification
{
    public function __construct(
        public readonly Tenant $tenant,
        public readonly string $password,
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
        $dominio = config('tenancy.dominio_base', 'demo.localhost');
        $url = "https://{$this->tenant->slug}.{$dominio}/admin";

        return (new MailMessage)
            ->subject('Tu demo de Landra ya está listo')
            ->greeting('Hola')
            ->line('Preparamos un sistema completo para que lo pruebes: es tuyo, con tus propios datos, y nadie más lo ve.')
            ->action('Entrar a mi demo', $url)
            // ENTRE ACENTOS GRAVES, y no es un adorno tipográfico.
            //
            // El cuerpo se escribe en markdown, y `Str::password()` genera
            // símbolos que markdown interpreta: `* _ [ ] \ < >`. Una contraseña
            // con `ab*cd*ef` llegaba como `abcdef`, con «cd» en cursiva.
            //
            // El síntoma era cruel: quien invita ve la contraseña correcta en
            // pantalla, el invitado recibe otra, y ninguno sabe por qué no entra.
            //
            // El acento grave NO está en ese alfabeto, así que sirve de
            // delimitador seguro. Y de paso queda en monoespaciada, que es más
            // fácil de copiar sin equivocarse.
            ->line("**Usuario:** `{$this->tenant->email}`")
            ->line("**Contraseña:** `{$this->password}`")
            ->line('Guarda esta contraseña: no se vuelve a enviar.')
            ->line("Tu demo está disponible hasta el {$this->tenant->expira_en->translatedFormat('j \d\e F \d\e Y')}. Después se borra completo, con todo lo que hayas cargado.")
            // El límite de RFC-14, dicho a quien lo necesita saber. Un límite
            // conocido que no llega a quien sube los archivos no es un límite
            // aceptado: es un descuido con papeles.
            ->line('**Un aviso:** las imágenes que publiques se sirven sin pedir contraseña, así que no subas nada que no pueda ser público.');
    }
}
