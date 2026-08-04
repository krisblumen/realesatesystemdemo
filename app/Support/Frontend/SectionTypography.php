<?php

namespace App\Support\Frontend;

/**
 * El PESO de la letra del título y del antetítulo de una sección.
 *
 * La decisión vive en dos alturas y esta clase es la que las junta:
 *
 *   el sitio    define el peso por defecto de todos los títulos y de todos los
 *               antetítulos, en la configuración general. Es lo que evita tener
 *               que decidirlo sección por sección.
 *   la sección  puede llevarle la contra, sólo para ella, sin tocar la global.
 *
 * Por eso el estado es de TRES valores y no un booleano: `true` es negrita,
 * `false` es normal y AUSENTE es «lo que diga la configuración». Un booleano
 * habría obligado a copiar el valor global dentro de cada sección al guardar, y
 * a partir de ahí cambiar la configuración no habría movido nada.
 *
 * Las clases se devuelven ENTERAS y literales: son las que Tailwind compila
 * leyendo este archivo, y nada del payload entra en el nombre de una clase
 * (§6.1).
 */
class SectionTypography
{
    /**
     * El peso del título de la sección.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function title(array $payload): string
    {
        return self::weight($payload['title_bold'] ?? null, 'font-weight-heading');
    }

    /**
     * El peso de su antetítulo.
     *
     * Heredar NO devuelve clase: la utility `eyebrow` ya trae el peso del sitio
     * en una variable, y es la misma que usan los antetítulos de las páginas que
     * no pasan por el CMS. Devolver una clase acá los dejaría desincronizados.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function eyebrow(array $payload): string
    {
        return self::weight($payload['eyebrow_bold'] ?? null, '');
    }

    /**
     * El `!` no es decoración: `eyebrow` y `font-weight-heading` también fijan
     * `font-weight`, y entre utilities de la misma especificidad gana la que
     * Tailwind haya puesto última en el archivo compilado — un orden que no
     * controlamos y que cambia al agregar utilities. Sin el `!`, que la sección
     * pueda o no pisar al sitio dependería de una recompilación.
     */
    private static function weight(mixed $bold, string $heredado): string
    {
        return match ($bold) {
            true => 'font-bold!',
            false => 'font-normal!',
            default => $heredado,
        };
    }
}
