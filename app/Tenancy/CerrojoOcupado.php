<?php

namespace App\Tenancy;

use RuntimeException;

/**
 * El cerrojo que serializa la copia de la plantilla estaba tomado y no se
 * soltó dentro de los intentos previstos.
 *
 * Existe como excepción propia para que el alta falle CON UN MENSAJE. Con
 * `pg_advisory_lock` a secas, una alta bloqueada no da error: espera. Y una
 * espera sin causa se ve como lentitud, que es lo último que alguien mira
 * cuando algo anda mal.
 */
class CerrojoOcupado extends RuntimeException {}
