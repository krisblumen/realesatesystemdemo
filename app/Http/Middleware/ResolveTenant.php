<?php

namespace App\Http\Middleware;

use App\Enums\TenantEstado;
use App\Models\Tenant;
use App\Tenancy\InquilinoActual;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resuelve de qué inquilino es la petición, leyendo el `Host`.
 *
 * CORRE ANTES DE `StartSession`, y no es preferencia. La sesión se guarda en
 * base de datos: arrancarla antes de resolver la leería de la base equivocada.
 *
 * Se resuelve del `Host` y no de la sesión porque la sesión es circular —vive
 * en la base, para leerla hay que saber a qué base conectarse, y para saberlo
 * hay que leerla—. El encabezado llega antes de que corra una sola línea
 * nuestra y rompe el ciclo.
 *
 * ADVERTENCIA DE DESPLIEGUE: eso último sólo es cierto si el proxy es de
 * confianza. `bootstrap/app.php` confía en todos (`trustProxies(at: '*')`) y
 * entre los encabezados confiados está `X-Forwarded-Host`, así que quien
 * alcance el origen sin pasar por el proxy elige a qué inquilino resuelve. Se
 * acota en el servidor, antes de invitar a nadie — está en el checklist de
 * `docs/deployment/DEMO-MULTI-INQUILINO.md`.
 */
class ResolveTenant
{
    /**
     * El mismo formato cerrado del alta.
     *
     * Se comprueba ANTES de consultar el padrón: un host que no lo cumple no
     * llega siquiera a ser una consulta.
     */
    private const FORMATO_SLUG = '/^[a-z][a-z0-9]{7,31}$/';

    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        $base = (string) Config::get('tenancy.dominio_base');

        // TRES CASOS, Y EL TERCERO ES EL QUE ME FALTABA.
        //
        // Un host que no pertenece al dominio del demo no es «el host central»:
        // es una petición ajena a esta épica, y no hay que tocarle nada. Al
        // tratarlo como central le cambiaba la conexión por defecto a TODA la
        // aplicación —incluidas las 1600 pruebas existentes, que corren contra
        // `localhost`— y las mandaba a leer a una base sin sus tablas.
        if ($host !== $base && ! str_ends_with($host, '.'.$base)) {
            return $next($request);
        }

        if ($host === $base) {
            // Host central: la conexión por defecto ES la central. Cualquier
            // modelo de inquilino que se consulte acá falla ruidosamente, que es
            // exactamente lo que se quiere: el host central no toca datos de
            // inquilino, y lo hace cumplir el motor y no la disciplina.
            Config::set('database.default', 'central');

            return $next($request);
        }

        $slug = $this->slugDelHost($host);

        $tenant = Tenant::query()
            ->where('slug', $slug)
            ->where('estado', TenantEstado::Activo->value)
            ->first();

        // Un slug inexistente, uno expirado y uno malformado devuelven LO MISMO.
        // Distinguirlos permitiría averiguar qué inquilinos existieron.
        abort_if($tenant === null, 404);

        $this->apuntarA($tenant);

        return $next($request);
    }

    /**
     * Extrae el slug de un host que YA se sabe del dominio del demo.
     */
    private function slugDelHost(string $host): string
    {
        $base = (string) Config::get('tenancy.dominio_base');

        $slug = substr($host, 0, -(strlen($base) + 1));

        // Malformado se trata igual que inexistente, y sin consultar.
        abort_if(preg_match(self::FORMATO_SLUG, $slug) !== 1, 404);

        return $slug;
    }

    /**
     * Apunta la conexión POR DEFECTO a la base del inquilino.
     *
     * Es lo que deja intactos a los 28 modelos que ya existen: siguen usando la
     * conexión por defecto y el aislamiento ocurre por debajo de ellos.
     *
     * Reapuntar la por defecto es seguro ACÁ y no en un comando de consola: en
     * este punto de la petición nada se conectó todavía. En un proceso largo que
     * ya resolvió conexiones, lo correcto es un nombre propio — así se rompió la
     * construcción de la plantilla en el lote B.
     */
    private function apuntarA(Tenant $tenant): void
    {
        Config::set('database.connections.pgsql.database', $tenant->database);
        DB::purge('pgsql');
        Config::set('database.default', 'pgsql');

        app(InquilinoActual::class)->fijar($tenant);
    }
}
