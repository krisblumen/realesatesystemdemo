<?php

namespace App\Observers;

use App\Models\Property;
use App\Support\PropertySlugGenerator;

class PropertyObserver
{
    public function __construct(private readonly PropertySlugGenerator $slugs) {}

    public function creating(Property $property): void
    {
        if (blank($property->slug)) {
            $property->slug = $this->slugs->generate($property);
        }
    }

    public function saving(Property $property): void
    {
        $property->assertPublishedInvariant();
    }
}
