<?php

namespace Tests\Feature\Frontend;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * §16.5: declaring `--theme-*` is useless if components keep resolving compiled
 * constants. Brand-critical roles must use the semantic utilities, and the two
 * patterns the design bans by name — `text-white` on a CTA and `bg-navy-900`
 * as a brand surface — must be gone.
 *
 * The scope is deliberate: decorative shades and state colours stay fixed
 * (§16.5 "Alcance explícito"). These tests draw that line so a future change
 * cannot quietly turn a themed role back into a constant.
 */
class FrontendBrandUtilitiesTest extends TestCase
{
    use RefreshDatabase;

    private function bladeSource(string $path): string
    {
        return file_get_contents(resource_path("views/{$path}"));
    }

    public function test_the_shared_button_themes_every_variant(): void
    {
        $button = $this->bladeSource('components/button.blade.php');

        // Accent CTA and its text colour both come from the theme.
        $this->assertStringContainsString('bg-brand-accent', $button);
        $this->assertStringContainsString('text-on-brand-accent', $button);

        // Primary brand surface and its text colour too.
        $this->assertStringContainsString('bg-brand-primary', $button);
        $this->assertStringContainsString('text-on-brand-primary', $button);

        $this->assertStringContainsString('rounded-brand-md', $button);

        // La tipografía se HEREDA del cuerpo, que ya es un rol del tema. Antes
        // acá se exigía la de títulos, y eso convirtió al botón en un titular:
        // con una tipografía de títulos expresiva —una manuscrita, por ejemplo—
        // las etiquetas de los botones salían escritas a mano.
        //
        // Lo que el contrato pide sigue en pie y es lo que se comprueba: que no
        // aparezca una familia FIJA. Heredar del cuerpo lo cumple; nombrar una
        // constante, no.
        $this->assertNoFixedFontFamily($button, 'components/button.blade.php');
    }

    /**
     * Falla si el archivo nombra una familia tipográfica que el tema no controla.
     */
    private function assertNoFixedFontFamily(string $source, string $file): void
    {
        foreach (['font-display', 'font-sans', 'font-serif', 'font-mono'] as $fija) {
            $this->assertDoesNotMatchRegularExpression(
                '/'.preg_quote($fija, '/').'(?!\s*:)/',
                $source,
                "{$file} fija la tipografía con «{$fija}»; el tema deja de mandar sobre ella.",
            );
        }
    }

    public function test_the_button_no_longer_hardcodes_cta_text_or_brand_surfaces(): void
    {
        $button = $this->bladeSource('components/button.blade.php');

        // Both banned by name in §16.5: a fixed white keeps an unreadable CTA
        // unreadable no matter what the owner picks.
        $this->assertStringNotContainsString('text-white', $button);
        $this->assertStringNotContainsString('bg-navy-900', $button);
        $this->assertStringNotContainsString('bg-navy ', $button);
        $this->assertStringNotContainsString('bg-orange ', $button);
    }

    public function test_the_public_layout_themes_header_drawer_and_footer(): void
    {
        $layout = $this->bladeSource('components/layouts/public.blade.php');

        $this->assertStringContainsString('bg-brand-primary', $layout, 'Footer/header brand surface.');
        $this->assertStringContainsString('text-on-brand-primary', $layout, 'Text over that surface.');
        // El chrome —menú, drawer, botón del header— hereda la tipografía del
        // cuerpo, que es rol del tema. Antes se le exigía la de TÍTULOS, y por eso
        // el menú salía escrito con la tipografía de los titulares.
        $this->assertStringContainsString('font-brand-body', $layout);
        $this->assertNoFixedFontFamily($layout, 'components/layouts/public.blade.php');
    }

    public function test_the_property_card_uses_themed_radius_and_brand_roles(): void
    {
        $card = $this->bladeSource('components/property-card.blade.php');

        $this->assertStringContainsString('rounded-brand-', $card);
        $this->assertStringContainsString('brand-primary', $card);
    }

    public function test_the_rendered_home_page_actually_emits_the_semantic_classes(): void
    {
        // The file-level assertions above prove intent; this one proves the
        // classes survive Blade and reach the browser.
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('bg-brand-accent', $html);
        $this->assertStringContainsString('text-on-brand-accent', $html);
        $this->assertStringContainsString('font-brand-', $html);
    }
}
