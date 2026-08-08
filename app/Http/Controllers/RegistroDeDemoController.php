<?php

namespace App\Http\Controllers;

use App\Jobs\AprovisionaUnInquilino;
use App\Models\Tenant;
use App\Tenancy\InquilinoActual;
use App\Tenancy\LimiteAlcanzado;
use App\Tenancy\LimiteDeAltas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\View\View;

/**
 * El registro público de un demo (fase 2).
 *
 * NO CREA EL INQUILINO: encola. Copiar una base tarda un par de segundos con la
 * plantilla de hoy y va a tardar más a medida que crezca; sostener una petición
 * HTTP mientras tanto es ocupar un proceso de PHP y una conexión de las 100 que
 * compartimos con la producción vecina. La respuesta es inmediata y el acceso
 * llega por correo.
 *
 * VIVE SÓLO EN EL HOST CENTRAL, y se comprueba acá y no en la ruta: en un
 * subdominio de inquilino esta página no significa nada, y servirla ahí sería
 * ofrecer registrarse desde adentro de un demo ajeno.
 *
 * SOBRE LA DIRECCIÓN DISCRETA. Por ahora la ruta la conocemos nosotros, y eso no
 * es lo que la protege: una dirección se comparte, se filtra y se adivina. Lo
 * que protege son los topes de RFC-10 —el duro de la instancia y el de origen— y
 * por eso se aplican igual, no «cuando abramos». El día que la dirección se
 * publique no hay que cambiar nada de este archivo.
 */
class RegistroDeDemoController extends Controller
{
    public function show(): View
    {
        $this->soloEnElHostCentral();

        return view('central.registro', [
            'dias' => (int) Config::get('tenancy.dias_de_vida', 30),
        ]);
    }

    public function store(Request $request, LimiteDeAltas $limites): RedirectResponse
    {
        $this->soloEnElHostCentral();

        $datos = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:190'],
        ], [
            'email.required' => 'Hace falta un correo para mandarte el acceso.',
            'email.email' => 'Ese correo no parece válido. Revísalo e inténtalo de nuevo.',
        ]);

        // EL SEÑUELO SE ATIENDE EN SILENCIO. Contestar «error» le enseña al robot
        // qué campo evitar la próxima vez; contestar «listo» lo manda a otra
        // parte convencido de que funcionó.
        if (trim((string) $request->input('sitio_web')) !== '') {
            return $this->listo();
        }

        $email = mb_strtolower(trim($datos['email']));

        // SE INFORMA QUE YA EXISTE, y es una decisión con costo. Un desconocido
        // podría averiguar así si un correo tiene demo. La alternativa —callar—
        // deja a quien ya se registró esperando un correo que no va a llegar,
        // que es el problema real y frecuente. El tope por origen acota de a tres
        // intentos por día, así que no sirve para recorrer una lista.
        if (Tenant::hayUnoActivoPara($email)) {
            return back()->withInput()->withErrors([
                'email' => 'Ya hay un demo activo para ese correo. Revisa tu bandeja de entrada y la carpeta de spam.',
            ]);
        }

        // EL TOPE SE COMPRUEBA ANTES DE ENCOLAR (RFC-10, regla 1). Encolar altas
        // que van a fallar es acumular basura en la cola y en el padrón.
        try {
            $limites->verificar($request->ip());
        } catch (LimiteAlcanzado $e) {
            return back()->withInput()->withErrors(['email' => $e->getMessage()]);
        }

        AprovisionaUnInquilino::dispatch($email, $limites->hashDe((string) $request->ip()));

        return $this->listo();
    }

    /**
     * La misma respuesta para el alta encolada y para el señuelo.
     *
     * Que sean idénticas no es comodidad: si difirieran en una coma, esa coma
     * sería el indicador que un robot necesita.
     */
    private function listo(): RedirectResponse
    {
        return back()->with(
            'registro.listo',
            'Listo. En un minuto te llega un correo con tu acceso. Si no aparece, revisa la carpeta de spam.',
        );
    }

    private function soloEnElHostCentral(): void
    {
        abort_unless(app(InquilinoActual::class)->esElHostCentral(), 404);
    }
}
