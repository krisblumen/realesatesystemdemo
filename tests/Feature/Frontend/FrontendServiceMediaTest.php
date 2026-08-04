<?php

namespace Tests\Feature\Frontend;

use App\Models\FrontendService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

/**
 * Non-destructive media (§18.13 / C-D1): replacing a service image must NEVER
 * physically delete the previous file. `singleFile()`/`onlyKeepLatest()` would
 * call clearMediaCollectionExcept and drop it — v1 forbids that. The current
 * image is the one image_media_id points at; superseded files just linger.
 */
class FrontendServiceMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_image_collection_never_keeps_only_the_latest(): void
    {
        $collection = FrontendService::query()->firstOrFail()->getRegisteredMediaCollections()
            ->firstWhere('name', 'image');

        $this->assertNotNull($collection);
        $this->assertFalse($collection->singleFile, 'The image collection must not be singleFile (C-D1).');
    }

    public function test_replacing_the_image_keeps_the_previous_media_and_file(): void
    {
        Storage::fake('public');

        $service = FrontendService::query()->firstOrFail();

        $first = $service->addMedia(UploadedFile::fake()->image('first.png'))->toMediaCollection('image');
        $second = $service->addMedia(UploadedFile::fake()->image('second.png'))->toMediaCollection('image');

        // Both media rows and both files survive — nothing was purged.
        $this->assertSame(2, $service->fresh()->getMedia('image')->count());
        $this->assertNotNull(Media::query()->find($first->id));
        $this->assertNotNull(Media::query()->find($second->id));
        $this->assertTrue(Storage::disk($first->disk)->exists($first->getPathRelativeToRoot()));
        $this->assertTrue(Storage::disk($second->disk)->exists($second->getPathRelativeToRoot()));
    }
}
