<?php

namespace App\Tenancy;

use App\Models\Tenant;
use App\Notifications\AltaDeDemoEntregada;
use App\Notifications\InvitacionAlDemo;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Los dos correos de un alta: el acceso a quien entra, el aviso a quien opera.
 *
 * VIVE APARTE DEL COMANDO porque dejó de ser cosa del comando. Cuando el acceso
 * salía sólo por consola, mandarlo desde ahí tenía sentido. Con el registro
 * público el correo ES la entrega: nadie está mirando una terminal, y un alta
 * que no manda el correo crea un inquilino al que su dueño no puede entrar.
 *
 * NO DEJA REVENTAR, Y ESA ES LA REGLA (RFC-11). El correo es el eslabón que no
 * controlamos —cae en spam, se demora, rebota— y si su fallo tumbara el alta,
 * cada problema de correo dejaría una base creada y a nadie adentro. Devuelve el
 * fallo en vez de lanzarlo, para que quien llamó decida qué hacer con él:
 * `demo:invitar` lo muestra junto al acceso impreso, que sigue siendo válido.
 */
class EntregaElAcceso
{
    public function entregar(Tenant $tenant, string $password): ?Throwable
    {
        try {
            // La contraseña va SÓLO acá. El aviso al operador confirma que se
            // entregó, no entrega: un correo de más con la contraseña adentro es
            // una copia más que puede quedar en una bandeja ajena.
            Notification::route('mail', $tenant->email)
                ->notify(new InvitacionAlDemo($tenant, $password));

            $operador = (string) Config::get('tenancy.aviso_de_altas', '');

            if ($operador !== '') {
                Notification::route('mail', $operador)
                    ->notify(new AltaDeDemoEntregada($tenant));
            }

            return null;
        } catch (Throwable $e) {
            // Se reporta igual: en el camino encolado no hay nadie mirando, y un
            // fallo que sólo se devuelve es un fallo que nadie ve.
            report($e);

            return $e;
        }
    }
}
