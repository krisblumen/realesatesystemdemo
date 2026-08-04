<?php

namespace Tests\Feature\Frontend;

use App\Models\FrontendPage;
use App\Models\FrontendSection;
use App\Models\User;
use App\Services\Frontend\FrontendCacheGeneration;
use App\Services\Frontend\FrontendPagePublisher;
use App\Services\Frontend\FrontendPageRenderer;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

/**
 * Épica 12.1, Lote B — TB-4 a TB-10: what the hero actually renders.
 *
 * The two DOM modes are the heart of it. They are mutually exclusive by design,
 * because the earlier attempt put `role="img"` and an alt INSIDE an
 * `aria-hidden` layer — where a screen reader would never announce it. So:
 * decorative backdrops rotate under `aria-hidden` with a pause control, and a
 * meaningful image renders alone, static, and announceable.
 */
class FrontendHeroRenderTest extends TestCase
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

    private function hero(string $key = 'home'): FrontendSection
    {
        return $this->page($key)->sections()->where('section_key', 'hero')->firstOrFail();
    }

    private function slide(string $key = 'home'): Media
    {
        return $this->hero($key)->addMedia(UploadedFile::fake()->image('s.png', 1200, 675))
            ->toMediaCollection('images');
    }

    /** @param  array<string, mixed>  $payload */
    private function publishHero(array $payload, string $key = 'home'): void
    {
        $this->hero($key)->update(['payload' => $payload]);
        $page = $this->page($key)->fresh();
        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, $this->owner);
        app(FrontendCacheGeneration::class)->bump();
    }

    /** @return array<string, mixed> */
    private function heroData(string $key = 'home'): array
    {
        return collect(app(FrontendPageRenderer::class)->render($key)['sections'])
            ->firstWhere('key', 'hero')['data'] ?? [];
    }

    /**
     * JUST the hero <section>, anchored on its overlay gradient. Slicing from the
     * top of the document would drag in <head> — including the theme's runtime
     * <style> — and make the CSP assertions below meaningless.
     */
    private function heroHtml(string $path = '/'): string
    {
        $html = $this->get($path)->assertOk()->getContent();

        // Anchored on the hero's own marker, not on an overlay class: the
        // overlay now differs per variant, and slicing from the top of the
        // document would drag in <head> — including the theme's runtime <style>.
        $marker = strpos($html, 'data-nh-hero');
        $this->assertNotFalse($marker, 'The hero section did not render.');

        $start = strrpos(substr($html, 0, $marker), '<section');
        $end = strpos($html, '</section>', $marker);

        return substr($html, $start, $end - $start);
    }

    // -------------------------------------------------------------- TB-4 ----

    public function test_a_hero_that_never_published_slides_falls_back_to_its_own_page_background(): void
    {
        // The key was never published: «not initialised» → the page's CURRENT
        // background, not another page's.
        $this->publishHero(['title' => 'Bienvenido']);

        $urls = collect($this->heroData()['slides'])->pluck('media_url');

        $this->assertCount(4, $urls, 'home falls back to its four rotating URLs.');
        $this->assertStringContainsString('unsplash', $urls->first());
    }

    public function test_each_page_falls_back_to_its_own_header_not_the_home_slideshow(): void
    {
        // The reason the fallback is per page: imposing home's slideshow here
        // would replace a header these pages already have.
        $this->hero('nosotros')->update(['payload' => ['title' => 'Nosotros']]);
        $page = $this->page('nosotros')->fresh();
        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, $this->owner);
        app(FrontendCacheGeneration::class)->bump();

        $urls = collect($this->heroData('nosotros')['slides'])->pluck('media_url');

        $this->assertCount(1, $urls);
        $this->assertStringContainsString('header_nosotros', $urls->first());
        $this->assertStringNotContainsString('unsplash', $urls->first());
    }

    public function test_an_explicitly_empty_slides_list_does_not_revive_the_fallback(): void
    {
        // Publishing `slides: []` is a decision, not an omission (§16.1.1).
        $this->publishHero(['title' => 'Bienvenido', 'slides' => []]);

        $this->assertSame([], $this->heroData()['slides']);
    }

    // -------------------------------------------------------------- TB-5 ----

    public function test_slides_render_in_sort_order_not_array_order(): void
    {
        $a = $this->slide();
        $b = $this->slide();

        // Deliberately inverted in the array: `sort_order` is the only authority.
        $this->publishHero([
            'title' => 'T',
            'slides' => [
                ['media_id' => (string) $b->uuid, 'alt' => null, 'decorative' => true, 'sort_order' => 1],
                ['media_id' => (string) $a->uuid, 'alt' => null, 'decorative' => true, 'sort_order' => 0],
            ],
        ]);

        $ids = collect($this->heroData()['slides'])->pluck('media_id')->all();

        $this->assertSame([(string) $a->uuid, (string) $b->uuid], $ids);
    }

    // --------------------------------------------------------- TB-6 / TB-7 --

    public function test_mode_a_hides_the_backdrop_layer_and_offers_a_pause_control(): void
    {
        $a = $this->slide();
        $b = $this->slide();

        $this->publishHero([
            'title' => 'T',
            'slides' => [
                ['media_id' => (string) $a->uuid, 'alt' => null, 'decorative' => true, 'sort_order' => 0],
                ['media_id' => (string) $b->uuid, 'alt' => null, 'decorative' => true, 'sort_order' => 1],
            ],
        ]);

        $this->assertSame('decorative', $this->heroData()['mode']);

        $html = $this->heroHtml();

        $this->assertStringContainsString('aria-hidden="true"', $html);
        $this->assertStringContainsString('nh-hero-slides--2', $html);
        $this->assertStringContainsString('nh-hero-delay-0', $html);
        $this->assertStringContainsString('nh-hero-delay-1', $html);

        // WCAG 2.2.2: rotation needs a stop, and it must live OUTSIDE the
        // aria-hidden layer or it would not exist for assistive tech.
        $this->assertStringContainsString('data-nh-hero-toggle', $html);
        $this->assertGreaterThan(
            strpos($html, 'data-nh-hero-slides'),
            strpos($html, 'data-nh-hero-toggle'),
            'The pause button must not be inside the hidden layer.'
        );
    }

    public function test_a_single_decorative_slide_rotates_nothing_and_needs_no_control(): void
    {
        $a = $this->slide();

        $this->publishHero([
            'title' => 'T',
            'slides' => [['media_id' => (string) $a->uuid, 'alt' => null, 'decorative' => true, 'sort_order' => 0]],
        ]);

        $html = $this->heroHtml();

        $this->assertStringContainsString('nh-hero-slides--1', $html);
        $this->assertStringNotContainsString('data-nh-hero-toggle', $html);
    }

    public function test_mode_b_renders_one_announceable_image_without_autoplay(): void
    {
        $meaningful = $this->slide();
        $other = $this->slide();

        $this->publishHero([
            'title' => 'T',
            'slides' => [
                ['media_id' => (string) $meaningful->uuid, 'alt' => 'Fachada del proyecto Alara', 'decorative' => false, 'sort_order' => 0],
                ['media_id' => (string) $other->uuid, 'alt' => null, 'decorative' => true, 'sort_order' => 1],
            ],
        ]);

        $data = $this->heroData();
        $this->assertSame('informative', $data['mode']);
        $this->assertCount(1, $data['slides'], 'Only the meaningful image renders — the rest would rotate content in silence.');

        $html = $this->heroHtml();

        $this->assertStringContainsString('alt="Fachada del proyecto Alara"', $html);
        $this->assertStringNotContainsString('data-nh-hero-slides', $html, 'No aria-hidden backdrop layer in mode B.');
        $this->assertStringNotContainsString('data-nh-hero-toggle', $html, 'Nothing moves, so nothing to pause.');
    }

    // ------------------------------------------------------------- TB-10 ----

    public function test_the_logo_is_decorative_when_the_hero_already_has_a_heading(): void
    {
        $this->publishHero(['title' => 'Encuentra tu hogar', 'logo_enabled' => true, 'logo_size' => 'xl']);

        $html = $this->heroHtml();

        // With an H1 the logo repeats the brand the heading already names.
        $this->assertStringContainsString('alt=""', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
        // home uses the `featured` variant, where the logo scales up to keep the
        // cover looking as it does today; the same token maps to a smaller fixed
        // class on the standard pages. Both are closed sets, never interpolated.
        $this->assertStringContainsString('h-32 sm:h-40 lg:h-48', $html, 'xl on the featured variant.');

        $this->hero('nosotros')->update(['payload' => ['title' => 'Nosotros', 'logo_enabled' => true, 'logo_size' => 'xl']]);
        $page = $this->page('nosotros')->fresh();
        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, $this->owner);
        app(FrontendCacheGeneration::class)->bump();

        // Design D5 (cambio cms-pagina-proyectos): la variante `standard`
        // tiene su PROPIA rampa para `xl` — 14rem fijos en `/proyectos` hoy,
        // 12rem en móvil en vez de un alto fijo. Reemplaza la rampa genérica
        // anterior, que ningún hero publicado usaba en producción.
        $this->assertStringContainsString('h-48 sm:h-56', $this->heroHtml('/nosotros'), 'xl on the standard variant.');
    }

    public function test_the_logo_is_named_when_there_is_no_heading_to_name_the_brand(): void
    {
        $this->publishHero(['title' => 'Hola', 'logo_enabled' => true]);
        $data = $this->heroData();

        // The presenter resolves the brand, so Blade never has to.
        $this->assertTrue($data['logo_enabled']);
        $this->assertNotEmpty($data['logo_url']);
        $this->assertSame('md', $data['logo_size'], 'Absent size defaults to md.');
    }

    public function test_the_logo_is_hidden_by_default(): void
    {
        $this->publishHero(['title' => 'Sin logo']);

        $this->assertFalse($this->heroData()['logo_enabled']);
    }

    // -------------------------------------------------------- alineación ----

    public function test_alignment_maps_to_fixed_classes_and_defaults_to_left(): void
    {
        $this->publishHero(['title' => 'T', 'text_align' => 'center']);
        $this->assertStringContainsString('text-center items-center mx-auto', $this->heroHtml());

        $this->publishHero(['title' => 'T']);
        $this->assertSame('left', $this->heroData()['text_align']);
    }

    public function test_a_corrupt_alignment_in_a_snapshot_degrades_instead_of_emitting_a_class(): void
    {
        // Hand-edited or legacy data must never reach a class name: the render
        // re-normalises what the schema validated on save (double boundary).
        $page = $this->page();
        $this->publishHero(['title' => 'T']);

        $snapshot = $page->fresh()->published_revision;
        foreach ($snapshot['sections'] as $i => $section) {
            if ($section['section_key'] === 'hero') {
                $snapshot['sections'][$i]['payload']['text_align'] = 'justify; background:url(x)';
            }
        }
        $page->fresh()->update(['published_revision' => $snapshot]);
        app(FrontendCacheGeneration::class)->bump();

        $this->assertSame('left', $this->heroData()['text_align']);
        $this->assertStringNotContainsString('justify', $this->heroHtml());
    }

    // -------------------------------------------------------------- TB-9 ----

    public function test_the_hero_ships_no_inline_script_or_style(): void
    {
        $a = $this->slide();
        $b = $this->slide();

        $this->publishHero([
            'title' => 'T',
            'slides' => [
                ['media_id' => (string) $a->uuid, 'alt' => null, 'decorative' => true, 'sort_order' => 0],
                ['media_id' => (string) $b->uuid, 'alt' => null, 'decorative' => true, 'sort_order' => 1],
            ],
        ]);

        $html = $this->heroHtml();

        // The animation is external CSS and the pause is a Vite module: the page
        // stays servable under a CSP without `unsafe-inline`. The legacy home
        // hero used an inline <style> block; this one replaces it.
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('<style', $html);
        $this->assertStringNotContainsString('@keyframes', $html);
        $this->assertStringNotContainsString('animation-delay', $html, 'Delays come from the fixed class set, never inline.');
    }
}
