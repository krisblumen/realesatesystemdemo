<?php

namespace App\Support\Frontend;

use Illuminate\Support\Facades\Route;

/**
 * The single resolver for every CTA and footer link (RFC-073).
 *
 * A CTA is a typed value object `{label, type, target}` with
 * `type ∈ {route, url, whatsapp, tel, mailto}`. This class is the ONLY place a
 * target becomes a URL, so the unsafe-scheme and allowlist rules live in one
 * spot instead of being re-checked at every call site (which is how one gets
 * missed). It returns `{label, url, external}` or `null` when the value object
 * is invalid — callers treat `null` as "drop this link", never as a fatal.
 *
 * The label is returned verbatim for Blade to escape; a label carrying markup
 * is rejected outright so it can never be a valid stored value.
 */
class CtaResolver
{
    /** Below this a wa.me / tel link is a dead end (matches settings service). */
    private const MIN_PHONE_DIGITS = 10;

    /**
     * El `type` viaja de vuelta junto al resultado, y no es información de más:
     * un destino de WhatsApp se dibuja como botón de WhatsApp —verde y con su
     * ícono—, y una vez resuelto a URL eso ya no se puede deducir. Adivinarlo
     * mirando si la URL empieza con `wa.me` sería reconstruir acá una decisión
     * que este método ya tomó.
     *
     * @param  mixed  $cta  the stored value object; anything but a well-formed array is invalid
     * @return array{label: string, type: string, url: string, external: bool}|null
     */
    public function resolve(mixed $cta): ?array
    {
        if (! is_array($cta)) {
            return null;
        }

        $label = is_string($cta['label'] ?? null) ? trim($cta['label']) : '';
        $type = is_string($cta['type'] ?? null) ? $cta['type'] : '';
        $target = is_string($cta['target'] ?? null) ? trim($cta['target']) : '';

        // A CTA with no readable label, or one smuggling HTML, is not a valid
        // value object — reject rather than render an empty or escaped-markup link.
        if ($label === '' || preg_match('/[<>]/', $label) === 1) {
            return null;
        }

        $resolved = match ($type) {
            'route' => $this->route($target, self::filtro($cta['query'] ?? null)),
            'url' => $this->url($target),
            'whatsapp' => $this->whatsapp($target),
            'mailto' => $this->mailto($target),
            'tel' => $this->tel($target),
            default => null,
        };

        if ($resolved === null) {
            return null;
        }

        return ['label' => $label, 'type' => $type, ...$resolved];
    }

    /**
     * A `route` target is a public allowlist KEY (`home`, `contacto`…), not a
     * raw Laravel route name: the owner picks a page, never types an internal
     * route, and one allowlist governs both navigation and CTAs (RFC-073).
     *
     * @return array{url: string, external: bool}|null
     */
    private function route(string $key, array $query = []): ?array
    {
        if (! PublicRoutes::isKey($key)) {
            return null;
        }

        $name = PublicRoutes::routeName($key);

        return Route::has($name) ? ['url' => route($name, $query), 'external' => false] : null;
    }

    /**
     * Los filtros que un CTA puede llevar pegados a su destino.
     *
     * Es una lista CERRADA, igual que la de destinos, y por el mismo motivo: un
     * parámetro libre en la URL es texto del owner llegando a una consulta. Hoy
     * sólo el catálogo filtrado a oportunidades de inversión.
     *
     * No se ofrece en ningún formulario — lo escribe el compilador al armar el
     * botón de una sección. Esto lo valida igual: la regla no puede depender de
     * que la pantalla no lo pregunte.
     *
     * @return array<string, string>
     */
    private static function filtro(mixed $query): array
    {
        if (! is_array($query)) {
            return [];
        }

        $permitidos = ['oportunidad' => ['1']];
        $limpio = [];

        foreach ($query as $clave => $valor) {
            if (is_string($clave) && in_array((string) $valor, $permitidos[$clave] ?? [], true)) {
                $limpio[$clave] = (string) $valor;
            }
        }

        return $limpio;
    }

    /** @return array{url: string, external: bool}|null */
    private function url(string $target): ?array
    {
        // HTTPS only, and it must parse as an absolute http(s) URL: this rejects
        // javascript:/data:/file:/vbscript:, protocol-relative `//host` and any
        // relative path in one check.
        if (! preg_match('#^https://#i', $target) || filter_var($target, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return ['url' => $target, 'external' => true];
    }

    /** @return array{url: string, external: bool}|null */
    private function whatsapp(string $target): ?array
    {
        $digits = preg_replace('/\D+/', '', $target) ?? '';

        return strlen($digits) >= self::MIN_PHONE_DIGITS
            ? ['url' => "https://wa.me/{$digits}", 'external' => true]
            : null;
    }

    /** @return array{url: string, external: bool}|null */
    private function mailto(string $target): ?array
    {
        return filter_var($target, FILTER_VALIDATE_EMAIL)
            ? ['url' => "mailto:{$target}", 'external' => false]
            : null;
    }

    /** @return array{url: string, external: bool}|null */
    private function tel(string $target): ?array
    {
        $digits = preg_replace('/\D+/', '', $target) ?? '';

        if (strlen($digits) < self::MIN_PHONE_DIGITS) {
            return null;
        }

        // Keep a leading + when the owner typed an international number.
        $plus = str_starts_with($target, '+') ? '+' : '';

        return ['url' => "tel:{$plus}{$digits}", 'external' => false];
    }
}
