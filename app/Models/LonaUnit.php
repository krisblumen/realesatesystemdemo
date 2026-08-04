<?php

namespace App\Models;

use App\Enums\LonaUnitStatus;
use App\Enums\OperationType;
use Database\Factories\LonaUnitFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property OperationType $operation_type
 * @property LonaUnitStatus $status
 * @property Carbon|null $placed_at
 */
class LonaUnit extends Model implements HasMedia
{
    /** @use HasFactory<LonaUnitFactory> */
    use HasFactory, InteractsWithMedia, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'lona_batch_id',
        'agent_id',
        'operation_type',
        'status',
        'property_id',
        'ubicacion_referencia',
        'placed_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => LonaUnitStatus::PendienteColocacion->value,
    ];

    /** @return BelongsTo<LonaBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(LonaBatch::class, 'lona_batch_id');
    }

    /** @return BelongsTo<User, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Unidades aún sin colocar.
     *
     * @param  Builder<LonaUnit>  $query
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', LonaUnitStatus::PendienteColocacion->value);
    }

    public function isPlaced(): bool
    {
        return $this->status === LonaUnitStatus::Colocada;
    }

    public function registerMediaCollections(): void
    {
        // Evidencia fotográfica de colocación (foto capturada en vivo, no galería).
        $this->addMediaCollection('evidencia')->singleFile();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'operation_type' => OperationType::class,
            'status' => LonaUnitStatus::class,
            'placed_at' => 'datetime',
        ];
    }
}
