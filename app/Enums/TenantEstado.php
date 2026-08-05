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
     * Si a este inquilino le corren las tareas programadas.
     *
     * Sólo los activos. Un `expirado` ya no atiende a nadie —correrle tareas es
     * trabajo perdido sobre datos que nadie va a ver— y un `fallido` puede no
     * tener base, así que el comando moriría por conexión.
     *
     * Es una pregunta distinta de `resuelvePeticiones()` aunque hoy den lo
     * mismo: aquella habla de HTTP, esta de trabajo de fondo. Si algún día se
     * quisiera, por ejemplo, seguir venciendo contratos durante la ventana de
     * gracia de un expirado, se cambia acá y no en el programa de tareas.
     */
    public function recibeTareasProgramadas(): bool
    {
        return $this === self::Activo;
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
