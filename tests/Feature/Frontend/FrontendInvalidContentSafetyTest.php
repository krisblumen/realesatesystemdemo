<?php

namespace Tests\Feature\Frontend;

use App\Models\FrontendPage;
use App\Services\Frontend\FrontendCacheGeneration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The public render NEVER throws on malformed content (RFC-076 error handling).
 * Even if a published snapshot is corrupt — an unknown section type, a section
 * that is not an array, a media_id that resolves to nothing, a CTA that is not a
 * valid value object — the route degrades to an empty/partial page with a 200,
 * never a 500. The presenter and dispatcher are the defensive boundary.
 */
class FrontendInvalidContentSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The array cache store persists across tests in the same process; a
        // read model cached by a previous test at the same generation would leak
        // in. Flush so each corrupt-snapshot probe reads its own state.
        Cache::flush();
    }

    public function test_a_corrupt_published_snapshot_still_returns_200(): void
    {
        $page = FrontendPage::query()->where('key', 'servicios')->firstOrFail();

        // A hand-corrupted snapshot: shapes the publisher would never emit, to
        // prove the render boundary — not the publisher — keeps the site up.
        $page->forceFill([
            'published_revision' => [
                'is_enabled' => true,
                'seo' => 'not-an-array',
                'sections' => [
                    ['section_key' => 'hero', 'type' => 'hero', 'is_enabled' => true, 'payload' => [
                        'title' => 'Hero corrupto',
                        'primary_cta' => 'esto-no-es-un-cta',          // invalid CTA → dropped
                        'slides' => [['media_id' => 'no-uuid', 'sort_order' => 0]], // unresolvable media
                    ]],
                    ['section_key' => 'ghost', 'type' => 'type_inexistente', 'is_enabled' => true, 'payload' => []], // unknown type → skipped
                    ['section_key' => 'evil', 'type' => '../../../etc/passwd', 'is_enabled' => true, 'payload' => []], // path-like type → never resolves a foreign view
                    'esto-no-es-una-seccion',                          // not an array → skipped
                    ['section_key' => 'cta', 'type' => 'cta', 'is_enabled' => true, 'payload' => null], // null payload
                ],
                'generated_from_ids' => [],
            ],
            'published_at' => now(),
            'revision' => 1,
        ])->save();

        // The generation bump the publisher would have done; force a fresh read.
        app(FrontendCacheGeneration::class)->bump();

        $this->get('/servicios')
            ->assertOk()
            ->assertSee('Hero corrupto');
    }

    /**
     * C-F1: the container itself may be corrupt, not only its elements. A
     * scalar/null/object `sections` must degrade to the fallback, never crash
     * the render foreach.
     */
    #[DataProvider('invalidSectionsProvider')]
    public function test_an_invalid_sections_container_returns_200(mixed $sections): void
    {
        $this->publishRawSnapshot(['is_enabled' => true, 'sections' => $sections]);

        // No section renders, so the page falls back to its hardcoded content.
        $this->get('/servicios')
            ->assertOk()
            ->assertSee('Del terreno a la entrega de llaves.');
    }

    public static function invalidSectionsProvider(): array
    {
        return [
            'scalar string' => ['not-an-array'],
            'null' => [null],
            'associative object' => [['foo' => 'bar']],
            'integer' => [42],
        ];
    }

    /**
     * C-F1: a non-scalar SEO field (array/object/number/bool) must be dropped so
     * the settings fallback applies, never handed to htmlspecialchars() in the
     * layout as a non-string.
     */
    #[DataProvider('invalidSeoProvider')]
    public function test_a_non_scalar_seo_field_falls_back_to_a_safe_title(mixed $metaTitle): void
    {
        $this->publishRawSnapshot([
            'is_enabled' => true,
            'seo' => ['meta_title' => $metaTitle, 'meta_description' => ['also-invalid']],
            'sections' => [],
        ]);

        // The corrupt SEO is dropped; the per-page static title survives intact
        // (a non-string handed to htmlspecialchars would have 500'd instead).
        $html = $this->get('/servicios')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<title>Servicios .* Landra</title>#', $html);
    }

    public static function invalidSeoProvider(): array
    {
        return [
            'array' => [['invalid']],
            'object' => [['a' => 'b']],
            'number' => [123],
            'bool' => [true],
        ];
    }

    private function publishRawSnapshot(array $revision): void
    {
        FrontendPage::query()->where('key', 'servicios')->firstOrFail()->forceFill([
            'published_revision' => $revision,
            'published_at' => now(),
            'revision' => 1,
        ])->save();

        app(FrontendCacheGeneration::class)->bump();
    }
}
