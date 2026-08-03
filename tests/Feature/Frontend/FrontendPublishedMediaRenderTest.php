<?php

namespace Tests\Feature\Frontend;

use App\Models\FrontendPage;
use App\Models\FrontendSection;
use App\Models\User;
use App\Services\Frontend\FrontendCacheGeneration;
use App\Services\Frontend\FrontendPagePublisher;
use App\Services\Frontend\FrontendPageRenderer;
use App\Services\Frontend\PublishedMediaReference;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

/**
 * Épica 12.1, Lote A — TA-10, TA-11: what the PUBLIC render is allowed to show.
 *
 * Two real defects were found here during the design gate, both pre-existing and
 * both invisible to the suite because nothing asserted that a published image
 * actually renders:
 *
 *  - soft-deleting a section made the published page LOSE its image, because the
 *    owner lookup went through a relation with the SoftDeletingScope;
 *  - and `withTrashed()` alone was not enough: the unique index is partial, so a
 *    recreated `section_key` shadowed the old owner and bound the snapshot to the
 *    wrong section.
 *
 * The fix is identity: a published media resolves through its OWN row
 * (`Media.model_id`), never through `section_key`.
 */
class FrontendPublishedMediaRenderTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('frontend-private');
        $this->seed(PermissionSeeder::class);
        $this->owner = User::factory()->withRole('owner')->create();
    }

    private function page(string $key = 'home'): FrontendPage
    {
        return FrontendPage::query()->where('key', $key)->firstOrFail();
    }

    private function section(string $sectionKey, string $pageKey): FrontendSection
    {
        return $this->page($pageKey)->sections()->where('section_key', $sectionKey)->firstOrFail();
    }

    private function publish(string $pageKey = 'home'): void
    {
        $page = $this->page($pageKey)->fresh();
        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, $this->owner);
        app(FrontendCacheGeneration::class)->bump();
    }

    /** The hero slide url the public render would emit, or null. */
    private function renderedSlideUrl(string $pageKey = 'home'): ?string
    {
        $rendered = app(FrontendPageRenderer::class)->render($pageKey);
        $hero = collect($rendered['sections'])->firstWhere('key', 'hero');

        return $hero['data']['slides'][0]['media_url'] ?? null;
    }

    private function heroWithSlide(string $uuid, string $pageKey = 'home'): void
    {
        $this->section('hero', $pageKey)->update(['payload' => [
            'title' => 'Hero de prueba',
            'slides' => [['media_id' => $uuid, 'alt' => null, 'decorative' => true, 'sort_order' => 0]],
        ]]);
    }

    private function attachSlide(FrontendSection $section): Media
    {
        return $section->addMedia(UploadedFile::fake()->image('slide.png', 1200, 675))
            ->toMediaCollection('images');
    }

    // -------------------------------------------------- render only promoted --

    public function test_the_public_render_emits_a_promoted_image(): void
    {
        $hero = $this->section('hero', 'home');
        $media = $this->attachSlide($hero);

        $this->heroWithSlide((string) $media->uuid);
        $this->publish();

        $url = $this->renderedSlideUrl();

        $this->assertNotNull($url, 'A promoted slide must render.');
        $this->assertStringNotContainsString('frontend-private', $url);
        $this->assertSame('public', $media->refresh()->disk);
    }

    public function test_media_that_is_not_promoted_yet_is_omitted_not_placeheld(): void
    {
        Queue::fake(); // the promotion never runs

        $hero = $this->section('hero', 'home');
        $media = $this->attachSlide($hero);

        $this->heroWithSlide((string) $media->uuid);
        $this->publish();

        // Not promoted ⇒ the slide is dropped. Never a private URL, never a
        // placeholder, never a "previous version" (§7.8 / C-10).
        $this->assertNull($this->renderedSlideUrl());
        $this->assertSame('frontend-private', $media->refresh()->disk);
    }

    // ------------------------------------------------------------ TA-10 -----

    public function test_soft_deleting_a_section_does_not_break_its_published_image(): void
    {
        $hero = $this->section('hero', 'home');
        $media = $this->attachSlide($hero);

        $this->heroWithSlide((string) $media->uuid);
        $this->publish();

        $this->assertNotNull($this->renderedSlideUrl());

        // Deleting a section removes it from the WORKING state, not from the
        // published revision: the live page must keep rendering its snapshot.
        $hero->delete();
        app(FrontendCacheGeneration::class)->bump();

        $this->assertNotNull(
            $this->renderedSlideUrl(),
            'A soft-deleted section must not take the published image down with it.'
        );
    }

    public function test_the_fix_covers_every_media_bearing_type_not_only_the_hero(): void
    {
        // `team` on nosotros carries media too. The repair lives in the shared
        // renderer, so it must hold for any type, not just the one this
        // increment is about.
        $hero = $this->section('hero', 'nosotros');
        $team = $this->section('team', 'nosotros');

        $hero->update(['payload' => ['title' => 'Nosotros']]);
        $portrait = $this->attachSlide($team);
        $team->update(['payload' => [
            'title' => 'Equipo',
            'members' => [['name' => 'Ana', 'role' => 'Dirección', 'media_id' => (string) $portrait->uuid, 'alt' => 'Retrato de Ana']],
        ]]);

        $this->publish('nosotros');

        $teamUrl = fn (): ?string => collect(app(FrontendPageRenderer::class)->render('nosotros')['sections'])
            ->firstWhere('key', 'team')['data']['members'][0]['media_url'] ?? null;

        $this->assertNotNull($teamUrl());

        $team->delete();
        app(FrontendCacheGeneration::class)->bump();

        $this->assertNotNull($teamUrl(), 'The soft-delete repair must apply to every media-bearing type.');
    }

    // ------------------------------------------------------------ TA-11 -----

    public function test_recreating_the_section_key_does_not_rebind_the_published_media(): void
    {
        $sectionA = $this->section('hero', 'home');
        $mediaA = $this->attachSlide($sectionA);

        $this->heroWithSlide((string) $mediaA->uuid);
        $this->publish();

        $urlBefore = $this->renderedSlideUrl();
        $this->assertNotNull($urlBefore);

        // Soft-delete A and recreate a LIVE section with the SAME key: the
        // partial unique index (`WHERE deleted_at IS NULL`) allows it.
        $sectionA->delete();
        $sectionB = FrontendSection::query()->create([
            'frontend_page_id' => $this->page()->getKey(),
            'section_key' => 'hero',
            'type' => 'hero',
            'payload' => null,
            'is_enabled' => true,
            'sort_order' => $sectionA->sort_order,
        ]);

        $this->assertNotSame($sectionA->getKey(), $sectionB->getKey());
        app(FrontendCacheGeneration::class)->bump();

        // The published revision still describes A. Resolution goes through the
        // media's own row, so the new B cannot shadow or rebind it.
        $this->assertSame(
            $urlBefore,
            $this->renderedSlideUrl(),
            'The published revision must keep resolving A, not the recreated B.'
        );

        $published = app(PublishedMediaReference::class);
        $this->assertSame(
            $sectionA->getKey(),
            $published->owningSection($mediaA->refresh())?->getKey(),
            'The owner identity comes from Media.model_id, never from section_key.'
        );
    }
}
