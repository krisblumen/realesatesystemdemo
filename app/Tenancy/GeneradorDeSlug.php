<?php

namespace App\Tenancy;

use InvalidArgumentException;
use Random\RandomException;

/**
 * Genera y valida el identificador de un inquilino.
 *
 * El slug es el subdominio Y el nombre de su base de datos. Lo segundo es lo
 * que lo vuelve delicado: `CREATE DATABASE` es DDL y Postgres no acepta
 * parámetros enlazados para identificadores, así que el nombre termina
 * interpolado en la sentencia. Es el único punto del sistema donde una falla no
 * significa «un inquilino ve a otro» sino «se pierden todos».
 *
 * Por eso el slug LO GENERA EL SERVIDOR. No hay campo en ningún formulario ni
 * argumento de ningún comando que llegue hasta acá.
 */
class GeneradorDeSlug
{
    /**
     * Sin `l`, `1`, `0` ni `o`.
     *
     * No es estética. El slug es un subdominio que alguien lee de una pantalla
     * y tipea en otra; una `l` que resulta ser un `1` es una consulta de
     * soporte de las que no se resuelven por teléfono.
     */
    private const ALFABETO = 'abcdefghijkmnpqrstuvwxyz23456789';

    private const LARGO = 12;

    private const FORMATO = '/^[a-z][a-z0-9]{7,31}$/';

    /**
     * @throws RandomException
     */
    public static function generar(): string
    {
        // La primera posición no puede ser un dígito: un identificador de
        // Postgres que arranca con número necesita comillas siempre, y un
        // subdominio que arranca con número confunde a más de un resolutor.
        $letras = preg_replace('/[0-9]/', '', self::ALFABETO);

        $slug = $letras[random_int(0, strlen($letras) - 1)];

        for ($i = 1; $i < self::LARGO; $i++) {
            $slug .= self::ALFABETO[random_int(0, strlen(self::ALFABETO) - 1)];
        }

        return $slug;
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function validar(string $slug): string
    {
        if (preg_match(self::FORMATO, $slug) !== 1) {
            throw new InvalidArgumentException("Slug de inquilino inválido: «{$slug}».");
        }

        return $slug;
    }

    /**
     * El nombre de la base, con prefijo fijo y el slug validado DE NUEVO.
     *
     * Volver a validar acá no es desconfiar de quien llamó. Es que la
     * validación tiene que estar pegada al uso peligroso: si viviera sólo donde
     * nace el slug, el segundo camino que alguien escriba dentro de seis meses
     * —una importación, un comando nuevo— no pasaría por ella.
     */
    public static function baseDe(string $slug): string
    {
        // La validación va primero. Si el slug es inválido, no se lee
        // configuración, no se compone nada y no se toca la base: se corta acá.
        self::validar($slug);

        return config('tenancy.prefijo_inquilino', 'demo_t_').$slug;
    }
}
