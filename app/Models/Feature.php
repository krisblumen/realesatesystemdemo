<?php

namespace App\Models;

use Database\Factories\FeatureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Feature extends Model
{
    /** @use HasFactory<FeatureFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['name', 'slug', 'icon'];

    protected static function booted(): void
    {
        static::saving(function (Feature $feature): void {
            if (blank($feature->slug)) {
                $feature->slug = Str::slug((string) $feature->name);
            }
        });
    }

    /** @return BelongsToMany<Property, $this> */
    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'property_feature')->withTimestamps();
    }
}
