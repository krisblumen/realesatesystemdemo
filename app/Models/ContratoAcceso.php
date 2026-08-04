<?php

namespace App\Models;

use App\Enums\OrigenAccesoContrato;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Token de acceso al formulario público de un contrato (RFC-064). El folio identifica
 * al contrato de forma permanente; el token es lo que da acceso y lo que se invalida o
 * renueva. En BD solo vive el hash SHA-256 del token, nunca el valor en claro.
 *
 * @property OrigenAccesoContrato $emitido_por
 * @property Carbon $expira_at
 * @property Carbon|null $usado_at
 */
class ContratoAcceso extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'contrato_intermediacion_id',
        'token_hash',
        'expira_at',
        'usado_at',
        'emitido_por',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expira_at' => 'datetime',
            'usado_at' => 'datetime',
            'emitido_por' => OrigenAccesoContrato::class,
        ];
    }

    /** @return BelongsTo<ContratoIntermediacion, $this> */
    public function contrato(): BelongsTo
    {
        return $this->belongsTo(ContratoIntermediacion::class, 'contrato_intermediacion_id');
    }

    public function estaVigente(): bool
    {
        return $this->usado_at === null && $this->expira_at->isFuture();
    }
}
