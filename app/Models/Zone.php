<?php

namespace App\Models;

use App\Enums\ZoneStatus;
use App\Services\Zones\ZoneGeometry;
use Clickbar\Magellan\Data\Geometries\MultiPolygon;
use Database\Factories\ZoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * @property ZoneStatus $status
 */
#[Fillable([
    'name',
    'slug',
    'description',
    'state_id',
    'municipality_id',
    'postal_code',
    'status',
    'polygon',
    'center_point',
])]
class Zone extends Model
{
    /** @use HasFactory<ZoneFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => ZoneStatus::Active->value,
    ];

    protected static function booted(): void
    {
        static::saving(function (Zone $zone): void {
            $baseSlug = Str::slug((string) ($zone->slug ?: $zone->name));

            $zone->slug = static::uniqueSlug($baseSlug, $zone);

            if ($zone->isDirty('polygon') && $zone->polygon !== null) {
                $zone->polygon = ZoneGeometry::validatePolygonEwkt((string) $zone->polygon);
            }
        });

        static::saved(function (Zone $zone): void {
            if (! $zone->wasRecentlyCreated && ! $zone->wasChanged('polygon')) {
                return;
            }

            DB::table($zone->getTable())
                ->where($zone->getKeyName(), $zone->getKey())
                ->update([
                    'center_point' => $zone->polygon === null
                        ? null
                        : DB::raw('ST_Centroid(polygon)'),
                ]);

            $zone->refresh();
        });
    }

    public function isActive(): bool
    {
        return $this->status === ZoneStatus::Active;
    }

    public function isInactive(): bool
    {
        return $this->status === ZoneStatus::Inactive;
    }

    /**
     * @param  array<string, mixed>|string  $geoJson
     */
    public function setPolygonFromGeoJson(array|string $geoJson): self
    {
        $this->polygon = ZoneGeometry::polygonEwktFromGeoJson($geoJson);

        return $this;
    }

    public function setPolygonFromWkt(string $wkt): self
    {
        $this->polygon = ZoneGeometry::polygonEwktFromWkt($wkt);

        return $this;
    }

    public function polygonAsGeoJson(): ?string
    {
        if (! $this->exists || $this->polygon === null) {
            return null;
        }

        return DB::table($this->getTable())
            ->where($this->getKeyName(), $this->getKey())
            ->selectRaw('ST_AsGeoJSON(polygon) as geojson')
            ->value('geojson');
    }

    /**
     * @param  Builder<Zone>  $query
     * @return Builder<Zone>
     */
    public function scopeContainingPoint(Builder $query, float $longitude, float $latitude): Builder
    {
        return $query
            ->whereRaw(
                'polygon && ST_SetSRID(ST_MakePoint(?, ?), 4326)',
                [$longitude, $latitude],
            )
            ->whereRaw(
                'ST_Contains(polygon, ST_SetSRID(ST_MakePoint(?, ?), 4326))',
                [$longitude, $latitude],
            );
    }

    /**
     * Prepared scope for Epic 4 when properties exposes a PostGIS point column.
     *
     * @param  Builder<Zone>  $query
     * @return Builder<Zone>
     */
    public function scopeContainingPropertyPoint(
        Builder $query,
        string $pointColumn = 'properties.location',
    ): Builder {
        $pointColumn = $this->safeQualifiedIdentifier($pointColumn);

        return $query
            ->whereRaw("polygon && {$pointColumn}")
            ->whereRaw("ST_Contains(polygon, {$pointColumn})");
    }

    /**
     * State assigned to this zone.
     *
     * @return BelongsTo<State, $this>
     */
    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    /**
     * Municipality assigned to this zone.
     *
     * @return BelongsTo<Municipality, $this>
     */
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    /**
     * Deferred contract for Epic 3 Lote D agent assignment.
     *
     * @return BelongsToMany<User, $this>
     */
    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'agent_zone', 'zone_id', 'agent_id')
            ->withTimestamps();
    }

    /**
     * Properties located in this zone.
     *
     * @return HasMany<Property, $this>
     */
    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    /**
     * Códigos postales que componen esta zona (catálogo con geometría).
     *
     * @return BelongsToMany<PostalCodeArea, $this>
     */
    public function postalCodeAreas(): BelongsToMany
    {
        return $this->belongsToMany(
            PostalCodeArea::class,
            'zone_postal_code',
            'zone_id',
            'postal_code',
            'id',
            'postal_code',
        )->withTimestamps();
    }

    /**
     * Lista plana de los CP de la zona.
     *
     * @return list<string>
     */
    public function postalCodeList(): array
    {
        return DB::table('zone_postal_code')
            ->where('zone_id', $this->getKey())
            ->orderBy('postal_code')
            ->pluck('postal_code')
            ->all();
    }

    /**
     * Impide borrar una zona con inmuebles o agentes asignados: si la FK se
     * limpiara igual (soft delete no dispara nullOnDelete/cascadeOnDelete),
     * las rutas quedarían apuntando a una zona invisible.
     */
    public function assertDeletable(): void
    {
        if ($this->properties()->exists()) {
            throw ValidationException::withMessages([
                'delete' => 'No se puede eliminar una zona con inmuebles asignados.',
            ]);
        }

        if ($this->agents()->exists()) {
            throw ValidationException::withMessages([
                'delete' => 'No se puede eliminar una zona con agentes asignados.',
            ]);
        }
    }

    /**
     * Reemplaza los CP de la zona en el pivote.
     *
     * @param  list<string>  $codes
     */
    public function syncPostalCodes(array $codes): void
    {
        $codes = collect($codes)
            ->map(fn (mixed $code): string => trim((string) $code))
            ->filter()
            ->unique()
            ->values();

        $removedCodes = collect($this->postalCodeList())->diff($codes);

        if ($removedCodes->isNotEmpty() && $this->properties()->whereIn('postal_code', $removedCodes)->exists()) {
            throw ValidationException::withMessages([
                'postal_codes' => 'No se pueden quitar códigos postales con inmuebles asignados en esta zona.',
            ]);
        }

        DB::table('zone_postal_code')->where('zone_id', $this->getKey())->delete();

        if ($codes->isNotEmpty()) {
            DB::table('zone_postal_code')->insert(
                $codes->map(fn (string $code): array => [
                    'zone_id' => $this->getKey(),
                    'postal_code' => $code,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all(),
            );
        }
    }

    /**
     * Deferred Epic 5 contract for zone-based lead routing.
     *
     * @return HasMany<Lead, $this>
     */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ZoneStatus::class,
            'polygon' => MultiPolygon::class,
        ];
    }

    private static function uniqueSlug(string $baseSlug, Zone $zone): string
    {
        $baseSlug = $baseSlug !== '' ? $baseSlug : Str::random(8);
        $slug = $baseSlug;
        $suffix = 2;

        while (static::withTrashed()
            ->where('slug', $slug)
            ->when($zone->exists, fn ($query) => $query->whereKeyNot($zone->getKey()))
            ->exists()
        ) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function safeQualifiedIdentifier(string $identifier): string
    {
        $parts = explode('.', $identifier);

        if ($parts === [] || count($parts) > 2) {
            throw new \InvalidArgumentException('Invalid PostGIS point column identifier.');
        }

        foreach ($parts as $part) {
            if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $part)) {
                throw new \InvalidArgumentException('Invalid PostGIS point column identifier.');
            }
        }

        return DB::connection()->getQueryGrammar()->wrap($identifier);
    }
}
