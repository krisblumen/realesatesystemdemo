<?php

namespace App\Tenancy;

/**
 * Un trabajo que opera sobre los datos de un inquilino.
 *
 * Lo declara guardando el NOMBRE DE SU BASE, no el modelo `Tenant` ni la
 * conexión: un modelo serializado se rehidrata consultando alguna conexión, y
 * cuál sea esa conexión es justo lo que este mecanismo existe para decidir.
 *
 * Un trabajo que no implementa esta interfaz corre contra la conexión que haya
 * y no puede alcanzar la base de ningún inquilino — ver
 * `App\Jobs\Middleware\UsaConexionDeInquilino`.
 */
interface CorreParaInquilino
{
    /**
     * La base del inquilino, o null si el trabajo no es de ninguno.
     */
    public function baseDeInquilino(): ?string;
}
