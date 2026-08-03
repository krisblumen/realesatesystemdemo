<?php

namespace App\Models;

use App\Enums\OperationType;
use Database\Factories\LonaBatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property OperationType $operation_type
 */
class LonaBatch extends Model implements HasMedia
{
    /** @use HasFactory<LonaBatchFactory> */
    use HasFactory, InteractsWithMedia, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'agent_id',
        'lona_request_id',
        'operation_type',
        'cantidad',
        'created_by',
    ];

    /** @return BelongsTo<User, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    /** @return BelongsTo<LonaRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(LonaRequest::class, 'lona_request_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<LonaUnit, $this> */
    public function units(): HasMany
    {
        return $this->hasMany(LonaUnit::class);
    }

    public function registerMediaCollections(): void
    {
        // PDF de diseño armado automáticamente al aprobar el lote.
        $this->addMediaCollection('diseno-pdf')->singleFile();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'operation_type' => OperationType::class,
            'cantidad' => 'integer',
        ];
    }
}
