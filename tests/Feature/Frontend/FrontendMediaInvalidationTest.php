<?php

namespace Tests\Feature\Frontend;

use App\Models\FrontendPage;
use App\Models\FrontendService;
use App\Models\FrontendSetting;
use App\Models\Property;
use App\Models\ServiceType;
use App\Services\Frontend\FrontendCacheGeneration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * M-2 (audit of batch A): §16.8 lists Media among the mutations that MUST bump
 * the cache generation — adding or removing a brand image changes what the
 * public site renders. Without it the site can serve a stale brand until the
 * TTL expires.
 *
 * The bump is deferred to DB::afterCommit: a rolled back transaction must not
 * move the generation, otherwise readers jump to a key whose data never landed.
 */
class FrontendMediaInvalidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_adding_brand_media_bumps_the_generation(): void
    {
        $setting = FrontendSetting::current();
        $before = app(FrontendCacheGeneration::class)->current();

        $setting->addMedia(UploadedFile::fake()->image('logo.png'))
            ->toMediaCollection('logo-light');

        $this->assertGreaterThan(
            $before,
            app(FrontendCacheGeneration::class)->current(),
            'Adding brand media must invalidate the frontend cache.'
        );
    }

    public function test_deleting_brand_media_bumps_the_generation(): void
    {
        $setting = FrontendSetting::current();
        $media = $setting->addMedia(UploadedFile::fake()->image('logo.png'))
            ->toMediaCollection('logo-light');

        $before = app(FrontendCacheGeneration::class)->current();
        $media->delete();

        $this->assertGreaterThan(
            $before,
            app(FrontendCacheGeneration::class)->current(),
            'Removing brand media must invalidate the frontend cache.'
        );
    }

    public function test_a_rolled_back_transaction_does_not_bump(): void
    {
        $setting = FrontendSetting::current();
        $before = app(FrontendCacheGeneration::class)->current();

        // Rolling back must discard the afterCommit callback: a generation
        // pointing at data that never landed is worse than a stale read.
        try {
            DB::transaction(function () use ($setting): void {
                $setting->addMedia(UploadedFile::fake()->image('logo.png'))
                    ->toMediaCollection('logo-light');

                throw new \RuntimeException('forced rollback');
            });
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertSame(
            $before,
            app(FrontendCacheGeneration::class)->current(),
            'A rolled back media write must not bump the generation.'
        );
    }

    public function test_adding_service_media_bumps_the_generation(): void
    {
        // A service image feeds the public render exactly like a brand image;
        // a media op on it must invalidate through the observer, not only
        // through the Filament screen that happens to bump explicitly.
        $type = ServiceType::query()->firstOr(fn () => ServiceType::query()->create(['code' => 'svc_media', 'label' => 'Servicio', 'active' => true]));
        $service = FrontendService::query()->firstOr(fn () => FrontendService::query()->create([
            'service_type_code' => $type->code, 'title' => 'Servicio',
            'show_in_home' => true, 'show_in_services' => true, 'allow_leads' => true, 'sort_order' => 1,
        ]));
        $before = app(FrontendCacheGeneration::class)->current();

        $service->addMedia(UploadedFile::fake()->image('service.png'))->toMediaCollection('image');

        $this->assertGreaterThan(
            $before,
            app(FrontendCacheGeneration::class)->current(),
            'Adding service media must invalidate the frontend cache.'
        );
    }

    public function test_adding_and_removing_section_media_bumps_the_generation(): void
    {
        // Section images are the twin path the first version of the observer
        // missed: a section media op must invalidate like every other.
        $section = FrontendPage::query()->where('key', 'home')->firstOrFail()
            ->sections()->where('section_key', 'hero')->firstOrFail();

        $beforeAdd = app(FrontendCacheGeneration::class)->current();
        $media = $section->addMedia(UploadedFile::fake()->image('hero.png'))->toMediaCollection('images');
        $afterAdd = app(FrontendCacheGeneration::class)->current();
        $this->assertGreaterThan($beforeAdd, $afterAdd, 'Adding section media must invalidate the frontend cache.');

        $media->delete();
        $this->assertGreaterThan($afterAdd, app(FrontendCacheGeneration::class)->current(), 'Removing section media must invalidate the frontend cache.');
    }

    public function test_media_of_unrelated_models_does_not_bump_the_frontend_generation(): void
    {
        // A property cover has nothing to do with the public frontend kernel:
        // bumping on it would invalidate the whole site on every listing edit.
        $property = Property::factory()->create();
        $before = app(FrontendCacheGeneration::class)->current();

        $property->addMedia(UploadedFile::fake()->image('cover.png'))
            ->toMediaCollection('cover');

        $this->assertSame(
            $before,
            app(FrontendCacheGeneration::class)->current(),
            'Only media owned by frontend entities may bump the generation.'
        );
    }
}
