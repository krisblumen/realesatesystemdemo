<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Un enlace para mostrar el sitio del inquilino sin pedir sesión.
 *
 * Del token sólo vive acá su SHA-256. El claro se ve una vez, cuando se genera.
 */
class EnlaceDeMuestra extends Model
{
    protected $table = 'enlaces_de_muestra';

    protected $fillable = ['token_hash', 'expira_en', 'revocado_en'];

    protected function casts(): array
    {
        return [
            'expira_en' => 'datetime',
            'revocado_en' => 'datetime',
        ];
    }

    /**
     * Los que todavía sirven: ni revocados ni vencidos.
     *
     * Las dos condiciones y no una: revocar es una decisión de quien lo generó,
     * vencer es el reloj. Un enlace puede estar en cualquiera de los dos estados
     * sin estar en el otro, y filtrar por uno solo dejaría vivo al otro.
     */
    public function scopeVigente(Builder $query): Builder
    {
        return $query->whereNull('revocado_en')->where('expira_en', '>', now());
    }
}
