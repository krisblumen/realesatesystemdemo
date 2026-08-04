<?php

namespace App\Enums;

/**
 * El ciclo de vida de un inquilino del demo.
 *
 * Las transiciones viven acá y no en quien las provoca. Sin un punto único,
 * cada camino —el alta, la expiración, el borrado, el padrón— inventa su
 * secuencia y nadie puede decir cuáles son válidas.
 */
enum TenantEstado: string
{
    case Aprovisionando = 'aprovisionando';
    case Activo = 'activo';
    case Fallido = 'fallido';
    case Expirado = 'expirado';
    case Borrado = 'borrado';

    /**
     * @return array<int, self>
     */
    public function siguientesPosibles(): array
    {
        return match ($this) {
            self::Aprovisionando => [self::Activo, self::Fallido],
            self::Activo => [self::Expirado],
            self::Expirado => [self::Borrado],
            // `fallido` y `borrado` no transicionan. Ver requiereBarridoDeBase().
            self::Fallido, self::Borrado => [],
        };
    }

    public function puedePasarA(self $destino): bool
    {
        return in_array($destino, $this->siguientesPosibles(), true);
    }

    /**
     * Si este estado puede haber dejado una base de datos viva.
     *
     * NO es lo mismo que «transiciona». `fallido` es terminal para el ciclo de
     * vida y aun así hay que barrerlo: si el alta murió DESPUÉS de
     * `CREATE DATABASE` —por ejemplo al crear el usuario del inquilino— quedó
     * una base sin dueño, ocupando conexiones y disco, y el padrón la muestra
     * como si no existiera.
     *
     * Una limpieza que filtre sólo `expirado` la deja ahí para siempre. Por eso
     * el barrido pregunta por esto y no por si el estado es terminal.
     */
    public function requiereBarridoDeBase(): bool
    {
        return match ($this) {
            self::Fallido, self::Expirado => true,
            // `aprovisionando` todavía no llegó a crear nada; `activo` está en
            // uso; `borrado` ya pasó por el barrido.
            self::Aprovisionando, self::Activo, self::Borrado => false,
        };
    }

    /**
     * Sólo un inquilino activo resuelve una petición.
     *
     * Hacia afuera, un inquilino que no resuelve devuelve lo mismo que uno
     * inexistente: distinguirlos permitiría enumerar inquilinos probando
     * subdominios.
     */
    public function resuelvePeticiones(): bool
    {
        return $this === self::Activo;
    }
}
