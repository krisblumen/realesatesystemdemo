<?php

namespace App\Models;

use Database\Factories\PropertyOwnerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PropertyOwner extends Model
{
    /** @use HasFactory<PropertyOwnerFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'email',
        'agent_id',
    ];

    /**
     * The agent (user) that owns this client.
     *
     * @return BelongsTo<User, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    /**
     * Properties listed under this owner.
     *
     * @return HasMany<Property, $this>
     */
    public function properties(): HasMany
    {
        return $this->hasMany(Property::class, 'owner_id');
    }

    /**
     * Full display name.
     */
    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * Restrict a query to the records the given user is allowed to see:
     * owner/admin see everyone, an agent only sees their own clients.
     *
     * @param  Builder<PropertyOwner>  $query
     * @return Builder<PropertyOwner>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasAnyRole(['owner', 'admin'])) {
            return $query;
        }

        return $query->where('agent_id', $user->id);
    }

    /**
     * El cliente ya registrado que coincide por TELÉFONO o por EMAIL, mirando
     * los de todos los agentes.
     *
     * Antes exigía nombre + apellido + teléfono, los tres a la vez, y por eso
     * se le escapaban los duplicados que importan: el mismo cliente cargado como
     * «Juan Carlos» y «Juan C.» pasaba como dos personas distintas aunque el
     * teléfono fuera idéntico. El nombre es justamente el dato que cada agente
     * escribe a su manera, así que no sirve para identificar.
     *
     * Teléfono O email, no los dos: pedir que coincidan ambos volvería a dejar
     * pasar al mismo cliente cargado con un solo dato de contacto, que es el
     * caso más común.
     *
     * El teléfono se compara por sus DÍGITOS y el email en minúsculas: son el
     * mismo cliente con «(442) 119-0959» o «4421190959», y con la casilla
     * escrita en mayúsculas o no.
     */
    public static function findDuplicate(?string $phone, ?string $email = null, ?int $ignoreId = null): ?self
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        $mail = mb_strtolower(trim((string) $email));

        if ($digits === '' && $mail === '') {
            return null;
        }

        return static::query()
            ->when($ignoreId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->where(function (Builder $query) use ($digits, $mail): void {
                if ($digits !== '') {
                    $query->orWhereRaw("regexp_replace(phone, '\\D', '', 'g') = ?", [$digits]);
                }

                if ($mail !== '') {
                    $query->orWhereRaw('lower(email) = ?', [$mail]);
                }
            })
            ->first();
    }
}
