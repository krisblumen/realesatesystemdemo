<?php

namespace App\Tenancy;

use App\Jobs\AprovisionaUnInquilino;
use App\Models\Tenant;

/**
 * Lo que hay que comprobar antes de encolar un alta, una sola vez.
 *
 * POR QUÉ EXISTE. Hasta RFC-078 el único camino de alta pública era el
 * formulario, y estas tres reglas vivían dentro de su controlador. Con la API
 * abriendo un segundo camino, dejarlas ahí significaba copiarlas — y una copia
 * de una regla de seguridad no es una copia: es una regla que un día va a
 * divergir de la otra sin que nadie lo note. El tope que protege la instancia no
 * puede depender de por qué puerta entró la petición.
 *
 * EL ORDEN NO ES ARBITRARIO y se conserva tal como estaba:
 *
 *  1. Normalizar el correo, porque el duplicado se busca sobre el normalizado.
 *  2. Rechazar el duplicado ANTES de mirar topes: quien ya tiene demo no debería
 *     gastar su cupo por pedir dos veces.
 *  3. Los topes ANTES de encolar (RFC-10, regla 1), nunca dentro del trabajo.
 *
 * NO DECIDE QUÉ CONTESTAR. Lanza y deja que cada camino traduzca: el formulario
 * a un error sobre el campo, la API a un código HTTP. Meter esa decisión acá
 * ataría el dominio a la forma de una respuesta.
 */
class SolicitaUnAlta
{
    public function __construct(private readonly LimiteDeAltas $limites) {}

    /**
     * Encola el alta y devuelve el correo ya normalizado.
     *
     * El origen es opcional: sin él sólo se comprueba el tope de la instancia.
     * Ese caso existe para las altas que no vienen de un visitante —la invitación
     * por consola— y no para ahorrarse el dato cuando sí se tiene.
     *
     * @throws YaHayUnDemo
     * @throws LimiteAlcanzado
     */
    public function encolar(string $email, ?string $origen = null): string
    {
        $email = mb_strtolower(trim($email));

        if (Tenant::hayUnoActivoPara($email)) {
            throw new YaHayUnDemo;
        }

        $this->limites->verificar($origen);

        // SE GUARDA EL HASH Y NO LA DIRECCIÓN: el padrón conserva de dónde vino
        // el alta sin conservar de quién. Ver `LimiteDeAltas::hashDe()`.
        AprovisionaUnInquilino::dispatch(
            $email,
            $origen === null ? null : $this->limites->hashDe($origen),
        );

        return $email;
    }
}
