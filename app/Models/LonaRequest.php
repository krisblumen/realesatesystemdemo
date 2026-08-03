<?php

namespace App\Models;

use App\Enums\LonaRequestStatus;
use App\Enums\OperationType;
use Database\Factories\LonaRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property OperationType $operation_type
 * @property LonaRequestStatus $estado
 * @property Carbon|null $reviewed_at
 */
class LonaRequest extends Model
{
    /** @use HasFactory<LonaRequestFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'agent_id',
        'operation_type',
        'cantidad_solicitada',
        'estado',
        'property_id',
        'reviewed_by',
        'reviewed_at',
        'motivo_rechazo',
    ];

    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'estado' => LonaRequestStatus::Pendiente->value,
    ];

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

    /** @return BelongsTo<User, $this> */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return HasOne<LonaBatch, $this> */
    public function batch(): HasOne
    {
        return $this->hasOne(LonaBatch::class);
    }

    public function isPending(): bool
    {
        return $this->estado === LonaRequestStatus::Pendiente;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'operation_type' => OperationType::class,
            'estado' => LonaRequestStatus::class,
            'cantidad_solicitada' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }
}
