<?php

namespace App\Models;

use App\Enums\OperationType;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\ZoneStatus;
use Database\Factories\PropertyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property OperationType $operation_type
 * @property PropertyType $property_type
 * @property PropertyStatus $status
 */
class Property extends Model implements HasMedia
{
    /** @use HasFactory<PropertyFactory> */
    use HasFactory, InteractsWithMedia, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'description',
        'operation_type',
        'property_type',
        'price',
        'bedrooms',
        'bathrooms',
        'parking_spaces',
        'land_area',
        'construction_area',
        'zone_id',
        'agent_id',
        'is_featured',
        'is_opportunity',
        'street',
        'exterior_number',
        'interior_number',
        'colonia',
        'postal_code',
        'owner_id',
        'commission_percentage',
        'meta_title',
        'meta_description',
        'canonical_url',
    ];

    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => PropertyStatus::Borrador->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'operation_type' => OperationType::class,
            'property_type' => PropertyType::class,
            'status' => PropertyStatus::class,
            'price' => 'decimal:2',
            'bathrooms' => 'decimal:1',
            'land_area' => 'decimal:2',
            'construction_area' => 'decimal:2',
            'commission_percentage' => 'decimal:2',
            'is_featured' => 'boolean',
            'is_opportunity' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // "Destacado" y "Oportunidad de inversión" son mutuamente excluyentes:
        // si quedaran ambos activos, gana el que se acaba de marcar.
        static::saving(function (Property $property): void {
            if ($property->is_featured && $property->is_opportunity) {
                if ($property->isDirty('is_opportunity')) {
                    $property->is_featured = false;
                } else {
                    $property->is_opportunity = false;
                }
            }
        });
    }

    /** @return BelongsTo<Zone, $this> */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /** @return BelongsTo<User, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    /** @return BelongsTo<PropertyOwner, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(PropertyOwner::class, 'owner_id');
    }

    /** @return BelongsToMany<Feature, $this> */
    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'property_feature')->withTimestamps();
    }

    /** @return HasMany<Lead, $this> */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /** @param Builder<Property> $query */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', PropertyStatus::Publicado->value);
    }

    /**
     * Inmuebles destacados en la Home: marcados a mano y vigentes. Como sólo se
     * muestran junto a scopePublished, los vendidos/rentados quedan fuera.
     *
     * @param  Builder<Property>  $query
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /**
     * Oportunidades de inversión en la Home: marcadas a mano y vigentes.
     *
     * @param  Builder<Property>  $query
     */
    public function scopeOpportunity(Builder $query): Builder
    {
        return $query->where('is_opportunity', true);
    }

    /** @param Builder<Property> $query */
    public function scopeByZone(Builder $query, int $zoneId): Builder
    {
        return $query->where('zone_id', $zoneId);
    }

    /** @param Builder<Property> $query */
    public function scopeByOperation(Builder $query, OperationType $operation): Builder
    {
        return $query->where('operation_type', $operation->value);
    }

    /** @param Builder<Property> $query */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasAnyRole(['owner', 'admin'])) {
            return $query;
        }

        return $query->where(function (Builder $properties) use ($user): void {
            $properties
                ->where('agent_id', $user->id)
                ->orWhere(function (Builder $unassigned) use ($user): void {
                    $unassigned
                        ->whereNull('agent_id')
                        ->whereIn('zone_id', $user->zones()->select('zones.id'));
                });
        });
    }

    public function isPublished(): bool
    {
        return $this->status === PropertyStatus::Publicado;
    }

    /**
     * Precio para mostrar al público: monto en MXN, con sufijo "/mes" en renta.
     */
    public function priceLabel(): string
    {
        $amount = '$'.number_format((float) $this->price, 0);

        return $this->operation_type === OperationType::Renta ? $amount.' /mes' : $amount;
    }

    /**
     * Superficie a destacar en la ficha (construcción si existe, si no terreno).
     */
    public function displayArea(): ?int
    {
        $area = $this->construction_area ?: $this->land_area;

        return $area ? (int) $area : null;
    }

    /**
     * La dirección precisa (calle y número) sólo es visible para el agente
     * asignado al inmueble, y para owner/admin. Otros agentes ven la colonia
     * pero no la calle ni el número.
     */
    public function preciseAddressVisibleTo(?User $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if ($user->hasAnyRole(['owner', 'admin'])) {
            return true;
        }

        return $this->agent_id !== null && $this->agent_id === $user->id;
    }

    public function seoTitle(): string
    {
        return $this->meta_title ?: $this->title;
    }

    public function seoDescription(): string
    {
        return $this->meta_description ?: Str::limit(strip_tags((string) $this->description), 160);
    }

    public function canonical(): string
    {
        return $this->canonical_url ?: url("/inmuebles/{$this->slug}");
    }

    public function hasCoverImage(): bool
    {
        return $this->hasMedia('cover');
    }

    /**
     * Mientras esto sea true, el candado que impide borrar la última foto
     * principal de un publicado se aparta.
     *
     * Existe por un desencuentro de ORDEN, no por una excepción a la regla: el
     * candado vive en el borrado de la media y cuenta los reemplazos mirando la
     * base, así que sólo tolera «primero agrego, después borro». El formulario
     * de Filament hace lo contrario. Quien sabe si hay un reemplazo en camino no
     * es el modelo —el archivo todavía no existe como media— sino la pantalla,
     * que lo tiene en la mano.
     *
     * Sólo lo levanta {@see EditProperty}, y sólo después de haber validado la
     * misma regla sobre lo que el agente eligió.
     */
    private static bool $coverGuardDeferred = false;

    public static function isCoverGuardDeferred(): bool
    {
        return self::$coverGuardDeferred;
    }

    /**
     * Corre `$callback` con ese candado apartado, y lo devuelve a su lugar pase
     * lo que pase — si una excepción lo dejara levantado, el resto del proceso
     * quedaría sin la protección.
     */
    public static function deferCoverGuard(callable $callback): mixed
    {
        self::$coverGuardDeferred = true;

        try {
            return $callback();
        } finally {
            self::$coverGuardDeferred = false;
        }
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);

        $this->addMediaCollection('gallery')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->nonQueued()
            ->fit(Fit::Crop, 400, 300);

        $this->addMediaConversion('web')
            ->nonQueued()
            ->fit(Fit::Max, 1280, 1024);
    }

    public function assertPublishedInvariant(): void
    {
        if (! $this->isPublished()) {
            return;
        }

        $zone = $this->zone()->first();

        if ($zone === null) {
            throw ValidationException::withMessages([
                'zone_id' => 'Un inmueble publicado requiere una zona vigente. Pausa primero.',
            ]);
        }

        if ($zone->status !== ZoneStatus::Active || $zone->polygonAsGeoJson() === null) {
            throw ValidationException::withMessages([
                'zone_id' => 'Un inmueble publicado requiere una zona activa con polígono. Pausa primero.',
            ]);
        }

        if (! $this->hasCoverImage()) {
            throw ValidationException::withMessages([
                'cover' => 'Un inmueble publicado no puede quedarse sin imagen principal. Pausa primero.',
            ]);
        }

        if ($this->owner_id === null) {
            throw ValidationException::withMessages([
                'owner_id' => 'Un inmueble publicado requiere un propietario. Pausa primero.',
            ]);
        }

        if (blank($this->street) || blank($this->colonia)) {
            throw ValidationException::withMessages([
                'street' => 'Un inmueble publicado requiere calle y colonia. Pausa primero.',
            ]);
        }

        if ($this->commission_percentage === null) {
            throw ValidationException::withMessages([
                'commission_percentage' => 'Un inmueble publicado requiere la comisión pactada. Pausa primero.',
            ]);
        }
    }
}
