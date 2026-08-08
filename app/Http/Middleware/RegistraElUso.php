<?php

namespace App\Http\Middleware;

use App\Tenancy\InquilinoActual;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Anota que alguien entró a este demo, para poder soltar los que nadie usa.
 *
 * VA EN `authMiddleware` DEL PANEL, y ahí está la definición de «uso»: una
 * petición AUTENTICADA al panel. No cuenta el sitio público del inquilino, y es
 * a propósito — el enlace para compartir se lo puede pasar a diez personas, y
 * diez desconocidos mirando una portada no significan que su dueño lo esté
 * usando. Tampoco cuenta la pantalla de login: quien no logra entrar no está
 * usando nada.
 *
 * SE ESCRIBE EN LA CENTRAL, no en la base del inquilino. El padrón es lo que
 * lee el barrido, y además así el dato sobrevive al borrado de la base.
 *
 * NO EN CADA PETICIÓN. Un panel activo hace decenas de peticiones por minuto y
 * todas dirían lo mismo; escribirlas todas es castigar el uso normal con un
 * UPDATE a la central por cada clic. Con una ventana de gracia, el dato pierde
 * precisión de minutos y conserva la que importa: la de días.
 */
class RegistraElUso
{
    /**
     * Cuánto tiene que haber pasado para volver a anotar.
     *
     * Quince minutos contra un tope que se mide en días: sobra de lejos, y deja
     * el costo en cuatro escrituras por hora de trabajo continuo.
     */
    private const GRACIA_EN_MINUTOS = 15;

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = app(InquilinoActual::class)->tenant();

        if ($tenant !== null && $this->hayQueAnotar($tenant->ultimo_acceso_en)) {
            // `forceFill` y `saveQuietly`: esto es un dato de operación, no un
            // cambio del inquilino. No tiene que disparar eventos ni tocar
            // `updated_at`, que sirve para otra cosa.
            $tenant->forceFill(['ultimo_acceso_en' => now()])->saveQuietly();
        }

        return $next($request);
    }

    private function hayQueAnotar(mixed $ultimo): bool
    {
        return $ultimo === null || $ultimo->lt(now()->subMinutes(self::GRACIA_EN_MINUTOS));
    }
}
