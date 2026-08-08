<?php

namespace App\Tenancy;

use Illuminate\Support\Str;

/**
 * La contraseña que recibe quien entra al demo.
 *
 * NO USA `Str::password()`, y el motivo salió de un defecto real.
 *
 * Su alfabeto incluye `< > &`, que el correo escapa DOS veces —una al
 * interpolar y otra al convertir markdown— así que una contraseña con `>`
 * llegaba escrita `&gt;`. Y también `* _ [ ]`, que markdown interpreta: con
 * `ab*cd*ef` el invitado recibía `abcdef`.
 *
 * En los dos casos el síntoma era el mismo y era cruel: quien invita ve la
 * contraseña correcta en pantalla, el invitado recibe otra, y ninguno de los dos
 * puede saber por qué no entra.
 *
 * Se podría escapar mejor. Pero esta contraseña la lee una persona de un correo
 * y la escribe en un formulario, así que los caracteres problemáticos no aportan
 * nada aunque se rendericen bien.
 *
 * SE EXCLUYEN TAMBIÉN LOS AMBIGUOS —`l`, `1`, `I`, `0`, `O`— por la misma razón
 * que en `GeneradorDeSlug`: quien transcribe no distingue un uno de una ele.
 *
 * Quedan 56 símbolos. Con 16 caracteres son unos 93 bits de entropía, de sobra
 * para una credencial que vive días y protege un demo.
 */
class GeneradorDeClave
{
    private const ALFABETO = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789-.#%+=?';

    public const LARGO = 16;

    public function generar(): string
    {
        $alfabeto = str_split(self::ALFABETO);
        $ultimo = count($alfabeto) - 1;

        $clave = '';

        for ($i = 0; $i < self::LARGO; $i++) {
            $clave .= $alfabeto[random_int(0, $ultimo)];
        }

        return $clave;
    }

    /**
     * Si una clave está hecha sólo de caracteres que sobreviven a un correo.
     *
     * Existe para que los tests puedan preguntarlo sin repetir el alfabeto.
     */
    public static function esSegura(string $clave): bool
    {
        return Str::of($clave)->trim()->isNotEmpty()
            && strspn($clave, self::ALFABETO) === strlen($clave);
    }
}
