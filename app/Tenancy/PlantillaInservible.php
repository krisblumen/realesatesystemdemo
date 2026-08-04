<?php

namespace App\Tenancy;

use RuntimeException;

/**
 * La plantilla vigente produjo un inquilino que no sirve para nada.
 *
 * Excepción propia y no un `RuntimeException` genérico porque el mensaje tiene
 * que llevar a la plantilla y no al alta: el alta hizo su trabajo, lo que está
 * mal es lo que copió. Sin esto, el síntoma —un panel vacío— aparece días
 * después y en manos de la persona invitada.
 */
class PlantillaInservible extends RuntimeException {}
