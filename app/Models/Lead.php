<?php

namespace App\Models;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property LeadSource $source
 * @property LeadStatus $status
 * @property Carbon|null $assigned_at
 */
class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'message',
        'service_type',
        'source',
        'status',
        'property_id',
        'agent_id',
        'zone_id',
        'assigned_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'source' => LeadSource::Web->value,
        'status' => LeadStatus::Nuevo->value,
        'service_type' => 'comercializacion',
    ];

    protected static function booted(): void
    {
        // La zona comercial del lead se hereda del inmueble cuando no se indica
        // explícitamente. Es un snapshot: queda fija aunque el inmueble cambie o
        // se elimine, y permite cobertura/reasignación por zona también para
        // leads cuyo inmueble desaparezca.
        static::creating(function (Lead $lead): void {
            if ($lead->service_type !== 'comercializacion') {
                $lead->property_id = null;
                $lead->zone_id = null;

                return;
            }

            if ($lead->zone_id === null && $lead->property_id !== null) {
                $lead->zone_id = Property::query()
                    ->whereKey($lead->property_id)
                    ->value('zone_id');
            }
        });
    }

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /** @return BelongsTo<ServiceType, $this> */
    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class, 'service_type', 'code');
    }

    /** @return BelongsTo<User, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    /**
     * Deferred Epic 3 contract: leads may be tied to a commercial zone when zone-based lead routing is implemented.
     *
     * @return BelongsTo<Zone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * Historial de seguimiento del lead. Más reciente primero.
     *
     * @return HasMany<LeadNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(LeadNote::class)->latest();
    }

    /** @param Builder<Lead> $query */
    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->whereNull('agent_id');
    }

    /**
     * Leads aún en gestión (no cerrados).
     *
     * @param  Builder<Lead>  $query
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            LeadStatus::CerradoGanado->value,
            LeadStatus::CerradoPerdido->value,
        ]);
    }

    /** @param Builder<Lead> $query */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasAnyRole(['owner', 'admin'])) {
            return $query;
        }

        return $query->where('agent_id', $user->id);
    }

    /** @param Builder<Lead> $query */
    public function scopeByAgent(Builder $query, User|int $agent): Builder
    {
        return $query->where('agent_id', $agent instanceof User ? $agent->id : $agent);
    }

    /** @param Builder<Lead> $query */
    public function scopeByStatus(Builder $query, LeadStatus|string $status): Builder
    {
        return $query->where('status', $status instanceof LeadStatus ? $status->value : $status);
    }

    public function isNew(): bool
    {
        return $this->status === LeadStatus::Nuevo;
    }

    public function isAssigned(): bool
    {
        return $this->agent_id !== null;
    }

    public function isClosed(): bool
    {
        return $this->status->isClosed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => LeadSource::class,
            'status' => LeadStatus::class,
            'assigned_at' => 'datetime',
        ];
    }
}
