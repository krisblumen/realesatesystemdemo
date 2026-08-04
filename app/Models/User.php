<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Notifications\Auth\ResetPassword as FilamentResetPasswordNotification;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property UserStatus $status
 */
#[Fillable([
    'name',
    'email',
    'password',
    'phone',
    'whatsapp',
    'avatar',
    'status',
    'last_login_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => UserStatus::Active->value,
    ];

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function isSuspended(): bool
    {
        return $this->status === UserStatus::Suspended;
    }

    public function isPending(): bool
    {
        return $this->status === UserStatus::Pending;
    }

    public function hasNewhauzMailbox(): bool
    {
        return str_ends_with(strtolower($this->email), '@'.strtolower((string) config('mail_indicator.domain')));
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isActive() && $this->hasAnyRole(['owner', 'admin', 'agente', 'arquitectura', 'proyectos']);
    }

    /**
     * Usa el generador de URLs de Filament (en vez del route "password.reset"
     * de un starter kit que este proyecto no tiene) para que el link del mail
     * de restablecimiento/activacion apunte a la pagina real del panel.
     */
    public function sendPasswordResetNotification($token): void
    {
        $url = Filament::getResetPasswordUrl($token, $this);

        $this->notify(new FilamentResetPasswordNotification($url));
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', UserStatus::Active->value);
    }

    /**
     * @return HasMany<UserStatusLog, $this>
     */
    public function statusLogs(): HasMany
    {
        return $this->hasMany(UserStatusLog::class);
    }

    /**
     * Properties managed by this user as the responsible agent.
     *
     * @return HasMany<Property, $this>
     */
    public function properties(): HasMany
    {
        return $this->hasMany(Property::class, 'agent_id');
    }

    /**
     * Commercial zones assigned to this agent.
     *
     * @return BelongsToMany<Zone, $this>
     */
    public function zones(): BelongsToMany
    {
        return $this->belongsToMany(Zone::class, 'agent_zone', 'agent_id', 'zone_id')
            ->withTimestamps();
    }

    /**
     * Leads assigned to this user as the responsible agent.
     *
     * @return HasMany<Lead, $this>
     */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'agent_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'mail_unseen_count' => 'integer',
            'mail_unseen_synced_at' => 'datetime',
        ];
    }
}
