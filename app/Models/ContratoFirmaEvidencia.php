<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Evidencia de la firma electrónica simple (RFC-067): IP, user-agent, hora de servidor y
 * hash del trazo. Es el respaldo probatorio reforzado del consentimiento del cliente.
 *
 * @property Carbon $firmado_at
 */
class ContratoFirmaEvidencia extends Model
{
    protected $table = 'contrato_firma_evidencias';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'contrato_intermediacion_id',
        'ip',
        'user_agent',
        'firmado_at',
        'firma_hash',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'firmado_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ContratoIntermediacion, $this> */
    public function contrato(): BelongsTo
    {
        return $this->belongsTo(ContratoIntermediacion::class, 'contrato_intermediacion_id');
    }
}
