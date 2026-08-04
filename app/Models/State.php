<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'country_id',
    'name',
    'clave',
    'source_id',
    'inegi_code',
])]
class State extends Model
{
    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return HasMany<Municipality, $this>
     */
    public function municipalities(): HasMany
    {
        return $this->hasMany(Municipality::class);
    }
}
