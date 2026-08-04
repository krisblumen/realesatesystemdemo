<?php

namespace App\Support;

use App\Models\Property;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Throwable;

class PropertySlugGenerator
{
    public function persist(Property $property): void
    {
        retry(3, function () use ($property): void {
            $property->slug = $this->generate(
                $property,
                $property->exists ? (int) $property->getKey() : null,
            );
            $property->save();
        }, 0, fn (Throwable $exception): bool => $exception instanceof UniqueConstraintViolationException);
    }

    public function generate(Property $property, ?int $ignoreId = null): string
    {
        $base = Str::slug(implode(' ', array_filter([
            $property->zone?->slug,
            $property->property_type->value,
            $property->title,
        ]))) ?: 'inmueble';

        $slug = $base;
        $suffix = 2;

        while ($this->exists($slug, $ignoreId)) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function exists(string $slug, ?int $ignoreId): bool
    {
        return Property::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId, fn (Builder $query): Builder => $query->whereKeyNot($ignoreId))
            ->exists();
    }
}
