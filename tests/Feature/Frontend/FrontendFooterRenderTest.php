<?php

namespace Tests\Feature\Frontend;

use App\Models\FrontendSetting;
use App\Services\Frontend\FrontendCacheGeneration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The footer consumes centralized links (RFC-073): a disabled link is NOT
 * rendered and a fallback never revives it, an unsafe target never reaches the
 * HTML, and the fallback footer has no `#` dead ends.
 */
class FrontendFooterRenderTest extends TestCase
{
    use RefreshDatabase;

    private function configureFooter(array $footer): void
    {
        $setting = FrontendSetting::current();
        $setting->footer = $footer;
        $setting->save();

        app(FrontendCacheGeneration::class)->bump();
    }

    private function configureSocial(array $social): void
    {
        $setting = FrontendSetting::current();
        $setting->social_links = $social;
        $setting->save();

        app(FrontendCacheGeneration::class)->bump();
    }

    public function test_a_social_network_icon_shows_only_when_its_url_is_set(): void
    {
        // Instagram has a profile, TikTok/Facebook do not: only Instagram's icon
        // (its accessible link) renders. The empty networks leave no trace.
        $this->configureSocial(['instagram' => 'https://instagram.com/newhauz']);

        $footer = substr($this->get('/')->assertOk()->getContent(), strpos($this->get('/')->getContent(), '<footer'));

        $this->assertStringContainsString('aria-label="Instagram"', $footer);
        $this->assertStringContainsString('https://instagram.com/newhauz', $footer);
        $this->assertStringNotContainsString('aria-label="TikTok"', $footer);
        $this->assertStringNotContainsString('aria-label="Facebook"', $footer);
    }

    public function test_an_empty_or_unsafe_social_url_never_renders_an_icon(): void
    {
        // Empty string and a non-https target are both dropped: no icon, no link.
        $this->configureSocial(['instagram' => '', 'tiktok' => 'http://tiktok.com/x', 'facebook' => 'javascript:alert(1)']);

        $footer = substr($this->get('/')->assertOk()->getContent(), strpos($this->get('/')->getContent(), '<footer'));

        $this->assertStringNotContainsString('aria-label="Instagram"', $footer);
        $this->assertStringNotContainsString('aria-label="TikTok"', $footer);
        $this->assertStringNotContainsString('aria-label="Facebook"', $footer);
    }

    public function test_the_fallback_footer_has_no_hash_destinations(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // Isolate the footer to avoid matching hrefs elsewhere on the page.
        $footer = substr($html, strpos($html, '<footer'));

        $this->assertStringNotContainsString('href="#"', $footer, 'The footer must not ship `#` dead links.');
    }

    public function test_a_disabled_footer_link_is_not_rendered_and_no_fallback_revives_it(): void
    {
        $this->configureFooter([
            'columns' => [[
                'title' => 'Enlaces',
                'links' => [
                    ['label' => 'Visible', 'type' => 'route', 'target' => 'nosotros', 'enabled' => true],
                    ['label' => 'Escondido', 'type' => 'route', 'target' => 'servicios', 'enabled' => false],
                ],
            ]],
            'legal_text' => '© New Hauz',
        ]);

        $footer = substr($this->get('/')->assertOk()->getContent(), strpos($this->get('/')->getContent(), '<footer'));

        $this->assertStringContainsString('Visible', $footer);
        $this->assertStringNotContainsString('Escondido', $footer, 'A disabled footer link must never render.');
    }

    /**
     * A footer JSON the form could never build — but an import, manual SQL or a
     * legacy row can — must degrade to the safe fallback, never 500 the home
     * (M-C1). Writes go straight to SQL to bypass every guard.
     *
     * @return array<string, array{0: string}>
     */
    public static function malformedFooters(): array
    {
        return [
            'columns is a string' => [json_encode(['columns' => 'x', 'legal_text' => 'y'])],
            'a column is a string' => [json_encode(['columns' => ['zzz'], 'legal_text' => 'y'])],
            'links is a string' => [json_encode(['columns' => [['title' => 'Bad', 'links' => 'malformed']], 'legal_text' => 'y'])],
            'a link is a scalar' => [json_encode(['columns' => [['title' => 'Bad', 'links' => [5]]], 'legal_text' => 'y'])],
            'columns is a scalar' => [json_encode(['columns' => 7])],
        ];
    }

    #[DataProvider('malformedFooters')]
    public function test_a_malformed_footer_degrades_instead_of_500(string $footerJson): void
    {
        DB::table('frontend_settings')->updateOrInsert(
            ['singleton_key' => 'default'],
            ['site_name' => 'New Hauz', 'footer' => $footerJson, 'created_at' => now(), 'updated_at' => now()],
        );
        app(FrontendCacheGeneration::class)->bump();

        $html = $this->get('/')->assertOk()->getContent();

        // A malformed block is dropped; the page still renders a footer.
        $this->assertStringContainsString('<footer', $html);
    }

    public function test_an_unsafe_footer_target_never_reaches_the_html(): void
    {
        $this->configureFooter([
            'columns' => [[
                'title' => 'Enlaces',
                'links' => [
                    ['label' => 'Malicioso', 'type' => 'url', 'target' => 'javascript:alert(1)', 'enabled' => true],
                    ['label' => 'Bueno', 'type' => 'route', 'target' => 'contacto', 'enabled' => true],
                ],
            ]],
            'legal_text' => '© New Hauz',
        ]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('Malicioso', $html);
        $this->assertStringNotContainsString('javascript:alert', $html);
        $this->assertStringContainsString('Bueno', $html);
    }
}
