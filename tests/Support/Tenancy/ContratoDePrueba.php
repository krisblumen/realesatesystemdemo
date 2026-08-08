<?php

namespace Tests\Support\Tenancy;

use Illuminate\Database\Eloquent\Model;

/**
 * Un modelo cuya tabla existe SÓLO en la base del inquilino.
 *
 * Ese es todo el punto: si el trabajo se deserializa contra otra base, la
 * consulta de rehidratación falla con «relation does not exist» — que es
 * literalmente el error que apareció en producción con
 * `contratos_intermediacion`.
 *
 * Se usa un modelo de prueba y no el real para no atar el test a las 30 columnas
 * de un contrato: lo que se prueba es el mecanismo de la cola, no el contrato.
 */
class ContratoDePrueba extends Model
{
    protected $table = 'probe_contratos';

    public $timestamps = false;

    protected $guarded = [];
}
