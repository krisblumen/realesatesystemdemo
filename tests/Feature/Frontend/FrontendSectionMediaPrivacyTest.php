<?php

namespace Tests\Feature\Frontend;

use App\Models\FrontendPage;
use App\Models\FrontendSection;
use App\Models\FrontendSetting;
use App\Models\User;
use App\Services\Frontend\FrontendMediaReference;
use App\Services\Frontend\FrontendPageRenderer;
use App\Services\Frontend\PublishedMediaReference;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

/**
 * Épica 12.1, Lote A — TA-1 a TA-4: the privacy frontier of draft media.
 *
 * Before this batch the `images` collection inherited media-library's default
 * disk (`public`), so every draft image was reachable by URL before anyone
 * published it. It now lives on `frontend-private` and is served only through an
 * owner-only route that answers 404 uniformly — anonymous, authorized-but-not-
 * owner, unknown section, foreign uuid and malformed uuid are indistinguishable.
 */
class FrontendSectionMediaPrivacyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('frontend-private');
        $this->seed(PermissionSeeder::class);
    }

    private function heroSection(string $pageKey = 'home'): FrontendSection
    {
        return FrontendPage::query()->where('key', $pageKey)->firstOrFail()
            ->sections()->where('section_key', 'hero')->firstOrFail();
    }

    private function attachSlide(FrontendSection $section): Media
    {
        return $section->addMedia(UploadedFile::fake()->image('slide.png', 1200, 675))
            ->toMediaCollection('images');
    }

    // ------------------------------------------------------------- TA-1 -----

    public function test_draft_media_lives_on_the_private_disk(): void
    {
        $media = $this->attachSlide($this->heroSection());

        // The ROW is what decides the URL, so the row is what the test asserts —
        // a getUrl() assertion alone would not prove where the bytes are.
        $this->assertSame('frontend-private', $media->disk);
        $this->assertTrue(
            Storage::disk('frontend-private')->exists($media->getPathRelativeToRoot()),
            'The uploaded draft file must land on the private disk.'
        );
        $this->assertFalse(
            Storage::disk('public')->exists($media->getPathRelativeToRoot()),
            'A draft file must NOT exist on the public disk before promotion.'
        );
    }

    // ------------------------------------------------------------- TA-2 -----

    public function test_the_preview_route_answers_404_uniformly_to_everyone_but_the_owner(): void
    {
        $section = $this->heroSection();
        $media = $this->attachSlide($section);

        $url = route('frontend.sections.media', ['section' => $section->getKey(), 'uuid' => $media->uuid]);

        // 1. Anonymous: 404, NOT a 302 redirect to the login page. The route has
        //    no `auth` middleware precisely so a binary endpoint answers
        //    uniformly instead of leaking that it exists.
        $this->get($url)->assertNotFound();

        // 2. Authenticated with the permission granted DIRECTLY but without the
        //    owner role: the policy requires both, a permission middleware alone
        //    would have let this through.
        $almost = User::factory()->withRole('admin')->create();
        $almost->givePermissionTo('frontend.manage');
        $this->actingAs($almost)->get($url)->assertNotFound();

        // 3. A plain non-owner role.
        $this->actingAs(User::factory()->withRole('agente')->create())->get($url)->assertNotFound();

        // 4. Owner gets the bytes, inline (not an attachment: the CMS shows it
        //    in an <img>).
        $this->actingAs(User::factory()->withRole('owner')->create())
            ->get($url)
            ->assertOk()
            ->assertHeaderMissing('Content-Disposition');
    }

    public function test_the_preview_route_answers_404_for_a_foreign_or_malformed_uuid(): void
    {
        $owner = User::factory()->withRole('owner')->create();
        $section = $this->heroSection('home');
        $foreignSection = $this->heroSection('nosotros');
        $foreignMedia = $this->attachSlide($foreignSection);

        // A uuid that exists but belongs to ANOTHER section.
        $this->actingAs($owner)
            ->get(route('frontend.sections.media', ['section' => $section->getKey(), 'uuid' => $foreignMedia->uuid]))
            ->assertNotFound();

        // A malformed uuid must be a clean 404, never a PostgreSQL 22P02: the
        // format guard runs in the frontier, before the query is built.
        foreach (['not-a-uuid', '1234', str_repeat('a', 40)] as $garbage) {
            $this->actingAs($owner)
                ->get(route('frontend.sections.media', ['section' => $section->getKey(), 'uuid' => $garbage]))
                ->assertNotFound();
        }

        // A soft-deleted section is out of the working state: implicit binding
        // excludes it, and the answer stays uniform.
        $section->delete();
        $this->actingAs($owner)
            ->get(route('frontend.sections.media', ['section' => $section->getKey(), 'uuid' => 'not-a-uuid']))
            ->assertNotFound();
    }

    // ------------------------------------------------------------- TA-3 -----

    public function test_the_reference_frontier_rejects_malformed_uuids_without_querying(): void
    {
        $references = app(FrontendMediaReference::class);
        $section = $this->heroSection();

        // `media.uuid` is a native PostgreSQL uuid column: a malformed string
        // raises SQLSTATE 22P02 instead of returning nothing. If the guard ever
        // regresses, these calls throw and the test fails loudly.
        foreach ([null, '', 'not-a-uuid', '9f8e7d6c', str_repeat('z', 36)] as $garbage) {
            $this->assertFalse($references->isEligible($garbage, $section, 'images'));
            $this->assertNull($references->resolve($garbage, $section, 'images'));
        }

        // A well-formed but unknown uuid is simply not eligible — no exception.
        $this->assertFalse($references->isEligible((string) Str::uuid(), $section, 'images'));
    }

    // ------------------------------------------------------------- TA-4 -----

    public function test_resolve_published_enforces_uuid_type_collection_and_page(): void
    {
        $published = app(PublishedMediaReference::class);

        $home = FrontendPage::query()->where('key', 'home')->firstOrFail();
        $other = FrontendPage::query()->where('key', 'nosotros')->firstOrFail();

        $section = $this->heroSection('home');
        $media = $this->attachSlide($section);

        // Happy path.
        $this->assertNotNull($published->resolvePublished((string) $media->uuid, $home));

        // Malformed uuid, unknown uuid, another page, another collection.
        $this->assertNull($published->resolvePublished('not-a-uuid', $home));
        $this->assertNull($published->resolvePublished((string) Str::uuid(), $home));
        $this->assertNull($published->resolvePublished((string) $media->uuid, $other));
        $this->assertNull($published->resolvePublished((string) $media->uuid, $home, 'otra-coleccion'));
        $this->assertNull($published->resolvePublished((string) $media->uuid, null));
    }

    public function test_the_owner_preview_points_at_the_private_route_never_a_public_url(): void
    {
        $section = $this->heroSection('home');
        $media = $this->attachSlide($section);

        $section->update(['payload' => [
            'title' => 'Hero de prueba',
            'slides' => [['media_id' => (string) $media->uuid, 'alt' => null, 'decorative' => true, 'sort_order' => 0]],
        ]]);

        $draft = app(FrontendPageRenderer::class)->renderDraft('home');
        $url = collect($draft['sections'])->firstWhere('key', 'hero')['data']['slides'][0]['media_url'] ?? null;

        // The draft file has no public URL yet: the preview must go through the
        // owner-only route, or the CMS would render a broken/leaky image.
        $this->assertNotNull($url);
        $this->assertStringContainsString('/admin/frontend/secciones/', $url);
        $this->assertStringNotContainsString('/storage/', $url);
    }

    public function test_the_preview_route_answers_404_for_a_section_that_does_not_exist(): void
    {
        $owner = User::factory()->withRole('owner')->create();
        $media = $this->attachSlide($this->heroSection());

        // Separated from the other failures on purpose: the contract is that an
        // unknown section is indistinguishable from an unauthorized one.
        $this->actingAs($owner)
            ->get(route('frontend.sections.media', ['section' => 999999, 'uuid' => $media->uuid]))
            ->assertNotFound();
    }

    public function test_resolve_published_rejects_media_owned_by_another_model_type(): void
    {
        $published = app(PublishedMediaReference::class);
        $home = FrontendPage::query()->where('key', 'home')->firstOrFail();

        // Brand media belongs to FrontendSetting, not to a section. Even inside
        // the same site it must never resolve as page content — the morph type
        // is part of the authorization, not a coincidence of the uuid.
        $foreign = FrontendSetting::current()
            ->addMedia(UploadedFile::fake()->image('logo.png'))
            ->toMediaCollection('logo-light');

        $this->assertNull($published->resolvePublished((string) $foreign->uuid, $home));
        $this->assertNull($published->owningSection($foreign));
        $this->assertNull($published->owningPage($foreign));
    }

    public function test_resolve_published_still_finds_media_whose_section_was_soft_deleted(): void
    {
        $published = app(PublishedMediaReference::class);
        $home = FrontendPage::query()->where('key', 'home')->firstOrFail();

        $section = $this->heroSection('home');
        $media = $this->attachSlide($section);

        $section->delete();

        // A soft-deleted section is still the rightful owner of the media its
        // published revision references — otherwise deleting a section from the
        // admin would silently break a live page.
        $this->assertNotNull(
            $published->resolvePublished((string) $media->uuid, $home),
            'A soft-deleted owner must not hide media from the published revision.'
        );
        $this->assertNotNull($published->owningPage($media->refresh()));
        $this->assertSame($home->getKey(), $published->owningPage($media)?->getKey());
    }
}
