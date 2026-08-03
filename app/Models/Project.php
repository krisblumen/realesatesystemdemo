<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Project extends Model implements HasMedia
{
    use InteractsWithMedia;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'slug',
        'project_type',
        'description',
        'is_featured',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Slug derivado del título; se regenera si el título cambia.
        static::saving(function (Project $project): void {
            if (blank($project->slug) || $project->isDirty('title')) {
                $project->slug = $project->generateUniqueSlug($project->title);
            }
        });
    }

    /** @return BelongsTo<ProjectType, $this> */
    public function projectType(): BelongsTo
    {
        return $this->belongsTo(ProjectType::class, 'project_type', 'code');
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

    private function generateUniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'proyecto';
        $slug = $base;
        $i = 2;

        while (
            static::query()
                ->where('slug', $slug)
                ->when($this->exists, fn ($q) => $q->whereKeyNot($this->getKey()))
                ->exists()
        ) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
