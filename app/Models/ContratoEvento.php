<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Evento de auditoría de un contrato de intermediación (RFC-057). Tabla per-entidad,
 * siguiendo el patrón real del repo (lead_assignment_logs / user_status_logs) — no hay
 * paquete de auditoría global. Decisión D-5 del diseño de la Épica 10.
 *
 * @property array<string, mixed> $meta
 */
class ContratoEvento extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'contrato_intermediacion_id',
        'tipo',
        'actor_id',
        'ip',
        'user_agent',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    /** @return BelongsTo<ContratoIntermediacion, $this> */
    public function contrato(): BelongsTo
    {
        return $this->belongsTo(ContratoIntermediacion::class, 'contrato_intermediacion_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
